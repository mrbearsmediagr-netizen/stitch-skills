<?php
/**
 * Εγκατάσταση: πίνακας βάσης, cron καθαρισμού, αναβαθμίσεις.
 *
 * @package WC_Stock_Log
 */

defined( 'ABSPATH' ) || exit;

class WSL_Install {

	/**
	 * Πλήρες όνομα πίνακα (με WP prefix).
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wsl_stock_log';
	}

	public static function activate() {
		self::create_table();

		if ( ! wp_next_scheduled( 'wsl_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wsl_daily_cleanup' );
		}

		add_option( 'wsl_retention_days', 365, '', 'no' );
		update_option( 'wsl_db_version', WSL_VERSION, 'no' );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'wsl_daily_cleanup' );
	}

	/**
	 * Τρέχει μόνο στη σελίδα του ιστορικού — αναβάθμιση σχήματος μετά από update.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'wsl_db_version' ) !== WSL_VERSION ) {
			self::create_table();
			update_option( 'wsl_db_version', WSL_VERSION, 'no' );
		}
		if ( ! wp_next_scheduled( 'wsl_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wsl_daily_cleanup' );
		}
	}

	public static function create_table() {
		global $wpdb;

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			product_name VARCHAR(255) NOT NULL DEFAULT '',
			sku VARCHAR(120) NOT NULL DEFAULT '',
			old_qty DECIMAL(15,3) NULL,
			new_qty DECIMAL(15,3) NULL,
			delta DECIMAL(15,3) NULL,
			source VARCHAR(24) NOT NULL DEFAULT 'other',
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_name VARCHAR(120) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY parent_id (parent_id),
			KEY user_id (user_id),
			KEY source (source),
			KEY order_id (order_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Ημερήσιος καθαρισμός: σβήνει εγγραφές παλαιότερες από Χ ημέρες (0 = ποτέ).
	 */
	public static function cleanup() {
		global $wpdb;

		$days = absint( get_option( 'wsl_retention_days', 365 ) );
		if ( $days < 1 ) {
			return;
		}

		$table     = self::table();
		$threshold = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		// Σε δόσεις, για να μην κλειδώνει ο πίνακας σε μεγάλα ιστορικά.
		for ( $i = 0; $i < 20; $i++ ) {
			$deleted = $wpdb->query(
				$wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT 5000", $threshold ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
			if ( empty( $deleted ) ) {
				break;
			}
		}
	}
}
