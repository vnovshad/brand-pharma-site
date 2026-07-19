<?php
/**
 * Registers our gateways with the WooCommerce Cart/Checkout blocks so they
 * appear on the modern block-based checkout (not just classic shortcode).
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Blocks_Support {

	public function __construct() {
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register' ) );
	}

	/**
	 * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry
	 */
	public function register( $registry ): void {
		if ( ! class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once PEPTIDE_STORE_PATH . 'includes/blocks/class-block-method.php';
		$registry->register( new Blocks\Block_Method( 'peptidestore_etransfer' ) );
	}
}
