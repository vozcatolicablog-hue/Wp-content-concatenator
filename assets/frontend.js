/* global document, window */
( function () {
	'use strict';

	/**
	 * Native <details>/<summary> handles open/close without JS. This script only:
	 *  1. opens the target entry when an index link (or the URL hash) points to it;
	 *  2. loads the iframes of an entry the first time it is opened (they ship as data-src).
	 */

	var sections = document.querySelectorAll( '.wcc-weekly-content' );
	if ( ! sections.length ) {
		return;
	}

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/**
	 * Load the deferred iframes of an entry.
	 *
	 * @param {Element} entry The <details> element.
	 */
	function loadIframes( entry ) {
		entry.querySelectorAll( 'iframe[data-src]' ).forEach( function ( iframe ) {
			iframe.setAttribute( 'src', iframe.getAttribute( 'data-src' ) );
			iframe.removeAttribute( 'data-src' );
		} );
	}

	/**
	 * Open an entry, load its iframes and bring it into view.
	 *
	 * @param {Element} entry       The <details> element.
	 * @param {boolean} moveFocus   Whether to move keyboard focus to its summary.
	 */
	function openEntry( entry, moveFocus ) {
		if ( 'DETAILS' === entry.tagName && ! entry.open ) {
			entry.open = true;
		}
		loadIframes( entry );

		var summary = entry.querySelector( 'summary' );
		if ( moveFocus && summary ) {
			summary.focus( { preventScroll: true } );
		}

		// Delay scrollIntoView so the browser can recalculate layout after the <details> expands.
		window.setTimeout( function () {
			entry.scrollIntoView( { behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' } );
		}, 50 );
	}

	/**
	 * Open the entry the URL hash points to (shared links, index links).
	 *
	 * @param {boolean} moveFocus Whether to move focus to the entry.
	 */
	function openFromHash( moveFocus ) {
		var hash = window.location.hash;
		if ( ! hash || hash.indexOf( '#entrega-' ) !== 0 ) {
			return;
		}
		var target = document.getElementById( hash.slice( 1 ) );
		if ( target && target.classList.contains( 'wcc-weekly-entry' ) ) {
			openEntry( target, moveFocus );
		}
	}

	sections.forEach( function ( section ) {
		section.querySelectorAll( '.wcc-weekly-content__index a[href^="#"]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				var id     = link.getAttribute( 'href' ).slice( 1 );
				var target = id ? document.getElementById( id ) : null;
				if ( ! target ) {
					return;
				}
				e.preventDefault();
				if ( window.history && window.history.replaceState ) {
					window.history.replaceState( null, '', '#' + id );
				}
				openEntry( target, true );
			} );
		} );

		section.querySelectorAll( '.wcc-weekly-entry' ).forEach( function ( entry ) {
			entry.addEventListener( 'toggle', function () {
				if ( entry.open ) {
					loadIframes( entry );
				}
			} );
		} );
	} );

	openFromHash( false );
	window.addEventListener( 'hashchange', function () {
		openFromHash( true );
	} );
}() );
