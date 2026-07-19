<?php
/**
 * Payment gateways module. Registers custom gateways. Keep modular/swappable
 * (processors drop high-risk merchants). Ships an Interac e-Transfer (offline)
 * gateway; add crypto / high-risk card gateways the same way.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Gateways {
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_gateways' ), 11 );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register' ) );
	}

	public function load_gateways(): void {
		require_once PEPTIDE_STORE_PATH . 'includes/gateways/class-etransfer-gateway.php';
		// Crypto handled by a dedicated third-party plugin. Add a high-risk card
		// gateway here when a processor underwrites us.
	}

	public function register( array $gateways ): array {
		$gateways[] = \Peptide_Store\Gateways\Etransfer_Gateway::class;
		return $gateways;
	}
}
