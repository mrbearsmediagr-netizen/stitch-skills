<?php
/**
 * Plugin Name:          WC Stock Log – Stock History
 * Description:          Lightweight stock audit log for WooCommerce: records every stock change on products and variations — who, when, old/new quantity and source (order, refund, manual edit, CSV import, REST API etc.) — with per-product history, filters and CSV export. Shopify-style adjustment history.
 * Version:              1.1.0
 * Author:               Bears Media
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 7.0
 * Text Domain:          wc-stock-log
 * License:              GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'WSL_VERSION', '1.1.0' );
define( 'WSL_FILE', __FILE__ );
define( 'WSL_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSL_URL', plugin_dir_url( __FILE__ ) );

require_once WSL_DIR . 'includes/class-wsl-install.php';

register_activation_hook( __FILE__, array( 'WSL_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WSL_Install', 'deactivate' ) );

// Μεταφράσεις: αγγλικά ως βάση, ελληνικά όταν η γλώσσα του site/χρήστη είναι ελληνικά.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'wc-stock-log', false, dirname( plugin_basename( WSL_FILE ) ) . '/languages' );
	}
);

// Συμβατότητα με HPOS (custom order tables).
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', 'wsl_boot', 20 );

function wsl_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>' .
					esc_html__( 'WC Stock Log requires an active WooCommerce installation.', 'wc-stock-log' ) .
					'</p></div>';
			}
		);
		return;
	}

	require_once WSL_DIR . 'includes/class-wsl-logger.php';
	WSL_Logger::init();

	add_action( 'wsl_daily_cleanup', array( 'WSL_Install', 'cleanup' ) );

	if ( is_admin() ) {
		require_once WSL_DIR . 'includes/class-wsl-admin.php';
		WSL_Admin::init();
	}
}
