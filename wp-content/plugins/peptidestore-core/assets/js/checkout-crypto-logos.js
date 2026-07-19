/**
 * Adds a row of accepted-coin logos inside the OxaPay payment option on the
 * block checkout (shown when the option is expanded). Re-applies on re-render
 * via a MutationObserver, since the block checkout is React-managed.
 */
( function () {
	var cfg = window.peptidestoreCheckoutLogos;
	if ( ! cfg || ! cfg.logos || ! cfg.logos.length ) {
		return;
	}

	function buildRow() {
		var wrap = document.createElement( 'div' );
		wrap.className = 'psc-coin-row';

		if ( cfg.heading ) {
			var label = document.createElement( 'span' );
			label.className = 'psc-coin-row__label';
			label.textContent = cfg.heading;
			wrap.appendChild( label );
		}

		var icons = document.createElement( 'span' );
		icons.className = 'psc-coin-row__icons';
		cfg.logos.forEach( function ( logo ) {
			var img = document.createElement( 'img' );
			img.src = logo.src;
			img.alt = logo.alt;
			img.title = logo.alt;
			img.loading = 'lazy';
			img.className = 'psc-coin-row__icon';
			icons.appendChild( img );
		} );
		wrap.appendChild( icons );
		return wrap;
	}

	function findOxapayRadio() {
		return (
			document.querySelector( 'input[name="radio-control-wc-payment-method-options"][value="' + cfg.gateway + '"]' ) ||
			document.getElementById( 'radio-control-wc-payment-method-options-' + cfg.gateway ) ||
			document.querySelector( 'input[value="' + cfg.gateway + '"]' )
		);
	}

	function inject() {
		var radio = findOxapayRadio();
		if ( ! radio ) {
			return;
		}
		var option =
			radio.closest( '.wc-block-components-radio-control-accordion-option' ) ||
			radio.closest( '.wc-block-components-radio-control__option' ) ||
			radio.closest( 'li' ) ||
			radio.parentNode;
		if ( ! option ) {
			return;
		}
		// Only inject into the EXPANDED content area, so nothing shows while the
		// option is collapsed. If it isn't expanded, do nothing.
		var content =
			option.querySelector( '.wc-block-components-radio-control-accordion-content' ) ||
			option.querySelector( '.wc-block-components-radio-control-accordion-content-wrapper' );
		if ( ! content ) {
			return;
		}
		// Remove any stray row that landed outside the content area.
		option.querySelectorAll( '.psc-coin-row' ).forEach( function ( el ) {
			if ( ! content.contains( el ) ) {
				el.remove();
			}
		} );
		// One row only, inside the content area.
		if ( content.querySelector( '.psc-coin-row' ) ) {
			return;
		}
		content.appendChild( buildRow() );
	}

	var pending = false;
	function schedule() {
		if ( pending ) {
			return;
		}
		pending = true;
		window.setTimeout( function () {
			pending = false;
			try {
				inject();
			} catch ( e ) {}
		}, 60 );
	}

	if ( document.readyState !== 'loading' ) {
		schedule();
	}
	document.addEventListener( 'DOMContentLoaded', schedule );

	var observer = new MutationObserver( schedule );
	observer.observe( document.body, { childList: true, subtree: true } );
} )();
