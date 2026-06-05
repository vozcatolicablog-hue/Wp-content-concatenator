/* global document */
( function () {
	'use strict';

	/**
	 * When the user clicks an index link, open the target <details> entry
	 * (if it is collapsed) and smooth-scroll to it.
	 *
	 * Native <details>/<summary> handles open/close without JS;
	 * this script only improves the index-link UX.
	 */
	document
		.querySelectorAll( '.wcc-weekly-content__index a[href^="#"]' )
		.forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				var target = document.querySelector( link.getAttribute( 'href' ) );
				if ( ! target ) {
					return;
				}
				e.preventDefault();

				// Open the entry if it is currently collapsed.
				if ( 'DETAILS' === target.tagName ) {
					target.open = true;
				}

				// Delay scrollIntoView so the browser can recalculate layout
				// after the <details> element expands.
				setTimeout( function () {
					target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}, 50 );
			} );
		} );

	/**
	 * Defer iframe loading: load iframes only when their parent accordion (<details>) is opened.
	 */
	document
		.querySelectorAll( '.wcc-weekly-entry' )
		.forEach( function ( entry ) {
			entry.addEventListener( 'toggle', function () {
				if ( entry.open ) {
					entry.querySelectorAll( 'iframe[data-src]' ).forEach( function ( iframe ) {
						iframe.setAttribute( 'src', iframe.getAttribute( 'data-src' ) );
						iframe.removeAttribute( 'data-src' );
					} );
				}
			} );
		} );
}() );
