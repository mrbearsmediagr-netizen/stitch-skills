<?php
/**
 * Καθαρισμός κατά την πλήρη διαγραφή του plugin.
 *
 * @package WC_Stock_Log
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wsl_stock_log" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'wsl_retention_days' );
delete_option( 'wsl_db_version' );

wp_clear_scheduled_hook( 'wsl_daily_cleanup' );
