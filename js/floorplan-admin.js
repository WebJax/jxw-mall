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
	var editorEl = document.getElementById( 'area-editor' );
	var statusEl = document.getElementById( 'floorplan-status' );
	var hintEl = document.getElementById( 'floorplan-hint' );
	var addBtn = document.getElementById( 'add-area-btn' );
	var finishBtn = document.getElementById( 'finish-area-btn' );
	var cancelBtn = document.getElementById( 'cancel-area-btn' );
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
	var selected = -1;
	var draft = null; // Array af [x, y] under tegning, ellers null.
	var rubber = null; // <line> fra sidste punkt til musen.
	var dirty = false;

	/* ---------- Hjælpefunktioner ---------- */

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
			if ( draft ) {
				return; // Klik under tegning håndteres af svg-lytteren.
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
		renderAreas();
		renderList();
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
		if ( hintEl ) {
			hintEl.textContent = on ? cfg.strings.drawHint : cfg.strings.selectHint;
		}
		svg.classList.toggle( 'is-drawing', on );
	}

	function startDraft() {
		draft = [];
		selected = -1;
		renderAreas();
		renderList();
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
			if ( ! draft ) {
				if ( selected >= 0 ) {
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
		} else if ( 'Escape' === evt.key && selected >= 0 ) {
			select( -1 );
		}
	} );

	if ( addBtn ) {
		addBtn.addEventListener( 'click', startDraft );
		finishBtn.addEventListener( 'click', finishDraft );
		cancelBtn.addEventListener( 'click', cancelDraft );
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
			post( 'save_floorplan_areas', { areas: JSON.stringify( payload ) } )
				.then( function ( res ) {
					if ( ! res.success ) {
						throw new Error( res.data || cfg.strings.error );
					}
					dirty = false;
					// Brug serverens rensede version, så skærm og database er ens.
					var keep = selected;
					areas = ( res.data.areas || [] ).map( function ( a ) {
						return {
							label: a.label,
							shop_id: a.shop_id,
							color: a.color,
							points: parsePoints( a.points )
						};
					} );
					select( keep < areas.length ? keep : -1 );
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
	renderList();
	renderEditor();
}() );
