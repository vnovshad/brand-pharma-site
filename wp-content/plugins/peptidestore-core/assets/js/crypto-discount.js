/**
 * Live crypto-discount refresh for the block checkout.
 *
 * Selecting a payment method in the block checkout does NOT recalculate cart
 * totals on its own. We watch the active payment method in the WooCommerce data
 * store and, whenever it changes, call extensionCartUpdate() to ping our Store
 * API callback — which records the method server-side and returns recalculated
 * totals (with or without the crypto discount line).
 */
( function () {
	var cfg = window.peptidestoreCryptoDiscount || {};
	var ns = cfg.namespace || 'brand_crypto_discount';

	function start() {
		var wpData = window.wp && window.wp.data;
		var blocksCheckout = window.wc && window.wc.blocksCheckout;
		if ( ! wpData || ! blocksCheckout || ! blocksCheckout.extensionCartUpdate ) {
			return; // not a block checkout, or deps missing — server still secures totals
		}

		var last = null;
		wpData.subscribe( function () {
			var store = wpData.select( 'wc/store/payment' );
			if ( ! store || ! store.getActivePaymentMethod ) {
				return;
			}
			var pm = store.getActivePaymentMethod();
			if ( pm === last ) {
				return;
			}
			last = pm;
			blocksCheckout.extensionCartUpdate( {
				namespace: ns,
				data: { paymentMethod: pm },
			} ).catch( function () {} );
		} );
	}

	if ( document.readyState !== 'loading' ) {
		start();
	} else {
		document.addEventListener( 'DOMContentLoaded', start );
	}
} )();
