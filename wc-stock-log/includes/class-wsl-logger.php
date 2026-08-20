<?php
/**
 * Καταγραφή αλλαγών αποθέματος.
 *
 * Πιάνει και τα δύο μονοπάτια του WooCommerce:
 *  1. wc_update_product_stock() (παραγγελίες, επιστροφές, REST adjust) —
 *     woocommerce_{product|variation}_before_set_stock / _set_stock.
 *  2. CRUD save ($product->set_stock_quantity() + save(): επεξεργασία προϊόντος,
 *     γρήγορη/μαζική επεξεργασία, εισαγωγή CSV, REST API) — το
 *     woocommerce_{product|variation}_set_stock πυροδοτείται από το data store
 *     ΠΡΙΝ το apply_changes(), οπότε get_data() κρατά την παλιά τιμή και
 *     get_changes() τη νέα.
 *
 * @package WC_Stock_Log
 */

defined( 'ABSPATH' ) || exit;

class WSL_Logger {

	/** @var WSL_Logger|null */
	private static $instance = null;

	/** Παλιές τιμές αποθέματος ανά product id (fallback από τα before hooks). */
	private $pre_stock = array();

	/** Πλαίσιο παραγγελίας για το τρέχον request: array( 'source' => ..., 'order_id' => ... ). */
	private $order_ctx = null;

	/** Εγγραφές που γράφτηκαν στο τρέχον request, ανά product id — για εκ των υστέρων διόρθωση πηγής. */
	private $request_rows = array();

	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	private function hooks() {
		// Παλιά τιμή πριν από άμεση αλλαγή μέσω wc_update_product_stock().
		add_action( 'woocommerce_product_before_set_stock', array( $this, 'capture_before' ), 1 );
		add_action( 'woocommerce_variation_before_set_stock', array( $this, 'capture_before' ), 1 );

		// Η καταγραφή.
		add_action( 'woocommerce_product_set_stock', array( $this, 'log_change' ), 20 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'log_change' ), 20 );

		// Πλαίσιο παραγγελίας: τα ίδια status hooks που χρησιμοποιεί το WooCommerce
		// για μείωση/επαναφορά αποθέματος, σε priority 0 ώστε να προηγούμαστε.
		foreach ( array( 'woocommerce_payment_complete', 'woocommerce_order_status_processing', 'woocommerce_order_status_completed', 'woocommerce_order_status_on-hold' ) as $hook ) {
			add_action( $hook, array( $this, 'set_order_ctx_reduce' ), 0 );
		}
		foreach ( array( 'woocommerce_order_status_cancelled', 'woocommerce_order_status_pending' ) as $hook ) {
			add_action( $hook, array( $this, 'set_order_ctx_restock' ), 0 );
		}

		// Δίχτυ ασφαλείας: μετά το τέλος μείωσης/επαναφοράς/restock επιστροφής,
		// διόρθωσε τις εγγραφές του request ώστε να δείχνουν τη σωστή πηγή.
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'retag_order_reduce' ) );
		add_action( 'woocommerce_restore_order_stock', array( $this, 'retag_order_restore' ) );
		add_action( 'woocommerce_restock_refunded_item', array( $this, 'retag_refund' ), 10, 5 );
	}

	/* ---------------------------------------------------------------------
	 * Σύλληψη παλιάς τιμής / πλαισίου
	 * ------------------------------------------------------------------- */

	public function capture_before( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		// Όριο μνήμης σε πολύ μεγάλα batch (CLI imports κ.λπ.).
		if ( count( $this->pre_stock ) > 500 ) {
			$this->pre_stock = array();
		}
		$this->pre_stock[ $product->get_id() ] = $product->get_stock_quantity();
	}

	public function set_order_ctx_reduce( $order_id ) {
		$this->order_ctx = array(
			'source'   => 'order',
			'order_id' => absint( $order_id ),
		);
	}

	public function set_order_ctx_restock( $order_id ) {
		$this->order_ctx = array(
			'source'   => 'order_restock',
			'order_id' => absint( $order_id ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Καταγραφή
	 * ------------------------------------------------------------------- */

	public function log_change( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$product_id = $product->get_id();
		$changes    = $product->get_changes();
		$data       = $product->get_data();
		$new_qty    = $product->get_stock_quantity();

		if ( array_key_exists( 'stock_quantity', $changes ) && array_key_exists( 'stock_quantity', $data ) ) {
			$old_qty = $data['stock_quantity'];
		} elseif ( array_key_exists( $product_id, $this->pre_stock ) ) {
			$old_qty = $this->pre_stock[ $product_id ];
		} else {
			$old_qty = null;
		}
		unset( $this->pre_stock[ $product_id ] );

		// Καμία πραγματική μεταβολή — μην γεμίζουμε τον πίνακα με θόρυβο.
		if ( null === $old_qty && null === $new_qty ) {
			return;
		}
		if ( null !== $old_qty && null !== $new_qty && (float) $old_qty === (float) $new_qty ) {
			return;
		}

		/**
		 * Επιτρέπει σε άλλα plugins να αποκλείσουν κάτι από την καταγραφή.
		 *
		 * @param bool       $should  Να καταγραφεί;
		 * @param WC_Product $product Το προϊόν/παραλλαγή.
		 * @param mixed      $old_qty Παλιά ποσότητα (ή null).
		 * @param mixed      $new_qty Νέα ποσότητα (ή null).
		 */
		if ( ! apply_filters( 'wsl_should_log', true, $product, $old_qty, $new_qty ) ) {
			return;
		}

		$is_variation = $product->is_type( 'variation' );
		$name         = $product->get_name();
		if ( $is_variation && function_exists( 'wc_get_formatted_variation' ) ) {
			$attrs = wc_get_formatted_variation( $product, true, true, false );
			if ( $attrs ) {
				$name .= ' – ' . wp_strip_all_tags( $attrs );
			}
		}

		$ctx  = $this->detect_context();
		$user = wp_get_current_user();

		$row = array(
			'product_id'   => $product_id,
			'parent_id'    => $is_variation ? $product->get_parent_id() : 0,
			'product_name' => mb_substr( $name, 0, 255 ),
			'sku'          => mb_substr( (string) $product->get_sku(), 0, 120 ),
			'old_qty'      => null === $old_qty ? null : (float) $old_qty,
			'new_qty'      => null === $new_qty ? null : (float) $new_qty,
			'delta'        => ( null === $old_qty || null === $new_qty ) ? null : (float) $new_qty - (float) $old_qty,
			'source'       => $ctx['source'],
			'order_id'     => $ctx['order_id'],
			'user_id'      => $user instanceof WP_User ? (int) $user->ID : 0,
			'user_name'    => ( $user instanceof WP_User && $user->ID ) ? mb_substr( $user->display_name ? $user->display_name : $user->user_login, 0, 120 ) : '',
			'created_at'   => current_time( 'mysql', true ),
		);

		global $wpdb;
		$inserted = $wpdb->insert(
			WSL_Install::table(),
			$row,
			array( '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( $inserted ) {
			$row_id = (int) $wpdb->insert_id;

			$this->request_rows[ $product_id ][] = array(
				'id'       => $row_id,
				'source'   => $row['source'],
				'order_id' => $row['order_id'],
			);

			/**
			 * Πυροδοτείται μετά την καταγραφή μιας αλλαγής αποθέματος.
			 *
			 * @param int   $row_id ID εγγραφής.
			 * @param array $row    Τα δεδομένα της εγγραφής.
			 */
			do_action( 'wsl_logged', $row_id, $row );
		}
	}

	/* ---------------------------------------------------------------------
	 * Ανίχνευση πηγής
	 * ------------------------------------------------------------------- */

	private function detect_context() {
		if ( is_array( $this->order_ctx ) ) {
			return $this->order_ctx;
		}

		$source   = 'web';
		$order_id = 0;
		$action   = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$source = 'cli';
		} elseif ( wp_doing_cron() ) {
			$source = 'cron';
		} elseif ( 'woocommerce_refund_line_items' === $action ) {
			$source   = 'refund';
			$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( 'woocommerce_do_ajax_product_import' === $action ) {
			$source = 'import';
		} elseif ( isset( $_REQUEST['woocommerce_quick_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$source = 'quick_edit';
		} elseif ( isset( $_REQUEST['bulk_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$source = 'bulk_edit';
		} elseif ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'WC' ) && is_callable( array( WC(), 'is_rest_api_request' ) ) && WC()->is_rest_api_request() ) ) {
			$source = 'rest';
		} elseif ( is_admin() ) {
			$source = 'admin';
		}

		return array(
			'source'   => $source,
			'order_id' => $order_id,
		);
	}

	/**
	 * Ετικέτες πηγών (κοινό λεξιλόγιο για τη σελίδα διαχείρισης και το CSV).
	 */
	public static function sources() {
		return array(
			'order'         => __( 'Order', 'wc-stock-log' ),
			'order_restock' => __( 'Order restock', 'wc-stock-log' ),
			'refund'        => __( 'Refund (restock)', 'wc-stock-log' ),
			'admin'         => __( 'Product edit', 'wc-stock-log' ),
			'quick_edit'    => __( 'Quick edit', 'wc-stock-log' ),
			'bulk_edit'     => __( 'Bulk edit', 'wc-stock-log' ),
			'import'        => __( 'CSV import', 'wc-stock-log' ),
			'rest'          => __( 'REST API / external app', 'wc-stock-log' ),
			'cron'          => __( 'Scheduled task (cron)', 'wc-stock-log' ),
			'cli'           => __( 'WP-CLI', 'wc-stock-log' ),
			'web'           => __( 'Storefront / checkout', 'wc-stock-log' ),
			'other'         => __( 'Other', 'wc-stock-log' ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Εκ των υστέρων διόρθωση πηγής (δίχτυ ασφαλείας)
	 * ------------------------------------------------------------------- */

	public function retag_order_reduce( $order ) {
		$this->retag_from_order( $order, 'order' );
		$this->order_ctx = null;
	}

	public function retag_order_restore( $order ) {
		$this->retag_from_order( $order, 'order_restock' );
		$this->order_ctx = null;
	}

	/**
	 * woocommerce_restock_refunded_item( $product_id, $old_stock, $new_stock, $order, $product )
	 */
	public function retag_refund( $product_id, $old_stock = null, $new_stock = null, $order = null, $product = null ) {
		$order_id = ( $order instanceof WC_Order ) ? $order->get_id() : 0;
		$this->retag_rows( absint( $product_id ), 'refund', $order_id );
		if ( $product instanceof WC_Product ) {
			$this->retag_rows( $product->get_stock_managed_by_id(), 'refund', $order_id );
		}
	}

	private function retag_from_order( $order, $source ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$order_id = $order->get_id();
		foreach ( $order->get_items() as $item ) {
			if ( ! is_callable( array( $item, 'get_product' ) ) ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$this->retag_rows( $product->get_stock_managed_by_id(), $source, $order_id );
		}
	}

	private function retag_rows( $product_id, $source, $order_id ) {
		if ( empty( $this->request_rows[ $product_id ] ) ) {
			return;
		}
		global $wpdb;
		$remaining = array();

		foreach ( $this->request_rows[ $product_id ] as $entry ) {
			// Εγγραφή ήδη δεμένη με ΑΛΛΗ παραγγελία στο ίδιο request (batch
			// ακυρώσεις κ.λπ.) — είναι οριστική, μην την πειράξεις.
			if ( $entry['order_id'] && $entry['order_id'] !== $order_id ) {
				$remaining[] = $entry;
				continue;
			}
			if ( $entry['source'] !== $source || $entry['order_id'] !== $order_id ) {
				$wpdb->update(
					WSL_Install::table(),
					array(
						'source'   => $source,
						'order_id' => $order_id,
					),
					array( 'id' => $entry['id'] ),
					array( '%s', '%d' ),
					array( '%d' )
				);
			}
			// Επιβεβαιωμένη σε παραγγελία → οριστική, βγαίνει από την ουρά.
		}

		if ( $remaining ) {
			$this->request_rows[ $product_id ] = $remaining;
		} else {
			unset( $this->request_rows[ $product_id ] );
		}
	}
}
