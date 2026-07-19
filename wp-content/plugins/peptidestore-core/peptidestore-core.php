<?php
/**
 * Plugin Name:       Peptide Store Core
 * Plugin URI:        https://example.ca
 * Description:       Custom core functionality for our WooCommerce research-peptide store: compliance gating, schema/GEO output, and custom payment gateways. Keep functionality here (not in the theme) so it survives theme changes.
 * Version:           0.1.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Your Brand
 * License:           GPL-2.0-or-later
 * Text Domain:       peptidestore
 */

defined( 'ABSPATH' ) || exit;

define( 'PEPTIDE_STORE_VERSION', '0.1.1' );
define( 'PEPTIDE_STORE_FILE', __FILE__ );
define( 'PEPTIDE_STORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PEPTIDE_STORE_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'peptidestore_bootstrap' );
function peptidestore_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Peptide Store Core requires WooCommerce to be installed and active.', 'peptidestore' );
			echo '</p></div>';
		} );
		return;
	}
	require_once PEPTIDE_STORE_PATH . 'includes/class-core.php';
	Peptide_Store\Core::instance()->init();
}

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables', PEPTIDE_STORE_FILE, true
		);
	}
} );
