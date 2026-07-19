<?php
/**
 * Crypto payment discount.
 *
 * Applies an automatic percentage discount when the customer pays with the
 * crypto gateway (OxaPay). WooCommerce has no native "discount by payment
 * method", and on the block checkout selecting a method does not recalculate
 * totals — so this does two things:
 *
 *   1. Server-side (authoritative): a negative fee on every cart fee calc when
 *      the chosen gateway is crypto. This sets the real order total regardless
 *      of the browser, so the discount can't be spoofed or skipped.
 *   2. Live preview: a Store API update callback + a small script that forces a
 *      totals refresh the instant the shopper selects/deselects crypto, so the
 *      discount line appears immediately in the order summary.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Crypto_Discount {

	/** Gateway id that earns the discount (OxaPay). */
	const CRYPTO_GATEWAY = 'oxapay';

	/** Discount rate (0.10 = 10%). */
	const RATE = 0.10;

	/** Store API extension namespace + our session key. */
	const NAMESPACE   = 'brand_crypto_discount';
	const SESSION_KEY = 'brand_selected_pm';

	public function __construct() {
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'maybe_apply_discount' ) );
		add_action( 'woocommerce_init', array( $this, 'register_store_api_callback' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Which payment method is currently in effect? Prefer WooCommerce's own
	 * chosen_payment_method (set authoritatively when the order is placed); fall
	 * back to the value our preview callback stored while the shopper browses.
	 */
	private function selected_method(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}
		$method = (string) WC()->session->get( 'chosen_payment_method' );
		if ( '' === $method ) {
			$method = (string) WC()->session->get( self::SESSION_KEY );
		}
		return $method;
	}

	/** Add the negative fee when paying with crypto. */
	public function maybe_apply_discount( $cart ): void {
		// Run during frontend + AJAX + Store API (REST); skip wp-admin screens.
		if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! $cart instanceof \WC_Cart ) {
			return;
		}
		if ( self::CRYPTO_GATEWAY !== $this->selected_method() ) {
			return;
		}

		$subtotal = (float) $cart->get_subtotal(); // product subtotal, ex tax
		$discount = round( $subtotal * self::RATE, 2 );
		if ( $discount <= 0 ) {
			return;
		}

		$label = sprintf(
			/* translators: %s = discount percentage */
			__( 'Crypto Payment Discount (%s%%)', 'peptidestore-core' ),
			(string) round( self::RATE * 100 )
		);
		// Negative, non-taxable fee = a clean discount line in the summary.
		$cart->add_fee( $label, -$discount, false );
	}

	/**
	 * Register the Store API update callback. The block checkout calls this
	 * (via extensionCartUpdate) whenever the selected payment method changes;
	 * we stash the method so the fee recalculation above can see it, then
	 * WooCommerce returns freshly recalculated totals.
	 */
	public function register_store_api_callback(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => self::NAMESPACE,
				'callback'  => function ( $data ) {
					if ( ! WC()->session ) {
						return;
					}
					$method = isset( $data['paymentMethod'] ) ? sanitize_text_field( (string) $data['paymentMethod'] ) : '';
					WC()->session->set( self::SESSION_KEY, $method );
					// Keep WC's own value in sync during preview so totals are
					// consistent; it is overwritten authoritatively at placement.
					WC()->session->set( 'chosen_payment_method', $method );
				},
			)
		);
	}

	/** Load the preview script on the checkout only. */
	public function enqueue(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		wp_enqueue_script(
			'peptidestore-crypto-discount',
			PEPTIDE_STORE_URL . 'assets/js/crypto-discount.js',
			array( 'wc-blocks-checkout', 'wp-data' ),
			PEPTIDE_STORE_VERSION,
			true
		);
		wp_localize_script(
			'peptidestore-crypto-discount',
			'peptidestoreCryptoDiscount',
			array( 'namespace' => self::NAMESPACE )
		);
	}
}
