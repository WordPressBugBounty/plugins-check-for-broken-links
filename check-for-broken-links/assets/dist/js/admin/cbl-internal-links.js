/**
 * Internal Link Optimizer admin page.
 *
 * Plain ES5, no build step, no jQuery dependency: this file is its own
 * self-contained page script, loaded only on the Internal Links screen.
 * Every value that reaches the DOM comes from brokenlinkchecker.io over
 * admin-ajax, so it is written with textContent / createElement only.
 * Never innerHTML with data from the API.
 *
 * @package WPCBL_Check_Broken_Links
 * @since 3.0.8
 */
( function () {
	'use strict';

	var CFG = window.wpcblIlo || {};

	// A run poll checks every six seconds while the SaaS is still working,
	// and gives up after ten minutes rather than polling forever if
	// something on the other end got stuck.
	var POLL_INTERVAL_MS = 6000;
	var POLL_MAX_MS = 10 * 60 * 1000;

	/**
	 * One admin-ajax POST. Resolves with the data payload, rejects with a
	 * message. A rejection carries `.transport = true` when the request
	 * itself failed (network error, or a response that was not the JSON
	 * the server was supposed to send) -- as opposed to the server
	 * answering with a deliberate refusal, which is not a transport
	 * problem and should not stop a batch of independent actions.
	 */
	function post( action, fields ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', CFG.nonce );

		Object.keys( fields || {} ).forEach( function ( key ) {
			var value = fields[ key ];
			if ( value === null || value === undefined ) {
				return;
			}
			if ( Array.isArray( value ) ) {
				// PHP only reads this back as an array off a `key[]`
				// field name -- admin-ajax parses the POST body the same
				// way a normal form submit would.
				value.forEach( function ( item ) {
					body.append( key + '[]', item );
				} );
				return;
			}
			body.append( key, value );
		} );

		function transportError() {
			var error = new Error( CFG.i18n.generic );
			error.transport = true;
			return error;
		}

		return fetch( CFG.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json().catch( function () {
				throw transportError();
			} );
		}, function () {
			throw transportError();
		} ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				throw new Error( ( json && json.data ) ? json.data : CFG.i18n.generic );
			}
			return json.data;
		} );
	}

	/** Text into an element. Never innerHTML with API data. */
	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined && text !== null ) {
			node.textContent = String( text );
		}
		return node;
	}

	/**
	 * Walk the site in batches. Each response tells us where to go next.
	 * `stored` carries the browser's own running total back to the server
	 * on every call, because the server echoes it straight through on a
	 * batch that added nothing new (a dedup or empty-permalink skip) --
	 * without it the progress number would reset to zero mid-walk. The
	 * short batch that ends the walk also starts the run, so an upload is
	 * never left half finished and unclaimed. Completion is read only
	 * from `done`, never guessed from a page count.
	 *
	 * `cap` is the account's page cap, read from the state payload's
	 * page_cap. The server needs it on every call to know how much room
	 * is left: without it a site with more pages than its plan reads got
	 * its next chunk refused mid-walk, which ended the walk before the
	 * run was ever started.
	 */
	function runAnalysis( cap, onProgress ) {
		function step( offset, upload, stored ) {
			return post( 'wpcbl_ilo_run', { offset: offset, upload: upload || '', stored: stored || 0, cap: cap || 0 } )
				.then( function ( data ) {
					var total = data.pages_stored || 0;
					onProgress( total );

					if ( data.done ) {
						return data;
					}

					return step( data.next_offset, data.upload, total );
				} );
		}

		return step( 0, '', 0 );
	}

	/** Apply one suggestion. The card passes its own placement through. */
	function applySuggestion( item ) {
		var placement = item.placement || {};

		return post( 'wpcbl_ilo_apply', {
			suggestion_id: item.id,
			post_id: item.source_post_id,
			// The post id alone is not proof: two installs on one domain
			// share a project, so the server checks this URL against the
			// post's own permalink before it edits anything.
			source_url: item.source_url,
			target_url: item.target_url,
			anchor_text: item.anchor_text,
			method: placement.method || 'wrap_existing',
			existing_text: placement.existing_text || '',
			insert_after: placement.insert_after || '',
			insert_sentence: placement.insert_sentence || ''
		} );
	}

	/**
	 * Apply every given suggestion in sequence, not in parallel, and stop
	 * on the first transport failure rather than firing the rest. A
	 * refused suggestion (the server answering "no") is a per-row outcome
	 * and the batch carries on past it.
	 */
	function applyAll( items, onRow ) {
		return items.reduce( function ( chain, item ) {
			return chain.then( function () {
				return applySuggestion( item )
					.then( function ( data ) {
						onRow( item, true, data && data.message );
					} )
					.catch( function ( error ) {
						onRow( item, false, error.message );
						if ( error.transport ) {
							throw error;
						}
					} );
			} );
		}, Promise.resolve() );
	}

	window.wpcblIloApi = { post: post, el: el, runAnalysis: runAnalysis, applySuggestion: applySuggestion, applyAll: applyAll };

	// ------------------------------------------------------------------
	// Page wiring. Only runs when the connected-site app shell is on the
	// page -- the not-connected card has none of these elements.
	// ------------------------------------------------------------------

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( init );

	function init() {
		var app = document.getElementById( 'wpcbl-ilo-app' );
		if ( ! app ) {
			return;
		}

		var skeleton = document.getElementById( 'wpcbl-ilo-skeleton' );
		var errorBox = document.getElementById( 'wpcbl-ilo-error' );
		var errorText = errorBox ? errorBox.querySelector( 'p' ) : null;
		var retryBtn = document.getElementById( 'wpcbl-ilo-retry' );
		var content = document.getElementById( 'wpcbl-ilo-content' );
		var runBtn = document.getElementById( 'wpcbl-ilo-run' );
		var quotaEl = document.getElementById( 'wpcbl-ilo-quota' );
		var postTypesEl = document.getElementById( 'wpcbl-ilo-posttypes' );

		var FINDING_TABS = [
			{ key: 'orphan', resultsKey: 'orphan_pages', label: 'tabOrphan', empty: 'emptyOrphan' },
			{ key: 'dead', resultsKey: 'dead_end_pages', label: 'tabDead', empty: 'emptyDead' },
			{ key: 'buried', resultsKey: 'buried_pages', label: 'tabBuried', empty: 'emptyBuried' },
			{ key: 'weak', resultsKey: 'weak_anchors', label: 'tabWeak', empty: 'emptyWeak' }
		];

		var state = null;
		var activeTab = 'suggested';
		var runNotice = '';
		var pollTimer = null;
		var pollStartedAt = 0;

		/** %1$s / %2$s placeholder substitution for the localized strings. */
		function fmt( str, a, b ) {
			var out = String( str || '' );
			if ( undefined !== a ) {
				out = out.replace( '%1$s', a );
			}
			if ( undefined !== b ) {
				out = out.replace( '%2$s', b );
			}
			return out;
		}

		/** The path of a crawled URL, for compact display. Falls back to
		 * the raw string when it is not a parseable absolute URL. */
		function pathOf( url ) {
			try {
				return new URL( url ).pathname || '/';
			} catch ( e ) {
				return url;
			}
		}

		function dashboardUrl() {
			var projectId = state && state.project ? state.project.id : '';
			return 'https://brokenlinkchecker.io/projects/' + encodeURIComponent( projectId ) + '/tools/internal-links';
		}

		function stopPolling() {
			if ( pollTimer ) {
				clearTimeout( pollTimer );
				pollTimer = null;
			}
		}

		function schedulePoll() {
			stopPolling();
			if ( Date.now() - pollStartedAt > POLL_MAX_MS ) {
				return;
			}
			pollTimer = setTimeout( function () {
				loadState( false );
			}, POLL_INTERVAL_MS );
		}

		function loadState( showSkeleton ) {
			if ( showSkeleton && skeleton ) {
				skeleton.style.display = '';
				if ( errorBox ) {
					errorBox.style.display = 'none';
				}
				if ( content ) {
					content.style.display = 'none';
				}
			}

			post( 'wpcbl_ilo_state', {} ).then( function ( data ) {
				state = data;

				if ( skeleton ) {
					skeleton.style.display = 'none';
				}
				if ( errorBox ) {
					errorBox.style.display = 'none';
				}
				if ( content ) {
					content.style.display = '';
				}

				var status = state.latest ? state.latest.status : '';

				if ( 'pending' === status || 'running' === status ) {
					if ( ! pollStartedAt ) {
						pollStartedAt = Date.now();
					}
					schedulePoll();
				} else {
					stopPolling();
					pollStartedAt = 0;
				}

				syncRunButton( status );
				render();
			} ).catch( function ( error ) {
				stopPolling();
				if ( skeleton ) {
					skeleton.style.display = 'none';
				}
				if ( content ) {
					content.style.display = 'none';
				}
				if ( errorText ) {
					errorText.textContent = error.message || CFG.i18n.loadError;
				}
				if ( errorBox ) {
					errorBox.style.display = '';
				}
			} );
		}

		function syncRunButton( status ) {
			if ( ! runBtn ) {
				return;
			}

			var busy = 'pending' === status || 'running' === status;
			var quota = state && state.quota;
			var spent = quota && null !== quota.limit && quota.used >= quota.limit;

			runBtn.disabled = busy || !! spent;
			runBtn.textContent = busy ? CFG.i18n.runningLabel : CFG.i18n.runLabel;
		}

		function renderQuota() {
			if ( ! quotaEl ) {
				return;
			}

			quotaEl.textContent = '';

			if ( ! state || ! state.quota ) {
				return;
			}

			var quota = state.quota;
			var text = null === quota.limit
				? fmt( CFG.i18n.quotaUnlimited, quota.used )
				: fmt( CFG.i18n.quotaUsed, quota.used, quota.limit );

			quotaEl.appendChild( document.createTextNode( text ) );

			var spent = null !== quota.limit && quota.used >= quota.limit;
			if ( spent && 'free' === state.plan && CFG.upgradeUrl ) {
				quotaEl.appendChild( document.createTextNode( ' ' ) );
				var link = document.createElement( 'a' );
				link.href = CFG.upgradeUrl;
				link.className = 'cbl-btn cbl-btn-sm';
				link.textContent = CFG.i18n.upgrade;
				quotaEl.appendChild( link );
			}
		}

		function runningCard() {
			var card = el( 'div', 'cbl-card cbl-audit-running' );
			var head = el( 'div', 'cbl-audit-running-head' );
			head.appendChild( el( 'span', 'cbl-audit-running-dot' ) );

			var text = el( 'div', 'cbl-audit-running-text' );
			text.appendChild( el( 'b', null, CFG.i18n.runningTitle ) );
			text.appendChild( el( 'span', null, CFG.i18n.runningHint ) );
			head.appendChild( text );
			card.appendChild( head );

			var bar = el( 'div', 'cbl-audit-running-bar' );
			bar.setAttribute( 'role', 'progressbar' );
			bar.setAttribute( 'aria-label', CFG.i18n.runningTitle );
			bar.appendChild( document.createElement( 'span' ) );
			card.appendChild( bar );

			return card;
		}

		function renderTiles( results ) {
			results = results || {};

			var tiles = [
				[ results.pages_analyzed || 0, CFG.i18n.tileAnalyzed ],
				[ results.avg_links_per_page || 0, CFG.i18n.tileLinksPerPage ],
				[ ( results.orphan_pages || [] ).length, CFG.i18n.tileOrphan ],
				[ ( results.dead_end_pages || [] ).length, CFG.i18n.tileDead ],
				[ ( results.buried_pages || [] ).length, CFG.i18n.tileBuried ],
				[ ( results.weak_anchors || [] ).length, CFG.i18n.tileWeak ]
			];

			var wrap = el( 'div', 'cbl-ilo-tiles' );
			tiles.forEach( function ( pair ) {
				var tile = el( 'div', 'cbl-ilo-tile' );
				tile.appendChild( el( 'b', 'cbl-ilo-tile-value', pair[ 0 ] ) );
				tile.appendChild( el( 'span', 'cbl-ilo-tile-label', pair[ 1 ] ) );
				wrap.appendChild( tile );
			} );

			return wrap;
		}

		function tabDefs() {
			var defs = [ { key: 'suggested', label: CFG.i18n.tabSuggested, count: ( state.suggestions || [] ).length } ];
			var results = ( state.latest && state.latest.results ) || {};

			FINDING_TABS.forEach( function ( tab ) {
				defs.push( { key: tab.key, label: CFG.i18n[ tab.label ], count: ( results[ tab.resultsKey ] || [] ).length } );
			} );

			return defs;
		}

		function renderTabs() {
			var bar = el( 'div', 'cbl-inner-tabs' );

			tabDefs().forEach( function ( def ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'cbl-inner-tab' + ( activeTab === def.key ? ' active' : '' );
				btn.textContent = def.label + ' (' + def.count + ')';
				btn.addEventListener( 'click', function () {
					activeTab = def.key;
					render();
				} );
				bar.appendChild( btn );
			} );

			return bar;
		}

		function renderFindingsTable( tabDef, rows ) {
			if ( ! rows.length ) {
				return el( 'div', 'cbl-card cbl-audit-empty', CFG.i18n[ tabDef.empty ] );
			}

			if ( 'weak' === tabDef.key ) {
				var table = document.createElement( 'table' );
				table.className = 'cbl-ilo-table';

				var thead = document.createElement( 'thead' );
				var headRow = document.createElement( 'tr' );
				[ CFG.i18n.colAnchor, CFG.i18n.colOnPage, CFG.i18n.colLinksTo ].forEach( function ( label ) {
					headRow.appendChild( el( 'th', null, label ) );
				} );
				thead.appendChild( headRow );
				table.appendChild( thead );

				var tbody = document.createElement( 'tbody' );
				rows.forEach( function ( row ) {
					var tr = document.createElement( 'tr' );
					tr.appendChild( el( 'td', null, row.anchor || '' ) );
					tr.appendChild( el( 'td', 'cbl-ilo-mono', pathOf( String( row.source_url || '' ) ) ) );
					tr.appendChild( el( 'td', 'cbl-ilo-mono', pathOf( String( row.target_url || '' ) ) ) );
					tbody.appendChild( tr );
				} );
				table.appendChild( tbody );

				var wrap = el( 'div', 'cbl-ilo-table-wrap' );
				wrap.appendChild( table );
				return wrap;
			}

			var list = document.createElement( 'ul' );
			list.className = 'cbl-ilo-page-list';
			rows.forEach( function ( url ) {
				var li = document.createElement( 'li' );
				li.appendChild( el( 'span', 'cbl-ilo-mono', pathOf( String( url ) ) ) );
				list.appendChild( li );
			} );

			var box = el( 'div', 'cbl-card' );
			box.appendChild( list );
			return box;
		}

		function statusLabel( status ) {
			if ( 'approved' === status ) {
				return CFG.i18n.statusApproved;
			}
			if ( 'skipped' === status ) {
				return CFG.i18n.statusSkipped;
			}
			if ( 'applied' === status ) {
				return CFG.i18n.statusApplied;
			}
			return CFG.i18n.statusPending;
		}

		function buildEnd( url, title ) {
			var end = el( 'span', 'cbl-ilo-end' );
			end.appendChild( el( 'span', 'cbl-ilo-mono', pathOf( String( url || '' ) ) ) );
			if ( title ) {
				end.appendChild( el( 'span', 'cbl-ilo-end-title', title ) );
			}
			return end;
		}

		function actionButton( label, className, onClick ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = className;
			btn.textContent = label;
			btn.addEventListener( 'click', onClick );
			return btn;
		}

		/**
		 * The post types the optimizer reads when it builds the link
		 * graph. Rendered once at load, independent of loadState()/
		 * render() -- it is not part of the SaaS-proxied analysis state,
		 * and changing it never starts a run, so it must survive every
		 * re-render triggered by polling or a new analysis.
		 */
		function renderPostTypes() {
			if ( ! postTypesEl ) {
				return;
			}

			var available = ( CFG.postTypes && CFG.postTypes.available ) || [];
			if ( ! available.length ) {
				return;
			}

			var saved = ( CFG.postTypes && CFG.postTypes.selected ) || [];
			// An empty saved selection means every type is read today
			// (see wpcbl_collect_site_pages()), so the picker starts with
			// everything ticked rather than nothing.
			var startChecked = saved.length ? saved : available.map( function ( option ) {
				return option.value;
			} );

			postTypesEl.textContent = '';

			var card = el( 'div', 'cbl-card cbl-ilo-posttypes' );
			card.appendChild( el( 'h3', null, CFG.i18n.postTypesTitle ) );
			card.appendChild( el( 'p', 'description', CFG.i18n.postTypesHint ) );

			var fieldset = document.createElement( 'fieldset' );
			fieldset.className = 'wpcbl-checkbox-group';
			var boxes = [];

			available.forEach( function ( option ) {
				var label = document.createElement( 'label' );

				var box = document.createElement( 'input' );
				box.type = 'checkbox';
				box.value = option.value;
				box.checked = startChecked.indexOf( option.value ) !== -1;
				boxes.push( box );

				label.appendChild( box );
				label.appendChild( el( 'span', null, option.label ) );
				fieldset.appendChild( label );
			} );

			card.appendChild( fieldset );

			var status = el( 'span', 'cbl-ilo-posttypes-status' );

			var saveBtn = actionButton( CFG.i18n.postTypesSave, 'cbl-btn cbl-btn-sm', function () {
				var chosen = boxes.filter( function ( box ) {
					return box.checked;
				} ).map( function ( box ) {
					return box.value;
				} );

				saveBtn.disabled = true;
				status.textContent = '';

				// Saving only changes what the NEXT analysis reads. It
				// never starts a run itself.
				post( 'wpcbl_ilo_post_types', { post_types: chosen } ).then( function () {
					status.textContent = CFG.i18n.postTypesSaved;
				} ).catch( function ( error ) {
					status.textContent = error.message || CFG.i18n.generic;
				} ).then( function () {
					saveBtn.disabled = false;
				} );
			} );

			var actions = el( 'div', 'cbl-ilo-posttypes-actions' );
			actions.appendChild( saveBtn );
			actions.appendChild( status );
			card.appendChild( actions );

			postTypesEl.appendChild( card );
		}

		/**
		 * Everything below builds the NOW / AFTER preview for one
		 * suggestion's paragraph, from its placement instructions. This
		 * is a display-only approximation of the edit the server will
		 * actually make (App\Support\LinkOptimizer\DiffPreview on the
		 * SaaS side does the byte-exact version) -- close enough to show
		 * an owner what is about to change, never used to write anything.
		 */
		function findAll( haystack, needle ) {
			var found = [];
			if ( ! haystack || ! needle ) {
				return found;
			}

			var hay = haystack.toLowerCase();
			var pin = needle.toLowerCase();
			var offset = 0;
			var at = hay.indexOf( pin, offset );

			while ( -1 !== at ) {
				found.push( at );
				offset = at + pin.length;
				at = hay.indexOf( pin, offset );
			}

			return found;
		}

		function markParts( text, length, at, styleAt ) {
			var parts = [];
			var cursor = 0;

			at.forEach( function ( offset, index ) {
				if ( offset > cursor ) {
					parts.push( [ text.substring( cursor, offset ), 'plain' ] );
				}
				parts.push( [ text.substr( offset, length ), styleAt( index ) ] );
				cursor = offset + length;
			} );

			if ( cursor < text.length ) {
				parts.push( [ text.substring( cursor ), 'plain' ] );
			}

			return parts;
		}

		function wrapDiff( paragraph, needle ) {
			if ( ! needle ) {
				return null;
			}

			var at = findAll( paragraph, needle );
			if ( ! at.length ) {
				return { now: [ [ needle, 'plain' ] ], after: [ [ needle, 'add' ] ] };
			}

			return {
				now: [ [ paragraph, 'plain' ] ],
				after: markParts( paragraph, needle.length, [ at[ 0 ] ], function () {
					return 'add';
				} )
			};
		}

		function removeDiff( paragraph, needle ) {
			if ( ! needle ) {
				return null;
			}

			var at = findAll( paragraph, needle );
			if ( at.length < 2 ) {
				return { now: [ [ needle, 'link' ] ], after: [ [ needle, 'cut' ] ] };
			}

			return {
				now: markParts( paragraph, needle.length, at, function () {
					return 'link';
				} ),
				after: markParts( paragraph, needle.length, at, function ( index ) {
					return 1 === index ? 'cut' : 'link';
				} )
			};
		}

		function sentenceEnd( paragraph, after ) {
			var length = paragraph.length;
			var start = 0;

			if ( after ) {
				var at = paragraph.toLowerCase().indexOf( after.toLowerCase() );
				start = -1 === at ? length : at + after.length;
			}

			if ( start >= length ) {
				return length;
			}

			var end = length;
			[ '.', '!', '?' ].forEach( function ( mark ) {
				var markAt = paragraph.indexOf( mark, start );
				if ( -1 !== markAt && markAt + 1 < end ) {
					end = markAt + 1;
				}
			} );

			return end;
		}

		function insertDiff( paragraph, after, sentence ) {
			if ( ! paragraph ) {
				var parts = [];
				if ( after ) {
					parts.push( [ after + ' ', 'plain' ] );
				}
				if ( sentence ) {
					parts.push( [ sentence, 'add' ] );
				}
				if ( ! parts.length ) {
					return null;
				}
				return after ? { now: [ [ after, 'plain' ] ], after: parts } : { after: parts };
			}

			if ( ! sentence ) {
				return null;
			}

			var cut = sentenceEnd( paragraph, after );
			var head = paragraph.substring( 0, cut ).replace( /\s+$/, '' );
			var tail = paragraph.substring( cut ).replace( /^\s+/, '' );

			var out = [];
			if ( head ) {
				out.push( [ head + ' ', 'plain' ] );
			}
			out.push( [ sentence, 'add' ] );
			if ( tail ) {
				out.push( [ ' ' + tail, 'plain' ] );
			}

			return { now: [ [ paragraph, 'plain' ] ], after: out };
		}

		function diffParts( paragraph, placement, anchorText ) {
			paragraph = paragraph || '';
			placement = placement || {};

			var method = placement.method || '';
			var existing = placement.existing_text || '';
			var sentence = placement.insert_sentence || '';
			var after = placement.insert_after || '';
			var anchor = anchorText || '';

			if ( 'wrap_existing' === method ) {
				return wrapDiff( paragraph, existing || anchor );
			}
			if ( 'insert_sentence' === method ) {
				return insertDiff( paragraph, after, sentence );
			}
			if ( 'remove_duplicate' === method ) {
				return removeDiff( paragraph, existing || anchor );
			}

			return null;
		}

		function diffRow( label, parts, isAfter ) {
			var row = el( 'div', 'cbl-ilo-diff-row' + ( isAfter ? ' is-after' : '' ) );
			row.appendChild( el( 'span', 'cbl-ilo-diff-tag' + ( isAfter ? ' is-after' : '' ), label ) );

			var p = document.createElement( 'p' );
			parts.forEach( function ( pair ) {
				var span = document.createElement( 'span' );
				span.className = 'cbl-ilo-t-' + pair[ 1 ];
				span.textContent = pair[ 0 ];
				p.appendChild( span );
			} );
			row.appendChild( p );

			return row;
		}

		function renderDiff( diff ) {
			var wrap = el( 'div', 'cbl-ilo-diff' );

			if ( diff.now ) {
				wrap.appendChild( diffRow( CFG.i18n.nowLabel, diff.now, false ) );
			}
			wrap.appendChild( diffRow( CFG.i18n.afterLabel, diff.after, true ) );

			return wrap;
		}

		function resolveSuggestion( item, status ) {
			post( 'wpcbl_ilo_resolve', { suggestion_id: item.id, status: status } ).then( function () {
				item.status = status;
				item._message = '';
				render();
			} ).catch( function ( error ) {
				item._message = error.message || CFG.i18n.generic;
				item._messageWarn = true;
				render();
			} );
		}

		function applyOneSuggestion( item ) {
			applySuggestion( item ).then( function ( data ) {
				item.status = 'applied';
				item._message = ( data && data.message ) || '';
				item._messageWarn = false;
				render();
			} ).catch( function ( error ) {
				item._message = error.message || CFG.i18n.generic;
				item._messageWarn = true;
				render();
			} );
		}

		function undoSuggestion( item ) {
			post( 'wpcbl_ilo_undo', { suggestion_id: item.id, post_id: item.source_post_id } ).then( function ( data ) {
				item.status = ( data && data.status ) || 'approved';
				item._message = ( data && data.message ) || '';
				item._messageWarn = false;
				render();
			} ).catch( function ( error ) {
				item._message = error.message || CFG.i18n.generic;
				item._messageWarn = true;
				render();
			} );
		}

		function renderSuggestionCard( item ) {
			var card = el( 'div', 'cbl-audit-sug' + ( 'applied' === item.status ? ' is-applied' : '' ) );

			var top = el( 'div', 'cbl-ilo-sug-top' );
			if ( item.impact ) {
				top.appendChild( el( 'span', 'cbl-ilo-impact cbl-ilo-impact-' + item.impact, item.impact ) );
			}
			top.appendChild( el( 'span', 'cbl-ilo-status cbl-ilo-status-' + item.status, statusLabel( item.status ) ) );
			card.appendChild( top );

			var pair = el( 'div', 'cbl-ilo-pair' );
			pair.appendChild( buildEnd( item.source_url, item.source_title ) );
			pair.appendChild( el( 'span', 'cbl-ilo-arrow', '→' ) );
			pair.appendChild( buildEnd( item.target_url, item.target_title ) );
			card.appendChild( pair );

			if ( item.reason ) {
				card.appendChild( el( 'p', 'cbl-ilo-reason', item.reason ) );
			}

			var diff = diffParts( item.paragraph, item.placement, item.anchor_text || '' );
			if ( diff ) {
				card.appendChild( renderDiff( diff ) );
			}

			var anchorRow = el( 'div', 'cbl-ilo-anchor' );
			anchorRow.appendChild( el( 'span', 'cbl-ilo-anchor-tag', CFG.i18n.anchorLabel ) );
			anchorRow.appendChild( el( 'span', 'cbl-ilo-anchor-text', item.anchor_text || '' ) );
			card.appendChild( anchorRow );

			var actions = el( 'div', 'cbl-audit-sug-actions' );

			if ( 'applied' !== item.status ) {
				if ( 'approved' !== item.status ) {
					actions.appendChild( actionButton( CFG.i18n.approve, 'cbl-btn cbl-btn-sm', function () {
						resolveSuggestion( item, 'approved' );
					} ) );
				}
				if ( 'skipped' !== item.status ) {
					actions.appendChild( actionButton( CFG.i18n.skip, 'cbl-btn cbl-btn-sm', function () {
						resolveSuggestion( item, 'skipped' );
					} ) );
				}
			}

			// Apply needs a post on this site to edit. Without one there is
			// nothing here to change, so the card links to the dashboard
			// instead of offering an action that can only fail.
			if ( item.source_post_id ) {
				if ( 'applied' === item.status ) {
					actions.appendChild( actionButton( CFG.i18n.undo, 'cbl-btn cbl-btn-sm', function () {
						undoSuggestion( item );
					} ) );
				} else {
					actions.appendChild( actionButton( CFG.i18n.apply, 'cbl-btn cbl-btn-sm cbl-btn-primary', function () {
						applyOneSuggestion( item );
					} ) );
				}
			} else if ( 'applied' !== item.status ) {
				var link = document.createElement( 'a' );
				link.href = dashboardUrl();
				link.target = '_blank';
				link.rel = 'noopener';
				link.className = 'cbl-btn cbl-btn-sm';
				link.textContent = CFG.i18n.dashboardLink;
				actions.appendChild( link );
			}

			card.appendChild( actions );

			if ( item._message ) {
				card.appendChild( el( 'p', 'cbl-audit-sug-msg' + ( item._messageWarn ? ' is-warn' : '' ), item._message ) );
			}

			return card;
		}

		function renderSuggestionsList( items ) {
			if ( ! items.length ) {
				return el( 'div', 'cbl-card cbl-audit-empty', CFG.i18n.emptySuggested );
			}

			var wrap = el( 'div', 'cbl-ilo-sugs' );

			var applyAllItems = items.filter( function ( item ) {
				return 'approved' === item.status && item.source_post_id;
			} );

			if ( applyAllItems.length ) {
				var bar = el( 'div', 'cbl-ilo-apply-all' );
				var btn = actionButton( CFG.i18n.applyAll, 'cbl-btn cbl-btn-primary', function () {
					btn.disabled = true;
					applyAll( applyAllItems, function ( rowItem, success, message ) {
						rowItem._message = message || '';
						rowItem._messageWarn = ! success;
						if ( success ) {
							rowItem.status = 'applied';
						}
						render();
					} ).catch( function () {
						// A transport failure stopped the batch. The rows
						// already processed keep their per-row messages;
						// nothing more to do here.
					} ).then( function () {
						btn.disabled = false;
					} );
				} );
				bar.appendChild( btn );
				wrap.appendChild( bar );
			}

			items.forEach( function ( item ) {
				wrap.appendChild( renderSuggestionCard( item ) );
			} );

			return wrap;
		}

		function renderTabBody() {
			if ( 'suggested' === activeTab ) {
				return renderSuggestionsList( state.suggestions || [] );
			}

			var tabDef = null;
			FINDING_TABS.forEach( function ( tab ) {
				if ( tab.key === activeTab ) {
					tabDef = tab;
				}
			} );

			if ( ! tabDef ) {
				return el( 'div' );
			}

			var results = ( state.latest && state.latest.results ) || {};
			return renderFindingsTable( tabDef, results[ tabDef.resultsKey ] || [] );
		}

		function render() {
			renderQuota();

			if ( ! content ) {
				return;
			}

			content.textContent = '';

			if ( runNotice ) {
				content.appendChild( el( 'div', 'cbl-audit-notice cbl-audit-notice-warn', runNotice ) );
			}

			if ( ! state || ! state.latest ) {
				content.appendChild( el( 'div', 'cbl-card cbl-audit-empty', CFG.i18n.none ) );
				return;
			}

			var latest = state.latest;

			if ( 'pending' === latest.status || 'running' === latest.status ) {
				content.appendChild( runningCard() );
				return;
			}

			if ( 'failed' === latest.status ) {
				content.appendChild( el( 'div', 'cbl-audit-notice cbl-audit-notice-warn', CFG.i18n.failed ) );
				return;
			}

			content.appendChild( renderTiles( latest.results ) );
			content.appendChild( renderTabs() );

			var panel = el( 'div', 'cbl-ilo-panel' );
			panel.appendChild( renderTabBody() );
			content.appendChild( panel );
		}

		function wireRun() {
			if ( ! runBtn ) {
				return;
			}

			runBtn.addEventListener( 'click', function () {
				if ( runBtn.disabled ) {
					return;
				}

				runNotice = '';
				runBtn.disabled = true;
				runBtn.textContent = fmt( CFG.i18n.uploading, 0 );

				var cap = state && state.page_cap ? state.page_cap : 0;

				runAnalysis( cap, function ( count ) {
					runBtn.textContent = fmt( CFG.i18n.uploading, count );
				} ).then( function ( data ) {
					// The plan's page cap ended the walk before the site
					// did. Say so plainly. This page is on every plan, so
					// this is a statement of what was read, not an upsell.
					// The server only ever sends `capped` when pages were
					// genuinely left out, so no local re-check is needed
					// here -- just the two numbers to report.
					if ( data && data.capped ) {
						var analyzed = data.pages_stored || 0;
						var planCap = data.page_cap || cap;
						runNotice = analyzed === planCap
							? fmt( CFG.i18n.capLimitedSame, analyzed )
							: fmt( CFG.i18n.capLimited, analyzed, planCap );
					}

					pollStartedAt = 0;
					loadState( false );
				} ).catch( function ( error ) {
					runNotice = error.message || CFG.i18n.generic;
					syncRunButton( state && state.latest ? state.latest.status : '' );
					render();
				} );
			} );
		}

		if ( retryBtn ) {
			retryBtn.addEventListener( 'click', function () {
				loadState( true );
			} );
		}

		wireRun();
		renderPostTypes();
		loadState( true );
	}
}() );
