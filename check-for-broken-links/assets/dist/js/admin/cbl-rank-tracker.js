/**
 * Rank Tracker admin page (3.1.3 layout).
 *
 * Self-contained page script, loaded only on the Rank Tracker screen.
 * Three views share one state fetch: first run (no keywords yet),
 * results, and settings (?view=settings). Everything that reaches the
 * DOM from brokenlinkchecker.io is written with textContent or
 * setAttribute, never innerHTML.
 *
 * The API returns one keyword row per device. The results table merges
 * the desktop and mobile rows of a keyword into one line, so selection
 * and "Stop tracking" work on the whole group.
 *
 * @package WPCBL_Check_Broken_Links
 * @since 3.1.3
 */
( function () {
	'use strict';

	var CFG = window.wpcblRank || {};
	var T = CFG.i18n || {};
	var app = document.getElementById( 'wpcbl-rank-app' );
	if ( ! app ) {
		return;
	}

	var VIEW = app.getAttribute( 'data-view' ) === 'settings' ? 'settings' : 'tracker';
	var content = document.getElementById( 'wpcbl-rank-content' );
	var skeleton = document.getElementById( 'wpcbl-rank-skeleton' );
	var errorBox = document.getElementById( 'wpcbl-rank-error' );
	var addBtn = document.getElementById( 'wpcbl-rank-add' );
	var exportBtn = document.getElementById( 'wpcbl-rank-export' );
	var marketMeta = document.getElementById( 'wpcbl-rank-market' );
	var savedHint = document.getElementById( 'wpcbl-rank-saved' );
	var SVG_NS = 'http://www.w3.org/2000/svg';

	var state = null;
	var ui = {
		engine: null,
		filter: '',
		selected: {},
		status: '',
		statusWarn: false,
		refreshing: false,
		pubBusy: false,
		copied: false,
		firstText: '',
		polls: 0,
		pollTimer: null
	};

	// ---- Helpers ---------------------------------------------------------

	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var out = String( str || '' );
		args.forEach( function ( value, i ) {
			out = out.split( '%' + ( i + 1 ) + '$s' ).join( String( value ) );
		} );
		return out.split( '%s' ).join( args.length ? String( args[ 0 ] ) : '' );
	}

	/** Element builder. Children may be strings (text), nodes, or null. */
	function h( tag, attrs, children ) {
		var el = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			var value = attrs[ key ];
			if ( value === null || value === undefined || value === false ) {
				return;
			}
			if ( key === 'class' ) {
				el.className = value;
			} else if ( key === 'text' ) {
				el.textContent = value;
			} else if ( key.indexOf( 'on' ) === 0 && typeof value === 'function' ) {
				el.addEventListener( key.slice( 2 ), value );
			} else if ( value === true ) {
				el.setAttribute( key, '' );
			} else {
				el.setAttribute( key, String( value ) );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child === null || child === undefined || child === false ) {
				return;
			}
			el.appendChild( typeof child === 'string' || typeof child === 'number' ? document.createTextNode( String( child ) ) : child );
		} );
		return el;
	}

	function svg( tag, attrs ) {
		var el = document.createElementNS( SVG_NS, tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			el.setAttribute( key, String( attrs[ key ] ) );
		} );
		return el;
	}

	function post( action, fields ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', CFG.nonce || '' );
		Object.keys( fields || {} ).forEach( function ( key ) {
			var value = fields[ key ];
			if ( Array.isArray( value ) ) {
				value.forEach( function ( item ) {
					body.append( key + '[]', String( item ) );
				} );
			} else if ( value !== undefined && value !== null ) {
				body.append( key, String( value ) );
			}
		} );

		return window.fetch( CFG.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json().catch( function () {
					return null;
				} );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( json && typeof json.data === 'string' ? json.data : T.generic );
				}
				return json.data;
			} );
	}

	function thousands( n ) {
		return Math.round( Number( n ) || 0 ).toString().replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
	}

	function compactVolume( v ) {
		if ( v === null || v === undefined || v === '' ) {
			return '–';
		}
		v = Number( v );
		if ( v >= 1000 ) {
			var k = v / 1000;
			return ( v >= 10000 ? Math.round( k ) : Math.round( k * 10 ) / 10 ) + 'K';
		}
		return String( v );
	}

	function shortDate( iso ) {
		if ( ! iso ) {
			return '';
		}
		var parts = String( iso ).slice( 0, 10 ).split( '-' );
		var d = new Date( Number( parts[ 0 ] ), Number( parts[ 1 ] ) - 1, Number( parts[ 2 ] ) );
		if ( isNaN( d.getTime() ) ) {
			return String( iso );
		}
		try {
			return d.toLocaleDateString( document.documentElement.lang || undefined, { month: 'short', day: 'numeric' } );
		} catch ( e ) {
			return d.toDateString().slice( 4, 10 );
		}
	}

	function engineLabel( engine ) {
		return T[ engine ] || engine;
	}

	function deviceLabel( device ) {
		return device === 'both' ? T.deviceBoth : T[ device ] || device;
	}

	function marketLabel( key ) {
		var match = ( state && state.markets || [] ).filter( function ( m ) {
			return m.key === key;
		} )[ 0 ];
		return match ? match.label : key || '';
	}

	function parseKeywords( text ) {
		var seen = {};
		return String( text || '' ).split( /[\n,]+/ ).map( function ( k ) {
			return k.trim().toLowerCase();
		} ).filter( function ( k ) {
			if ( ! k || seen[ k ] ) {
				return false;
			}
			seen[ k ] = true;
			return true;
		} );
	}

	function freeSlots() {
		var q = state.quota || {};
		return Math.max( 0, ( Number( q.positions_limit ) || 0 ) - ( Number( q.positions_used ) || 0 ) );
	}

	function engineCount() {
		return state.settings && state.settings.engine === 'both' ? 2 : 1;
	}

	/** Polyline points for a series. invert: lower values plot higher. */
	function linePoints( values, w, hgt, invert ) {
		var nums = values.filter( function ( v ) {
			return v !== null && v !== undefined;
		} ).map( Number );
		if ( nums.length < 2 ) {
			return '';
		}
		var min = Math.min.apply( null, nums );
		var max = Math.max.apply( null, nums );
		var range = max - min || 1;
		return nums.map( function ( v, i ) {
			var x = ( i / ( nums.length - 1 ) ) * ( w - 4 ) + 2;
			var norm = ( v - min ) / range;
			var y = ( invert ? norm : 1 - norm ) * ( hgt - 4 ) + 2;
			return x.toFixed( 1 ) + ',' + y.toFixed( 1 );
		} ).join( ' ' );
	}

	function sparkline( values, w, hgt, invert, color ) {
		var el = svg( 'svg', { width: w, height: hgt, viewBox: '0 0 ' + w + ' ' + hgt, 'aria-hidden': 'true', focusable: 'false' } );
		var points = linePoints( values, w, hgt, invert );
		if ( points ) {
			el.appendChild( svg( 'polyline', { points: points, fill: 'none', stroke: color, 'stroke-width': 1.8, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' } ) );
		}
		return el;
	}

	function segmented( options, current, onPick, label, extraClass ) {
		var wrap = h( 'div', { class: 'cbl-rt-seg' + ( extraClass ? ' ' + extraClass : '' ), role: 'radiogroup', 'aria-label': label } );
		options.forEach( function ( o ) {
			var on = o.value === current;
			wrap.appendChild( h( 'button', {
				type: 'button',
				role: 'radio',
				'aria-checked': on ? 'true' : 'false',
				class: on ? 'is-on' : '',
				onclick: function () {
					onPick( o.value );
				}
			}, [ o.label ] ) );
		} );
		return wrap;
	}

	function setStatus( message, warn ) {
		ui.status = message || '';
		ui.statusWarn = !! warn;
		var line = document.getElementById( 'wpcbl-rank-status' );
		if ( line ) {
			line.textContent = ui.status;
			line.classList.toggle( 'is-warn', ui.statusWarn );
			line.hidden = ! ui.status;
		}
	}

	function statusLine() {
		return h( 'p', { id: 'wpcbl-rank-status', class: 'cbl-rt-status' + ( ui.statusWarn ? ' is-warn' : '' ), role: 'status', 'aria-live': 'polite', hidden: ! ui.status }, [ ui.status ] );
	}

	// ---- Loading ---------------------------------------------------------

	function showError( message ) {
		skeleton.hidden = true;
		content.textContent = '';
		document.getElementById( 'wpcbl-rank-error-text' ).textContent = message || T.loadError;
		errorBox.hidden = false;
		if ( addBtn ) {
			addBtn.hidden = true;
		}
	}

	function schedulePoll() {
		window.clearTimeout( ui.pollTimer );
		// Never re-render the settings view under a half-typed field.
		if ( VIEW === 'settings' || ! state || ! state.pending || ui.polls >= 40 ) {
			return;
		}
		ui.pollTimer = window.setTimeout( function () {
			ui.polls++;
			load( true );
		}, 15000 );
	}

	function load( fresh ) {
		return post( 'wpcbl_rank_state', fresh ? { fresh: 1 } : {} )
			.then( function ( data ) {
				state = data || {};
				render();
				schedulePoll();
			} )
			.catch( function ( err ) {
				showError( err && err.message );
			} );
	}

	// ---- Keyword groups --------------------------------------------------

	/** Merge per-device rows into one line per keyword and market. */
	function groups() {
		var map = {};
		var order = [];
		( state.keywords || [] ).forEach( function ( kw ) {
			var key = kw.keyword + '|' + kw.market;
			if ( ! map[ key ] ) {
				map[ key ] = { key: key, keyword: kw.keyword, market: kw.market, rows: {}, ids: [], meta: kw, pending: false };
				order.push( key );
			}
			map[ key ].rows[ kw.device === 'mobile' ? 'mobile' : 'desktop' ] = kw;
			map[ key ].ids.push( kw.id );
			map[ key ].pending = map[ key ].pending || !! kw.pending;
		} );
		return order.map( function ( key ) {
			return map[ key ];
		} );
	}

	function checkFor( row, engine ) {
		if ( ! row || ! row.checks ) {
			return null;
		}
		return row.checks[ engine + ':' + row.device ] || null;
	}

	// ---- View: first run -------------------------------------------------

	function renderFirstRun() {
		var settings = state.settings || {};
		var device = settings.device || 'desktop';
		var devices = device === 'both' ? 2 : 1;
		var available = freeSlots();

		var textarea = h( 'textarea', { class: 'cbl-rt-textarea', rows: 4, placeholder: T.kwPlaceholder, 'aria-label': T.firstTitle } );
		textarea.value = ui.firstText;
		var slotLine = h( 'span', { class: 'cbl-rt-slot-line' } );
		var ideasWrap = h( 'div', { class: 'cbl-rt-ideas' } );
		var startBtn = h( 'button', { type: 'button', class: 'cbl-btn cbl-btn-primary cbl-rt-start' } );

		function sync() {
			var kws = parseKeywords( textarea.value );
			var n = kws.length;
			var slots = n * devices * engineCount();
			var over = slots > available;
			ui.firstText = textarea.value;

			slotLine.textContent = n
				? ( over ? fmt( T.slotsNeeds, slots, available ) : fmt( T.slotsUses, slots, available ) )
				: fmt( T.slotsAvailable, available );
			slotLine.classList.toggle( 'is-over', over );

			startBtn.textContent = n === 1 ? T.startTracking1 : n ? fmt( T.startTrackingN, n ) : T.startTracking;
			startBtn.disabled = ! n || over;

			Array.prototype.forEach.call( ideasWrap.children, function ( chip ) {
				var on = kws.indexOf( chip.getAttribute( 'data-kw' ) ) !== -1;
				chip.classList.toggle( 'is-on', on );
				chip.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				chip.firstChild.textContent = on ? '✓ ' : '+ ';
			} );
		}

		( CFG.ideas || [] ).forEach( function ( idea ) {
			ideasWrap.appendChild( h( 'button', {
				type: 'button',
				class: 'cbl-rt-idea',
				'data-kw': idea,
				'aria-pressed': 'false',
				onclick: function () {
					var kws = parseKeywords( textarea.value );
					var at = kws.indexOf( idea );
					if ( at === -1 ) {
						kws.push( idea );
					} else {
						kws.splice( at, 1 );
					}
					textarea.value = kws.join( '\n' );
					sync();
				}
			}, [ h( 'span', { 'aria-hidden': 'true' }, [ '+ ' ] ), idea ] ) );
		} );

		textarea.addEventListener( 'input', sync );
		startBtn.addEventListener( 'click', function () {
			var kws = parseKeywords( textarea.value );
			if ( ! kws.length ) {
				return;
			}
			startBtn.disabled = true;
			startBtn.textContent = T.adding;
			addKeywords( kws.join( '\n' ), state.project && state.project.market, device ).then( function ( ok ) {
				if ( ok ) {
					ui.firstText = '';
				} else {
					sync();
				}
			} );
		} );

		var engineText = engineLabel( settings.engine || 'google' ) + ' · ' + deviceLabel( device ) + ' · ';

		var left = h( 'div', { class: 'cbl-rt-first-main' }, [
			h( 'div', {}, [
				h( 'h2', { class: 'cbl-rt-first-title', text: T.firstTitle } ),
				h( 'p', { class: 'cbl-rt-lead', text: T.firstLead } )
			] ),
			h( 'div', { class: 'cbl-rt-field' }, [
				textarea,
				h( 'div', { class: 'cbl-rt-field-foot' }, [
					slotLine,
					h( 'span', { class: 'cbl-rt-muted' }, [ engineText, h( 'a', { href: CFG.settingsUrl, text: T.change } ) ] )
				] )
			] ),
			( CFG.ideas || [] ).length ? h( 'div', { class: 'cbl-rt-ideas-block' }, [
				h( 'span', { class: 'cbl-rt-label-sm', text: T.ideasTitle } ),
				ideasWrap
			] ) : null,
			h( 'div', { class: 'cbl-rt-start-row' }, [
				startBtn,
				h( 'span', { class: 'cbl-rt-muted', text: state.has_daily ? T.daily : T.weekly } )
			] ),
			statusLine()
		] );

		var preview = [
			[ T.previewKw1, 7, 3, 9, 'up', [ 15, 13, 12, 9, 7, 3 ] ],
			[ T.previewKw2, 12, -2, 14, 'down', [ 6, 5, 4, 6, 9, 12 ] ],
			[ T.previewKw3, 4, 1, 5, 'up', [ 12, 11, 9, 8, 6, 5 ] ]
		];
		var table = h( 'div', { class: 'cbl-rt-preview', 'aria-hidden': 'true' }, [
			h( 'div', { class: 'cbl-rt-preview-row is-head' }, [
				h( 'span', { text: T.keyword } ), h( 'span', { text: T.desktop } ), h( 'span', { text: T.mobile } ), h( 'span', { text: T.trend } )
			] )
		] );
		preview.forEach( function ( p ) {
			var up = p[ 4 ] === 'up';
			table.appendChild( h( 'div', { class: 'cbl-rt-preview-row' }, [
				h( 'span', { class: 'cbl-rt-preview-kw', text: p[ 0 ] } ),
				h( 'span', {}, [ h( 'strong', { text: p[ 1 ] } ), ' ', h( 'span', { class: 'cbl-rt-delta ' + ( up ? 'is-up' : 'is-down' ), text: ( up ? '▲' : '▼' ) + Math.abs( p[ 2 ] ) } ) ] ),
				h( 'strong', { text: p[ 3 ] } ),
				sparkline( p[ 5 ], 56, 18, true, up ? 'var(--cbl-rt-up)' : 'var(--cbl-rt-down)' )
			] ) );
		} );

		var points = h( 'ul', { class: 'cbl-rt-points' } );
		[ T.point1, T.point2, T.point3 ].forEach( function ( text ) {
			points.appendChild( h( 'li', {}, [ checkIcon(), h( 'span', { text: text } ) ] ) );
		} );

		var right = h( 'div', { class: 'cbl-rt-first-side' }, [
			h( 'span', { class: 'cbl-rt-side-title', text: T.previewTitle } ),
			table,
			points
		] );

		content.appendChild( h( 'section', { class: 'cbl-rt-card cbl-rt-first' }, [ left, right ] ) );
		sync();
	}

	function checkIcon() {
		var el = svg( 'svg', { width: 14, height: 14, viewBox: '0 0 24 24', 'aria-hidden': 'true', focusable: 'false', class: 'cbl-rt-check' } );
		el.appendChild( svg( 'path', { d: 'M5 12.5l4.5 4.5L19 7.5', stroke: 'currentColor', 'stroke-width': 2.8, fill: 'none', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' } ) );
		return el;
	}

	// ---- View: results ---------------------------------------------------

	function renderSlots() {
		var q = state.quota || {};
		var used = Number( q.positions_used ) || 0;
		var limit = Number( q.positions_limit ) || 0;
		var pct = limit ? Math.min( 100, ( used / limit ) * 100 ) : 0;
		var refreshes = state.refreshes || { used: 0, limit: 0 };
		var left = Math.max( 0, ( refreshes.limit || 0 ) - ( refreshes.used || 0 ) );

		var refresh = h( 'button', {
			type: 'button',
			class: 'cbl-btn cbl-rt-btn-sm',
			title: fmt( T.refreshesLeft, left, refreshes.limit || 0 ),
			disabled: ui.refreshing || left < 1,
			onclick: onRefresh
		}, [ ui.refreshing ? T.refreshing : T.refreshNow ] );

		var when = state.last_checked
			? fmt( T.updatedNext, shortDate( state.last_checked ), shortDate( state.next_check ) )
			: fmt( T.firstUpdate, shortDate( state.next_check ) );

		return h( 'div', { class: 'cbl-rt-card cbl-rt-bar' }, [
			h( 'div', { class: 'cbl-rt-slots' }, [
				h( 'div', { class: 'cbl-rt-slots-head' }, [
					h( 'span', { class: 'cbl-rt-slots-label', text: fmt( T.slotsUsed, used, limit ) } ),
					CFG.upgradeUrl ? h( 'a', { href: CFG.upgradeUrl, text: T.getMore } ) : null
				] ),
				h( 'div', { class: 'cbl-rt-meter', role: 'progressbar', 'aria-valuemin': 0, 'aria-valuemax': limit, 'aria-valuenow': used, 'aria-label': fmt( T.slotsUsed, used, limit ) }, [
					h( 'span', { style: 'width:' + pct + '%' } )
				] )
			] ),
			h( 'span', { class: 'cbl-rt-when', text: when } ),
			refresh
		] );
	}

	function renderPublic() {
		var s = state.settings || {};
		var on = !! s.share_url;
		var text = h( 'div', { class: 'cbl-rt-pub-text' }, [
			h( 'div', { class: 'cbl-rt-pub-title', text: T.pubTitle } ),
			h( 'div', { class: 'cbl-rt-muted', text: on ? ( s.share_has_password ? T.pubOnPwHint : T.pubOnHint ) : T.pubOffHint } )
		] );

		if ( ! on ) {
			return h( 'div', { class: 'cbl-rt-card cbl-rt-pub' }, [
				text,
				h( 'button', { type: 'button', class: 'cbl-btn cbl-rt-btn-outline', disabled: ui.pubBusy, onclick: function () {
					share( 'on' );
				} }, [ ui.pubBusy ? T.generating : T.generate ] )
			] );
		}

		return h( 'div', { class: 'cbl-rt-card cbl-rt-pub' }, [
			text,
			h( 'div', { class: 'cbl-rt-pub-link' }, [
				h( 'code', { class: 'cbl-rt-pub-url', title: s.share_url, text: s.share_url } ),
				h( 'button', { type: 'button', class: 'cbl-btn cbl-btn-primary cbl-rt-copy' + ( ui.copied ? ' is-copied' : '' ), onclick: function () {
					copy( s.share_url );
				} }, [ ui.copied ? T.copied : T.copy ] ),
				h( 'a', { class: 'cbl-btn', href: s.share_url, target: '_blank', rel: 'noopener noreferrer' }, [ T.open + ' ', h( 'span', { 'aria-hidden': 'true', text: '↗' } ) ] ),
				h( 'button', { type: 'button', class: 'cbl-btn cbl-rt-icon-btn', title: T.disableLink, 'aria-label': T.disableLink, disabled: ui.pubBusy, onclick: function () {
					share( 'off' );
				} }, [ h( 'span', { class: 'dashicons dashicons-no-alt', 'aria-hidden': 'true' } ) ] )
			] )
		] );
	}

	function renderKpis() {
		var c = state.charts;
		if ( ! c || ! c.dates || ! c.dates.length ) {
			return null;
		}
		var defs = [
			{ label: T.visibility, series: c.visibility || [], invert: false, value: function ( v ) {
				return ( Math.round( v * 10 ) / 10 ) + '%';
			}, delta: function ( d ) {
				return fmt( T.pts, Math.round( Math.abs( d ) * 10 ) / 10 );
			} },
			{ label: T.traffic, series: c.traffic || [], invert: false, value: function ( v ) {
				return thousands( v ) + T.perMonth;
			}, delta: function ( d ) {
				return thousands( Math.abs( d ) );
			} },
			{ label: T.avgPosition, series: c.avg_position || [], invert: true, value: function ( v ) {
				return String( Math.round( v * 10 ) / 10 );
			}, delta: function ( d ) {
				return String( Math.round( Math.abs( d ) * 10 ) / 10 );
			} }
		];

		var grid = h( 'div', { class: 'cbl-rt-kpis' } );
		defs.forEach( function ( def ) {
			var series = def.series.map( Number );
			if ( ! series.length ) {
				return;
			}
			var last = series[ series.length - 1 ];
			var diff = series.length > 1 ? last - series[ series.length - 2 ] : 0;
			var better = def.invert ? diff < 0 : diff > 0;
			var deltaEl = diff
				? h( 'span', { class: 'cbl-rt-delta ' + ( better ? 'is-up' : 'is-down' ), text: ( better ? '▲ ' : '▼ ' ) + def.delta( diff ) } )
				: null;

			grid.appendChild( h( 'div', { class: 'cbl-rt-card cbl-rt-kpi' }, [
				h( 'div', { class: 'cbl-rt-kpi-head' }, [ h( 'span', { text: def.label } ), deltaEl ] ),
				h( 'div', { class: 'cbl-rt-kpi-body' }, [
					h( 'span', { class: 'cbl-rt-kpi-value', text: def.value( last ) } ),
					sparkline( series.slice( -8 ), 110, 34, def.invert, 'var(--cbl-purple)' )
				] )
			] ) );
		} );
		return grid.children.length ? grid : null;
	}

	function positionCell( row, check, pending ) {
		if ( ! row ) {
			return h( 'div', { class: 'cbl-rt-td cbl-rt-pos is-none', title: T.notTracked }, [ h( 'span', { class: 'cbl-rt-pos-n', text: '–' } ) ] );
		}
		if ( pending && ( ! check || check.position === undefined ) ) {
			return h( 'div', { class: 'cbl-rt-td cbl-rt-pos is-pending', title: T.checking }, [ h( 'span', { class: 'cbl-rt-dots', 'aria-label': T.checking } ) ] );
		}
		if ( ! check || check.position === null || check.position === undefined ) {
			return h( 'div', { class: 'cbl-rt-td cbl-rt-pos is-none', title: T.notRanked }, [ h( 'span', { class: 'cbl-rt-pos-n', text: '>100' } ) ] );
		}
		var change = Number( check.change ) || 0;
		return h( 'div', { class: 'cbl-rt-td cbl-rt-pos' }, [
			h( 'span', { class: 'cbl-rt-pos-n', text: check.position } ),
			change ? h( 'span', { class: 'cbl-rt-delta ' + ( change > 0 ? 'is-up' : 'is-down' ), text: ( change > 0 ? '▲' : '▼' ) + Math.abs( change ) } ) : null
		] );
	}

	function renderTable() {
		var engines = state.engines && state.engines.length ? state.engines : [ 'google' ];
		if ( ! ui.engine || engines.indexOf( ui.engine ) === -1 ) {
			ui.engine = engines[ 0 ];
		}
		var engine = ui.engine;
		var all = groups();
		var q = ui.filter.trim().toLowerCase();
		var list = all.filter( function ( g ) {
			return ! q || g.keyword.toLowerCase().indexOf( q ) !== -1;
		} );
		var hasDesktop = all.some( function ( g ) {
			return !! g.rows.desktop;
		} );
		var hasMobile = all.some( function ( g ) {
			return !! g.rows.mobile;
		} );
		var projectMarket = state.project && state.project.market;

		// Drop selections for keywords that no longer exist.
		var live = {};
		all.forEach( function ( g ) {
			live[ g.key ] = true;
		} );
		Object.keys( ui.selected ).forEach( function ( key ) {
			if ( ! live[ key ] ) {
				delete ui.selected[ key ];
			}
		} );
		var nSel = Object.keys( ui.selected ).length;

		var cols = [ 'check', 'keyword' ];
		if ( hasDesktop ) {
			cols.push( 'desktop' );
		}
		if ( hasMobile ) {
			cols.push( 'mobile' );
		}
		cols = cols.concat( [ 'best', 'trend', 'volume', 'cpc', 'intent', 'page' ] );
		var gridClass = 'cbl-rt-grid cbl-rt-cols-' + ( hasDesktop && hasMobile ? 'both' : 'one' );

		var toolbar = h( 'div', { class: 'cbl-rt-toolbar' }, [
			engines.length > 1 ? segmented( engines.map( function ( e ) {
				return { value: e, label: engineLabel( e ) };
			} ), engine, function ( value ) {
				ui.engine = value;
				render();
			}, T.engine ) : null,
			h( 'input', { type: 'search', class: 'cbl-rt-filter', placeholder: T.filter, 'aria-label': T.filter, value: ui.filter, oninput: function ( e ) {
				ui.filter = e.target.value;
				var pos = e.target.selectionStart;
				render();
				var again = content.querySelector( '.cbl-rt-filter' );
				if ( again ) {
					again.focus();
					again.setSelectionRange( pos, pos );
				}
			} } ),
			nSel ? h( 'div', { class: 'cbl-rt-selbar' }, [
				h( 'span', { text: fmt( T.selected, nSel ) } ),
				h( 'button', { type: 'button', class: 'cbl-rt-btn-danger', onclick: onStopTracking }, [ T.stopTracking ] )
			] ) : null
		] );

		var allChecked = all.length > 0 && nSel === all.length;
		var head = h( 'div', { class: gridClass + ' cbl-rt-thead', role: 'row' } );
		cols.forEach( function ( col ) {
			if ( col === 'check' ) {
				var box = h( 'input', { type: 'checkbox', 'aria-label': T.selectAll } );
				box.checked = allChecked;
				box.indeterminate = nSel > 0 && ! allChecked;
				box.addEventListener( 'change', function () {
					ui.selected = {};
					if ( box.checked ) {
						all.forEach( function ( g ) {
							ui.selected[ g.key ] = true;
						} );
					}
					render();
				} );
				head.appendChild( h( 'div', { class: 'cbl-rt-th cbl-rt-c-check', role: 'columnheader' }, [ box ] ) );
				return;
			}
			var labels = { keyword: T.keyword, desktop: T.desktop, mobile: T.mobile, best: T.best, trend: T.weeks8, volume: T.volume, cpc: T.cpc, intent: T.intent, page: T.rankingPage };
			head.appendChild( h( 'div', { class: 'cbl-rt-th cbl-rt-c-' + col, role: 'columnheader', text: labels[ col ] } ) );
		} );

		var body = h( 'div', { role: 'rowgroup' } );
		var isBing = engine === 'bing';
		list.forEach( function ( g ) {
			var dRow = g.rows.desktop;
			var mRow = g.rows.mobile;
			var dCheck = checkFor( dRow, engine );
			var mCheck = checkFor( mRow, engine );
			var main = dCheck || mCheck;
			var bests = [ dCheck, mCheck ].filter( function ( c ) {
				return c && c.best !== null && c.best !== undefined;
			} ).map( function ( c ) {
				return Number( c.best );
			} );
			var history = main && Array.isArray( main.history ) ? main.history : [];
			var ranked = history.filter( function ( v ) {
				return v !== null && v !== undefined;
			} );
			var improving = ranked.length > 1 ? ranked[ ranked.length - 1 ] <= ranked[ 0 ] : true;
			var meta = g.meta;
			var volume = isBing ? meta.bing_search_volume : meta.search_volume;
			var cpc = isBing ? meta.bing_cpc : meta.cpc;
			var intents = String( meta.intent || '' ).split( ',' ).map( function ( s ) {
				return s.trim();
			} ).filter( Boolean );
			var sel = !! ui.selected[ g.key ];

			var box = h( 'input', { type: 'checkbox', 'aria-label': fmt( T.selectRow, g.keyword ) } );
			box.checked = sel;
			box.addEventListener( 'change', function () {
				if ( box.checked ) {
					ui.selected[ g.key ] = true;
				} else {
					delete ui.selected[ g.key ];
				}
				render();
			} );

			var kwCell = h( 'div', { class: 'cbl-rt-td cbl-rt-c-keyword' } );
			if ( g.market && g.market !== projectMarket && CFG.flagsBase ) {
				var flag = h( 'img', { class: 'cbl-rt-flag', alt: marketLabel( g.market ), title: marketLabel( g.market ), width: 16, height: 12, src: CFG.flagsBase + encodeURIComponent( String( g.market ).split( '-' )[ 0 ] ) + '.svg' } );
				flag.addEventListener( 'error', function () {
					flag.remove();
				} );
				kwCell.appendChild( flag );
			}
			kwCell.appendChild( h( 'span', { class: 'cbl-rt-kw', title: g.keyword, text: g.keyword } ) );

			var pageCell = h( 'div', { class: 'cbl-rt-td cbl-rt-c-page' } );
			if ( main && main.found_url ) {
				var path = main.found_url;
				try {
					path = new URL( main.found_url ).pathname;
				} catch ( e ) {}
				pageCell.appendChild( h( 'a', { href: main.found_url, target: '_blank', rel: 'noopener noreferrer', title: main.found_url, text: path } ) );
			} else {
				pageCell.appendChild( h( 'span', { class: 'cbl-rt-dim', text: '–' } ) );
			}

			var row = h( 'div', { class: gridClass + ' cbl-rt-row' + ( sel ? ' is-selected' : '' ), role: 'row' }, [
				h( 'div', { class: 'cbl-rt-td cbl-rt-c-check' }, [ box ] ),
				kwCell,
				hasDesktop ? positionCell( dRow, dCheck, g.pending ) : null,
				hasMobile ? positionCell( mRow, mCheck, g.pending ) : null,
				h( 'div', { class: 'cbl-rt-td cbl-rt-c-best', text: bests.length ? Math.min.apply( null, bests ) : '–' } ),
				h( 'div', { class: 'cbl-rt-td cbl-rt-c-trend' }, [ ranked.length > 1 ? sparkline( ranked, 72, 22, true, improving ? 'var(--cbl-rt-up)' : 'var(--cbl-rt-down)' ) : h( 'span', { class: 'cbl-rt-dim', text: '–' } ) ] ),
				h( 'div', { class: 'cbl-rt-td cbl-rt-c-volume', text: compactVolume( volume ) } ),
				h( 'div', { class: 'cbl-rt-td cbl-rt-c-cpc', text: cpc ? '$' + Number( cpc ).toFixed( 2 ) : '–' } ),
				h( 'div', { class: 'cbl-rt-td cbl-rt-c-intent' }, [
					intents.length
						? h( 'span', { class: 'cbl-rt-intent is-' + intents[ 0 ].toLowerCase().replace( /[^a-z]/g, '' ), title: intents.join( ', ' ), text: intents[ 0 ].charAt( 0 ).toUpperCase() + intents[ 0 ].slice( 1 ) } )
						: h( 'span', { class: 'cbl-rt-dim', text: '–' } )
				] ),
				pageCell
			] );
			body.appendChild( row );
		} );

		var section = h( 'section', { class: 'cbl-rt-card cbl-rt-table-card' }, [
			toolbar,
			h( 'div', { class: 'cbl-rt-scroll' }, [ h( 'div', { class: 'cbl-rt-table', role: 'table', 'aria-label': T.keyword }, [ head, body ] ) ] ),
			! list.length && q ? h( 'p', { class: 'cbl-rt-nomatch', text: fmt( T.noMatch, ui.filter.trim() ) } ) : null,
			all.some( function ( g ) {
				return g.pending;
			} ) ? h( 'p', { class: 'cbl-rt-pending-note', text: T.pendingNote } ) : null
		] );
		return section;
	}

	function renderDaily() {
		if ( state.has_daily || ! CFG.upgradeUrl ) {
			return null;
		}
		return h( 'div', { class: 'cbl-rt-card cbl-rt-daily' }, [
			h( 'div', { class: 'cbl-rt-daily-text' }, [
				h( 'strong', { text: T.dailyTitle } ),
				h( 'span', { text: T.dailyText } )
			] ),
			h( 'a', { class: 'cbl-btn cbl-btn-primary', href: CFG.upgradeUrl, text: T.dailyButton } )
		] );
	}

	function renderResults() {
		content.appendChild( statusLine() );
		content.appendChild( renderSlots() );
		content.appendChild( renderPublic() );
		var kpis = renderKpis();
		if ( kpis ) {
			content.appendChild( kpis );
		}
		content.appendChild( renderTable() );
		var daily = renderDaily();
		if ( daily ) {
			content.appendChild( daily );
		}
	}

	// ---- Actions ---------------------------------------------------------

	function addKeywords( text, market, device ) {
		ui.polls = 0;
		setStatus( '' );
		return post( 'wpcbl_rank_add_keywords', { keywords: text, market: market || '', device: device || '' } )
			.then( function ( data ) {
				ui.status = data && data.message ? data.message : '';
				ui.statusWarn = false;
				return load( true ).then( function () {
					return true;
				} );
			} )
			.catch( function ( err ) {
				setStatus( err.message, true );
				return false;
			} );
	}

	function onRefresh() {
		ui.refreshing = true;
		ui.polls = 0;
		render();
		post( 'wpcbl_rank_refresh', {} )
			.then( function ( data ) {
				ui.status = data && data.message ? data.message : '';
				ui.statusWarn = !! ( data && data.ok === false );
			} )
			.catch( function ( err ) {
				ui.status = err.message;
				ui.statusWarn = true;
			} )
			.then( function () {
				ui.refreshing = false;
				return load( true );
			} );
	}

	function share( mode ) {
		ui.pubBusy = true;
		render();
		post( 'wpcbl_rank_share', { sharing: mode } )
			.catch( function ( err ) {
				ui.status = err.message;
				ui.statusWarn = true;
			} )
			.then( function () {
				ui.pubBusy = false;
				return load( true );
			} );
	}

	function copy( url ) {
		var done = function () {
			ui.copied = true;
			render();
			window.setTimeout( function () {
				ui.copied = false;
				render();
			}, 1500 );
		};
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( url ).then( done, function () {} );
		} else {
			var field = h( 'textarea', { class: 'screen-reader-text' } );
			field.value = url;
			document.body.appendChild( field );
			field.select();
			document.execCommand( 'copy' );
			field.remove();
			done();
		}
	}

	function selectedIds() {
		var ids = [];
		groups().forEach( function ( g ) {
			if ( ui.selected[ g.key ] ) {
				ids = ids.concat( g.ids );
			}
		} );
		return ids;
	}

	function onStopTracking() {
		var ids = selectedIds();
		if ( ! ids.length ) {
			return;
		}
		confirmDialog( T.stopTracking, T.stopConfirm, T.stopTracking ).then( function ( ok ) {
			if ( ! ok ) {
				return;
			}
			post( 'wpcbl_rank_bulk_delete', { ids: ids } )
				.then( function ( data ) {
					ui.status = data && data.message ? data.message : '';
					ui.statusWarn = false;
				} )
				.catch( function ( err ) {
					ui.status = err.message;
					ui.statusWarn = true;
				} )
				.then( function () {
					ui.selected = {};
					return load( true );
				} );
		} );
	}

	function onExport() {
		var ids = selectedIds();
		post( 'wpcbl_rank_export', ids.length ? { ids: ids } : {} )
			.then( function ( data ) {
				var blob = new Blob( [ data.csv ], { type: 'text/csv' } );
				var url = URL.createObjectURL( blob );
				var a = h( 'a', { href: url, download: data.filename } );
				document.body.appendChild( a );
				a.click();
				a.remove();
				window.setTimeout( function () {
					URL.revokeObjectURL( url );
				}, 0 );
			} )
			.catch( function ( err ) {
				setStatus( err.message, true );
			} );
	}

	// ---- Dialogs ---------------------------------------------------------

	/** Modal shell with focus trap, Escape and backdrop close. */
	function dialog( labelId, build ) {
		var previous = document.activeElement;
		var overlay = h( 'div', { class: 'cbl-rt-overlay' } );
		var box = h( 'div', { class: 'cbl-rt-dialog', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': labelId } );
		overlay.appendChild( box );

		function close() {
			overlay.remove();
			document.removeEventListener( 'keydown', onKey, true );
			if ( previous && previous.focus ) {
				previous.focus();
			}
		}

		function onKey( e ) {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				close();
				return;
			}
			if ( e.key !== 'Tab' ) {
				return;
			}
			var focusable = box.querySelectorAll( 'button:not([disabled]), a[href], textarea, select, input' );
			if ( ! focusable.length ) {
				return;
			}
			var first = focusable[ 0 ];
			var last = focusable[ focusable.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}

		overlay.addEventListener( 'mousedown', function ( e ) {
			if ( e.target === overlay ) {
				close();
			}
		} );
		document.addEventListener( 'keydown', onKey, true );
		build( box, close );
		document.body.appendChild( overlay );
		var autofocus = box.querySelector( '[data-autofocus]' ) || box.querySelector( 'button, textarea, input' );
		if ( autofocus ) {
			autofocus.focus();
		}
		return close;
	}

	function confirmDialog( title, message, confirmText ) {
		return new Promise( function ( resolve ) {
			dialog( 'cbl-rt-confirm-title', function ( box, close ) {
				box.classList.add( 'is-small' );
				var finish = function ( value ) {
					close();
					resolve( value );
				};
				box.appendChild( h( 'div', { class: 'cbl-rt-dialog-body' }, [
					h( 'h2', { id: 'cbl-rt-confirm-title', class: 'cbl-rt-dialog-title', text: title } ),
					h( 'p', { class: 'cbl-rt-muted', text: message } )
				] ) );
				box.appendChild( h( 'div', { class: 'cbl-rt-dialog-foot' }, [
					h( 'span', { class: 'cbl-rt-grow' } ),
					h( 'button', { type: 'button', class: 'cbl-btn', onclick: function () {
						finish( false );
					} }, [ T.cancel ] ),
					h( 'button', { type: 'button', class: 'cbl-btn cbl-rt-btn-danger-solid', 'data-autofocus': true, onclick: function () {
						finish( true );
					} }, [ confirmText ] )
				] ) );
			} );
		} );
	}

	function openAddModal() {
		if ( ! state ) {
			return;
		}
		var settings = state.settings || {};
		var device = settings.device || 'desktop';
		var engine = settings.engine || 'google';
		var q = state.quota || {};
		var limit = Number( q.positions_limit ) || 0;
		var used = Math.min( Number( q.positions_used ) || 0, limit );
		var available = Math.max( 0, limit - used );

		dialog( 'cbl-rt-add-title', function ( box, close ) {
			var textarea = h( 'textarea', { class: 'cbl-rt-textarea', rows: 5, placeholder: ( CFG.ideas || [] ).slice( 0, 2 ).join( '\n' ) || T.kwPlaceholder, 'aria-describedby': 'cbl-rt-add-count', 'data-autofocus': true } );
			var count = h( 'div', { id: 'cbl-rt-add-count', class: 'cbl-rt-muted cbl-rt-small' } );
			var market = h( 'select', { class: 'cbl-rt-select', id: 'cbl-rt-add-market' } );
			( state.markets || [] ).forEach( function ( m ) {
				market.appendChild( h( 'option', { value: m.key, text: m.label } ) );
			} );
			market.value = ( state.project && state.project.market ) || market.value;

			var deviceWrap = h( 'div', {} );
			var slotTitle = h( 'span', { class: 'cbl-rt-slotbox-title' } );
			var slotMath = h( 'span', { class: 'cbl-rt-muted cbl-rt-small' } );
			var barUsed = h( 'span', { class: 'is-used' } );
			var barNew = h( 'span', { class: 'is-new' } );
			var slotFoot = h( 'span', {} );
			var more = CFG.upgradeUrl ? h( 'a', { href: CFG.upgradeUrl, class: 'cbl-rt-strong-link', text: T.getMoreKeywords, hidden: true } ) : null;
			var slotBox = h( 'div', { class: 'cbl-rt-slotbox' }, [
				h( 'div', { class: 'cbl-rt-slotbox-head' }, [ slotTitle, slotMath ] ),
				h( 'div', { class: 'cbl-rt-slotbar', 'aria-hidden': 'true' }, [ barUsed, barNew ] ),
				h( 'div', { class: 'cbl-rt-slotbox-foot cbl-rt-muted cbl-rt-small' }, [ slotFoot, more ] )
			] );
			var summary = h( 'span', { class: 'cbl-rt-grow cbl-rt-muted', role: 'status', 'aria-live': 'polite' } );
			var start = h( 'button', { type: 'button', class: 'cbl-btn cbl-btn-primary' }, [ T.startTracking ] );

			function unit( n, one, many ) {
				return n + ' ' + ( n === 1 ? one : many );
			}

			function drawDevices() {
				deviceWrap.textContent = '';
				deviceWrap.appendChild( segmented( [
					{ value: 'desktop', label: T.desktop },
					{ value: 'mobile', label: T.mobile },
					{ value: 'both', label: T.deviceBoth }
				], device, function ( value ) {
					device = value;
					drawDevices();
					sync();
					var on = deviceWrap.querySelector( '.is-on' );
					if ( on ) {
						on.focus();
					}
				}, T.device, 'is-full' ) );
			}

			function sync() {
				var n = parseKeywords( textarea.value ).length;
				var ne = engineCount();
				var nd = device === 'both' ? 2 : 1;
				var slots = n * ne * nd;
				var over = slots > available;
				count.textContent = n === 1 ? T.detected1 : n ? fmt( T.detected, n ) : T.dupes;
				slotTitle.textContent = over ? fmt( T.slotsNeeds, slots, available ) : fmt( T.slotsUses, slots, available );
				slotMath.textContent = fmt( T.slotMath, unit( n, T.kwUnit, T.kwUnits ), unit( ne, T.engineUnit, T.engineUnits ), unit( nd, T.deviceUnit, T.deviceUnits ) );
				slotBox.classList.toggle( 'is-over', over );
				barUsed.style.width = limit ? ( used / limit ) * 100 + '%' : '0';
				barNew.style.width = limit ? ( Math.min( slots, available ) / limit ) * 100 + '%' : '0';
				slotFoot.textContent = over ? T.slotsOverFoot : fmt( T.slotsAfter, used, limit, available - slots );
				if ( more ) {
					more.hidden = ! over;
				}
				var where = [ engineLabel( engine ), deviceLabel( device ).toLowerCase() ];
				summary.textContent = n === 1
					? fmt( T.oneOnEngineDevice, where[ 0 ], where[ 1 ] )
					: n ? fmt( T.nOnEngineDevice, n, where[ 0 ], where[ 1 ] ) : fmt( T.onEngineDevice, where[ 0 ], where[ 1 ] );
				start.disabled = ! n || over;
			}

			textarea.addEventListener( 'input', sync );
			start.addEventListener( 'click', function () {
				var kws = parseKeywords( textarea.value );
				if ( ! kws.length ) {
					return;
				}
				start.disabled = true;
				start.textContent = T.adding;
				addKeywords( kws.join( '\n' ), market.value, device ).then( function ( ok ) {
					if ( ok ) {
						close();
					} else {
						start.textContent = T.startTracking;
						summary.textContent = ui.status;
						sync();
					}
				} );
			} );

			box.appendChild( h( 'div', { class: 'cbl-rt-dialog-head' }, [
				h( 'div', {}, [
					h( 'h2', { id: 'cbl-rt-add-title', class: 'cbl-rt-dialog-title', text: T.addTitle } ),
					h( 'p', { class: 'cbl-rt-muted', text: T.addLead } )
				] ),
				h( 'button', { type: 'button', class: 'cbl-rt-x', 'aria-label': T.close, onclick: close }, [ h( 'span', { class: 'dashicons dashicons-no-alt', 'aria-hidden': 'true' } ) ] )
			] ) );
			box.appendChild( h( 'div', { class: 'cbl-rt-dialog-body' }, [
				h( 'div', { class: 'cbl-rt-field' }, [ textarea, count ] ),
				h( 'div', { class: 'cbl-rt-field' }, [ h( 'label', { class: 'cbl-rt-label', for: 'cbl-rt-add-market', text: T.location } ), market ] ),
				h( 'div', { class: 'cbl-rt-field' }, [
					h( 'span', { class: 'cbl-rt-label', text: T.engine } ),
					h( 'div', { class: 'cbl-rt-fixed' }, [
						h( 'strong', { text: engineLabel( engine ) } ),
						h( 'span', { class: 'cbl-rt-muted' }, [ T.engineHint + ' ', h( 'a', { href: CFG.settingsUrl, text: T.change } ) ] )
					] )
				] ),
				h( 'div', { class: 'cbl-rt-field' }, [ h( 'span', { class: 'cbl-rt-label', text: T.device } ), deviceWrap ] )
			] ) );
			box.appendChild( slotBox );
			box.appendChild( h( 'div', { class: 'cbl-rt-dialog-foot' }, [
				summary,
				h( 'button', { type: 'button', class: 'cbl-btn', onclick: close }, [ T.cancel ] ),
				start
			] ) );

			drawDevices();
			sync();
		} );
	}

	// ---- View: settings --------------------------------------------------

	var saveTimer = null;
	var savedTimer = null;

	function savedState( which ) {
		if ( ! savedHint ) {
			return;
		}
		savedHint.textContent = savedHint.getAttribute( 'data-' + which ) || '';
		savedHint.classList.toggle( 'is-saved', which === 'saved' );
		savedHint.classList.toggle( 'is-error', which === 'error' );
	}

	function renderSettings() {
		var s = state.settings || {};
		var form = {
			engine: s.engine || 'google',
			device: s.device || 'desktop',
			emailOn: !! s.email_enabled,
			emails: String( s.email_recipients || '' ).split( /[\s,;]+/ ).filter( Boolean )
		};

		function save() {
			window.clearTimeout( saveTimer );
			saveTimer = window.setTimeout( function () {
				savedState( 'saving' );
				post( 'wpcbl_rank_settings', {
					engine: form.engine,
					device: form.device,
					email_enabled: form.emailOn ? 1 : 0,
					email_recipients: form.emails.join( ', ' )
				} ).then( function () {
					savedState( 'saved' );
					state.settings = Object.assign( {}, state.settings, {
						engine: form.engine,
						device: form.device,
						email_enabled: form.emailOn,
						email_recipients: form.emails.join( ', ' )
					} );
					window.clearTimeout( savedTimer );
					savedTimer = window.setTimeout( function () {
						savedState( 'idle' );
					}, 1800 );
				} ).catch( function ( err ) {
					savedState( 'idle' );
					setStatus( err.message, true );
				} );
			}, 350 );
		}

		// Tracking: engine cards and default device.
		var engineCards = h( 'div', { class: 'cbl-rt-choice-grid', role: 'radiogroup', 'aria-label': T.engine } );
		function drawEngines() {
			engineCards.textContent = '';
			[
				{ value: 'google', label: T.google, hint: T.googleHint },
				{ value: 'bing', label: T.bing, hint: T.bingHint },
				{ value: 'both', label: T.both, hint: T.bothHint }
			].forEach( function ( o ) {
				var on = form.engine === o.value;
				engineCards.appendChild( h( 'button', {
					type: 'button',
					role: 'radio',
					'aria-checked': on ? 'true' : 'false',
					class: 'cbl-rt-choice' + ( on ? ' is-on' : '' ),
					onclick: function () {
						if ( form.engine === o.value ) {
							return;
						}
						form.engine = o.value;
						drawEngines();
						engineCards.querySelector( '.is-on' ).focus();
						save();
					}
				}, [
					h( 'span', { class: 'cbl-rt-radio', 'aria-hidden': 'true' } ),
					h( 'span', {}, [ h( 'span', { class: 'cbl-rt-choice-label', text: o.label } ), h( 'span', { class: 'cbl-rt-choice-hint', text: o.hint } ) ] )
				] ) );
			} );
		}
		drawEngines();

		var deviceWrap = h( 'div', {} );
		function drawDevice() {
			deviceWrap.textContent = '';
			deviceWrap.appendChild( segmented( [
				{ value: 'desktop', label: T.desktop },
				{ value: 'mobile', label: T.mobile },
				{ value: 'both', label: T.deviceBoth }
			], form.device, function ( value ) {
				form.device = value;
				drawDevice();
				deviceWrap.querySelector( '.is-on' ).focus();
				save();
			}, T.defaultDevice ) );
		}
		drawDevice();

		// Email reports: switch + address chips.
		var emailSwitch = h( 'button', { type: 'button', role: 'switch', class: 'cbl-rt-switch', 'aria-label': T.emailReports } );
		var chipBox = h( 'div', { class: 'cbl-rt-chipbox' } );
		var draft = h( 'input', { type: 'email', class: 'cbl-rt-chip-input', id: 'cbl-rt-email-draft', autocomplete: 'email' } );
		var emailHint = h( 'span', { class: 'cbl-rt-muted cbl-rt-small', id: 'cbl-rt-email-hint', text: T.emailHint } );
		draft.setAttribute( 'aria-describedby', 'cbl-rt-email-hint' );
		var emailPanel = h( 'div', { class: 'cbl-rt-email-panel' }, [
			h( 'label', { class: 'cbl-rt-label', for: 'cbl-rt-email-draft', text: T.sendTo } ),
			chipBox,
			emailHint
		] );

		function drawEmail() {
			emailSwitch.setAttribute( 'aria-checked', form.emailOn ? 'true' : 'false' );
			emailPanel.hidden = ! form.emailOn;
			chipBox.textContent = '';
			form.emails.forEach( function ( address ) {
				chipBox.appendChild( h( 'span', { class: 'cbl-rt-chip' }, [
					address,
					h( 'button', { type: 'button', 'aria-label': fmt( T.emailRemove, address ), onclick: function () {
						form.emails = form.emails.filter( function ( e ) {
							return e !== address;
						} );
						drawEmail();
						draft.focus();
						save();
					} }, [ h( 'span', { class: 'dashicons dashicons-no-alt', 'aria-hidden': 'true' } ) ] )
				] ) );
			} );
			draft.placeholder = form.emails.length ? T.emailAdd : T.emailFirst;
			chipBox.appendChild( draft );
		}

		function commitDraft() {
			var parts = draft.value.split( /[,\s;]+/ ).filter( Boolean );
			if ( ! parts.length ) {
				return;
			}
			var valid = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
			var bad = parts.filter( function ( p ) {
				return ! valid.test( p );
			} );
			var added = false;
			parts.forEach( function ( p ) {
				if ( valid.test( p ) && form.emails.indexOf( p ) === -1 ) {
					form.emails.push( p );
					added = true;
				}
			} );
			draft.value = bad.join( ', ' );
			emailHint.textContent = bad.length ? T.emailBad : T.emailHint;
			emailHint.classList.toggle( 'is-error', !! bad.length );
			drawEmail();
			draft.focus();
			if ( added ) {
				save();
			}
		}

		emailSwitch.addEventListener( 'click', function () {
			form.emailOn = ! form.emailOn;
			drawEmail();
			save();
		} );
		draft.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ',' ) {
				e.preventDefault();
				commitDraft();
			} else if ( e.key === 'Backspace' && ! draft.value && form.emails.length ) {
				form.emails.pop();
				drawEmail();
				draft.focus();
				save();
			}
		} );
		draft.addEventListener( 'input', function () {
			emailHint.textContent = T.emailHint;
			emailHint.classList.remove( 'is-error' );
		} );
		draft.addEventListener( 'blur', function () {
			if ( draft.value.trim() ) {
				commitDraft();
			}
		} );
		chipBox.addEventListener( 'mousedown', function ( e ) {
			if ( e.target === chipBox ) {
				e.preventDefault();
				draft.focus();
			}
		} );
		drawEmail();

		// Public report password: optional, needs a live public link.
		var pw = h( 'input', { type: 'password', class: 'cbl-rt-input', id: 'cbl-rt-pw', autocomplete: 'new-password', placeholder: T.pwPlaceholder, minlength: 4, maxlength: 72, disabled: ! s.share_url } );
		var pwBtn = h( 'button', { type: 'button', class: 'cbl-btn', disabled: ! s.share_url }, [ T.pwSave ] );
		var pwNote = h( 'span', { class: 'cbl-rt-muted cbl-rt-small', role: 'status', text: ! s.share_url ? T.pwNeedsLink : s.share_has_password ? T.pwIsSet : '' } );
		pwBtn.addEventListener( 'click', function () {
			if ( pw.value.length < 4 ) {
				pw.focus();
				return;
			}
			pwBtn.disabled = true;
			post( 'wpcbl_rank_share', { sharing: 'on', password: pw.value } )
				.then( function () {
					pw.value = '';
					pwNote.textContent = T.pwSaved;
					pwNote.classList.remove( 'is-error' );
				} )
				.catch( function ( err ) {
					pwNote.textContent = err.message;
					pwNote.classList.add( 'is-error' );
				} )
				.then( function () {
					pwBtn.disabled = false;
				} );
		} );

		content.appendChild( h( 'div', { class: 'cbl-rt-settings' }, [
			statusLine(),
			h( 'section', { class: 'cbl-rt-card' }, [
				h( 'h2', { class: 'cbl-rt-card-title', text: T.tracking } ),
				h( 'div', { class: 'cbl-rt-set-row' }, [
					h( 'div', {}, [ h( 'div', { class: 'cbl-rt-set-name', text: T.engine } ), h( 'div', { class: 'cbl-rt-muted', text: T.engineLead } ) ] ),
					engineCards
				] ),
				h( 'div', { class: 'cbl-rt-set-row' }, [
					h( 'div', {}, [ h( 'div', { class: 'cbl-rt-set-name', text: T.defaultDevice } ), h( 'div', { class: 'cbl-rt-muted', text: T.deviceLead } ) ] ),
					deviceWrap
				] )
			] ),
			h( 'section', { class: 'cbl-rt-card' }, [
				h( 'div', { class: 'cbl-rt-set-head' }, [
					h( 'div', { class: 'cbl-rt-grow' }, [ h( 'h2', { class: 'cbl-rt-card-title is-inline', text: T.emailReports } ), h( 'div', { class: 'cbl-rt-muted', text: T.emailLead } ) ] ),
					emailSwitch
				] ),
				emailPanel
			] ),
			h( 'section', { class: 'cbl-rt-card' }, [
				h( 'div', { class: 'cbl-rt-set-head is-stack' }, [
					h( 'h2', { class: 'cbl-rt-card-title is-inline' }, [ h( 'label', { for: 'cbl-rt-pw', text: T.pwTitle } ) ] ),
					h( 'div', { class: 'cbl-rt-muted', text: T.pwLead } ),
					h( 'div', { class: 'cbl-rt-pw-row' }, [ pw, pwBtn ] ),
					pwNote
				] )
			] )
		] ) );
	}

	// ---- Render ----------------------------------------------------------

	function render() {
		skeleton.hidden = true;
		errorBox.hidden = true;

		// Keep focus on the control that caused a re-render when it survives.
		var active = document.activeElement;
		var focusKey = active && content.contains( active ) ? active.getAttribute( 'aria-label' ) || active.textContent : null;

		content.textContent = '';
		var hasKeywords = !! ( state.keywords && state.keywords.length );

		if ( marketMeta ) {
			marketMeta.textContent = marketLabel( state.project && state.project.market );
		}
		if ( addBtn ) {
			addBtn.hidden = VIEW !== 'tracker' || ! hasKeywords;
		}
		if ( exportBtn ) {
			exportBtn.hidden = VIEW !== 'tracker' || ! hasKeywords;
		}

		if ( VIEW === 'settings' ) {
			renderSettings();
		} else if ( ! hasKeywords ) {
			renderFirstRun();
		} else {
			renderResults();
		}

		if ( focusKey ) {
			var match = Array.prototype.filter.call( content.querySelectorAll( 'button, input' ), function ( el ) {
				return ( el.getAttribute( 'aria-label' ) || el.textContent ) === focusKey;
			} )[ 0 ];
			if ( match && match.className.indexOf( 'cbl-rt-filter' ) === -1 ) {
				match.focus();
			}
		}
	}

	if ( addBtn ) {
		addBtn.addEventListener( 'click', openAddModal );
	}
	if ( exportBtn ) {
		exportBtn.addEventListener( 'click', onExport );
	}
	document.getElementById( 'wpcbl-rank-retry' ).addEventListener( 'click', function () {
		errorBox.hidden = true;
		skeleton.hidden = false;
		load( false );
	} );

	load( false );
}() );
