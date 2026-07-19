<?php
/**
 * WooCommerce Blocks payment-method integration for one of our gateways.
 *
 * Classic WC_Payment_Gateway gateways don't appear on the block-based Checkout
 * unless an integration like this is registered. One instance per gateway id.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store\Blocks;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Block_Method extends AbstractPaymentMethodType {

	public function __construct( string $gateway_id ) {
		$this->name = $gateway_id;
	}

	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );
	}

	public function is_active(): bool {
		// Base this on the gateway's real enabled state, not the raw saved
		// option — the option may be empty if the gateway has never been saved,
		// yet the gateway still defaults to enabled.
		$gateway = $this->get_gateway();
		return $gateway ? ( 'yes' === $gateway->enabled ) : false;
	}

	public function get_payment_method_script_handles(): array {
		$handle = 'peptidestore-blocks';
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script(
				$handle,
				PEPTIDE_STORE_URL . 'assets/js/blocks/payment-methods.js',
				array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
				PEPTIDE_STORE_VERSION,
				true
			);
		}
		return array( $handle );
	}

	public function get_payment_method_data(): array {
		$gateway = $this->get_gateway();
		return array(
			'title'       => $gateway ? $gateway->get_title() : '',
			'description' => $gateway ? wpautop( wptexturize( (string) $gateway->get_description() ) ) : '',
			'icon'        => ( $gateway && ! empty( $gateway->icon ) ) ? $gateway->icon : '',
			'supports'    => $gateway ? array_values( (array) $gateway->supports ) : array( 'products' ),
		);
	}

	private function get_gateway() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return null;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		return $gateways[ $this->name ] ?? null;
	}
}
