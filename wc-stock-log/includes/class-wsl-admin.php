<?php
/**
 * Σελίδα διαχείρισης, εξαγωγή CSV, ρυθμίσεις, σύνδεσμοι στα προϊόντα.
 *
 * @package WC_Stock_Log
 */

defined( 'ABSPATH' ) || exit;

class WSL_Admin {

	const PAGE_SLUG = 'wsl-stock-log';
	const CAP       = 'manage_woocommerce';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_wsl_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_wsl_save_settings', array( __CLASS__, 'save_settings' ) );

		// Σύνδεσμοι «Ιστορικό αποθέματος» στην καρτέλα αποθέματος προϊόντος & σε κάθε παραλλαγή.
		add_action( 'woocommerce_product_options_inventory_product_data', array( __CLASS__, 'product_history_link' ) );
		add_action( 'woocommerce_variation_options_inventory', array( __CLASS__, 'variation_history_link' ), 10, 3 );

		add_filter( 'plugin_action_links_' . plugin_basename( WSL_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		$hook = add_submenu_page(
			'woocommerce',
			__( 'Stock history', 'wc-stock-log' ),
			__( 'Stock history', 'wc-stock-log' ),
			self::CAP,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
		add_action( 'load-' . $hook, array( 'WSL_Install', 'maybe_upgrade' ) );
	}

	public static function assets( $hook ) {
		if ( 'woocommerce_page_' . self::PAGE_SLUG === $hook ) {
			wp_enqueue_style( 'wsl-admin', WSL_URL . 'assets/admin.css', array(), WSL_VERSION );
		}
	}

	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'History', 'wc-stock-log' ) . '</a>'
		);
		return $links;
	}

	/* ---------------------------------------------------------------------
	 * Σελίδα
	 * ------------------------------------------------------------------- */

	public static function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wc-stock-log' ) );
		}

		require_once WSL_DIR . 'includes/class-wsl-list-table.php';

		$table = new WSL_List_Table();
		$table->prepare_items();

		$filters    = WSL_List_Table::get_filters();
		$export_url = wp_nonce_url(
			add_query_arg(
				array_filter(
					array(
						'action'        => 'wsl_export',
						'product_id'    => $filters['product_id'],
						'filter_user'   => $filters['user_id'],
						'filter_source' => $filters['source'],
						'filter_order'  => $filters['order_id'],
						'filter_from'   => $filters['from'],
						'filter_to'     => $filters['to'],
						's'             => $filters['s'],
					)
				),
				admin_url( 'admin-post.php' )
			),
			'wsl_export'
		);

		echo '<div class="wrap wsl-wrap">';

		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Stock history', 'wc-stock-log' ) . '</h1>';
		echo '<a href="' . esc_url( $export_url ) . '" class="page-title-action">' . esc_html__( 'Export CSV', 'wc-stock-log' ) . '</a>';
		echo '<hr class="wp-header-end" />';

		if ( isset( $_GET['wsl_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wc-stock-log' ) . '</p></div>';
		}

		// Ενεργό φίλτρο προϊόντος → chip με το όνομα και επιλογή καθαρισμού.
		if ( $filters['product_id'] ) {
			$product = wc_get_product( $filters['product_id'] );
			$name    = $product ? $product->get_formatted_name() : sprintf( __( 'Product #%d', 'wc-stock-log' ), $filters['product_id'] );
			echo '<div class="wsl-active-filter">' .
				sprintf(
					/* translators: %s: product name */
					esc_html__( 'Showing history for: %s', 'wc-stock-log' ),
					'<strong>' . esc_html( wp_strip_all_tags( $name ) ) . '</strong>'
				) .
				' <a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( '× Clear', 'wc-stock-log' ) . '</a>' .
				'</div>';
		}

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
		if ( $filters['product_id'] ) {
			echo '<input type="hidden" name="product_id" value="' . absint( $filters['product_id'] ) . '" />';
		}
		$table->search_box( __( 'Search (name or SKU)', 'wc-stock-log' ), 'wsl-search' );
		$table->display();
		echo '</form>';

		self::render_settings();

		echo '</div>';
	}

	private static function render_settings() {
		global $wpdb;

		$retention = absint( get_option( 'wsl_retention_days', 365 ) );
		$total     = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . WSL_Install::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		echo '<details class="wsl-settings">';
		echo '<summary>' . esc_html__( 'Settings', 'wc-stock-log' ) . '</summary>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="wsl_save_settings" />';
		wp_nonce_field( 'wsl_save_settings' );

		echo '<p><label for="wsl_retention_days">' . esc_html__( 'Keep history for (days):', 'wc-stock-log' ) . '</label> ';
		echo '<input type="number" min="0" step="1" id="wsl_retention_days" name="wsl_retention_days" value="' . esc_attr( $retention ) . '" class="small-text" /> ';
		echo '<span class="description">' . esc_html__( '0 = never delete automatically. Older entries are removed once a day.', 'wc-stock-log' ) . '</span></p>';

		/* translators: %s: number of entries */
		echo '<p class="description">' . sprintf( esc_html__( 'Total entries in the database: %s', 'wc-stock-log' ), esc_html( number_format_i18n( $total ) ) ) . '</p>';

		submit_button( __( 'Save', 'wc-stock-log' ) );
		echo '</form>';
		echo '</details>';
	}

	public static function save_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wc-stock-log' ) );
		}
		check_admin_referer( 'wsl_save_settings' );

		$days = isset( $_POST['wsl_retention_days'] ) ? absint( $_POST['wsl_retention_days'] ) : 365;
		update_option( 'wsl_retention_days', $days, 'no' );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&wsl_saved=1' ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Εξαγωγή CSV
	 * ------------------------------------------------------------------- */

	public static function export_csv() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wc-stock-log' ) );
		}
		check_admin_referer( 'wsl_export' );

		require_once WSL_DIR . 'includes/class-wsl-list-table.php';

		global $wpdb;
		$table   = WSL_Install::table();
		$where   = WSL_List_Table::build_where( WSL_List_Table::get_filters() );
		$sources = WSL_Logger::sources();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=stock-log-' . gmdate( 'Ymd-His' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		// BOM ώστε το Excel να διαβάζει σωστά τα ελληνικά.
		fwrite( $out, "\xEF\xBB\xBF" );

		fputcsv(
			$out,
			array(
				__( 'Date', 'wc-stock-log' ),
				__( 'Product', 'wc-stock-log' ),
				'SKU',
				__( 'Old stock', 'wc-stock-log' ),
				__( 'New stock', 'wc-stock-log' ),
				__( 'Adjustment', 'wc-stock-log' ),
				__( 'Event', 'wc-stock-log' ),
				__( 'Order', 'wc-stock-log' ),
				__( 'User', 'wc-stock-log' ),
				'user_id',
				'product_id',
			),
			',',
			'"',
			'\\'
		);

		$batch  = 2000;
		$offset = 0;
		$max    = 50000;

		while ( $offset < $max ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$batch,
					$offset
				)
			);
			if ( empty( $rows ) ) {
				break;
			}
			foreach ( $rows as $row ) {
				fputcsv(
					$out,
					array(
						get_date_from_gmt( $row->created_at, 'd/m/Y H:i' ),
						$row->product_name,
						$row->sku,
						null === $row->old_qty ? '' : wc_stock_amount( (float) $row->old_qty ),
						null === $row->new_qty ? '' : wc_stock_amount( (float) $row->new_qty ),
						null === $row->delta ? '' : wc_stock_amount( (float) $row->delta ),
						isset( $sources[ $row->source ] ) ? $sources[ $row->source ] : $row->source,
						$row->order_id ? '#' . $row->order_id : '',
						$row->user_id ? $row->user_name : __( 'System / Guest', 'wc-stock-log' ),
						$row->user_id,
						$row->product_id,
					),
					',',
					'"',
					'\\'
				);
			}
			$offset += $batch;
		}

		fclose( $out );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Σύνδεσμοι στην επεξεργασία προϊόντος (όπως το «View adjustment history»)
	 * ------------------------------------------------------------------- */

	public static function product_history_link() {
		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return;
		}
		echo '<p class="form-field wsl-history-link"><a href="' . esc_url( self::history_url( $product_id ) ) . '">' .
			esc_html__( '↗ View stock history', 'wc-stock-log' ) . '</a></p>';
	}

	public static function variation_history_link( $loop, $variation_data, $variation ) {
		if ( empty( $variation->ID ) ) {
			return;
		}
		echo '<p class="form-row form-row-full wsl-history-link"><a href="' . esc_url( self::history_url( (int) $variation->ID ) ) . '">' .
			esc_html__( '↗ Variation stock history', 'wc-stock-log' ) . '</a></p>';
	}

	public static function history_url( $product_id ) {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&product_id=' . absint( $product_id ) );
	}
}
