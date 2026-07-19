<?php
/**
 * Third-party gateway compatibility.
 *
 * The "verified-crypto-checkout" plugin injects extra settings fields into its
 * gateways via the `woocommerce_settings_api_form_fields_{id}` filter, but omits
 * the 'default' key on those injected fields. WooCommerce's set_defaults() runs
 * BEFORE that filter, so the injected fields never get a default — and then
 * WC_Settings_API::init_settings() calls wp_list_pluck( $fields, 'default' ) on
 * each unsaved gateway, emitting a PHP warning for every field missing the key
 * (hundreds per page across ~26 gateways).
 *
 * Root-cause fix: re-run the same backfill WooCommerce itself does (add
 * 'default' => '' where missing), but at a LATER priority so it sees the
 * plugin's injected fields. This corrects the malformed settings data without
 * changing any gateway behaviour — 'default' is only the pre-save fallback value
 * — and is update-safe (their files are untouched). Discovers the gateway IDs
 * from the plugin's own class files so new providers are covered automatically.
 *
 * The upstream bug should also be reported to the plugin author.
 *
 * @package Peptide_Store
 */
namespace Peptide_Store;

defined( 'ABSPATH' ) || exit;

class Gateway_Compat {

	public function __construct() {
		// Register during plugins_loaded (when this boots) — before WooCommerce
		// instantiates gateways on init, so the filters are in place in time.
		foreach ( $this->target_gateway_ids() as $id ) {
			add_filter( 'woocommerce_settings_api_form_fields_' . $id, array( $this, 'backfill_defaults' ), 99 );
		}
	}

	/**
	 * Gateway/setting IDs to repair. Auto-discovers verified-crypto-checkout's
	 * gateways from its class filenames (class-vccp-gateway-{name}.php →
	 * vccp-gateway-{name}) and includes its known background settings classes.
	 *
	 * @return string[]
	 */
	private function target_gateway_ids(): array {
		$ids = array(
			'vccp_auto_recovery',
			'vccp_order_payment_request',
			'vccp_renewal_reminder',
			'vccp_stuck_order_alert',
		);

		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$files = glob( WP_PLUGIN_DIR . '/verified-crypto-checkout/includes/class-vccp-gateway-*.php' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					$name  = preg_replace( '/^class-vccp-gateway-(.+)\.php$/', '$1', basename( $file ) );
					$ids[] = 'vccp-gateway-' . $name;
				}
			}
		}

		return $ids;
	}

	/**
	 * Add the missing 'default' key to any settings field that lacks it —
	 * identical to WooCommerce's own WC_Settings_API::set_defaults().
	 *
	 * @param mixed $fields Form fields array (or anything, if filtered oddly).
	 * @return mixed
	 */
	public function backfill_defaults( $fields ) {
		if ( is_array( $fields ) ) {
			foreach ( $fields as $key => $field ) {
				if ( is_array( $field ) && ! array_key_exists( 'default', $field ) ) {
					$fields[ $key ]['default'] = '';
				}
			}
		}
		return $fields;
	}
}
