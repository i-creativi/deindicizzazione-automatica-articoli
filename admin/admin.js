/* Deindicizzazione Automatica Articoli: impostazioni "Contenuti da elaborare". */
( function () {
	'use strict';

	function update( target ) {
		var toggle = target.querySelector( '.icrea-target-toggle input' );
		var select = target.querySelector( '.icrea-target-taxonomy' );
		target.classList.toggle( 'is-enabled', toggle.checked );
		target.querySelectorAll( '.icrea-target-terms' ).forEach( function ( list ) {
			if ( list.getAttribute( 'data-taxonomy' ) === select.value ) {
				list.removeAttribute( 'data-inactive' );
			} else {
				list.setAttribute( 'data-inactive', '1' );
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var targets = document.querySelectorAll( '.icrea-target' );
		if ( ! targets.length ) {
			return;
		}
		document.documentElement.classList.add( 'icrea-js' );
		targets.forEach( function ( target ) {
			update( target );
			target.addEventListener( 'change', function () {
				update( target );
			} );
		} );
	} );
}() );
