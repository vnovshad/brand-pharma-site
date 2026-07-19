/* Peptide Store — newsletter signup AJAX handler.
 * Scoped to .psc-signup__form elements; handles multiple forms per page. */
( function () {
	'use strict';

	function initForm( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var emailInput  = form.querySelector( '[name="psc_email"]' );
			var nameInput   = form.querySelector( '[name="psc_name"]' );
			var hpInput     = form.querySelector( '[name="psc_hp"]' );
			var statusEl    = form.querySelector( '.psc-signup__status' );
			var btn         = form.querySelector( '.psc-signup__btn' );
			var source      = form.dataset.source || 'footer';
			var strings     = ( window.pscSignup && window.pscSignup.strings ) || {};

			var email = emailInput ? emailInput.value.trim() : '';

			if ( !email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
				setStatus( statusEl, strings.invalid || 'Please enter a valid email address.', 'error' );
				return;
			}

			var origText = btn.textContent;
			btn.disabled    = true;
			btn.textContent = strings.sending || 'Subscribing…';
			setStatus( statusEl, '', '' );

			var body = new FormData();
			body.append( 'action', 'psc_subscribe' );
			body.append( 'nonce',  window.pscSignup ? window.pscSignup.nonce : '' );
			body.append( 'psc_email', email );
			body.append( 'psc_name',  nameInput ? nameInput.value.trim() : '' );
			body.append( 'psc_hp',    hpInput   ? hpInput.value : '' );
			body.append( 'source',    source );

			fetch( window.pscSignup ? window.pscSignup.ajaxUrl : '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body:   body,
				credentials: 'same-origin',
			} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				btn.disabled    = false;
				btn.textContent = origText;

				if ( data.success ) {
					setStatus( statusEl, strings.success || 'Thank you, you\'ve been subscribed.', 'success' );
					form.reset();
				} else {
					var code = ( data.data && data.data.code ) || '';
					var msg  = code === 'exists'
						? ( strings.exists || 'This address is already subscribed.' )
						: ( strings.error  || 'Something went wrong. Please try again.' );
					setStatus( statusEl, msg, code === 'exists' ? 'success' : 'error' );
				}
			} )
			.catch( function () {
				btn.disabled    = false;
				btn.textContent = origText;
				setStatus( statusEl, strings.error || 'Something went wrong. Please try again.', 'error' );
			} );
		} );
	}

	function setStatus( el, msg, type ) {
		if ( !el ) return;
		el.textContent = msg;
		el.className   = 'psc-signup__status' + ( type ? ' psc-signup__status--' + type : '' );
	}

	function boot() {
		document.querySelectorAll( '.psc-signup__form' ).forEach( initForm );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
