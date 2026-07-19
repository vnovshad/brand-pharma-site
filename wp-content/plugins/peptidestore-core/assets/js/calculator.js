/* Peptide Store — Peptide Dosage Calculator with syringe visual.
 *
 * Logic:
 *   desired_mcg = desired_mg × 1000   (dose entered in mg)
 *   total_mcg   = vial_mg    × 1000
 *   mcg_per_ml  = total_mcg  ÷ water_ml
 *   required_ml = desired_mcg ÷ mcg_per_ml
 *   ml_per_unit = syringe_ml ÷ total_units
 *   draw_units  = required_ml ÷ ml_per_unit
 *
 * For research calculation purposes only.
 */
( function () {
	'use strict';

	function round( n, dp ) { return parseFloat( n.toFixed( dp ) ); }

	function fieldVal( el, name ) {
		var node = el.querySelector( '[data-field="' + name + '"]' );
		return node ? parseFloat( node.value ) : NaN;
	}

	// ── SVG syringe (barrel only) ─────────────────────────────────────────────

	function buildSyringe( drawUnits, totalUnits, isError, uid ) {
		var VW = 560, VH = 86;
		var bx = 20, by = 6, bw = 520, bh = 44;
		var tickBaseY = by + bh;
		var bMid      = by + bh / 2;

		// drawUnits === -1 means "render barrel only, no marker, no fill"
		var empty      = ( drawUnits === -1 );
		var fraction   = empty ? 0 : Math.min( Math.max( drawUnits / totalUnits, 0 ), 1 );
		var markerX    = bx + fraction * bw;
		var showMarker = !empty && !isError && isFinite( drawUnits ) && drawUnits >= 0 && drawUnits <= totalUnits;

		var parts = [];
		var clipId = 'psc-bclip-' + uid;

		parts.push( '<defs><clipPath id="' + clipId + '"><rect x="' + bx + '" y="' + by + '" width="' + bw + '" height="' + bh + '" rx="4"/></clipPath></defs>' );

		// Barrel background
		parts.push( '<rect x="' + bx + '" y="' + by + '" width="' + bw + '" height="' + bh + '" rx="4" fill="#f0f0f0" stroke="none"/>' );

		// Fill (drawn volume left → right) — only when there's a real result
		if ( showMarker && fraction > 0 ) {
			parts.push( '<rect x="' + bx + '" y="' + by + '" width="' + ( fraction * bw ) + '" height="' + bh + '" fill="#c8d8e8" clip-path="url(#' + clipId + ')"/>' );
		}

		// Barrel border
		parts.push( '<rect x="' + bx + '" y="' + by + '" width="' + bw + '" height="' + bh + '" rx="4" fill="none" stroke="#999" stroke-width="1.5"/>' );

		// Ruler-style tick marks: 5 sub-ticks between each labeled major tick.
		// Major (labeled, tall) at 0, 10, 20 … totalUnits.
		// Mid (medium)         at 5, 15, 25 … (halfway between majors).
		// Minor (short)        everywhere else.
		var subdivisions   = 5;
		var totalSegments  = 10 * subdivisions; // 50 segments across the barrel

		for ( var i = 0; i <= totalSegments; i++ ) {
			var tx      = bx + ( i / totalSegments ) * bw;
			var isMajor = ( i % subdivisions === 0 );
			var isMid   = ( !isMajor && i % subdivisions === Math.floor( subdivisions / 2 ) );

			var th  = isMajor ? 12 : ( isMid ? 8 : 5 );
			var col = isMajor ? '#555' : ( isMid ? '#888' : '#bbb' );
			var sw  = isMajor ? 1.5 : 1;

			parts.push(
				'<line x1="' + tx + '" y1="' + tickBaseY + '" x2="' + tx + '" y2="' + ( tickBaseY + th ) + '" stroke="' + col + '" stroke-width="' + sw + '"/>'
			);

			// Label only on major ticks
			if ( isMajor ) {
				var lbl = round( ( i / totalSegments ) * totalUnits, 0 );
				parts.push(
					'<text x="' + tx + '" y="' + ( tickBaseY + 12 + 12 ) + '" text-anchor="middle" font-size="10" fill="#555" font-family="system-ui,sans-serif">' + lbl + '</text>'
				);
			}
		}

		parts.push( '<text x="' + ( bx + bw / 2 ) + '" y="' + ( VH - 1 ) + '" text-anchor="middle" font-size="9" fill="#999" font-family="system-ui,sans-serif">Units</text>' );

		// Red dashed marker + label
		if ( showMarker ) {
			parts.push( '<line x1="' + markerX + '" y1="' + ( by - 2 ) + '" x2="' + markerX + '" y2="' + ( tickBaseY + 13 ) + '" stroke="#dc2626" stroke-width="2.5" stroke-dasharray="5,4" stroke-linecap="round"/>' );

			var labelX      = Math.min( Math.max( markerX, bx + 16 ), bx + bw - 16 );
			var labelAnchor = fraction < 0.08 ? 'start' : ( fraction > 0.92 ? 'end' : 'middle' );
			var labelFill   = fraction > 0.14 ? '#ffffff' : '#1a2332';
			parts.push( '<text x="' + labelX + '" y="' + ( bMid + 4 ) + '" text-anchor="' + labelAnchor + '" font-size="12" font-weight="700" fill="' + labelFill + '" font-family="system-ui,sans-serif">' + round( drawUnits, 2 ) + ' U</text>' );
		}

		return '<svg viewBox="0 0 ' + VW + ' ' + VH + '" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" style="width:100%;height:auto;display:block;">' + parts.join( '' ) + '</svg>';
	}

	// ── Calculation ───────────────────────────────────────────────────────────

	function recalc( el ) {
		var textEl    = el.querySelector( '[data-output="text"]' );
		var syringeEl = el.querySelector( '[data-output="syringe"]' );
		var instrEl   = el.querySelector( '[data-output="instructions"]' );
		var resultEl  = el.querySelector( '[data-output="result"]' );
		var uid       = el.dataset.pscCalc || 'x';

		var syringe_ml  = fieldVal( el, 'syringe_ml' );
		var total_units = fieldVal( el, 'total_units' );
		var vial_mg     = fieldVal( el, 'vial_mg' );
		var water_ml    = fieldVal( el, 'water_ml' );
		var desired_mg  = fieldVal( el, 'desired_mg' );

		var allValid = [ syringe_ml, total_units, vial_mg, water_ml, desired_mg ]
			.every( function ( v ) { return !isNaN( v ) && v > 0; } );

		if ( !allValid ) {
			// Hide only the text and instructions — keep the empty syringe barrel visible.
			if ( textEl )  { textEl.textContent = ''; textEl.style.display = 'none'; }
			if ( instrEl ) { instrEl.innerHTML = ''; }
			var heading = resultEl.querySelector( '.psc-calc__your-dosage' );
			if ( heading ) heading.style.display = 'none';
			// Render empty syringe (barrel + ticks, no marker, no fill)
			if ( syringeEl && total_units > 0 && syringe_ml > 0 ) {
				syringeEl.innerHTML = buildSyringe( -1, total_units, true, uid );
			} else if ( syringeEl ) {
				syringeEl.innerHTML = buildSyringe( -1, 100, true, uid );
			}
			return;
		}

		// All inputs valid — show everything
		if ( textEl )  textEl.style.display = '';
		var heading = resultEl.querySelector( '.psc-calc__your-dosage' );
		if ( heading ) heading.style.display = '';

		var desired_mcg = desired_mg * 1000;
		var total_mcg   = vial_mg * 1000;
		var mcg_per_ml  = total_mcg  / water_ml;
		var required_ml = desired_mcg / mcg_per_ml;
		var ml_per_unit = syringe_ml  / total_units;
		var draw_units  = required_ml / ml_per_unit;

		if ( draw_units > total_units ) {
			if ( textEl )  { textEl.textContent = 'Required draw exceeds syringe capacity.'; textEl.style.display = ''; }
			if ( instrEl )   instrEl.innerHTML = '';
			if ( syringeEl ) syringeEl.innerHTML = buildSyringe( draw_units, total_units, true, uid );
			resultEl.dataset.state = 'error';
			return;
		}

		if ( draw_units <= 0 || !isFinite( draw_units ) ) {
			if ( textEl )  { textEl.textContent = 'Result is out of range. Check inputs.'; textEl.style.display = ''; }
			if ( instrEl )   instrEl.innerHTML = '';
			if ( syringeEl ) syringeEl.innerHTML = buildSyringe( -1, total_units, true, uid );
			resultEl.dataset.state = 'error';
			return;
		}

		// ── Success ───────────────────────────────────────────────────────────

		textEl.textContent = 'Draw to ' + round( draw_units, 2 ) + ' Units';
		resultEl.dataset.state = 'ok';

		// Syringe visual
		if ( syringeEl ) {
			syringeEl.innerHTML = buildSyringe( draw_units, total_units, false, uid );
		}

		// Numbered instructions
		if ( instrEl ) {
			var steps = [
				'Reconstitute your peptide with ' + round( water_ml, 1 ) + ' ml bacteriostatic water',
				'Draw to exactly ' + round( draw_units, 2 ) + ' units on your syringe as shown by the red line',
				'This provides a dose of ' + round( desired_mg, 3 ) + ' mg',
				'Store reconstituted peptide solution at 2-8 °C for laboratory use'
			];
			var html = '<p class="psc-calc__instr-label">Instructions:</p><ol class="psc-calc__instr-list">';
			steps.forEach( function ( s ) { html += '<li>' + s + '</li>'; } );
			html += '</ol>';
			instrEl.innerHTML = html;
		}
	}

	// ── Init ──────────────────────────────────────────────────────────────────

	function initCalc( el ) {
		var inputs = el.querySelectorAll( '.psc-calc__input' );
		Array.prototype.forEach.call( inputs, function ( inp ) {
			inp.addEventListener( 'input',  function () { recalc( el ); } );
			inp.addEventListener( 'change', function () { recalc( el ); } );
		} );
		recalc( el );
	}

	function boot() {
		var calcs = document.querySelectorAll( '[data-psc-calc]' );
		Array.prototype.forEach.call( calcs, initCalc );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
