/**
 * CenterShop Grundplan – frontend
 *
 * Interaktivt kort: hover/klik/tastatur på lokaler og symboler, popup og butikssøgning.
 * Ingen afhængigheder.
 */
( function () {
	'use strict';

	var cfg = window.centershopFloorplanFrontend || { ajaxUrl: '', strings: {} };

	/**
	 * @param {HTMLDialogElement} dialog
	 * @param {{html?: string}} item
	 */
	function fillPopup( dialog, item ) {
		var body = dialog.querySelector( '.floorplan-popup__body' );
		body.innerHTML = item.html || '';
		var heading = body.querySelector( '.butikerhvervnavn, .floorplan-popup__title' );
		var labelId = dialog.getAttribute( 'aria-labelledby' );
		if ( heading && labelId ) {
			heading.id = labelId;
		}
	}

	function init( wrap ) {
		var svg = wrap.querySelector( '.floorplan-svg' );
		if ( ! svg ) {
			return;
		}
		var display = wrap.querySelector( '.floorplan-display' );
		var tooltip = wrap.querySelector( '.floorplan-tooltip' );
		var dialog = wrap.querySelector( '.floorplan-popup' );
		var input = wrap.querySelector( '.floorplan-search-input' );
		var results = wrap.querySelector( '.floorplan-search-results' );
		var areas = Array.prototype.slice.call( svg.querySelectorAll( '.floorplan-area-front.has-shop' ) );
		var markerButtons = Array.prototype.slice.call( wrap.querySelectorAll( '.floorplan-marker' ) );
		var shops = {};
		var markerCards = [];
		var dataEl = wrap.querySelector( '.floorplan-shop-data' );
		var markerDataEl = wrap.querySelector( '.floorplan-marker-data' );
		var lastTrigger = null;

		if ( dataEl && dataEl.textContent ) {
			try {
				shops = JSON.parse( dataEl.textContent );
			} catch ( err ) {
				shops = {};
			}
		}

		if ( markerDataEl && markerDataEl.textContent ) {
			try {
				markerCards = JSON.parse( markerDataEl.textContent );
			} catch ( err ) {
				markerCards = [];
			}
		}

		/**
		 * @param {string} text
		 * @param {number} x
		 * @param {number} y
		 * @param {boolean} anchored
		 */
		function showTooltip( text, x, y, anchored ) {
			if ( ! tooltip ) {
				return;
			}
			tooltip.textContent = text;
			tooltip.style.left = x + 'px';
			tooltip.style.top = y + 'px';
			tooltip.classList.toggle( 'is-anchored', anchored );
			if ( ! anchored ) {
				tooltip.classList.remove( 'is-below' );
			}
			tooltip.hidden = false;
		}

		function hideTooltip() {
			if ( ! tooltip ) {
				return;
			}
			tooltip.hidden = true;
			tooltip.classList.remove( 'is-anchored' );
			tooltip.classList.remove( 'is-below' );
		}

		/**
		 * Overskriften står centreret over symbolet, så den knytter sig til netop det punkt.
		 *
		 * @param {HTMLElement} el
		 * @param {string} text
		 */
		function showAnchoredTooltip( el, text ) {
			if ( ! display || ! tooltip ) {
				return;
			}
			var box = display.getBoundingClientRect();
			var mark = el.getBoundingClientRect();
			var x = mark.left - box.left + ( mark.width / 2 );
			var above = mark.top - box.top - 6;
			var placeBelow = above < 32;
			showTooltip(
				text,
				x,
				placeBelow ? ( mark.bottom - box.top + 6 ) : above,
				true
			);
			tooltip.classList.toggle( 'is-below', placeBelow );
		}

		/* ---------- Markering og popup ---------- */

		function clearHighlight() {
			svg.querySelectorAll( '.floorplan-area-front.selected' ).forEach( function ( el ) {
				el.classList.remove( 'selected' );
			} );
		}

		function highlightShop( shopId ) {
			clearHighlight();
			var matches = areas.filter( function ( el ) {
				return el.getAttribute( 'data-shop-id' ) === String( shopId );
			} );
			matches.forEach( function ( el ) {
				el.classList.add( 'selected' );
			} );
			return matches;
		}

		/**
		 * @param {string} html
		 * @param {HTMLElement} trigger
		 * @param {boolean} note
		 */
		function openPopup( html, trigger, note ) {
			if ( ! dialog || ! html || 'function' !== typeof dialog.showModal ) {
				return false;
			}
			dialog.classList.toggle( 'floorplan-popup--note', note );
			fillPopup( dialog, { html: html } );
			lastTrigger = trigger;
			if ( ! dialog.open ) {
				dialog.showModal();
			}
			return true;
		}

		function openShop( shopId ) {
			var matches = highlightShop( shopId );
			if ( ! matches.length ) {
				return false;
			}
			var shop = shops[ String( shopId ) ];
			if ( shop ) {
				openPopup( shop.html, matches[ 0 ], false );
			}
			return true;
		}

		if ( dialog ) {
			var closeBtn = dialog.querySelector( '.floorplan-popup__close' );
			if ( closeBtn ) {
				closeBtn.addEventListener( 'click', function () {
					dialog.close();
				} );
			}

			dialog.addEventListener( 'click', function ( evt ) {
				if ( evt.target === dialog ) {
					dialog.close();
				}
			} );

			dialog.addEventListener( 'close', function () {
				dialog.classList.remove( 'floorplan-popup--note' );
				if ( lastTrigger && 'function' === typeof lastTrigger.focus ) {
					lastTrigger.focus();
				}
				lastTrigger = null;
			} );
		}

		areas.forEach( function ( el ) {
			function activate() {
				openShop( el.getAttribute( 'data-shop-id' ) );
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
				showTooltip( el.getAttribute( 'data-shop-name' ) || '', 8, 8, false );
			} );

			el.addEventListener( 'mousemove', function ( evt ) {
				if ( ! display ) {
					return;
				}
				var box = display.getBoundingClientRect();
				showTooltip(
					el.getAttribute( 'data-shop-name' ) || '',
					evt.clientX - box.left + 12,
					evt.clientY - box.top + 12,
					false
				);
			} );

			el.addEventListener( 'mouseleave', hideTooltip );

			el.addEventListener( 'focus', function () {
				showTooltip( el.getAttribute( 'data-shop-name' ) || '', 8, 8, false );
			} );

			el.addEventListener( 'blur', hideTooltip );
		} );

		markerButtons.forEach( function ( el ) {
			var index = parseInt( el.getAttribute( 'data-marker-index' ), 10 );
			var card = markerCards[ index ];
			var label = ( card && card.label ) || el.getAttribute( 'data-label' ) || '';

			function activate() {
				clearHighlight();
				hideTooltip();
				if ( card && card.html ) {
					openPopup( card.html, el, true );
				}
			}

			el.addEventListener( 'click', function ( evt ) {
				evt.stopPropagation();
				activate();
			} );

			el.addEventListener( 'mouseenter', function () {
				showAnchoredTooltip( el, label );
			} );

			el.addEventListener( 'mouseleave', hideTooltip );

			el.addEventListener( 'focus', function () {
				showAnchoredTooltip( el, label );
			} );

			el.addEventListener( 'blur', hideTooltip );
		} );

		// Klik på baggrunden fjerner markeringen.
		svg.addEventListener( 'click', clearHighlight );

		var preselected = wrap.getAttribute( 'data-selected-shop' );
		if ( preselected ) {
			highlightShop( preselected );
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
					if ( shop.on_map && openShop( shop.id ) ) {
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
