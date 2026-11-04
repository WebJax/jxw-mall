/**
 * CenterShop Grundplan – admin-editor
 *
 * Alle koordinater i hukommelsen og i databasen er normaliserede (0-1).
 * SVG'ens viewBox er billedets pixelstørrelse, så der omregnes ved tegning.
 */
( function () {
	'use strict';

	var cfg = window.centershopFloorplan;
	if ( ! cfg ) {
		return;
	}

	var SVG_NS = 'http://www.w3.org/2000/svg';
	var W = parseInt( cfg.image.width, 10 ) || 0;
	var H = parseInt( cfg.image.height, 10 ) || 0;

	var svg = document.getElementById( 'floorplan-canvas-svg' );
	var gAreas = document.getElementById( 'fp-areas' );
	var gDraft = document.getElementById( 'fp-draft' );
	var gHandles = document.getElementById( 'fp-handles' );
	var listEl = document.getElementById( 'areas-list' );
	var markersListEl = document.getElementById( 'markers-list' );
	var markerLayer = document.getElementById( 'fp-marker-layer' );
	var editorEl = document.getElementById( 'area-editor' );
	var statusEl = document.getElementById( 'floorplan-status' );
	var hintEl = document.getElementById( 'floorplan-hint' );
	var addBtn = document.getElementById( 'add-area-btn' );
	var finishBtn = document.getElementById( 'finish-area-btn' );
	var cancelBtn = document.getElementById( 'cancel-area-btn' );
	var addMarkerBtn = document.getElementById( 'add-marker-btn' );
	var cancelMarkerBtn = document.getElementById( 'cancel-marker-btn' );
	var saveBtn = document.getElementById( 'save-floorplan' );
	var uploadBtn = document.getElementById( 'upload-svg-btn' );
	var importInput = document.getElementById( 'import-areas-file' );

	var areas = ( cfg.areas || [] ).map( function ( a ) {
		return {
			label: a.label || '',
			shop_id: parseInt( a.shop_id, 10 ) || 0,
			color: a.color || '',
			points: parsePoints( a.points )
		};
	} );
	var limits = cfg.markerLimits || {
		circleMin: 28,
		circleMax: 120,
		circleDefault: 44,
		iconMin: 12,
		iconMax: 96,
		iconDefault: 20,
		facilityBg: '#1d2327',
		entranceBg: '#c2410c'
	};
	var markers = ( cfg.markers || [] ).map( normalizeMarker );
	var selected = -1;
	var selectedMarker = -1;
	var draft = null; // Array af [x, y] under tegning, ellers null.
	var rubber = null; // <line> fra sidste punkt til musen.
	var placing = false;
	var dirty = false;
	var iconFrame = null;

	/* ---------- Hjælpefunktioner ---------- */

	/**
	 * @param {string} kind
	 * @return {string}
	 */
	function defaultBg( kind ) {
		return 'entrance' === kind ? limits.entranceBg : limits.facilityBg;
	}

	/**
	 * @param {number|string} value
	 * @param {number} min
	 * @param {number} max
	 * @param {number} fallback
	 * @return {number}
	 */
	function clampSize( value, min, max, fallback ) {
		var n = parseInt( value, 10 );
		if ( isNaN( n ) ) {
			return fallback;
		}
		return Math.min( max, Math.max( min, n ) );
	}

	/**
	 * @param {Object} m
	 * @return {Object}
	 */
	function normalizeMarker( m ) {
		var kind = 'entrance' === m.kind ? 'entrance' : 'facility';
		var bg = /^#[0-9a-f]{6}$/i.test( m.bg || '' ) ? m.bg : defaultBg( kind );
		return {
			kind: kind,
			label: m.label || '',
			description: m.description || '',
			icon: m.icon || ( 'entrance' === kind ? 'number' : 'info' ),
			icon_id: parseInt( m.icon_id, 10 ) || 0,
			icon_url: m.icon_url || '',
			number: m.number || '',
			bg: bg,
			circle_size: clampSize( m.circle_size, limits.circleMin, limits.circleMax, limits.circleDefault ),
			icon_size: clampSize( m.icon_size, limits.iconMin, limits.iconMax, limits.iconDefault ),
			x: parseFloat( m.x ) || 0,
			y: parseFloat( m.y ) || 0
		};
	}

	/**
	 * @param {Object} marker
	 * @return {boolean}
	 */
	function hasCustomIcon( marker ) {
		return !!( marker.icon_id && marker.icon_url );
	}

	/**
	 * @param {HTMLElement} el
	 * @param {Object} marker
	 */
	function applyMarkerStyle( el, marker ) {
		el.style.left = ( marker.x * 100 ) + '%';
		el.style.top = ( marker.y * 100 ) + '%';
		el.style.setProperty( '--marker-size', marker.circle_size + 'px' );
		el.style.setProperty( '--marker-icon', marker.icon_size + 'px' );
		el.style.setProperty( '--marker-bg', marker.bg || defaultBg( marker.kind ) );
	}

	function parsePoints( str ) {
		if ( ! str ) {
			return [];
		}
		return String( str ).trim().split( /\s+/ ).map( function ( pair ) {
			var xy = pair.split( ',' );
			return [ parseFloat( xy[ 0 ] ), parseFloat( xy[ 1 ] ) ];
		} ).filter( function ( p ) {
			return ! isNaN( p[ 0 ] ) && ! isNaN( p[ 1 ] );
		} );
	}

	function pointsToString( pts ) {
		return pts.map( function ( p ) {
			return p[ 0 ].toFixed( 5 ) + ',' + p[ 1 ].toFixed( 5 );
		} ).join( ' ' );
	}

	function pointsToPx( pts ) {
		return pts.map( function ( p ) {
			return ( p[ 0 ] * W ).toFixed( 1 ) + ',' + ( p[ 1 ] * H ).toFixed( 1 );
		} ).join( ' ' );
	}

	function clamp01( v ) {
		return Math.min( 1, Math.max( 0, v ) );
	}

	function make( name, attrs ) {
		var node = document.createElementNS( SVG_NS, name );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			node.setAttribute( k, attrs[ k ] );
		} );
		return node;
	}

	function clear( node ) {
		while ( node && node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function setStatus( text, isError ) {
		if ( ! statusEl ) {
			return;
		}
		statusEl.textContent = text || '';
		statusEl.className = isError ? 'is-error' : '';
	}

	function markDirty() {
		dirty = true;
		setStatus( cfg.strings.unsaved );
	}

	/** Antal SVG-enheder pr. skærm-pixel (til håndtag der skal have fast skærmstørrelse). */
	function unitsPerPx() {
		var rect = svg.getBoundingClientRect();
		return rect.width ? W / rect.width : 1;
	}

	function eventToNorm( evt ) {
		var pt = svg.createSVGPoint();
		pt.x = evt.clientX;
		pt.y = evt.clientY;
		var p = pt.matrixTransform( svg.getScreenCTM().inverse() );
		return [ clamp01( p.x / W ), clamp01( p.y / H ) ];
	}

	function shopTitle( id ) {
		var found = null;
		( cfg.shops || [] ).forEach( function ( s ) {
			if ( s.id === id ) {
				found = s.title;
			}
		} );
		return found;
	}

	/**
	 * @param {string} id
	 * @return {string}
	 */
	function iconSvg( id ) {
		var found = '';
		( cfg.icons || [] ).forEach( function ( icon ) {
			if ( icon.id === id ) {
				found = icon.svg || '';
			}
		} );
		return found;
	}

	/**
	 * @param {Object} marker
	 * @param {number} index
	 * @return {string}
	 */
	function markerLabel( marker, index ) {
		if ( marker.label ) {
			return marker.label;
		}
		if ( 'entrance' === marker.kind ) {
			return marker.number
				? cfg.strings.entrance + ' ' + marker.number
				: cfg.strings.entrance + ' ' + ( index + 1 );
		}
		return cfg.strings.facility + ' ' + ( index + 1 );
	}

	/* ---------- Tegning af områder ---------- */

	function polygonFor( area, index ) {
		var poly = make( 'polygon', {
			'class': 'floorplan-area' + ( index === selected ? ' is-selected' : '' ) + ( area.shop_id ? ' has-shop' : '' ),
			'data-index': index,
			points: pointsToPx( area.points )
		} );
		if ( area.color ) {
			poly.style.fill = area.color;
		}
		poly.addEventListener( 'click', function ( evt ) {
			if ( draft || placing ) {
				return; // Klik under tegning eller placering håndteres af svg-lytteren.
			}
			evt.stopPropagation();
			select( index );
		} );
		poly.addEventListener( 'dblclick', function ( evt ) {
			if ( draft || index !== selected ) {
				return;
			}
			evt.preventDefault();
			insertVertex( index, eventToNorm( evt ) );
		} );
		return poly;
	}

	function renderAreas() {
		if ( ! svg ) {
			return;
		}
		clear( gAreas );
		areas.forEach( function ( area, i ) {
			gAreas.appendChild( polygonFor( area, i ) );
		} );
		renderHandles();
	}

	function renderHandles() {
		if ( ! svg ) {
			return;
		}
		clear( gHandles );
		if ( selected < 0 || ! areas[ selected ] || draft ) {
			return;
		}
		var r = 6 * unitsPerPx();
		areas[ selected ].points.forEach( function ( p, i ) {
			var h = make( 'circle', {
				'class': 'floorplan-handle',
				cx: p[ 0 ] * W,
				cy: p[ 1 ] * H,
				r: r
			} );
			attachHandleEvents( h, i );
			gHandles.appendChild( h );
		} );
	}

	function attachHandleEvents( handle, vertexIndex ) {
		var dragging = false;

		handle.addEventListener( 'pointerdown', function ( evt ) {
			evt.preventDefault();
			evt.stopPropagation();
			dragging = true;
			handle.setPointerCapture( evt.pointerId );
		} );

		handle.addEventListener( 'pointermove', function ( evt ) {
			if ( ! dragging ) {
				return;
			}
			var p = eventToNorm( evt );
			areas[ selected ].points[ vertexIndex ] = p;
			handle.setAttribute( 'cx', p[ 0 ] * W );
			handle.setAttribute( 'cy', p[ 1 ] * H );
			var poly = gAreas.querySelector( '[data-index="' + selected + '"]' );
			if ( poly ) {
				poly.setAttribute( 'points', pointsToPx( areas[ selected ].points ) );
			}
		} );

		handle.addEventListener( 'pointerup', function ( evt ) {
			if ( ! dragging ) {
				return;
			}
			dragging = false;
			handle.releasePointerCapture( evt.pointerId );
			markDirty();
		} );

		handle.addEventListener( 'click', function ( evt ) {
			evt.stopPropagation();
		} );

		handle.addEventListener( 'dblclick', function ( evt ) {
			evt.preventDefault();
			evt.stopPropagation();
			if ( areas[ selected ].points.length > 3 ) {
				areas[ selected ].points.splice( vertexIndex, 1 );
				markDirty();
				renderAreas();
			}
		} );
	}

	/** Indsæt et nyt hjørne på den kant, der ligger tættest på punktet. */
	function insertVertex( index, p ) {
		var pts = areas[ index ].points;
		var best = 0;
		var bestDist = Infinity;
		pts.forEach( function ( a, i ) {
			var b = pts[ ( i + 1 ) % pts.length ];
			var d = distToSegment( p, a, b );
			if ( d < bestDist ) {
				bestDist = d;
				best = i;
			}
		} );
		pts.splice( best + 1, 0, p );
		markDirty();
		renderAreas();
	}

	function distToSegment( p, a, b ) {
		var px = p[ 0 ] * W, py = p[ 1 ] * H;
		var ax = a[ 0 ] * W, ay = a[ 1 ] * H;
		var bx = b[ 0 ] * W, by = b[ 1 ] * H;
		var dx = bx - ax, dy = by - ay;
		var len2 = dx * dx + dy * dy;
		var t = len2 ? Math.max( 0, Math.min( 1, ( ( px - ax ) * dx + ( py - ay ) * dy ) / len2 ) ) : 0;
		return Math.hypot( px - ( ax + t * dx ), py - ( ay + t * dy ) );
	}

	/* ---------- Symboler: faciliteter og indgange ---------- */

	/**
	 * @param {HTMLButtonElement} btn
	 * @param {Object} marker
	 */
	function fillMarkerButton( btn, marker ) {
		while ( btn.firstChild ) {
			btn.removeChild( btn.firstChild );
		}

		if ( hasCustomIcon( marker ) ) {
			var image = document.createElement( 'span' );
			image.className = 'floorplan-marker__glyph';
			var img = document.createElement( 'img' );
			img.src = marker.icon_url;
			img.alt = '';
			image.appendChild( img );
			btn.appendChild( image );
		} else if ( 'number' === marker.icon ) {
			var num = document.createElement( 'span' );
			num.className = 'floorplan-marker__num';
			num.textContent = marker.number || ( 'entrance' === marker.kind ? '–' : '1' );
			btn.appendChild( num );
			return;
		} else {
			var glyph = document.createElement( 'span' );
			glyph.className = 'floorplan-marker__glyph';
			glyph.innerHTML = iconSvg( marker.icon ) || iconSvg( 'info' );
			btn.appendChild( glyph );
		}

		if ( 'entrance' === marker.kind && marker.number ) {
			var badge = document.createElement( 'span' );
			badge.className = 'floorplan-marker__badge';
			badge.textContent = marker.number;
			btn.appendChild( badge );
		}
	}

	/**
	 * @param {HTMLButtonElement} btn
	 * @param {number} index
	 */
	function attachMarkerEvents( btn, index ) {
		var dragging = false;
		var moved = false;
		var origin = null;

		btn.addEventListener( 'pointerdown', function ( evt ) {
			if ( draft || placing ) {
				return;
			}
			evt.stopPropagation();
			dragging = true;
			moved = false;
			origin = { x: evt.clientX, y: evt.clientY };
			btn.setPointerCapture( evt.pointerId );
		} );

		btn.addEventListener( 'pointermove', function ( evt ) {
			if ( ! dragging ) {
				return;
			}
			if ( ! moved && Math.hypot( evt.clientX - origin.x, evt.clientY - origin.y ) < 4 ) {
				return;
			}
			moved = true;
			var p = eventToNorm( evt );
			markers[ index ].x = p[ 0 ];
			markers[ index ].y = p[ 1 ];
			btn.style.left = ( p[ 0 ] * 100 ) + '%';
			btn.style.top = ( p[ 1 ] * 100 ) + '%';
		} );

		btn.addEventListener( 'pointerup', function ( evt ) {
			if ( ! dragging ) {
				return;
			}
			dragging = false;
			btn.releasePointerCapture( evt.pointerId );
			if ( moved ) {
				markDirty();
			}
		} );

		btn.addEventListener( 'click', function ( evt ) {
			evt.stopPropagation();
			if ( moved ) {
				moved = false;
				return;
			}
			if ( draft || placing ) {
				return;
			}
			selectMarker( index );
		} );
	}

	function renderMarkers() {
		if ( ! markerLayer ) {
			return;
		}
		clear( markerLayer );
		markers.forEach( function ( marker, i ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			var custom = hasCustomIcon( marker );
			btn.className = 'floorplan-marker'
				+ ( 'entrance' === marker.kind ? ' is-entrance' : '' )
				+ ( ! custom && 'number' === marker.icon ? ' is-number' : '' )
				+ ( i === selectedMarker ? ' is-selected' : '' );
			applyMarkerStyle( btn, marker );
			btn.setAttribute( 'aria-label', markerLabel( marker, i ) );
			fillMarkerButton( btn, marker );
			attachMarkerEvents( btn, i );
			markerLayer.appendChild( btn );
		} );
	}

	function renderMarkerList() {
		if ( ! markersListEl ) {
			return;
		}
		clear( markersListEl );
		if ( ! markers.length ) {
			var p = document.createElement( 'p' );
			p.className = 'no-areas';
			p.textContent = cfg.strings.noMarkers;
			markersListEl.appendChild( p );
			return;
		}
		markers.forEach( function ( marker, i ) {
			var item = document.createElement( 'div' );
			item.className = 'area-item' + ( i === selectedMarker ? ' is-selected' : '' );

			var title = document.createElement( 'button' );
			title.type = 'button';
			title.className = 'area-title';
			var strong = document.createElement( 'strong' );
			strong.textContent = markerLabel( marker, i );
			title.appendChild( strong );
			var kind = document.createElement( 'span' );
			kind.className = 'shop-name';
			kind.textContent = ' (' + ( 'entrance' === marker.kind ? cfg.strings.entrance : cfg.strings.facility ) + ')';
			title.appendChild( kind );
			title.addEventListener( 'click', function () {
				selectMarker( i );
			} );

			var del = document.createElement( 'button' );
			del.type = 'button';
			del.className = 'delete-area';
			del.setAttribute( 'aria-label', cfg.strings.deleteMarker );
			del.innerHTML = '<span class="dashicons dashicons-trash"></span>';
			del.addEventListener( 'click', function () {
				removeMarker( i );
			} );

			item.appendChild( title );
			item.appendChild( del );
			markersListEl.appendChild( item );
		} );
	}

	/**
	 * @param {string} labelText
	 * @param {number} value
	 * @param {number} min
	 * @param {number} max
	 * @param {function(number): void} onInput
	 * @return {HTMLElement}
	 */
	function sizeField( labelText, value, min, max, onInput ) {
		var p = document.createElement( 'p' );
		p.className = 'marker-size-field';
		var label = document.createElement( 'label' );
		var head = document.createElement( 'span' );
		head.className = 'marker-size-head';
		var strong = document.createElement( 'strong' );
		strong.textContent = labelText;
		var out = document.createElement( 'span' );
		out.className = 'marker-size-value';
		out.textContent = value + ' px';
		head.appendChild( strong );
		head.appendChild( out );
		var range = document.createElement( 'input' );
		range.type = 'range';
		range.min = String( min );
		range.max = String( max );
		range.value = String( value );
		range.addEventListener( 'input', function () {
			var n = clampSize( range.value, min, max, value );
			out.textContent = n + ' px';
			onInput( n );
		} );
		label.appendChild( head );
		label.appendChild( range );
		p.appendChild( label );
		return p;
	}

	function openIconFrame() {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		if ( ! iconFrame ) {
			iconFrame = window.wp.media( {
				title: cfg.strings.chooseIcon,
				button: { text: cfg.strings.useIcon },
				library: { type: [ 'image/png', 'image/webp', 'image/svg+xml' ] },
				multiple: false
			} );
			iconFrame.on( 'select', function () {
				var marker = markers[ selectedMarker ];
				if ( ! marker ) {
					return;
				}
				var att = iconFrame.state().get( 'selection' ).first().toJSON();
				post( 'check_floorplan_marker_icon', { attachment_id: att.id } )
					.then( function ( res ) {
						if ( ! res.success ) {
							throw new Error( 'string' === typeof res.data ? res.data : cfg.strings.iconInvalid );
						}
						marker.icon_id = res.data.id;
						marker.icon_url = res.data.url;
						markDirty();
						renderMarkers();
						renderMarkerList();
						renderEditor();
					} )
					.catch( function ( err ) {
						var message = ( err && err.message ) || cfg.strings.iconInvalid;
						var slot = editorEl && editorEl.querySelector( '.marker-icon-error' );
						if ( slot ) {
							slot.textContent = message;
						}
						setStatus( message, true );
					} );
			} );
		}
		iconFrame.open();
	}

	function renderMarkerEditor( marker ) {
		var kind = document.createElement( 'select' );
		kind.className = 'widefat';
		[
			[ 'facility', cfg.strings.facility ],
			[ 'entrance', cfg.strings.entrance ]
		].forEach( function ( pair ) {
			var opt = document.createElement( 'option' );
			opt.value = pair[ 0 ];
			opt.textContent = pair[ 1 ];
			if ( pair[ 0 ] === marker.kind ) {
				opt.selected = true;
			}
			kind.appendChild( opt );
		} );
		kind.addEventListener( 'change', function () {
			var previous = marker.kind;
			marker.kind = 'entrance' === kind.value ? 'entrance' : 'facility';
			if ( 'entrance' === marker.kind && 'info' === marker.icon && ! hasCustomIcon( marker ) ) {
				marker.icon = 'number';
			}
			if ( marker.bg === defaultBg( previous ) ) {
				marker.bg = defaultBg( marker.kind );
			}
			markDirty();
			renderMarkers();
			renderMarkerList();
			renderEditor();
			var nextKind = editorEl.querySelector( 'select' );
			if ( nextKind ) {
				nextKind.focus();
			}
		} );

		var label = document.createElement( 'input' );
		label.type = 'text';
		label.className = 'widefat';
		label.maxLength = 80;
		label.value = marker.label;
		label.addEventListener( 'input', function () {
			marker.label = label.value;
			markDirty();
			renderMarkers();
			renderMarkerList();
		} );

		var picker = document.createElement( 'div' );
		picker.className = 'marker-icon-picker';
		( cfg.icons || [] ).forEach( function ( icon ) {
			var choice = document.createElement( 'label' );
			choice.title = icon.label;
			if ( ! hasCustomIcon( marker ) && icon.id === marker.icon ) {
				choice.className = 'is-selected';
			}
			var input = document.createElement( 'input' );
			input.type = 'radio';
			input.name = 'marker-icon';
			input.value = icon.id;
			input.checked = ! hasCustomIcon( marker ) && icon.id === marker.icon;
			var hidden = document.createElement( 'span' );
			hidden.className = 'screen-reader-text';
			hidden.textContent = icon.label;
			var glyph = document.createElement( 'span' );
			glyph.className = 'marker-icon-picker__glyph';
			glyph.innerHTML = icon.svg || '';
			input.addEventListener( 'change', function () {
				marker.icon = input.value;
				marker.icon_id = 0;
				marker.icon_url = '';
				markDirty();
				picker.querySelectorAll( 'label' ).forEach( function ( el ) {
					el.classList.toggle( 'is-selected', el.contains( input ) && input.checked );
				} );
				syncCustom();
				renderMarkers();
				renderMarkerList();
			} );
			choice.appendChild( input );
			choice.appendChild( hidden );
			choice.appendChild( glyph );
			picker.appendChild( choice );
		} );

		var number = document.createElement( 'input' );
		number.type = 'text';
		number.className = 'widefat';
		number.maxLength = 6;
		number.value = marker.number;
		number.addEventListener( 'input', function () {
			marker.number = number.value;
			markDirty();
			renderMarkers();
			renderMarkerList();
		} );

		var description = document.createElement( 'textarea' );
		description.className = 'widefat';
		description.rows = 4;
		description.maxLength = 2000;
		description.value = marker.description;
		description.addEventListener( 'input', function () {
			marker.description = description.value;
			markDirty();
		} );

		var del = document.createElement( 'button' );
		del.type = 'button';
		del.className = 'button delete-area-btn';
		del.textContent = cfg.strings.deleteMarker;
		del.addEventListener( 'click', function () {
			removeMarker( selectedMarker );
		} );

		editorEl.appendChild( field( cfg.strings.kindLabel, kind ) );
		editorEl.appendChild( field( cfg.strings.markerLabel, label ) );
		var iconField = document.createElement( 'p' );
		var iconStrong = document.createElement( 'strong' );
		iconStrong.textContent = cfg.strings.markerIcon;
		iconField.appendChild( iconStrong );
		iconField.appendChild( document.createElement( 'br' ) );
		iconField.appendChild( picker );
		editorEl.appendChild( iconField );

		var customRow = document.createElement( 'div' );
		customRow.className = 'marker-custom-icon';
		var upload = document.createElement( 'button' );
		upload.type = 'button';
		upload.className = 'button';
		upload.textContent = cfg.strings.uploadIcon;
		var removeIcon = document.createElement( 'button' );
		removeIcon.type = 'button';
		removeIcon.className = 'button';
		removeIcon.textContent = cfg.strings.removeIcon;
		var preview = document.createElement( 'img' );
		preview.className = 'marker-custom-icon__preview';
		preview.alt = '';
		var iconError = document.createElement( 'p' );
		iconError.className = 'description marker-icon-error';

		function syncCustom() {
			var on = hasCustomIcon( marker );
			preview.hidden = ! on;
			removeIcon.hidden = ! on;
			if ( on ) {
				preview.src = marker.icon_url;
			}
		}

		upload.addEventListener( 'click', function () {
			openIconFrame();
		} );
		removeIcon.addEventListener( 'click', function () {
			marker.icon_id = 0;
			marker.icon_url = '';
			iconError.textContent = '';
			markDirty();
			syncCustom();
			picker.querySelectorAll( 'label' ).forEach( function ( el ) {
				var radio = el.querySelector( 'input' );
				var selected = radio && radio.value === marker.icon;
				if ( radio ) {
					radio.checked = !! selected;
				}
				el.classList.toggle( 'is-selected', !! selected );
			} );
			renderMarkers();
			renderMarkerList();
		} );
		syncCustom();
		customRow.appendChild( upload );
		customRow.appendChild( removeIcon );
		customRow.appendChild( preview );
		iconField.appendChild( customRow );
		var iconHelp = document.createElement( 'span' );
		iconHelp.className = 'description';
		iconHelp.textContent = cfg.strings.iconHelp;
		iconField.appendChild( iconHelp );
		iconField.appendChild( iconError );

		var bg = document.createElement( 'input' );
		bg.type = 'color';
		bg.value = marker.bg || defaultBg( marker.kind );
		bg.addEventListener( 'input', function () {
			marker.bg = bg.value;
			markDirty();
			var current = markerLayer && markerLayer.querySelector( '.floorplan-marker.is-selected' );
			if ( current ) {
				current.style.setProperty( '--marker-bg', marker.bg );
			}
		} );

		editorEl.appendChild( field( cfg.strings.markerBg, bg ) );
		editorEl.appendChild( sizeField( cfg.strings.circleSize, marker.circle_size, limits.circleMin, limits.circleMax, function ( n ) {
			marker.circle_size = n;
			markDirty();
			var current = markerLayer && markerLayer.querySelector( '.floorplan-marker.is-selected' );
			if ( current ) {
				current.style.setProperty( '--marker-size', n + 'px' );
			}
		} ) );
		editorEl.appendChild( sizeField( cfg.strings.iconSize, marker.icon_size, limits.iconMin, limits.iconMax, function ( n ) {
			marker.icon_size = n;
			markDirty();
			var current = markerLayer && markerLayer.querySelector( '.floorplan-marker.is-selected' );
			if ( current ) {
				current.style.setProperty( '--marker-icon', n + 'px' );
			}
		} ) );
		if ( 'entrance' === marker.kind ) {
			var numberField = field( cfg.strings.markerNumber, number );
			var help = document.createElement( 'span' );
			help.className = 'description';
			help.textContent = cfg.strings.markerNumberHelp;
			numberField.appendChild( help );
			editorEl.appendChild( numberField );
		}
		editorEl.appendChild( field( cfg.strings.markerDescription, description ) );
		var dragNote = document.createElement( 'p' );
		dragNote.className = 'description';
		dragNote.textContent = cfg.strings.dragMarker;
		editorEl.appendChild( dragNote );
		var actions = document.createElement( 'p' );
		actions.appendChild( del );
		editorEl.appendChild( actions );
	}

	/**
	 * @param {number} index
	 * @param {boolean} [focusLabel] Sæt fokus i overskriften, når symbolet lige er placeret.
	 */
	function selectMarker( index, focusLabel ) {
		selected = -1;
		selectedMarker = index;
		renderAreas();
		renderMarkers();
		renderList();
		renderMarkerList();
		renderEditor();
		var labelInput = editorEl && editorEl.querySelector( 'input[type="text"]' );
		if ( focusLabel && labelInput ) {
			labelInput.focus();
			labelInput.select();
		}
	}

	function removeMarker( index ) {
		if ( ! markers[ index ] || ! window.confirm( cfg.strings.confirmDeleteMarker ) ) {
			return;
		}
		markers.splice( index, 1 );
		selectedMarker = -1;
		markDirty();
		select( -1 );
	}

	function setPlaceUi( on ) {
		if ( addMarkerBtn ) {
			addMarkerBtn.hidden = on;
		}
		if ( cancelMarkerBtn ) {
			cancelMarkerBtn.hidden = ! on;
		}
		if ( addBtn ) {
			addBtn.disabled = on;
		}
		if ( svg ) {
			svg.classList.toggle( 'is-placing', on );
		}
		if ( hintEl ) {
			hintEl.textContent = on ? cfg.strings.placeHint : cfg.strings.selectHint;
		}
	}

	function startPlace() {
		if ( ! svg ) {
			return;
		}
		if ( draft ) {
			cancelDraft();
		}
		placing = true;
		selected = -1;
		selectedMarker = -1;
		renderAreas();
		renderMarkers();
		renderList();
		renderMarkerList();
		renderEditor();
		setPlaceUi( true );
	}

	function cancelPlace() {
		placing = false;
		setPlaceUi( false );
	}

	/* ---------- Sidebar: liste og editor ---------- */

	function renderList() {
		clear( listEl );
		if ( ! areas.length ) {
			var p = document.createElement( 'p' );
			p.className = 'no-areas';
			p.textContent = cfg.strings.noAreas;
			listEl.appendChild( p );
			return;
		}
		areas.forEach( function ( area, i ) {
			var item = document.createElement( 'div' );
			item.className = 'area-item' + ( i === selected ? ' is-selected' : '' );

			var title = document.createElement( 'button' );
			title.type = 'button';
			title.className = 'area-title';
			var strong = document.createElement( 'strong' );
			strong.textContent = area.label || ( cfg.strings.newArea + ' ' + ( i + 1 ) );
			title.appendChild( strong );
			var name = area.shop_id ? shopTitle( area.shop_id ) : null;
			if ( name ) {
				var span = document.createElement( 'span' );
				span.className = 'shop-name';
				span.textContent = ' (' + name + ')';
				title.appendChild( span );
			}
			title.addEventListener( 'click', function () {
				select( i );
			} );

			var del = document.createElement( 'button' );
			del.type = 'button';
			del.className = 'delete-area';
			del.setAttribute( 'aria-label', cfg.strings.deleteArea );
			del.innerHTML = '<span class="dashicons dashicons-trash"></span>';
			del.addEventListener( 'click', function () {
				removeArea( i );
			} );

			item.appendChild( title );
			item.appendChild( del );
			listEl.appendChild( item );
		} );
	}

	function field( labelText, control ) {
		var p = document.createElement( 'p' );
		var label = document.createElement( 'label' );
		var strong = document.createElement( 'strong' );
		strong.textContent = labelText;
		label.appendChild( strong );
		label.appendChild( document.createElement( 'br' ) );
		label.appendChild( control );
		p.appendChild( label );
		return p;
	}

	function renderEditor() {
		clear( editorEl );
		if ( selectedMarker >= 0 && markers[ selectedMarker ] ) {
			renderMarkerEditor( markers[ selectedMarker ] );
			return;
		}
		var area = areas[ selected ];
		if ( ! area ) {
			var d = document.createElement( 'p' );
			d.className = 'description';
			d.textContent = cfg.strings.selectHint;
			editorEl.appendChild( d );
			return;
		}

		var label = document.createElement( 'input' );
		label.type = 'text';
		label.className = 'widefat';
		label.value = area.label;
		label.addEventListener( 'input', function () {
			area.label = label.value;
			markDirty();
			renderList();
		} );

		var shop = document.createElement( 'select' );
		shop.className = 'widefat';
		var none = document.createElement( 'option' );
		none.value = '0';
		none.textContent = cfg.strings.noShop;
		shop.appendChild( none );
		( cfg.shops || [] ).forEach( function ( s ) {
			var opt = document.createElement( 'option' );
			opt.value = String( s.id );
			opt.textContent = s.title;
			if ( s.id === area.shop_id ) {
				opt.selected = true;
			}
			shop.appendChild( opt );
		} );
		shop.addEventListener( 'change', function () {
			area.shop_id = parseInt( shop.value, 10 ) || 0;
			markDirty();
			renderList();
			renderAreas();
		} );

		var color = document.createElement( 'input' );
		color.type = 'color';
		color.value = area.color || '#3498db';
		color.addEventListener( 'input', function () {
			area.color = color.value;
			markDirty();
			var poly = gAreas.querySelector( '[data-index="' + selected + '"]' );
			if ( poly ) {
				poly.style.fill = color.value;
			}
		} );

		var del = document.createElement( 'button' );
		del.type = 'button';
		del.className = 'button delete-area-btn';
		del.textContent = cfg.strings.deleteArea;
		del.addEventListener( 'click', function () {
			removeArea( selected );
		} );

		editorEl.appendChild( field( cfg.strings.areaLabel, label ) );
		editorEl.appendChild( field( cfg.strings.areaShop, shop ) );
		editorEl.appendChild( field( cfg.strings.areaColor, color ) );
		var actions = document.createElement( 'p' );
		actions.appendChild( del );
		editorEl.appendChild( actions );
	}

	function select( index ) {
		selected = index;
		selectedMarker = -1;
		renderAreas();
		renderMarkers();
		renderList();
		renderMarkerList();
		renderEditor();
	}

	function removeArea( index ) {
		if ( ! areas[ index ] || ! window.confirm( cfg.strings.confirmDelete ) ) {
			return;
		}
		areas.splice( index, 1 );
		selected = -1;
		markDirty();
		select( -1 );
	}

	/* ---------- Tegn nyt område ---------- */

	function setDrawUi( on ) {
		addBtn.hidden = on;
		finishBtn.hidden = ! on;
		cancelBtn.hidden = ! on;
		if ( addMarkerBtn && ! placing ) {
			addMarkerBtn.disabled = on;
		}
		if ( hintEl && ! placing ) {
			hintEl.textContent = on ? cfg.strings.drawHint : cfg.strings.selectHint;
		}
		if ( svg ) {
			svg.classList.toggle( 'is-drawing', on );
		}
	}

	function startDraft() {
		if ( placing ) {
			placing = false;
			setPlaceUi( false );
		}
		draft = [];
		selected = -1;
		selectedMarker = -1;
		renderAreas();
		renderMarkers();
		renderList();
		renderMarkerList();
		renderEditor();
		renderDraft( null );
		setDrawUi( true );
	}

	function cancelDraft() {
		draft = null;
		rubber = null;
		clear( gDraft );
		setDrawUi( false );
		renderAreas();
	}

	function finishDraft() {
		if ( ! draft || draft.length < 3 ) {
			return;
		}
		areas.push( {
			label: cfg.strings.newArea + ' ' + ( areas.length + 1 ),
			shop_id: 0,
			color: '',
			points: draft
		} );
		draft = null;
		rubber = null;
		clear( gDraft );
		setDrawUi( false );
		markDirty();
		select( areas.length - 1 );
	}

	function renderDraft( cursor ) {
		clear( gDraft );
		rubber = null;
		if ( ! draft ) {
			return;
		}
		var r = 6 * unitsPerPx();
		if ( draft.length > 1 ) {
			gDraft.appendChild( make( 'polyline', {
				'class': 'floorplan-draft-line',
				points: pointsToPx( draft )
			} ) );
		}
		draft.forEach( function ( p, i ) {
			gDraft.appendChild( make( 'circle', {
				'class': 'floorplan-draft-point' + ( 0 === i ? ' is-first' : '' ),
				cx: p[ 0 ] * W,
				cy: p[ 1 ] * H,
				r: 0 === i ? r * 1.5 : r
			} ) );
		} );
		if ( cursor && draft.length ) {
			var last = draft[ draft.length - 1 ];
			rubber = make( 'line', {
				'class': 'floorplan-draft-rubber',
				x1: last[ 0 ] * W,
				y1: last[ 1 ] * H,
				x2: cursor[ 0 ] * W,
				y2: cursor[ 1 ] * H
			} );
			gDraft.appendChild( rubber );
		}
	}

	if ( svg ) {
		svg.addEventListener( 'click', function ( evt ) {
			var p = eventToNorm( evt );
			if ( placing ) {
				markers.push( normalizeMarker( {
					kind: 'facility',
					label: cfg.strings.facility + ' ' + ( markers.length + 1 ),
					description: '',
					icon: 'info',
					number: '',
					bg: limits.facilityBg,
					circle_size: limits.circleDefault,
					icon_size: limits.iconDefault,
					x: p[ 0 ],
					y: p[ 1 ]
				} ) );
				placing = false;
				setPlaceUi( false );
				markDirty();
				selectMarker( markers.length - 1, true );
				return;
			}
			if ( ! draft ) {
				if ( selected >= 0 || selectedMarker >= 0 ) {
					select( -1 ); // Klik på baggrunden fjerner markeringen.
				}
				return;
			}
			if ( draft.length >= 3 ) {
				var first = draft[ 0 ];
				var dist = Math.hypot( ( p[ 0 ] - first[ 0 ] ) * W, ( p[ 1 ] - first[ 1 ] ) * H );
				if ( dist < 10 * unitsPerPx() ) {
					finishDraft();
					return;
				}
			}
			draft.push( p );
			renderDraft( p );
		} );

		svg.addEventListener( 'mousemove', function ( evt ) {
			if ( draft && draft.length ) {
				var p = eventToNorm( evt );
				if ( rubber ) {
					rubber.setAttribute( 'x2', p[ 0 ] * W );
					rubber.setAttribute( 'y2', p[ 1 ] * H );
				} else {
					renderDraft( p );
				}
			}
		} );

		window.addEventListener( 'resize', function () {
			renderHandles();
			if ( draft ) {
				renderDraft( null );
			}
		} );
	}

	document.addEventListener( 'keydown', function ( evt ) {
		var tag = ( evt.target && evt.target.tagName ) || '';
		if ( /^(INPUT|SELECT|TEXTAREA)$/.test( tag ) ) {
			return;
		}
		if ( draft ) {
			if ( 'Enter' === evt.key ) {
				evt.preventDefault();
				finishDraft();
			} else if ( 'Escape' === evt.key ) {
				cancelDraft();
			} else if ( 'Backspace' === evt.key ) {
				evt.preventDefault();
				draft.pop();
				renderDraft( null );
			}
		} else if ( placing && 'Escape' === evt.key ) {
			cancelPlace();
		} else if ( 'Escape' === evt.key && ( selected >= 0 || selectedMarker >= 0 ) ) {
			select( -1 );
		}
	} );

	if ( addBtn ) {
		addBtn.addEventListener( 'click', startDraft );
		finishBtn.addEventListener( 'click', finishDraft );
		cancelBtn.addEventListener( 'click', cancelDraft );
	}

	if ( addMarkerBtn ) {
		addMarkerBtn.addEventListener( 'click', startPlace );
		cancelMarkerBtn.addEventListener( 'click', cancelPlace );
	}

	/* ---------- Gem, upload, import ---------- */

	function post( action, data ) {
		var fd = new FormData();
		fd.append( 'action', action );
		fd.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( k ) {
			fd.append( k, data[ k ] );
		} );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd } )
			.then( function ( r ) {
				return r.json();
			} );
	}

	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', function () {
			saveBtn.disabled = true;
			setStatus( '…' );
			var payload = areas.map( function ( a ) {
				return {
					label: a.label,
					shop_id: a.shop_id,
					color: a.color,
					points: pointsToString( a.points )
				};
			} );
			var markerPayload = markers.map( function ( m ) {
				return {
					kind: m.kind,
					label: m.label,
					description: m.description,
					icon: m.icon,
					icon_id: m.icon_id,
					number: m.number,
					bg: m.bg,
					circle_size: m.circle_size,
					icon_size: m.icon_size,
					x: m.x,
					y: m.y
				};
			} );
			post( 'save_floorplan_areas', {
				areas: JSON.stringify( payload ),
				markers: JSON.stringify( markerPayload )
			} )
				.then( function ( res ) {
					if ( ! res.success ) {
						throw new Error( res.data || cfg.strings.error );
					}
					dirty = false;
					// Brug serverens rensede version, så skærm og database er ens.
					var keep = selected;
					var keepMarker = selectedMarker;
					areas = ( res.data.areas || [] ).map( function ( a ) {
						return {
							label: a.label,
							shop_id: a.shop_id,
							color: a.color,
							points: parsePoints( a.points )
						};
					} );
					markers = ( res.data.markers || [] ).map( normalizeMarker );
					if ( keepMarker >= 0 && keepMarker < markers.length ) {
						selectMarker( keepMarker );
					} else {
						select( keep < areas.length ? keep : -1 );
					}
					setStatus( cfg.strings.saved );
				} )
				.catch( function ( err ) {
					setStatus( ( err && err.message ) || cfg.strings.error, true );
				} )
				.then( function () {
					saveBtn.disabled = false;
				} );
		} );
	}

	if ( uploadBtn && window.wp && window.wp.media ) {
		var frame = null;
		uploadBtn.addEventListener( 'click', function () {
			if ( ! frame ) {
				frame = window.wp.media( {
					title: cfg.strings.chooseImage,
					button: { text: cfg.strings.useImage },
					library: { type: 'image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					post( 'save_floorplan_svg', { attachment_id: att.id } )
						.then( function ( res ) {
							if ( ! res.success ) {
								throw new Error( res.data || cfg.strings.error );
							}
							dirty = false;
							window.location.reload();
						} )
						.catch( function ( err ) {
							setStatus( ( err && err.message ) || cfg.strings.error, true );
						} );
				} );
			}
			frame.open();
		} );
	}

	/**
	 * Importér JSON: { width, height, areas: [ { label, points: "x,y x,y ...", ... } ] }
	 * Punkter > 1 behandles som pixels i filens width/height og normaliseres.
	 */
	if ( importInput ) {
		importInput.addEventListener( 'change', function () {
			var file = importInput.files && importInput.files[ 0 ];
			if ( ! file ) {
				return;
			}
			var reader = new FileReader();
			reader.onload = function () {
				try {
					var data = JSON.parse( reader.result );
					var list = Array.isArray( data ) ? data : data.areas;
					if ( ! Array.isArray( list ) ) {
						throw new Error( 'format' );
					}
					var srcW = ( data.width || W ) || 1;
					var srcH = ( data.height || H ) || 1;
					var imported = [];
					list.forEach( function ( a ) {
						var raw = parsePoints( a.points );
						var isPx = raw.some( function ( p ) {
							return p[ 0 ] > 1 || p[ 1 ] > 1;
						} );
						var pts = raw.map( function ( p ) {
							return isPx ? [ clamp01( p[ 0 ] / srcW ), clamp01( p[ 1 ] / srcH ) ] : [ clamp01( p[ 0 ] ), clamp01( p[ 1 ] ) ];
						} );
						if ( pts.length >= 3 ) {
							imported.push( {
								label: String( a.label || '' ),
								shop_id: parseInt( a.shop_id, 10 ) || 0,
								color: a.color && /^#[0-9a-f]{6}$/i.test( a.color ) ? a.color : '',
								points: pts
							} );
						}
					} );
					if ( ! imported.length ) {
						throw new Error( 'empty' );
					}
					if ( areas.length && window.confirm( cfg.strings.importConfirm ) ) {
						areas = [];
					}
					areas = areas.concat( imported );
					markDirty();
					select( -1 );
					setStatus( imported.length + ' ' + cfg.strings.importDone );
				} catch ( e ) {
					setStatus( cfg.strings.importInvalid, true );
				}
				importInput.value = '';
			};
			reader.readAsText( file );
		} );
	}

	window.addEventListener( 'beforeunload', function ( evt ) {
		if ( dirty ) {
			evt.preventDefault();
			evt.returnValue = '';
		}
	} );

	/* ---------- Start ---------- */

	if ( svg ) {
		setDrawUi( false );
	}
	renderAreas();
	renderMarkers();
	renderList();
	renderMarkerList();
	renderEditor();
}() );
