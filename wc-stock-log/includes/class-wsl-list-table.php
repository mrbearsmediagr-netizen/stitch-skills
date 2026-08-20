<?php
/**
 * Πίνακας ιστορικού αποθέματος (WP_List_Table).
 *
 * @package WC_Stock_Log
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WSL_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'wsl_log',
				'plural'   => 'wsl_logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Διαβάζει τα φίλτρα από το request (κοινό με την εξαγωγή CSV).
	 */
	public static function get_filters() {
		// phpcs:disable WordPress.Security.NonceVerification -- φίλτρα ανάγνωσης μόνο, με έλεγχο δικαιωμάτων στη σελίδα.
		return array(
			'product_id' => isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0,
			'user_id'    => isset( $_GET['filter_user'] ) ? absint( $_GET['filter_user'] ) : 0,
			'source'     => isset( $_GET['filter_source'] ) ? sanitize_key( $_GET['filter_source'] ) : '',
			'order_id'   => isset( $_GET['filter_order'] ) ? absint( $_GET['filter_order'] ) : 0,
			'from'       => isset( $_GET['filter_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_from'] ) ) : '',
			'to'         => isset( $_GET['filter_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_to'] ) ) : '',
			's'          => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);
		// phpcs:enable
	}

	/**
	 * Χτίζει το WHERE (prepared) από τα φίλτρα.
	 *
	 * @return string Πλήρες "WHERE ..." ή κενό.
	 */
	public static function build_where( $f ) {
		global $wpdb;
		$where = array();

		if ( $f['product_id'] ) {
			$where[] = $wpdb->prepare( '(product_id = %d OR parent_id = %d)', $f['product_id'], $f['product_id'] );
		}
		if ( $f['user_id'] ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $f['user_id'] );
		}
		if ( $f['source'] ) {
			$where[] = $wpdb->prepare( 'source = %s', $f['source'] );
		}
		if ( $f['order_id'] ) {
			$where[] = $wpdb->prepare( 'order_id = %d', $f['order_id'] );
		}
		if ( $f['from'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f['from'] ) ) {
			$where[] = $wpdb->prepare( 'created_at >= %s', get_gmt_from_date( $f['from'] . ' 00:00:00' ) );
		}
		if ( $f['to'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f['to'] ) ) {
			$where[] = $wpdb->prepare( 'created_at <= %s', get_gmt_from_date( $f['to'] . ' 23:59:59' ) );
		}
		if ( '' !== $f['s'] ) {
			$like    = '%' . $wpdb->esc_like( $f['s'] ) . '%';
			$where[] = $wpdb->prepare( '(product_name LIKE %s OR sku LIKE %s)', $like, $like );
		}

		return $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
	}

	public function get_columns() {
		return array(
			'created_at' => __( 'Ημερομηνία', 'wc-stock-log' ),
			'product'    => __( 'Προϊόν', 'wc-stock-log' ),
			'event'      => __( 'Συμβάν', 'wc-stock-log' ),
			'user'       => __( 'Από ποιον', 'wc-stock-log' ),
			'delta'      => __( 'Μεταβολή', 'wc-stock-log' ),
			'stock'      => __( 'Απόθεμα', 'wc-stock-log' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'delta'      => array( 'delta', false ),
			'stock'      => array( 'new_qty', false ),
		);
	}

	public function prepare_items() {
		global $wpdb;

		$table    = WSL_Install::table();
		$filters  = self::get_filters();
		$where    = self::build_where( $filters );
		$per_page = (int) apply_filters( 'wsl_per_page', 50 );

		$orderby_allowed = array( 'created_at', 'delta', 'new_qty' );
		$orderby         = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $orderby_allowed, true ) ? $_GET['orderby'] : 'created_at'; // phpcs:ignore WordPress.Security.NonceVerification
		$order           = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( $_GET['order'] ) ) ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$page   = max( 1, $this->get_pagenum() );
		$offset = ( $page - 1 ) * $per_page;

		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}, id {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'created_at' );
	}

	/* ---------------------------------------------------------------------
	 * Στήλες
	 * ------------------------------------------------------------------- */

	public function column_created_at( $row ) {
		$local = get_date_from_gmt( $row->created_at, 'Y-m-d H:i:s' );
		return '<span class="wsl-date">' . esc_html( date_i18n( 'd/m/Y', strtotime( $local ) ) ) . '</span>' .
			'<span class="wsl-muted">' . esc_html( date_i18n( 'H:i', strtotime( $local ) ) ) . '</span>';
	}

	public function column_product( $row ) {
		$edit_id = $row->parent_id ? $row->parent_id : $row->product_id;
		$url     = get_edit_post_link( $edit_id, 'raw' );
		$name    = $row->product_name ? $row->product_name : sprintf( __( 'Προϊόν #%d', 'wc-stock-log' ), $row->product_id );

		$html = $url
			? '<a class="wsl-product" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>'
			: '<span class="wsl-product">' . esc_html( $name ) . '</span>';

		if ( $row->sku ) {
			$html .= '<span class="wsl-muted">SKU: ' . esc_html( $row->sku ) . '</span>';
		}
		return $html;
	}

	public function column_event( $row ) {
		$sources = WSL_Logger::sources();
		$label   = isset( $sources[ $row->source ] ) ? $sources[ $row->source ] : $row->source;
		$class   = 'wsl-chip wsl-chip--' . sanitize_html_class( $row->source );

		$html = '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';

		if ( $row->order_id ) {
			$order_url = self::order_edit_url( (int) $row->order_id );
			$html     .= ' <a class="wsl-order-link" href="' . esc_url( $order_url ) . '">#' . esc_html( $row->order_id ) . '</a>';
		}
		return $html;
	}

	public function column_user( $row ) {
		if ( ! $row->user_id ) {
			return '<span class="wsl-muted-strong">' . esc_html__( 'Σύστημα / Επισκέπτης', 'wc-stock-log' ) . '</span>';
		}
		$name = $row->user_name ? $row->user_name : sprintf( '#%d', $row->user_id );
		$url  = admin_url( 'user-edit.php?user_id=' . absint( $row->user_id ) );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
	}

	public function column_delta( $row ) {
		if ( null === $row->delta ) {
			return '<span class="wsl-badge wsl-badge--set">' . esc_html__( 'Ορισμός', 'wc-stock-log' ) . '</span>';
		}
		$delta = (float) $row->delta;
		if ( $delta > 0 ) {
			return '<span class="wsl-badge wsl-badge--up">+' . esc_html( wc_stock_amount( $delta ) ) . '</span>';
		}
		return '<span class="wsl-badge wsl-badge--down">−' . esc_html( wc_stock_amount( abs( $delta ) ) ) . '</span>';
	}

	public function column_stock( $row ) {
		$new = null === $row->new_qty ? '—' : wc_stock_amount( (float) $row->new_qty );
		$old = null === $row->old_qty ? '—' : wc_stock_amount( (float) $row->old_qty );
		return '<span class="wsl-stock-new">' . esc_html( $new ) . '</span>' .
			'<span class="wsl-muted">' . sprintf( esc_html__( 'από %s', 'wc-stock-log' ), esc_html( $old ) ) . '</span>';
	}

	public function column_default( $row, $column_name ) {
		return '';
	}

	public function no_items() {
		esc_html_e( 'Δεν υπάρχουν καταγραφές ακόμη. Μόλις αλλάξει το απόθεμα κάποιου προϊόντος, θα εμφανιστεί εδώ.', 'wc-stock-log' );
	}

	/* ---------------------------------------------------------------------
	 * Φίλτρα πάνω από τον πίνακα
	 * ------------------------------------------------------------------- */

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		global $wpdb;

		$f       = self::get_filters();
		$table   = WSL_Install::table();
		$sources = WSL_Logger::sources();

		// Χρήστες που έχουν κάνει αλλαγές (μικρό, indexed query).
		$user_ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$table} WHERE user_id > 0 ORDER BY user_id LIMIT 300" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		echo '<div class="alignleft actions wsl-filters">';

		echo '<select name="filter_source">';
		echo '<option value="">' . esc_html__( 'Όλα τα συμβάντα', 'wc-stock-log' ) . '</option>';
		foreach ( $sources as $key => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), selected( $f['source'], $key, false ), esc_html( $label ) );
		}
		echo '</select>';

		echo '<select name="filter_user">';
		echo '<option value="">' . esc_html__( 'Όλοι οι χρήστες', 'wc-stock-log' ) . '</option>';
		foreach ( $user_ids as $uid ) {
			$u     = get_userdata( (int) $uid );
			$label = $u ? $u->display_name : sprintf( __( 'Χρήστης #%d', 'wc-stock-log' ), $uid );
			printf( '<option value="%d" %s>%s</option>', (int) $uid, selected( $f['user_id'], (int) $uid, false ), esc_html( $label ) );
		}
		echo '</select>';

		printf(
			'<input type="date" name="filter_from" value="%s" title="%s" /> <span class="wsl-dash">–</span> <input type="date" name="filter_to" value="%s" title="%s" />',
			esc_attr( $f['from'] ),
			esc_attr__( 'Από ημερομηνία', 'wc-stock-log' ),
			esc_attr( $f['to'] ),
			esc_attr__( 'Έως ημερομηνία', 'wc-stock-log' )
		);

		submit_button( __( 'Φιλτράρισμα', 'wc-stock-log' ), '', 'filter_action', false );

		if ( $f['product_id'] || $f['user_id'] || $f['source'] || $f['from'] || $f['to'] || $f['s'] || $f['order_id'] ) {
			echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=wsl-stock-log' ) ) . '">' . esc_html__( 'Καθαρισμός', 'wc-stock-log' ) . '</a>';
		}

		echo '</div>';
	}

	/**
	 * URL επεξεργασίας παραγγελίας, συμβατό με HPOS χωρίς να φορτώνει το order.
	 */
	public static function order_edit_url( $order_id ) {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) &&
			is_callable( array( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) &&
			\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
		}
		return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}
}
