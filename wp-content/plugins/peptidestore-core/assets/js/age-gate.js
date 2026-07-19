/* Peptide Store Core — age/research-use acknowledgement gate.
 * Client-side, cookie-based starting point. NOTE: client-side gating is a
 * notice/UX mechanism, not hard enforcement (bypassable). Move server-side if
 * real enforcement is needed. */
( function () {
	'use strict';
	var COOKIE = 'peptidestore_ack';
	var MAX_AGE = 60 * 60 * 24 * 30;
	function hasAck() {
		return document.cookie.split( ';' ).some( function ( c ) {
			return c.trim().indexOf( COOKIE + '=1' ) === 0;
		} );
	}
	function setAck() {
		document.cookie = COOKIE + '=1; path=/; max-age=' + MAX_AGE + '; SameSite=Lax';
	}
	document.addEventListener( 'DOMContentLoaded', function () {
		var gate = document.getElementById( 'peptidestore-age-gate' );
		if ( ! gate || hasAck() ) { return; }
		gate.hidden = false;
		document.body.style.overflow = 'hidden';
		var btn = gate.querySelector( '[data-peptidestore-age-confirm]' );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				setAck(); gate.hidden = true; document.body.style.overflow = '';
			} );
		}
	} );
} )();
