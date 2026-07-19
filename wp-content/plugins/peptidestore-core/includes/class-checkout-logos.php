<?php
/**
 * Checkout crypto-logo row.
 *
 * OxaPay renders its description as plain text, so we can't inject logos via the
 * description. Instead we enqueue a small script on the checkout that adds a row
 * of accepted-coin logos inside the OxaPay payment box when it's expanded.
 *
 * Logos are auto-discovered from the Media Library using the common
 * "{name}-{ticker}-logo.{ext}" file naming (e.g. cryptologos.cc downloads), so
 * uploading more coins makes them appear automatically — no code change.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Checkout_Logos {

	/** Display order + friendly names for alt text. */
	private const COINS = array(
		'btc'  => 'Bitcoin',
		'eth'  => 'Ethereum',
		'usdt' => 'Tether',
		'usdc' => 'USD Coin',
		'bnb'  => 'BNB',
		'doge' => 'Dogecoin',
		'matic' => 'Polygon',
		'ltc'  => 'Litecoin',
		'sol'  => 'Solana',
		'trx'  => 'Tron',
		'xmr'  => 'Monero',
		'dai'  => 'Dai',
		'bch'  => 'Bitcoin Cash',
		'xrp'  => 'XRP',
	);

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		$logos = $this->discover_logos();
		if ( empty( $logos ) ) {
			return;
		}
		wp_register_script(
			'peptidestore-checkout-logos',
			PEPTIDE_STORE_URL . 'assets/js/checkout-crypto-logos.js',
			array(),
			PEPTIDE_STORE_VERSION,
			true
		);
		wp_localize_script(
			'peptidestore-checkout-logos',
			'peptidestoreCheckoutLogos',
			array(
				'gateway' => 'oxapay',
				'heading' => '', // no heading — they accept more coins than shown
				'logos'   => $logos,
			)
		);
		wp_enqueue_script( 'peptidestore-checkout-logos' );
	}

	/** @return array<int,array{src:string,alt:string}> */
	private function discover_logos(): array {
		$uploads = wp_get_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] );
		$baseurl = trailingslashit( $uploads['baseurl'] );

		$files = array_merge(
			(array) glob( $base . '*-logo.{png,svg,jpg,jpeg,webp}', GLOB_BRACE ),
			(array) glob( $base . '*/*/*-logo.{png,svg,jpg,jpeg,webp}', GLOB_BRACE )
		);

		$found = array();
		foreach ( $files as $file ) {
			$name = basename( $file );
			if ( false !== strpos( $name, '-scaled.' ) ) {
				continue; // skip WordPress's scaled duplicate
			}
			if ( preg_match( '/-([a-z0-9]+)-logo\.(png|svg|jpe?g|webp)$/i', $name, $m ) ) {
				$ticker = strtolower( $m[1] );
				if ( ! isset( $found[ $ticker ] ) ) {
					$rel             = ltrim( str_replace( $base, '', $file ), '/\\' );
					$found[ $ticker ] = $baseurl . str_replace( '\\', '/', $rel );
				}
			}
		}

		$logos = array();
		// Known coins first, in our defined order.
		foreach ( self::COINS as $ticker => $label ) {
			if ( isset( $found[ $ticker ] ) ) {
				$logos[] = array( 'src' => $found[ $ticker ], 'alt' => $label );
				unset( $found[ $ticker ] );
			}
		}
		// Any other detected coins after.
		foreach ( $found as $ticker => $src ) {
			$logos[] = array( 'src' => $src, 'alt' => strtoupper( $ticker ) );
		}

		return $logos;
	}
}
