/**
 * CenterShop Grundplan – frontend
 *
 * Interaktivt kort: hover/klik/tastatur på lokaler, infopanel og butikssøgning.
 * Ingen afhængigheder.
 */
( function () {
	'use strict';

	var cfg = window.centershopFloorplanFrontend || { ajaxUrl: '', strings: {} };

	function init( wrap ) {
		var svg = wrap.querySelector( '.floorplan-svg' );
		if ( ! svg ) {
			return;
		}
		var display = wrap.querySelector( '.floorplan-display' );
		var tooltip = wrap.querySelector( '.floorplan-tooltip' );
		var info = wrap.querySelector( '.floorplan-info' );
		var infoName = wrap.querySelector( '.floorplan-info .shop-name' );
		var infoLink = wrap.querySelector( '.floorplan-info .shop-link' );
		var input = wrap.querySelector( '.floorplan-search-input' );
		var results = wrap.querySelector( '.floorplan-search-results' );
		var areas = Array.prototype.slice.call( svg.querySelectorAll( '.floorplan-area-front.has-shop' ) );

		/* ---------- Markering ---------- */

		function clearSelection() {
			svg.querySelectorAll( '.floorplan-area-front.selected' ).forEach( function ( el ) {
				el.classList.remove( 'selected' );
			} );
			info.hidden = true;
		}

		function selectShop( shopId ) {
			clearSelection();
			var matches = areas.filter( function ( el ) {
				return el.getAttribute( 'data-shop-id' ) === String( shopId );
			} );
			if ( ! matches.length ) {
				return false;
			}
			matches.forEach( function ( el ) {
				el.classList.add( 'selected' );
			} );
			infoName.textContent = matches[ 0 ].getAttribute( 'data-shop-name' );
			infoLink.setAttribute( 'href', matches[ 0 ].getAttribute( 'data-shop-url' ) );
			info.hidden = false;
			return true;
		}

		areas.forEach( function ( el ) {
			function activate() {
				selectShop( el.getAttribute( 'data-shop-id' ) );
			}

			el.addEventListener( 'click', function ( evt ) {
				evt.stopPropagation();
				activate();
			} );

			el.addEventListener( 'keydown', function ( evt ) {
				if ( 'Enter' === evt.key || ' ' === evt.key ) {
					evt.preventDefault();
					activate();
				}
			} );

			el.addEventListener( 'mouseenter', function () {
				tooltip.textContent = el.getAttribute( 'data-shop-name' );
				tooltip.hidden = false;
			} );

			el.addEventListener( 'mousemove', function ( evt ) {
				var box = display.getBoundingClientRect();
				tooltip.style.left = ( evt.clientX - box.left + 12 ) + 'px';
				tooltip.style.top = ( evt.clientY - box.top + 12 ) + 'px';
			} );

			el.addEventListener( 'mouseleave', function () {
				tooltip.hidden = true;
			} );

			el.addEventListener( 'focus', function () {
				tooltip.textContent = el.getAttribute( 'data-shop-name' );
				tooltip.style.left = '8px';
				tooltip.style.top = '8px';
				tooltip.hidden = false;
			} );

			el.addEventListener( 'blur', function () {
				tooltip.hidden = true;
			} );
		} );

		// Klik på baggrunden fjerner markeringen.
		svg.addEventListener( 'click', clearSelection );

		var preselected = wrap.getAttribute( 'data-selected-shop' );
		if ( preselected ) {
			selectShop( preselected );
		}

		/* ---------- Søgning ---------- */

		if ( ! input || ! results ) {
			return;
		}

		var timer = null;
		var controller = null;

		function closeResults() {
			results.innerHTML = '';
			results.classList.remove( 'is-open' );
		}

		function renderResults( list ) {
			results.innerHTML = '';
			if ( ! list.length ) {
				var none = document.createElement( 'div' );
				none.className = 'floorplan-search-empty';
				none.textContent = cfg.strings.noResults || '';
				results.appendChild( none );
				results.classList.add( 'is-open' );
				return;
			}
			list.forEach( function ( shop ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'floorplan-search-item';
				btn.setAttribute( 'role', 'option' );
				btn.textContent = shop.title;
				if ( ! shop.on_map ) {
					var note = document.createElement( 'small' );
					note.textContent = ' (' + ( cfg.strings.notOnMap || '' ) + ')';
					btn.appendChild( note );
				}
				btn.addEventListener( 'click', function () {
					if ( shop.on_map && selectShop( shop.id ) ) {
						input.value = shop.title;
						closeResults();
						svg.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
					} else {
						window.location.href = shop.url;
					}
				} );
				results.appendChild( btn );
			} );
			results.classList.add( 'is-open' );
		}

		function search( term ) {
			if ( controller ) {
				controller.abort();
			}
			controller = 'function' === typeof AbortController ? new AbortController() : null;
			var url = cfg.ajaxUrl + '?action=search_shops&s=' + encodeURIComponent( term );
			fetch( url, { credentials: 'same-origin', signal: controller ? controller.signal : undefined } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( res ) {
					if ( res && res.success ) {
						renderResults( res.data );
					}
				} )
				.catch( function () {
					/* Afbrudt eller netværksfejl – ignorer. */
				} );
		}

		input.addEventListener( 'input', function () {
			var term = input.value.trim();
			window.clearTimeout( timer );
			if ( term.length < 2 ) {
				closeResults();
				return;
			}
			timer = window.setTimeout( function () {
				search( term );
			}, 250 );
		} );

		input.addEventListener( 'keydown', function ( evt ) {
			if ( 'Escape' === evt.key ) {
				closeResults();
			} else if ( 'ArrowDown' === evt.key ) {
				var first = results.querySelector( '.floorplan-search-item' );
				if ( first ) {
					evt.preventDefault();
					first.focus();
				}
			}
		} );

		results.addEventListener( 'keydown', function ( evt ) {
			var items = Array.prototype.slice.call( results.querySelectorAll( '.floorplan-search-item' ) );
			var i = items.indexOf( document.activeElement );
			if ( 'ArrowDown' === evt.key && i < items.length - 1 ) {
				evt.preventDefault();
				items[ i + 1 ].focus();
			} else if ( 'ArrowUp' === evt.key ) {
				evt.preventDefault();
				( i > 0 ? items[ i - 1 ] : input ).focus();
			} else if ( 'Escape' === evt.key ) {
				closeResults();
				input.focus();
			}
		} );

		document.addEventListener( 'click', function ( evt ) {
			if ( ! wrap.contains( evt.target ) ) {
				closeResults();
			}
		} );
	}

	document.querySelectorAll( '.centershop-floorplan-wrapper' ).forEach( init );
}() );
