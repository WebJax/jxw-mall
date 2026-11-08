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

	/**
	 * Pan og zoom i en fast høj ramme, så det aflange center kan læses på mobil.
	 *
	 * @param {HTMLElement} wrap
	 * @param {HTMLElement} display
	 * @param {HTMLElement} stage
	 * @param {SVGSVGElement} svg
	 * @return {{frameShop: function(): void}}
	 */
	function bindViewport( wrap, display, stage, svg ) {
		var view = { s: 1, x: 0, y: 0 };
		var home = { s: 1, x: 0, y: 0 };
		var active = {};
		var drag = null;
		var pinch = null;
		var moved = false;
		var suppressClick = false;
		var lastW = 0;
		var lastH = 0;
		var focusZoom = parseInt( wrap.getAttribute( 'data-focus-zoom' ), 10 );
		var zoomValue = display.querySelector( '.floorplan-zoom__value' );
		if ( ! focusZoom || focusZoom < 100 ) {
			focusZoom = 150;
		} else if ( focusZoom > 250 ) {
			focusZoom = 250;
		}
		var focusScale = focusZoom / 100;

		function metrics() {
			var vb = svg.viewBox && svg.viewBox.baseVal ? svg.viewBox.baseVal : { width: 0, height: 0 };
			var vh = display.clientHeight || 1;
			var vw = display.clientWidth || 1;
			var baseW = vh;
			if ( vb.width > 0 && vb.height > 0 ) {
				baseW = vh * ( vb.width / vb.height );
			}
			return { vw: vw, vh: vh, baseW: baseW, baseH: vh, vb: vb };
		}

		function layout() {
			var m = metrics();
			stage.style.width = m.baseW + 'px';
			stage.style.height = m.baseH + 'px';
			svg.style.width = m.baseW + 'px';
			svg.style.height = m.baseH + 'px';
			return m;
		}

		function apply() {
			stage.style.transform = 'translate(' + view.x + 'px,' + view.y + 'px) scale(' + view.s + ')';
			if ( zoomValue ) {
				zoomValue.textContent = Math.round( view.s * 100 ) + '%';
			}
		}

		function limits( m ) {
			var min = m.baseW > 0 ? Math.min( 1, m.vw / m.baseW ) : 1;
			return { min: min, max: 8 };
		}

		function clamp( m ) {
			var sw = m.baseW * view.s;
			var sh = m.baseH * view.s;
			if ( sw <= m.vw + 0.5 ) {
				view.x = ( m.vw - sw ) / 2;
			} else {
				view.x = Math.min( 0, Math.max( m.vw - sw, view.x ) );
			}
			if ( sh <= m.vh + 0.5 ) {
				view.y = ( m.vh - sh ) / 2;
			} else {
				view.y = Math.min( 0, Math.max( m.vh - sh, view.y ) );
			}
		}

		function setView( next, originX, originY ) {
			var m = layout();
			var bound = limits( m );
			next = Math.max( bound.min, Math.min( bound.max, next ) );
			var ratio = view.s ? next / view.s : 1;
			view.x = originX - ( originX - view.x ) * ratio;
			view.y = originY - ( originY - view.y ) * ratio;
			view.s = next;
			clamp( m );
			apply();
		}

		function boxFromPolygons( nodes, m ) {
			if ( ! nodes.length || ! m.vb.width || ! m.vb.height ) {
				return null;
			}
			var minX = Infinity;
			var minY = Infinity;
			var maxX = -Infinity;
			var maxY = -Infinity;
			nodes.forEach( function ( el ) {
				if ( typeof el.getBBox !== 'function' ) {
					return;
				}
				var b = el.getBBox();
				minX = Math.min( minX, b.x );
				minY = Math.min( minY, b.y );
				maxX = Math.max( maxX, b.x + b.width );
				maxY = Math.max( maxY, b.y + b.height );
			} );
			if ( minX === Infinity ) {
				return null;
			}
			return {
				x0: ( minX / m.vb.width ) * m.baseW,
				y0: ( minY / m.vb.height ) * m.baseH,
				x1: ( maxX / m.vb.width ) * m.baseW,
				y1: ( maxY / m.vb.height ) * m.baseH
			};
		}

		function markerBox( m, key ) {
			var markerKey = key || wrap.getAttribute( 'data-focus-marker' );
			if ( ! markerKey ) {
				return null;
			}
			key = markerKey;
			var btn = null;
			stage.querySelectorAll( '.floorplan-marker' ).forEach( function ( el ) {
				if ( el.getAttribute( 'data-marker-key' ) === key ) {
					btn = el;
				}
			} );
			if ( ! btn ) {
				return null;
			}
			var left = parseFloat( btn.style.left );
			var top = parseFloat( btn.style.top );
			if ( isNaN( left ) || isNaN( top ) ) {
				return null;
			}
			var cx = ( left / 100 ) * m.baseW;
			var cy = ( top / 100 ) * m.baseH;
			var span = Math.min( m.baseW, m.baseH ) * 0.18;
			return { x0: cx - span, y0: cy - span, x1: cx + span, y1: cy + span };
		}

		function fit( box, remember ) {
			var m = layout();
			var bound = limits( m );
			var s = 1;
			var x = 0;
			var y = 0;
			if ( box ) {
				s = Math.max( bound.min, Math.min( bound.max, focusScale ) );
				x = ( m.vw / 2 ) - ( ( box.x0 + box.x1 ) / 2 ) * s;
				y = ( m.vh / 2 ) - ( ( box.y0 + box.y1 ) / 2 ) * s;
			}
			view.s = s;
			view.x = x;
			view.y = y;
			clamp( m );
			apply();
			if ( remember ) {
				home = { s: view.s, x: view.x, y: view.y };
			}
		}

		function showHome() {
			if ( ! wrap.isConnected ) {
				return;
			}
			var w = display.clientWidth;
			var h = display.clientHeight;
			if ( w === lastW && h === lastH && display.classList.contains( 'is-ready' ) ) {
				return;
			}
			lastW = w;
			lastH = h;
			var m = layout();
			var box = markerBox( m ) || boxFromPolygons(
				Array.prototype.slice.call( svg.querySelectorAll( '.floorplan-area-front.selected' ) ),
				m
			);
			fit( box, true );
			display.classList.add( 'is-ready' );
		}

		showHome();
		window.requestAnimationFrame( showHome );
		if ( window.ResizeObserver ) {
			var observer = new ResizeObserver( function () {
				if ( ! wrap.isConnected ) {
					observer.disconnect();
					return;
				}
				showHome();
			} );
			observer.observe( display );
		} else {
			window.addEventListener( 'resize', showHome );
		}

		display.querySelectorAll( '.floorplan-zoom button' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( evt ) {
				evt.preventDefault();
				evt.stopPropagation();
				var action = button.getAttribute( 'data-zoom' );
				var m = layout();
				if ( 'reset' === action ) {
					view.s = home.s;
					view.x = home.x;
					view.y = home.y;
					clamp( m );
					apply();
					return;
				}
				setView( view.s * ( 'in' === action ? 1.25 : 0.8 ), m.vw / 2, m.vh / 2 );
			} );
		} );

		display.addEventListener( 'wheel', function ( evt ) {
			evt.preventDefault();
			var rect = display.getBoundingClientRect();
			var factor = evt.deltaY < 0 ? 1.12 : ( 1 / 1.12 );
			setView( view.s * factor, evt.clientX - rect.left, evt.clientY - rect.top );
		}, { passive: false } );

		display.addEventListener( 'pointerdown', function ( evt ) {
			if ( evt.button !== undefined && evt.button !== 0 ) {
				return;
			}
			if ( evt.target.closest && evt.target.closest( '.floorplan-zoom' ) ) {
				return;
			}
			active[ evt.pointerId ] = { x: evt.clientX, y: evt.clientY };
			var ids = Object.keys( active );
			if ( ids.length >= 2 ) {
				var a = active[ ids[ 0 ] ];
				var b = active[ ids[ 1 ] ];
				drag = null;
				pinch = {
					dist: Math.hypot( a.x - b.x, a.y - b.y ) || 1,
					scale: view.s
				};
				return;
			}
			drag = {
				id: evt.pointerId,
				x: evt.clientX,
				y: evt.clientY,
				vx: view.x,
				vy: view.y
			};
			moved = false;
		} );

		display.addEventListener( 'pointermove', function ( evt ) {
			if ( ! active[ evt.pointerId ] ) {
				return;
			}
			active[ evt.pointerId ] = { x: evt.clientX, y: evt.clientY };
			var ids = Object.keys( active );
			if ( pinch && ids.length >= 2 ) {
				var a = active[ ids[ 0 ] ];
				var b = active[ ids[ 1 ] ];
				var dist = Math.hypot( a.x - b.x, a.y - b.y ) || 1;
				var rect = display.getBoundingClientRect();
				setView(
					pinch.scale * ( dist / pinch.dist ),
					( ( a.x + b.x ) / 2 ) - rect.left,
					( ( a.y + b.y ) / 2 ) - rect.top
				);
				moved = true;
				return;
			}
			if ( ! drag || evt.pointerId !== drag.id ) {
				return;
			}
			var dx = evt.clientX - drag.x;
			var dy = evt.clientY - drag.y;
			if ( ( dx * dx ) + ( dy * dy ) > 16 ) {
				moved = true;
				display.classList.add( 'is-panning' );
				if ( display.setPointerCapture ) {
					try {
						display.setPointerCapture( evt.pointerId );
					} catch ( err ) {
						/* Allerede fanget. */
					}
				}
			}
			var m = layout();
			view.x = drag.vx + dx;
			view.y = drag.vy + dy;
			clamp( m );
			apply();
		} );

		function endPointer( evt ) {
			delete active[ evt.pointerId ];
			if ( Object.keys( active ).length < 2 ) {
				pinch = null;
			}
			if ( drag && evt.pointerId === drag.id ) {
				if ( moved ) {
					suppressClick = true;
				}
				drag = null;
				moved = false;
				display.classList.remove( 'is-panning' );
			}
		}

		display.addEventListener( 'pointerup', endPointer );
		display.addEventListener( 'pointercancel', endPointer );

		display.addEventListener( 'click', function ( evt ) {
			if ( ! suppressClick ) {
				return;
			}
			suppressClick = false;
			evt.preventDefault();
			evt.stopPropagation();
		}, true );

		display.addEventListener( 'keydown', function ( evt ) {
			if ( document.activeElement !== display ) {
				return;
			}
			var m = layout();
			var step = Math.max( 24, Math.min( m.vw, m.vh ) * 0.08 );
			if ( 'ArrowLeft' === evt.key ) {
				view.x += step;
			} else if ( 'ArrowRight' === evt.key ) {
				view.x -= step;
			} else if ( 'ArrowUp' === evt.key ) {
				view.y += step;
			} else if ( 'ArrowDown' === evt.key ) {
				view.y -= step;
			} else if ( '+' === evt.key || '=' === evt.key ) {
				setView( view.s * 1.25, m.vw / 2, m.vh / 2 );
				evt.preventDefault();
				return;
			} else if ( '-' === evt.key ) {
				setView( view.s * 0.8, m.vw / 2, m.vh / 2 );
				evt.preventDefault();
				return;
			} else if ( '0' === evt.key ) {
				view.s = home.s;
				view.x = home.x;
				view.y = home.y;
			} else {
				return;
			}
			evt.preventDefault();
			clamp( m );
			apply();
		} );

		return {
			frameShop: function () {
				var m = layout();
				var box = boxFromPolygons(
					Array.prototype.slice.call( svg.querySelectorAll( '.floorplan-area-front.selected' ) ),
					m
				);
				if ( box ) {
					fit( box, false );
				}
			},
			frameMarker: function ( key ) {
				var m = layout();
				var box = markerBox( m, key );
				if ( box ) {
					fit( box, false );
				}
			}
		};
	}

	function init( wrap ) {
		if ( wrap.getAttribute( 'data-floorplan-ready' ) ) {
			return;
		}
		wrap.setAttribute( 'data-floorplan-ready', '1' );

		var svg = wrap.querySelector( '.floorplan-svg' );
		if ( ! svg ) {
			return;
		}
		var display = wrap.querySelector( '.floorplan-display' );
		var stage = wrap.querySelector( '.floorplan-stage' );
		var camera = ( display && stage ) ? bindViewport( wrap, display, stage, svg ) : null;
		var tooltip = wrap.querySelector( '.floorplan-tooltip' );
		var dialog = wrap.querySelector( '.floorplan-popup' );
		var input = wrap.querySelector( '.floorplan-search-input' );
		var results = wrap.querySelector( '.floorplan-search-results' );
		var areas = Array.prototype.slice.call( svg.querySelectorAll( '.floorplan-area-front.has-shop, .floorplan-area-front.has-link' ) );
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
			if ( camera ) {
				camera.frameShop();
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

		/**
		 * @param {Element} el
		 * @return {string}
		 */
		function areaCaption( el ) {
			return el.getAttribute( 'data-shop-name' ) || el.getAttribute( 'data-area-label' ) || '';
		}

		/**
		 * @param {string} url
		 * @return {boolean}
		 */
		function followLink( url ) {
			if ( ! url || ! /^https?:\/\//i.test( url ) ) {
				return false;
			}
			window.location.assign( url );
			return true;
		}

		areas.forEach( function ( el ) {
			function activate() {
				if ( followLink( el.getAttribute( 'data-link-url' ) || '' ) ) {
					return;
				}
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
				showTooltip( areaCaption( el ), 8, 8, false );
			} );

			el.addEventListener( 'mousemove', function ( evt ) {
				if ( ! display ) {
					return;
				}
				var box = display.getBoundingClientRect();
				showTooltip(
					areaCaption( el ),
					evt.clientX - box.left + 12,
					evt.clientY - box.top + 12,
					false
				);
			} );

			el.addEventListener( 'mouseleave', hideTooltip );

			el.addEventListener( 'focus', function () {
				showTooltip( areaCaption( el ), 8, 8, false );
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
					if ( shop.type === 'marker' ) {
						var target = null;
						markerButtons.forEach( function ( el ) {
							if ( el.getAttribute( 'data-marker-key' ) === shop.key ) {
								target = el;
							}
						} );
						if ( ! target ) {
							return;
						}
						target.click();
						if ( camera ) {
							camera.frameMarker( shop.key );
						}
						input.value = shop.title;
						closeResults();
						return;
					}
					if ( followLink( shop.link_url || '' ) ) {
						return;
					}
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

	function boot() {
		document.querySelectorAll( '.centershop-floorplan-wrapper:not([data-floorplan-ready])' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	if ( window.MutationObserver && document.documentElement ) {
		var pending = false;
		new MutationObserver( function () {
			if ( pending ) {
				return;
			}
			pending = true;
			window.requestAnimationFrame( function () {
				pending = false;
				boot();
			} );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}
}() );
