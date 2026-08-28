/**
 * AI Visibility Tracker admin page.
 *
 * Plain ES5, no build step, no jQuery dependency: this file is its own
 * self-contained page script, loaded only on the AI Visibility screen.
 * Every value that reaches the DOM comes from brokenlinkchecker.io over
 * admin-ajax, so it is written with textContent / createElement only.
 * Never innerHTML with data from the API.
 *
 * Cadence (how often a reading is captured) stays server-owned, because
 * each reading has a real cost -- this script never polls for one. The
 * "Run check now" button (wired in init(), below) is the one deliberate
 * exception: it asks the SaaS for an extra capture, which the SaaS itself
 * allows at most once per project per calendar day. This script never
 * enforces that limit and never retries automatically on the 429 that
 * means the day's manual check is already spent.
 *
 * @package WPCBL_Check_Broken_Links
 * @since 3.0.8
 */
( function () {
	'use strict';

	var CFG = window.wpcblAiv || {};

	var ENGINES = [
		{ key: 'chatgpt', label: 'ChatGPT' },
		{ key: 'perplexity', label: 'Perplexity' },
		{ key: 'google_ai', label: 'Google AI' }
	];

	/**
	 * One admin-ajax POST. Resolves with the data payload, rejects with a
	 * message. Mirrors wpcblIloApi.post() (cbl-internal-links.js) -- kept
	 * as its own copy rather than a shared dependency, so this screen has
	 * no load-order requirement on that one.
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
			body.append( key, value );
		} );

		function transportError() {
			return new Error( CFG.i18n.generic );
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
	 * %1$s / %2$s placeholder substitution for the localized strings. The
	 * replacement is passed as a function, not a string: String.replace()
	 * treats a string replacement's own `$&`/`$1`/backtick-`$` sequences
	 * as special patterns, and `a`/`b` here can carry API data (a
	 * competitor domain, for example) that a site owner does not control.
	 * A function replacement is inserted literally, whatever it contains.
	 */
	function fmt( str, a, b ) {
		var out = String( str || '' );
		if ( undefined !== a ) {
			out = out.replace( '%1$s', function () {
				return String( a );
			} );
		}
		if ( undefined !== b ) {
			out = out.replace( '%2$s', function () {
				return String( b );
			} );
		}
		return out;
	}

	function actionButton( label, className, onClick ) {
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = className;
		btn.textContent = label;
		btn.addEventListener( 'click', onClick );
		return btn;
	}

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( init );

	function init() {
		var app = document.getElementById( 'wpcbl-aiv-app' );
		if ( ! app ) {
			return;
		}

		var skeleton = document.getElementById( 'wpcbl-aiv-skeleton' );
		var errorBox = document.getElementById( 'wpcbl-aiv-error' );
		var errorText = errorBox ? errorBox.querySelector( 'p' ) : null;
		var retryBtn = document.getElementById( 'wpcbl-aiv-retry' );
		var content = document.getElementById( 'wpcbl-aiv-content' );
		var quotaEl = document.getElementById( 'wpcbl-aiv-quota' );
		var runBtn = document.getElementById( 'wpcbl-aiv-run' );
		var runStatus = document.getElementById( 'wpcbl-aiv-run-status' );

		var state = null;

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

			post( 'wpcbl_aiv_state', {} ).then( function ( data ) {
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

				renderQuota();
				render();
			} ).catch( function ( error ) {
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

		function renderQuota() {
			if ( ! quotaEl ) {
				return;
			}

			quotaEl.textContent = '';

			if ( ! state || ! state.quota ) {
				return;
			}

			var quota = state.quota;
			var text = fmt( CFG.i18n.quotaUsed, quota.used, quota.limit );
			quotaEl.appendChild( document.createTextNode( text ) );

			// The Roxi rule: a paying customer never sees an upgrade prompt
			// for something their plan covers. This one only ever shows
			// for a free account that has spent its allowance.
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

		/**
		 * The movement line under the mentions figure. Three distinct
		 * states, matching the dashboard's own wording exactly so the two
		 * surfaces never contradict each other on the same reading:
		 *   - no `previous` snapshot at all: no fabricated "no change"
		 *   - previous present, delta > 0 / < 0 / === 0: the three cases
		 * The delta itself is `latest.mentions - previous.mentions`, an
		 * absolute count, never a percentage.
		 */
		function movementNote( overview, previous ) {
			if ( null === previous ) {
				return { text: CFG.i18n.noPrevious, cls: '' };
			}

			var delta = overview.mentions - previous.mentions;

			if ( delta > 0 ) {
				return { text: fmt( CFG.i18n.up, delta ), cls: 'is-up' };
			}
			if ( delta < 0 ) {
				return { text: fmt( CFG.i18n.down, Math.abs( delta ) ), cls: 'is-down' };
			}
			return { text: CFG.i18n.noChange, cls: '' };
		}

		/**
		 * "Next check" is never an authoritative date from the API --
		 * there is none in the payload -- so this is always an estimate
		 * built from `cadence` (days between checks) plus, once a
		 * snapshot exists, how long ago it was captured. Never rendered
		 * as a promise ("runs every Monday"): a due-or-overdue estimate
		 * reads as "due" rather than a confusing zero or negative count.
		 */
		function nextCheckEstimate() {
			var cadence = state.cadence || 7;

			if ( null === state.overview ) {
				return { value: cadence + ' ' + CFG.i18n.nextDaysUnit, note: fmt( CFG.i18n.nextRunsNote, cadence ) };
			}

			var captured = state.overview.captured_on;
			var elapsedMs = Date.now() - new Date( captured + 'T00:00:00Z' ).getTime();
			var elapsedDays = Math.floor( elapsedMs / 86400000 );
			var remaining = cadence - elapsedDays;

			if ( remaining <= 0 ) {
				return { value: CFG.i18n.nextDueNow, note: CFG.i18n.nextDueNote };
			}

			return { value: remaining + ' ' + CFG.i18n.nextDaysUnit, note: fmt( CFG.i18n.nextRunsNote, cadence ) };
		}

		function kpiCard( kicker, valueText, note, noteClass ) {
			var card = el( 'div', 'cbl-card' );
			var stat = el( 'div', 'cbl-aiv-stat' );
			stat.appendChild( el( 'span', 'cbl-aiv-stat-kicker', kicker ) );
			stat.appendChild( el( 'span', 'cbl-aiv-stat-value', valueText ) );
			stat.appendChild( el( 'span', 'cbl-aiv-stat-note' + ( noteClass ? ' ' + noteClass : '' ), note ) );
			card.appendChild( stat );
			return card;
		}

		function renderKpis() {
			var wrap = el( 'div', 'cbl-aiv-kpis' );

			// Visibility score: null means no tracked prompt has an answer
			// yet. Never mistaken for a real 0%, which renders as "0%"
			// below, not "--".
			var score = state.visibility_score;
			wrap.appendChild( kpiCard(
				CFG.i18n.scoreTitle,
				null === score ? '—' : score + '%',
				null === score ? CFG.i18n.scoreNoneNote : CFG.i18n.scoreNote
			) );

			// Brand mentions: null overview means never measured. A
			// measured overview with mentions === 0 is a real zero and
			// renders as "0" via movementNote(), never mistaken for "not
			// measured".
			var overview = state.overview;
			var mentionsNote = null === overview
				? { text: CFG.i18n.mentionsNoneNote, cls: '' }
				: movementNote( overview, state.previous );
			wrap.appendChild( kpiCard(
				CFG.i18n.mentionsTitle,
				null === overview ? '—' : String( overview.mentions ),
				mentionsNote.text,
				mentionsNote.cls
			) );

			var next = nextCheckEstimate();
			wrap.appendChild( kpiCard( CFG.i18n.nextCheckTitle, next.value, next.note ) );

			content.appendChild( wrap );
		}

		/**
		 * The three-step onboarding strip shown before the first reading
		 * lands. Replaces the mockup's "we email you" promise -- this
		 * product does not send that email -- with the true fact that
		 * results show up on this same page.
		 */
		function onboardingSteps() {
			var quota = state.quota || { used: 0, limit: 0 };
			var used = quota.used || 0;
			var limit = quota.limit || 0;
			var cadence = state.cadence || 7;
			var hasPrompts = used > 0;

			var wrap = el( 'div', 'cbl-aiv-steps' );

			var step1 = el( 'div', 'cbl-aiv-step' + ( hasPrompts ? ' is-done' : ' is-current' ) );
			var head1 = el( 'div', 'cbl-aiv-step-head' );
			head1.appendChild( el( 'span', 'cbl-aiv-step-icon' ) );
			head1.appendChild( el( 'span', 'cbl-aiv-step-title', CFG.i18n.stepPromptTitle ) );
			step1.appendChild( head1 );
			step1.appendChild( el( 'div', 'cbl-aiv-step-desc', fmt( CFG.i18n.stepPromptDesc, used, limit ) ) );
			wrap.appendChild( step1 );

			var step2 = el( 'div', 'cbl-aiv-step' + ( hasPrompts ? ' is-current' : ' is-upcoming' ) );
			var head2 = el( 'div', 'cbl-aiv-step-head' );
			head2.appendChild( el( 'span', 'cbl-aiv-step-icon' ) );
			head2.appendChild( el( 'span', 'cbl-aiv-step-title', CFG.i18n.stepQueuedTitle ) );
			step2.appendChild( head2 );
			step2.appendChild( el( 'div', 'cbl-aiv-step-desc', CFG.i18n.stepQueuedDesc ) );
			wrap.appendChild( step2 );

			var step3 = el( 'div', 'cbl-aiv-step is-upcoming' );
			var head3 = el( 'div', 'cbl-aiv-step-head' );
			head3.appendChild( el( 'span', 'cbl-aiv-step-icon' ) );
			head3.appendChild( el( 'span', 'cbl-aiv-step-title', fmt( CFG.i18n.stepResultsTitle, cadence ) ) );
			step3.appendChild( head3 );
			step3.appendChild( el( 'div', 'cbl-aiv-step-desc', CFG.i18n.stepResultsDesc ) );
			wrap.appendChild( step3 );

			return wrap;
		}

		/**
		 * Short "M/D" label from a 'YYYY-MM-DD' date string, without
		 * pulling in a full Date-formatting dependency for what is a
		 * plain string split.
		 */
		function shortDateLabel( isoDate ) {
			var parts = String( isoDate || '' ).split( '-' );
			if ( 3 !== parts.length ) {
				return String( isoDate || '' );
			}
			return String( parseInt( parts[ 1 ], 10 ) ) + '/' + String( parseInt( parts[ 2 ], 10 ) );
		}

		/**
		 * "Mentions per weekly check", drawn from `state.history` -- up to
		 * the last 12 snapshots, oldest first, as the API now sends them.
		 * Two or more points draw the bar chart; fewer than two draw no
		 * bars at all. A single point is not a flat line: one bar sized
		 * against nothing would read as a measured flat trend rather than
		 * as "not enough readings yet", so that case (and the zero-reading
		 * case) is text only. Plain styled divs, no charting library.
		 */
		function weeklyChart( history, overview ) {
			var wrap = el( 'div' );
			wrap.appendChild( el( 'div', 'cbl-aiv-chart-label', CFG.i18n.weeklyChartTitle ) );

			// history is always an array per the API contract (never null),
			// but this function only ever runs once `overview` already
			// proves at least one reading exists -- if history somehow
			// came back empty anyway, fall back to that one known reading
			// rather than showing "no readings yet" while a reading exists.
			var points = history && history.length ? history : ( overview ? [ { captured_on: overview.captured_on, mentions: overview.mentions } ] : [] );

			if ( points.length < 2 ) {
				wrap.appendChild( el(
					'div',
					'cbl-aiv-chart-note',
					0 === points.length ? CFG.i18n.weeklyChartEmpty : CFG.i18n.weeklyChartOnePoint
				) );
				return wrap;
			}

			var maxVal = 0;
			points.forEach( function ( point ) {
				if ( point.mentions > maxVal ) {
					maxVal = point.mentions;
				}
			} );

			var bars = el( 'div', 'cbl-aiv-weekbars' );
			points.forEach( function ( point, index ) {
				var bar = el( 'div', 'cbl-aiv-weekbar' );
				bar.appendChild( el( 'div', 'cbl-aiv-weekbar-value', point.mentions ) );

				var fill = document.createElement( 'div' );
				fill.className = 'cbl-aiv-weekbar-fill' + ( index === points.length - 1 ? ' is-latest' : '' );
				fill.style.height = ( maxVal > 0 ? Math.round( ( point.mentions / maxVal ) * 100 ) : 0 ) + '%';
				bar.appendChild( fill );

				bar.appendChild( el( 'div', 'cbl-aiv-weekbar-label', shortDateLabel( point.captured_on ) ) );
				bars.appendChild( bar );
			} );
			wrap.appendChild( bars );

			return wrap;
		}

		/**
		 * "Mention rate by assistant": cited-over-checked for each engine,
		 * across every tracked prompt. An engine none of the prompts have
		 * been checked on yet reads as "Not measured yet", never as 0%.
		 */
		function engineRatesChart( prompts ) {
			var wrap = el( 'div' );
			wrap.appendChild( el( 'div', 'cbl-aiv-chart-label', CFG.i18n.engineChartTitle ) );

			var rows = el( 'div', 'cbl-aiv-engine-rates' );

			ENGINES.forEach( function ( engine ) {
				var checked = 0;
				var cited = 0;
				prompts.forEach( function ( prompt ) {
					var check = prompt.checks ? prompt.checks[ engine.key ] : null;
					if ( check ) {
						checked++;
						if ( check.cited ) {
							cited++;
						}
					}
				} );

				var pct = checked ? Math.round( 100 * cited / checked ) : null;

				var row = el( 'div' );
				var head = el( 'div', 'cbl-aiv-engine-row-head' );
				head.appendChild( el( 'b', null, engine.label ) );
				head.appendChild( el( 'span', null, null === pct ? CFG.i18n.engineRateNone : pct + '%' ) );
				row.appendChild( head );

				var track = el( 'div', 'cbl-aiv-engine-track' );
				var fill = document.createElement( 'div' );
				fill.className = 'cbl-aiv-engine-fill';
				fill.style.width = ( null === pct ? 0 : pct ) + '%';
				track.appendChild( fill );
				row.appendChild( track );

				rows.appendChild( row );
			} );

			wrap.appendChild( rows );
			return wrap;
		}

		/**
		 * Competitor list. The API withholds the `sources` key entirely
		 * for a free account -- checked here as key presence, never
		 * assumed to exist just because `overview` itself is not null.
		 */
		function sourcesBlock( overview ) {
			var block = el( 'div', 'cbl-aiv-block' );
			block.appendChild( el( 'span', 'cbl-aiv-block-label', CFG.i18n.sourcesTitle ) );

			if ( ! Object.prototype.hasOwnProperty.call( overview, 'sources' ) ) {
				block.appendChild( el( 'span', 'description', CFG.i18n.sourcesPaidOnly ) );
			} else if ( ! overview.sources || ! overview.sources.length ) {
				block.appendChild( el( 'span', 'description', CFG.i18n.sourcesEmpty ) );
			} else {
				var list = document.createElement( 'ul' );
				list.className = 'cbl-aiv-source-list';
				overview.sources.forEach( function ( source ) {
					var li = document.createElement( 'li' );
					li.appendChild( el( 'span', null, fmt( CFG.i18n.sourceItem, source.key, source.mentions ) ) );
					list.appendChild( li );
				} );
				block.appendChild( list );
			}

			return block;
		}

		function renderOverview() {
			var card = el( 'div', 'cbl-card' );
			var header = el( 'div', 'cbl-card-header' );
			var headText = el( 'div' );
			headText.appendChild( el( 'h2', 'cbl-card-title', CFG.i18n.overviewTitle ) );

			var overview = state.overview;
			headText.appendChild( el( 'p', 'cbl-card-desc', null === overview ? CFG.i18n.overviewSubPending : CFG.i18n.overviewSubResults ) );
			header.appendChild( headText );
			card.appendChild( header );

			if ( null === overview ) {
				card.appendChild( onboardingSteps() );
			} else {
				var charts = el( 'div', 'cbl-aiv-charts' );
				var left = el( 'div' );
				left.appendChild( weeklyChart( state.history || [], overview ) );
				var right = el( 'div', 'cbl-aiv-chart-col-right' );
				right.appendChild( engineRatesChart( state.prompts || [] ) );
				charts.appendChild( left );
				charts.appendChild( right );
				card.appendChild( charts );

				card.appendChild( sourcesBlock( overview ) );
			}

			content.appendChild( card );
		}

		function checkPill( check ) {
			if ( null === check ) {
				return el( 'span', 'cbl-pill cbl-pill-pending', CFG.i18n.pending );
			}

			if ( check.cited ) {
				var label = check.position ? fmt( CFG.i18n.citedWithPosition, check.position ) : CFG.i18n.cited;
				return el( 'span', 'cbl-pill cbl-pill-success', label );
			}

			return el( 'span', 'cbl-pill cbl-pill-neutral', CFG.i18n.absent );
		}

		/**
		 * `prompt.visibility` is null until at least one engine has
		 * checked this prompt, and a real 0% (checked everywhere, cited
		 * nowhere) still renders a bar and a "0%" label -- only the null
		 * case falls back to text with no bar at all.
		 */
		function visibilityCell( vis ) {
			var wrap = el( 'div', 'cbl-aiv-vis-cell' );

			if ( null === vis ) {
				wrap.appendChild( el( 'span', 'description', CFG.i18n.notMeasured ) );
				return wrap;
			}

			var track = el( 'div', 'cbl-aiv-vis-track' );
			var fill = document.createElement( 'div' );
			fill.className = 'cbl-aiv-vis-fill' + ( vis >= 50 ? ' is-high' : ( vis > 0 ? ' is-mid' : '' ) );
			fill.style.width = vis + '%';
			track.appendChild( fill );
			wrap.appendChild( track );
			wrap.appendChild( el( 'span', 'cbl-aiv-vis-label', vis + '%' ) );

			return wrap;
		}

		function promptCheckedDate( prompt ) {
			var latest = '';
			ENGINES.forEach( function ( engine ) {
				var check = prompt.checks ? prompt.checks[ engine.key ] : null;
				if ( check && check.checked_on && check.checked_on > latest ) {
					latest = check.checked_on;
				}
			} );

			return latest || CFG.i18n.neverChecked;
		}

		/**
		 * Remove one prompt. On success the whole table reloads from
		 * fresh state, so there is nothing to update in place. On
		 * failure a status row is inserted right after this one -- and
		 * only then: no placeholder row exists in the table until there
		 * is actually something to say, so a list of prompts never shows
		 * a blank row after every entry.
		 */
		function removePrompt( prompt, btn, tr ) {
			btn.disabled = true;

			post( 'wpcbl_aiv_delete_prompt', { id: prompt.id } ).then( function () {
				loadState( false );
			} ).catch( function ( error ) {
				btn.disabled = false;

				var statusRow = document.createElement( 'tr' );
				var statusTd = document.createElement( 'td' );
				statusTd.colSpan = 7;
				statusTd.appendChild( el( 'span', 'description', error.message || CFG.i18n.generic ) );
				statusRow.appendChild( statusTd );

				if ( tr.parentNode ) {
					tr.parentNode.insertBefore( statusRow, tr.nextSibling );
				}
			} );
		}

		function renderPromptsTable( prompts ) {
			var wrap = el( 'div', 'cbl-ilo-table-wrap' );
			var table = document.createElement( 'table' );
			table.className = 'cbl-ilo-table';

			var thead = document.createElement( 'thead' );
			var headRow = document.createElement( 'tr' );
			[ CFG.i18n.colPrompt, CFG.i18n.colChatgpt, CFG.i18n.colPerplexity, CFG.i18n.colGoogleAi, CFG.i18n.colVisibility, CFG.i18n.colChecked, '' ]
				.forEach( function ( label ) {
					headRow.appendChild( el( 'th', null, label ) );
				} );
			thead.appendChild( headRow );
			table.appendChild( thead );

			var tbody = document.createElement( 'tbody' );

			prompts.forEach( function ( prompt ) {
				var tr = document.createElement( 'tr' );
				tr.appendChild( el( 'td', null, prompt.prompt ) );

				ENGINES.forEach( function ( engine ) {
					var td = document.createElement( 'td' );
					td.appendChild( checkPill( prompt.checks ? prompt.checks[ engine.key ] : null ) );
					tr.appendChild( td );
				} );

				var visTd = document.createElement( 'td' );
				visTd.appendChild( visibilityCell( undefined === prompt.visibility ? null : prompt.visibility ) );
				tr.appendChild( visTd );

				tr.appendChild( el( 'td', null, promptCheckedDate( prompt ) ) );

				var actionsTd = document.createElement( 'td' );
				var removeBtn = actionButton( CFG.i18n.remove, 'cbl-btn cbl-btn-sm', function () {
					removePrompt( prompt, removeBtn, tr );
				} );
				actionsTd.appendChild( removeBtn );
				tr.appendChild( actionsTd );

				tbody.appendChild( tr );
			} );

			table.appendChild( tbody );
			wrap.appendChild( table );
			return wrap;
		}

		function renderPrompts() {
			var card = el( 'div', 'cbl-card' );
			var header = el( 'div', 'cbl-card-header' );
			var headText = el( 'div' );
			headText.appendChild( el( 'h2', 'cbl-card-title', CFG.i18n.promptsTitle ) );
			headText.appendChild( el( 'p', 'cbl-card-desc', CFG.i18n.promptsHint ) );
			header.appendChild( headText );
			card.appendChild( header );

			var addRow = el( 'div', 'cbl-aiv-add-row' );
			var textarea = document.createElement( 'textarea' );
			textarea.className = 'cbl-aiv-add-textarea';
			textarea.rows = 3;
			textarea.placeholder = CFG.i18n.addPlaceholder;
			addRow.appendChild( textarea );
			card.appendChild( addRow );

			var suggestionsRow = el( 'div', 'cbl-aiv-suggestions' );
			suggestionsRow.appendChild( el( 'span', 'cbl-aiv-suggestions-label', CFG.i18n.suggestionsLabel ) );
			( CFG.suggestions || [] ).forEach( function ( text ) {
				suggestionsRow.appendChild( actionButton( text, 'cbl-aiv-suggestion-btn', function () {
					var current = textarea.value ? textarea.value.replace( /^\s+|\s+$/g, '' ) : '';
					textarea.value = current ? current + '\n' + text : text;
					textarea.focus();
				} ) );
			} );
			card.appendChild( suggestionsRow );

			var addStatus = el( 'span', 'description' );

			var addBtn = actionButton( CFG.i18n.addButton, 'cbl-btn cbl-btn-primary cbl-btn-sm', function () {
				var value = textarea.value ? textarea.value.replace( /^\s+|\s+$/g, '' ) : '';
				if ( ! value ) {
					addStatus.textContent = CFG.i18n.addEmpty;
					return;
				}

				addBtn.disabled = true;
				addStatus.textContent = '';

				post( 'wpcbl_aiv_add_prompts', { prompts: value } ).then( function ( data ) {
					textarea.value = '';
					addStatus.textContent = ( data && data.message ) || '';
					loadState( false );
				} ).catch( function ( error ) {
					addStatus.textContent = error.message || CFG.i18n.generic;
				} ).then( function () {
					addBtn.disabled = false;
				} );
			} );

			var addActions = el( 'div', 'cbl-ilo-posttypes-actions' );
			addActions.appendChild( addBtn );

			var quota = state.quota || { used: 0, limit: 0 };
			var creditsLeft = quota.limit - quota.used;
			addActions.appendChild( el( 'span', 'description', fmt( CFG.i18n.creditsLeft, creditsLeft > 0 ? creditsLeft : 0 ) ) );
			addActions.appendChild( addStatus );
			card.appendChild( addActions );

			var prompts = state.prompts || [];
			if ( ! prompts.length ) {
				card.appendChild( el( 'div', 'cbl-audit-empty', CFG.i18n.promptsEmpty ) );
			} else {
				card.appendChild( renderPromptsTable( prompts ) );
			}

			card.appendChild( el( 'p', 'description', CFG.i18n.promptsFooter ) );

			content.appendChild( card );
		}

		function render() {
			if ( ! content ) {
				return;
			}

			content.textContent = '';

			if ( ! state ) {
				return;
			}

			renderKpis();
			renderOverview();
			renderPrompts();
		}

		if ( retryBtn ) {
			retryBtn.addEventListener( 'click', function () {
				loadState( true );
			} );
		}

		/**
		 * "Run check now": at most one manual capture per project per
		 * calendar day, enforced server-side. The three real outcomes --
		 * 202 accepted, 429 the day's manual check is already spent, 404
		 * the feature is off for this account -- each render their own
		 * server-supplied message; none of them trigger another request
		 * on their own. A 429 or 404 leaves the button enabled again only
		 * so a later, separate click (tomorrow, or once the feature is on)
		 * can succeed, never so this script retries by itself.
		 */
		if ( runBtn ) {
			runBtn.addEventListener( 'click', function () {
				runBtn.disabled = true;
				if ( runStatus ) {
					runStatus.textContent = CFG.i18n.runChecking;
				}

				post( 'wpcbl_aiv_refresh', {} ).then( function ( data ) {
					runBtn.disabled = false;
					if ( runStatus ) {
						runStatus.textContent = ( data && data.message ) || '';
					}
					loadState( false );
				} ).catch( function ( error ) {
					runBtn.disabled = false;
					if ( runStatus ) {
						runStatus.textContent = error.message || CFG.i18n.generic;
					}
				} );
			} );
		}

		loadState( true );
	}
}() );
