/**
 * Premium Guest Posts page (3.1.7).
 *
 * Picks a package, collects the target pages, and hands the order to
 * admin-ajax, which returns the Stripe Checkout address. A site that is not
 * connected yet keeps the draft in localStorage, connects, and continues to
 * checkout when it comes back. Prices are display only: the package key is
 * all that is sent, and brokenlinkchecker.io decides the amount.
 */
( function () {
	'use strict';

	var cfg  = window.wpcblGp;
	var form = document.getElementById( 'wpcbl-gp' );

	if ( ! cfg || ! form || ! cfg.packages || ! cfg.packages.length ) {
		return;
	}

	var DRAFT_KEY = 'wpcblGpDraft';
	var DRAFT_TTL = 60 * 60 * 1000;
	var t         = cfg.i18n;

	var el = {
		flash: document.getElementById( 'wpcbl-gp-flash' ),
		targets: document.getElementById( 'wpcbl-gp-targets' ),
		add: document.getElementById( 'wpcbl-gp-add' ),
		spread: document.getElementById( 'wpcbl-gp-spread' ),
		notes: document.getElementById( 'wpcbl-gp-notes' ),
		picked: document.getElementById( 'wpcbl-gp-picked' ),
		sumPackage: document.getElementById( 'wpcbl-gp-sum-package' ),
		sumPosts: document.getElementById( 'wpcbl-gp-sum-posts' ),
		sumTargets: document.getElementById( 'wpcbl-gp-sum-targets' ),
		sumDelivery: document.getElementById( 'wpcbl-gp-sum-delivery' ),
		sumTotal: document.getElementById( 'wpcbl-gp-sum-total' ),
		submit: document.getElementById( 'wpcbl-gp-submit' ),
		hint: document.getElementById( 'wpcbl-gp-hint' ),
		orders: document.getElementById( 'wpcbl-gp-orders' ),
		orderList: document.getElementById( 'wpcbl-gp-order-list' )
	};

	var state = {
		picked: cfg.defaultKey,
		targets: [ { url: '', keyword: '' } ],
		busy: false
	};

	function fmt( template ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return String( template ).replace( /%(\d)\$s|%s/g, function ( match, index ) {
			return index ? args[ index - 1 ] : args.shift();
		} );
	}

	function money( amount ) {
		return '$' + Number( amount ).toLocaleString( 'en-US' );
	}

	function pkg() {
		for ( var i = 0; i < cfg.packages.length; i++ ) {
			if ( cfg.packages[ i ].key === state.picked ) {
				return cfg.packages[ i ];
			}
		}
		return cfg.packages[ 0 ];
	}

	function node( tag, className, text ) {
		var n = document.createElement( tag );
		if ( className ) {
			n.className = className;
		}
		if ( undefined !== text ) {
			n.textContent = text;
		}
		return n;
	}

	function flash( message, kind ) {
		el.flash.textContent = message;
		el.flash.className   = 'cbl-gp-flash' + ( kind ? ' is-' + kind : '' );
		el.flash.hidden      = ! message;
	}

	/* ---- Draft: survives the trip to connect and to Stripe ---- */

	function saveDraft() {
		try {
			window.localStorage.setItem( DRAFT_KEY, JSON.stringify( {
				at: Date.now(),
				picked: state.picked,
				targets: state.targets,
				notes: el.notes.value
			} ) );
		} catch ( e ) {} // Storage blocked: the order still works, the draft just does not survive.
	}

	function clearDraft() {
		try {
			window.localStorage.removeItem( DRAFT_KEY );
		} catch ( e ) {}
	}

	function loadDraft() {
		var draft;
		try {
			draft = JSON.parse( window.localStorage.getItem( DRAFT_KEY ) || 'null' );
		} catch ( e ) {
			draft = null;
		}

		if ( ! draft || ! draft.at || Date.now() - draft.at > DRAFT_TTL || ! Array.isArray( draft.targets ) ) {
			return false;
		}

		var known = cfg.packages.some( function ( p ) {
			return p.key === draft.picked;
		} );
		if ( known ) {
			state.picked = draft.picked;
		}

		var targets = draft.targets.filter( function ( target ) {
			return target && 'string' === typeof target.url;
		} ).map( function ( target ) {
			return { url: target.url, keyword: 'string' === typeof target.keyword ? target.keyword : '' };
		} ).slice( 0, pkg().posts );

		if ( targets.length ) {
			state.targets = targets;
		}
		el.notes.value = 'string' === typeof draft.notes ? draft.notes : '';

		return targets.length > 0;
	}

	/* ---- Rendering ---- */

	function renderCards() {
		var cards = form.querySelectorAll( '.cbl-gp-card' );
		Array.prototype.forEach.call( cards, function ( card ) {
			var input  = card.querySelector( 'input[type="radio"]' );
			var picked = input.value === state.picked;
			input.checked = picked;
			card.classList.toggle( 'is-picked', picked );
		} );
	}

	function renderTargets() {
		el.targets.textContent = '';

		state.targets.forEach( function ( target, i ) {
			var row = node( 'div', 'cbl-gp-target' );
			row.appendChild( node( 'span', 'cbl-gp-num', String( i + 1 ) ) );

			var urlField = node( 'label', 'cbl-gp-field' );
			if ( 0 === i ) {
				urlField.appendChild( node( 'span', 'cbl-gp-label', t.targetUrl ) );
			}
			var url = node( 'input' );
			url.type        = 'url';
			url.required    = true;
			url.maxLength   = 2048;
			url.placeholder = t.urlPlaceholder;
			url.value       = target.url;
			url.setAttribute( 'aria-label', fmt( t.targetUrlN, i + 1 ) );
			url.addEventListener( 'input', function () {
				target.url = url.value;
				renderSummary();
			} );
			urlField.appendChild( url );
			row.appendChild( urlField );

			var kwField = node( 'label', 'cbl-gp-field' );
			if ( 0 === i ) {
				kwField.appendChild( node( 'span', 'cbl-gp-label', t.targetKeyword ) );
			}
			var kw = node( 'input' );
			kw.type        = 'text';
			kw.maxLength   = 120;
			kw.placeholder = t.keywordPlaceholder;
			kw.value       = target.keyword;
			kw.setAttribute( 'aria-label', fmt( t.targetKeywordN, i + 1 ) );
			kw.addEventListener( 'input', function () {
				target.keyword = kw.value;
			} );
			kwField.appendChild( kw );
			row.appendChild( kwField );

			if ( state.targets.length > 1 ) {
				var remove = node( 'button', 'cbl-gp-remove', '×' );
				remove.type = 'button';
				remove.setAttribute( 'aria-label', fmt( t.removeTargetN, i + 1 ) );
				remove.addEventListener( 'click', function () {
					state.targets.splice( i, 1 );
					renderTargets();
					renderSummary();
				} );
				row.appendChild( remove );
			} else {
				row.appendChild( node( 'span' ) );
			}

			el.targets.appendChild( row );
		} );

		el.add.hidden    = state.targets.length >= pkg().posts;
		el.spread.hidden = state.targets.length < 2;
	}

	function filled() {
		return state.targets.filter( function ( target ) {
			return '' !== target.url.trim();
		} ).length;
	}

	function ready() {
		return state.targets.length > 0 && filled() === state.targets.length;
	}

	function renderSummary() {
		var p   = pkg();
		var due = new Date();
		due.setDate( due.getDate() + p.delivery_days );

		el.picked.textContent      = fmt( t.pickedMeta, p.name, p.posts );
		el.sumPackage.textContent  = p.name;
		el.sumPosts.textContent    = fmt( t.postsWords, p.posts );
		el.sumTargets.textContent  = String( filled() || 1 );
		el.sumDelivery.textContent = fmt( t.deliveryBy, p.delivery_days, due.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } ) );
		el.sumTotal.textContent    = money( p.price );

		if ( state.busy ) {
			el.submit.disabled    = true;
			el.submit.textContent = t.opening;
			return;
		}

		el.submit.disabled    = ! ready();
		el.submit.textContent = fmt( cfg.connected ? t.placeOrder : t.connectAndOrder, money( p.price ) );

		if ( ! ready() ) {
			el.hint.textContent = t.hintEmpty;
		} else {
			el.hint.textContent = cfg.connected ? t.hintReady : t.hintConnect;
		}
	}

	function pick( key ) {
		state.picked = key;
		// A smaller package cannot carry more targets than posts.
		if ( state.targets.length > pkg().posts ) {
			state.targets = state.targets.slice( 0, pkg().posts );
		}
		renderCards();
		renderTargets();
		renderSummary();
	}

	/* ---- Server ---- */

	function post( action, fields ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( fields || {} ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
		} );

		return window.fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json().catch( function () {
				return { success: false, data: t.generic };
			} );
		}, function () {
			return { success: false, data: t.generic };
		} );
	}

	function firstInvalid() {
		var inputs = el.targets.querySelectorAll( 'input[type="url"]' );
		for ( var i = 0; i < inputs.length; i++ ) {
			if ( ! inputs[ i ].checkValidity() || ! /^https?:\/\//i.test( inputs[ i ].value.trim() ) ) {
				return inputs[ i ];
			}
		}
		return null;
	}

	function checkout() {
		state.busy = true;
		renderSummary();
		// Kept until payment is confirmed, so a cancelled checkout comes
		// back to a filled form.
		saveDraft();

		post( 'wpcbl_gp_checkout', {
			order: JSON.stringify( {
				'package': state.picked,
				targets: state.targets,
				notes: el.notes.value
			} )
		} ).then( function ( result ) {
			if ( result && result.success && result.data && result.data.url ) {
				window.location.href = result.data.url;
				return;
			}

			state.busy = false;
			renderSummary();
			flash( result && 'string' === typeof result.data && result.data ? result.data : t.generic, 'error' );
			el.flash.scrollIntoView( { block: 'center' } );
		} );
	}

	function submit() {
		if ( state.busy || ! ready() ) {
			return;
		}

		var invalid = firstInvalid();
		if ( invalid ) {
			invalid.setCustomValidity( /^https?:\/\//i.test( invalid.value.trim() ) ? '' : t.urlInvalid );
			invalid.reportValidity();
			invalid.addEventListener( 'input', function clear() {
				invalid.setCustomValidity( '' );
				invalid.removeEventListener( 'input', clear );
			} );
			return;
		}

		flash( '' );

		if ( ! cfg.connected ) {
			saveDraft();
			state.busy = true;
			renderSummary();
			window.location.href = cfg.connectUrl;
			return;
		}

		checkout();
	}

	/* ---- Orders ---- */

	function day( iso, withYear ) {
		var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec( iso || '' );
		if ( ! parts ) {
			return '';
		}
		var options = { month: 'short', day: 'numeric' };
		if ( withYear ) {
			options.year = 'numeric';
		}
		return new Date( +parts[ 1 ], +parts[ 2 ] - 1, +parts[ 3 ] ).toLocaleDateString( undefined, options );
	}

	function renderOrders( orders ) {
		if ( ! el.orders || ! Array.isArray( orders ) || ! orders.length ) {
			return;
		}

		el.orderList.textContent = '';

		orders.forEach( function ( order ) {
			var row = node( 'div', 'cbl-gp-order' );

			var when = node( 'div', 'cbl-gp-order-cell' );
			when.appendChild( node( 'strong', '', day( order.paid_at, true ) ) );
			if ( order.due_at && 'delivered' !== order.status && 'refunded' !== order.status ) {
				when.appendChild( node( 'span', 'cbl-gp-note', fmt( t.due, day( order.due_at ) ) ) );
			}
			row.appendChild( when );

			var what = node( 'div', 'cbl-gp-order-cell' );
			what.appendChild( node( 'strong', '', order[ 'package' ] ) );
			what.appendChild( node( 'span', 'cbl-gp-note', fmt( t.postsCount, order.posts ) ) );
			row.appendChild( what );

			var targets = node( 'div', 'cbl-gp-order-cell cbl-gp-order-targets' );
			( order.targets || [] ).forEach( function ( target ) {
				targets.appendChild( node( 'span', 'cbl-gp-order-url', target.url ) );
				if ( /^https?:\/\//i.test( target.live_url || '' ) ) {
					var live = node( 'a', 'cbl-gp-order-live', fmt( t.liveAt, target.live_url ) );
					live.href   = target.live_url;
					live.target = '_blank';
					live.rel    = 'noopener noreferrer';
					targets.appendChild( live );
				}
			} );
			row.appendChild( targets );

			var done   = 'delivered' === order.status;
			var status = node( 'span', 'cbl-pill cbl-gp-order-status ' + ( done ? 'cbl-pill-success' : 'cbl-pill-neutral' ), t.status[ order.status ] || order.status );
			row.appendChild( status );

			row.appendChild( node( 'span', 'cbl-gp-order-amount', order.amount ) );

			el.orderList.appendChild( row );
		} );

		el.orders.hidden = false;
	}

	function loadOrders( session ) {
		if ( ! cfg.connected ) {
			return;
		}

		post( 'wpcbl_gp_orders', session ? { session: session } : {} ).then( function ( result ) {
			if ( ! result || ! result.success || ! result.data ) {
				// The order list is a convenience. A payment that just
				// happened must still not read as a failure.
				if ( session ) {
					flash( t.paidPending, 'info' );
				}
				return;
			}

			if ( session ) {
				flash( result.data.ok ? t.paid : t.paidPending, result.data.ok ? '' : 'info' );
			}
			renderOrders( result.data.orders );
		} );
	}

	/* ---- Wiring ---- */

	form.addEventListener( 'change', function ( event ) {
		// change, not click: arrow keys in a radio group fire change only,
		// and the checked card must always be the package that is ordered.
		if ( event.target && 'wpcbl_gp_package' === event.target.name ) {
			pick( event.target.value );
		}
	} );

	el.add.addEventListener( 'click', function () {
		if ( state.targets.length < pkg().posts ) {
			state.targets.push( { url: '', keyword: '' } );
			renderTargets();
			renderSummary();
			var inputs = el.targets.querySelectorAll( 'input[type="url"]' );
			inputs[ inputs.length - 1 ].focus();
		}
	} );

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
		submit();
	} );

	// Drop the one-time return markers, so a reload does not confirm the
	// payment again or restart a checkout.
	if ( cfg.flash && window.history && window.history.replaceState ) {
		window.history.replaceState( null, '', cfg.pageUrl );
	}

	var resumed = false;

	if ( 'paid' === cfg.flash ) {
		clearDraft();
	} else {
		resumed = loadDraft();
		// A draft is only for coming back from connect or from Stripe.
		// Opening the page on its own later starts clean.
		if ( ! cfg.flash ) {
			clearDraft();
			if ( resumed ) {
				state.picked   = cfg.defaultKey;
				state.targets  = [ { url: '', keyword: '' } ];
				el.notes.value = '';
				resumed        = false;
			}
		}
	}

	renderCards();
	renderTargets();
	renderSummary();

	if ( 'paid' === cfg.flash ) {
		loadOrders( cfg.session );
	} else {
		loadOrders( '' );

		if ( 'cancelled' === cfg.flash ) {
			flash( t.cancelled, 'info' );
		} else if ( 'connected' === cfg.flash && cfg.connected ) {
			if ( resumed && ready() && ! firstInvalid() ) {
				flash( t.connectedOpening, 'info' );
				checkout();
			} else {
				flash( t.connected, '' );
			}
		}
	}
}() );
