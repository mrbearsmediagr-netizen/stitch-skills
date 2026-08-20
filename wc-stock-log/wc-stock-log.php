<?php
/**
 * Plugin Name:          WC Stock Log – Ιστορικό Αποθέματος
 * Description:          Καταγράφει κάθε αλλαγή αποθέματος σε προϊόντα και παραλλαγές του WooCommerce: ποιος την έκανε, πότε, από πού (παραγγελία, επιστροφή χρημάτων, χειροκίνητη επεξεργασία, εισαγωγή CSV, REST API κ.λπ.), με ιστορικό ανά προϊόν, φίλτρα και εξαγωγή CSV — στο στιλ του «adjustment history» του Shopify.
 * Version:              1.0.0
 * Author:               Bears Media
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 7.0
 * Text Domain:          wc-stock-log
 * License:              GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'WSL_VERSION', '1.0.0' );
define( 'WSL_FILE', __FILE__ );
define( 'WSL_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSL_URL', plugin_dir_url( __FILE__ ) );

require_once WSL_DIR . 'includes/class-wsl-install.php';

register_activation_hook( __FILE__, array( 'WSL_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WSL_Install', 'deactivate' ) );

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
					esc_html__( 'Το «WC Stock Log» χρειάζεται ενεργό WooCommerce για να λειτουργήσει.', 'wc-stock-log' ) .
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
