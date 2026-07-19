/**
 * Registers our payment gateways with the WooCommerce Checkout/Cart blocks.
 * Without this, classic WC_Payment_Gateway gateways don't appear on the
 * block-based checkout. Reads per-method data exposed by the PHP integration
 * (Block_Method::get_payment_method_data → "{id}_data" setting).
 */
( function () {
	var registry = window.wc && window.wc.wcBlocksRegistry;
	var settings = window.wc && window.wc.wcSettings;
	var element  = window.wp && window.wp.element;

	if ( ! registry || ! registry.registerPaymentMethod || ! settings || ! element ) {
		return;
	}

	var ids = [ 'peptidestore_etransfer' ];

	ids.forEach( function ( id ) {
		var data = settings.getSetting( id + '_data', null );
		if ( ! data ) {
			return; // not active / not configured
		}

		var content = element.createElement( 'div', {
			dangerouslySetInnerHTML: { __html: data.description || '' },
		} );

		// Label: title on the left, icon on the right (like card logos).
		var label = data.icon
			? element.createElement(
				'span',
				{ style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' } },
				element.createElement( 'span', null, data.title || '' ),
				element.createElement( 'img', {
					src: data.icon,
					alt: '',
					style: { height: '26px', width: 'auto', marginLeft: '8px' },
				} )
			)
			: ( data.title || '' );

		registry.registerPaymentMethod( {
			name: id,
			label: label,
			ariaLabel: data.title || '',
			canMakePayment: function () {
				return true;
			},
			content: content,
			edit: content,
			supports: {
				features: ( data.supports && data.supports.length ) ? data.supports : [ 'products' ],
			},
		} );
	} );
} )();
