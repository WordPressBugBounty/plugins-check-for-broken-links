"use strict";

/* jshint esversion: 6 */

/**
 * SEO / AEO Audit page.
 *
 * Split out of check-for-broken-links-admin-scripts.js in 3.1.7, so the
 * other plugin pages stop loading it. Self-contained: needs jQuery only.
 */

($ => {
  const wpcbl_check_for_broken_links_params = window.wpcblAudit || {};

  // ===== SEO / AEO Audit =====
  const auditApp = $('#wpcbl-audit-app');
  if (auditApp.length) {
    const params = wpcbl_check_for_broken_links_params;
    const auditSkeleton = $('#wpcbl-audit-skeleton');
    const auditErrorBox = $('#wpcbl-audit-error');
    const auditContent = $('#wpcbl-audit-content');
    const auditControls = $('#wpcbl-audit-controls');
    const pdfBase = auditApp.data('pdf-url') || '';

    let auditState = null;
    let auditPollTimer = null;
    let auditPolls = 0;
    let auditNotice = null;
    let openRows = {};
    // Keyed by issue id, which repeats across audits, so both are dropped
    // whenever a different audit is rendered. Without that, running a second
    // audit and expanding an issue showed the PREVIOUS audit's page list,
    // including pages deleted since.
    let issuePages = {};
    let issueFixes = {};
    let renderedAuditId = null;
    let passedOpen = false;

    const auditPost = (action, data) =>
      $.post(params.ajaxUrl, Object.assign({ action: action, nonce: params.nonce }, data || {}));

    const auditErrorText = jqXHR =>
      jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : params.auditLoadError;

    // .text().html() escapes &, < and > but leaves " intact, and this
    // value lands in an HTML attribute in a few places (data-issue="...").
    // Nothing attacker-controlled reaches it today, but escape it anyway
    // so the pattern stays safe by construction as new callers show up.
    const esc = value => $('<div>').text(value == null ? '' : String(value)).html().replace(/"/g, '&quot;');

    const fmtNum = value => Number(value || 0).toLocaleString('en-US');

    const fmt = (template, values) =>
      values.reduce((out, v, idx) => out.replace('%' + (idx + 1) + '$s', v).replace('%' + (idx + 1) + '$d', v), String(template || ''));

    const auditPlural = (n, one, many) => (1 === Number(n) ? one : many);

    // "last audit …" needs the same relative-time shape as the dashboard's
    // Carbon diffForHumans(). Reuses the uptime block's already-localized
    // "%1$s ago" / "Just now" / minute-hour-day units (params.uptimeAgo,
    // params.uptimeJustNow, params.uptimeIntervalMinute(s)/Hour(s)/Day(s))
    // instead of duplicating them, and adds only the month/year units an
    // audit needs that a monitor check never does.
    const auditTimeAgo = iso => {
      const then = iso ? new Date(iso).getTime() : NaN;
      if (!then || isNaN(then)) {
        return '';
      }
      const seconds = Math.max(0, Math.round((Date.now() - then) / 1000));
      const units = [
        [31536000, params.auditIntervalYear, params.auditIntervalYears],
        [2592000, params.auditIntervalMonth, params.auditIntervalMonths],
        [86400, params.uptimeIntervalDay, params.uptimeIntervalDays],
        [3600, params.uptimeIntervalHour, params.uptimeIntervalHours],
        [60, params.uptimeIntervalMinute, params.uptimeIntervalMinutes]
      ];
      for (let i = 0; i < units.length; i++) {
        const secs = units[i][0];
        if (seconds >= secs) {
          const value = Math.floor(seconds / secs);
          return fmt(params.uptimeAgo, [value + ' ' + auditPlural(value, units[i][1], units[i][2])]);
        }
      }
      return params.uptimeJustNow;
    };

    // Tone/recover_tone are NAMES returned by App\Support\SeoAuditor::
    // triage() (score_tone, per-bar tone, per-group recover_tone), not
    // colors -- the dashboard maps them to its .pj-* palette, this maps
    // the same names to the plugin's own --cbl-* tokens. Never recompute
    // the 90/60 or >=5 thresholds behind these names here.
    const TONE = { good: 'var(--cbl-green)', warn: 'var(--cbl-amber)', bad: 'var(--cbl-red)' };
    const SEV = {
      critical: { fg: 'var(--cbl-red)', bg: 'var(--cbl-red-bg)' },
      warning: { fg: 'var(--cbl-amber)', bg: 'var(--cbl-amber-bg)' },
      notice: { fg: 'var(--cbl-text-secondary)', bg: 'var(--cbl-bg-tertiary)' }
    };
    const RECOVER = { urgent: 'var(--cbl-red)', muted: 'var(--cbl-text-hint)' };

    const auditStop = () => {
      if (auditPollTimer) {
        clearTimeout(auditPollTimer);
        auditPollTimer = null;
      }
    };

    const auditSchedule = () => {
      auditStop();
      // Audits take a minute or two, so back off instead of hammering.
      const delay = Math.min(15000, 4000 + auditPolls * 1000);
      auditPolls += 1;
      auditPollTimer = setTimeout(() => auditLoad(true), delay);
    };

    // ---- Render functions -------------------------------------------
    // Every number here comes from latest.triage / latest.passed
    // (App\Support\SeoAuditor::triage()/passedChecks(), proxied verbatim
    // from the SaaS). Nothing is recomputed, so this can never disagree
    // with the dashboard's resources/views/projects/partials/
    // seo-audit-report.blade.php, which reads the same functions.

    const renderHero = (latest, triage) => {
      const lever = triage.lever;
      const ringPct = Math.max(0, Math.min(100, Number(latest.score) || 0));

      let html = '<div class="cbl-card cbl-audit-hero">';

      html +=
        '<div class="cbl-audit-ring-wrap">' +
        // The ring must READ the score, not just colour it. A solid disc was
        // always a full circle, so a 60 looked identical to a 100. Conic
        // gradient fills exactly score%, starting at 12 o'clock.
        '<div class="cbl-audit-ring" style="background: conic-gradient(' +
        TONE[triage.score_tone] + ' 0 ' + ringPct + '%, var(--cbl-bg-tertiary) ' + ringPct + '% 100%)">' +
        '<div class="cbl-audit-ring-in"><b>' + esc(latest.score) + '</b><span>' + esc(params.auditOf100) + '</span></div>' +
        '</div></div>';

      html += '<div class="cbl-audit-hero-body">';

      if (lever) {
        html += '<div class="cbl-audit-eyebrow">' + esc(params.auditLever) + '</div>';
        html += '<h2 class="cbl-audit-lever">' +
          esc(fmt(params.auditLeverLine, [lever.label, lever.lost.toFixed(1), triage.total_lost.toFixed(1)])) +
          '</h2>';
        html += '<p class="cbl-audit-lever-lead">' + esc(lever.lead) + '</p>';
      } else {
        html += '<div class="cbl-audit-eyebrow">' + esc(params.auditScoreLabel) + '</div>';
        html += '<h2 class="cbl-audit-lever">' + esc(params.auditPerfectScore) + '</h2>';
      }

      html +=
        '<div class="cbl-audit-figures">' +
        '<div><b>' + esc(triage.issue_types) + '</b><span>' +
        esc(auditPlural(triage.issue_types, params.auditIssueType, params.auditIssueTypes)) + '</span></div>' +
        '<div><b>' + esc(fmtNum(latest.pages_audited)) + '</b><span>' + esc(params.auditPagesLabel) + '</span></div>' +
        '<div><b class="cbl-audit-good-fg">' + esc((latest.passed || []).length) + '</b><span>' +
        esc(params.auditPassedLabel) + '</span></div>' +
        '</div>';

      html += '</div></div>';

      return html;
    };

    const renderBars = triage => {
      // The block's own track color is the "red is unearned" cue
      // auditPointsHint promises: light red once this category has lost
      // any points, light grey otherwise. The decision is bar.lost > 0,
      // already in the payload -- not a new threshold, and not one of
      // triage()'s named tones (those color the filled height instead).
      const blocks = triage.bars
        .map(b =>
          '<div class="cbl-audit-block" style="flex:' + (Number(b.weight) || 0) + ';background:' +
          (b.lost > 0 ? 'var(--cbl-red-bg)' : 'var(--cbl-bg-tertiary)') + '" title="' +
          esc(b.label + ' ' + b.score + '/100') + '">' +
          '<div style="height:' + (Number(b.score) || 0) + '%;background:' + TONE[b.tone] + '"></div></div>'
        )
        .join('');

      const labels = triage.bars
        .map(b =>
          '<div class="cbl-audit-block-label" style="flex:' + (Number(b.weight) || 0) + '">' +
          '<span>' + esc(b.label) + '</span>' +
          // The score reads directly. A loss figure made the reader infer
          // health from a negative, and a perfect category showed "0.0",
          // which looks like a zero score rather than a full one.
          '<em style="color:' + TONE[b.tone] + '">' + esc(b.score) + '/100</em>' +
          '</div>'
        )
        .join('');

      return (
        '<div class="cbl-card cbl-audit-points">' +
        '<div class="cbl-audit-points-head"><b>' + esc(params.auditPointsWent) + '</b><span>' + esc(params.auditPointsHint) + '</span></div>' +
        '<div class="cbl-audit-blocks">' + blocks + '</div>' +
        '<div class="cbl-audit-block-labels">' + labels + '</div>' +
        '</div>'
      );
    };

    // Mirrors SeoFix::FIXABLE on the server. An issue not in this list gets
    // no button, and the server refuses it anyway with a reason.
    const FIXABLE = [
      'title-missing', 'title-long', 'title-duplicate',
      'desc-missing', 'desc-duplicate', 'desc-long'
    ];

    // Suggestions awaiting a decision. Nothing here has touched the site yet.
    const renderFixes = issue => {
      const state = issueFixes[issue.id];

      if (!state || !state.suggestions || !state.suggestions.length) {
        return '';
      }

      const rows = state.suggestions.map(fix =>
        '<div class="cbl-audit-sug" data-fix="' + esc(fix.id) + '">' +
        '<div class="cbl-audit-sug-url">' + esc(fix.url) + '</div>' +
        (fix.current
          ? '<div class="cbl-audit-sug-was"><span>' + esc(params.auditFixNow) + '</span>' + esc(fix.current) + '</div>'
          : '') +
        '<div class="cbl-audit-sug-new"><span>' + esc(params.auditFixNew) + '</span>' +
        '<textarea class="cbl-audit-sug-text" rows="2">' + esc(fix.suggested) + '</textarea></div>' +
        '<div class="cbl-audit-sug-actions">' +
        '<button type="button" class="cbl-btn cbl-btn-sm cbl-btn-primary cbl-audit-sug-apply"' +
        ' data-fix="' + esc(fix.id) + '" data-url="' + esc(fix.url) + '" data-field="' + esc(fix.field) + '">' +
        esc(params.auditFixApply) + '</button>' +
        '<button type="button" class="cbl-btn cbl-btn-sm cbl-audit-sug-dismiss" data-fix="' + esc(fix.id) + '">' +
        esc(params.auditFixDismiss) + '</button>' +
        '<span class="cbl-audit-sug-msg"></span>' +
        '</div></div>'
      ).join('');

      return '<div class="cbl-audit-sugs">' +
        '<div class="cbl-audit-sugs-head">' + esc(params.auditFixReview) +
        (state.target ? ' <em>' + esc(state.target) + '</em>' : '') + '</div>' +
        rows + '</div>';
    };

    const renderQueueIssue = issue => {
      const open = !!openRows[issue.id];
      const sev = SEV[issue.severity] || SEV.notice;
      // The polled state carries a 10-URL preview. Once the full list has
      // been fetched for this issue it replaces the preview outright.
      const full = issuePages[issue.id];
      const shown = full ? full.pages : (issue.pages || []);
      const hidden = issue.count - shown.length;
      const pages = shown
        .map(u => '<div class="cbl-audit-page-url">' + esc(u) + '</div>')
        .join('');
      // An audit run before full lists were stored only kept a sample, so
      // offering "Show all" again after fetching would loop forever. Say so.
      const more = hidden <= 0
        ? ''
        : '<div class="cbl-audit-more">' +
          esc(fmt(params.auditAndMore, [fmtNum(hidden)])) +
          (full && full.truncated
            ? ' <span class="cbl-audit-sample">' + esc(params.auditPagesSample) + '</span>'
            : ' <button type="button" class="cbl-audit-showall" data-issue="' + esc(issue.id) + '">' +
              esc(fmt(params.auditShowAllPages, [fmtNum(issue.count)])) + '</button>') +
          '</div>';

      let html = '<div class="cbl-audit-row-wrap">';
      html +=
        '<div class="cbl-audit-row" data-issue="' + esc(issue.id) + '">' +
        '<span class="cbl-audit-caret' + (open ? ' is-open' : '') + '">&#9654;</span>' +
        '<span class="cbl-audit-sev" style="color:' + sev.fg + ';background:' + sev.bg + '">' + esc(String(issue.severity || '').toUpperCase()) + '</span>' +
        '<span class="cbl-audit-row-title">' + esc(issue.title) + '</span>' +
        '<span class="cbl-audit-row-pages">' + esc(fmtNum(issue.count)) + ' ' +
        esc(auditPlural(issue.count, params.auditPageUnit, params.auditPagesUnit)) + '</span>' +
        '</div>';

      if (open) {
        html +=
          '<div class="cbl-audit-row-body">' +
          '<p class="cbl-audit-fix">' + esc(issue.fix) + '</p>' +
          (pages
          ? '<div class="cbl-audit-urls' + (shown.length > 12 ? ' is-long' : '') + '">' + pages + more + '</div>'
          : '') +
          '<div class="cbl-audit-row-actions">' +
          (FIXABLE.indexOf(issue.id) !== -1
            ? '<button type="button" class="cbl-btn cbl-btn-sm cbl-btn-primary cbl-audit-aifix" data-issue="' +
              esc(issue.id) + '">' + esc(params.auditFixWithAi) + '</button>'
            : '') +
          '<button type="button" class="cbl-btn cbl-btn-sm cbl-audit-copy-urls" data-issue="' + esc(issue.id) + '">' +
          esc(params.auditCopyUrls) + '</button>' +
          '</div>' +
          renderFixes(issue) +
          '</div>';
      }

      return html + '</div>';
    };

    const renderQueue = (triage, pagesAudited) => {
      if (!triage.groups.length) {
        return '';
      }

      const groups = triage.groups
        .map(g =>
          '<div class="cbl-card cbl-audit-group">' +
          '<div class="cbl-audit-group-head">' +
          '<div><b>' + esc(g.label) + '</b><span>' + esc(g.issues.length) + ' ' +
          esc(auditPlural(g.issues.length, params.auditIssueType, params.auditIssueTypes)) + '</span></div>' +
          '<div class="cbl-audit-group-score">' +
          '<em style="color:' + (RECOVER[g.recover_tone] || RECOVER.muted) + '">' + esc(fmt(params.auditRecover, [g.lost.toFixed(1)])) + '</em>' +
          '<span class="cbl-audit-mini"><i style="width:' + (Number(g.score) || 0) + '%;background:' + TONE[g.tone] + '"></i></span>' +
          '<b style="color:' + TONE[g.tone] + '">' + esc(g.score) + '</b>' +
          '</div></div>' +
          g.issues.map(renderQueueIssue).join('') +
          '</div>'
        )
        .join('');

      // Mirrors the dashboard's queue subhead: "Grouped by recoverable
      // points · N issue types across N pages" (seo-audit-report.blade.php).
      const issueTypesPhrase = fmtNum(triage.issue_types) + ' ' +
        auditPlural(triage.issue_types, params.auditIssueType, params.auditIssueTypes);
      const pagesPhrase = fmtNum(pagesAudited) + ' ' +
        auditPlural(pagesAudited, params.auditPageUnit, params.auditPagesUnit);
      const queueHint = fmt(params.auditQueueHint, [issueTypesPhrase, pagesPhrase]);

      return (
        '<div class="cbl-audit-queue">' +
        '<div class="cbl-audit-queue-head"><b>' + esc(params.auditQueue) + '</b><span>' + esc(queueHint) + '</span></div>' +
        groups +
        '</div>'
      );
    };

    const renderFooter = (latest, results) => {
      const passed = latest.passed || [];
      const external = results.external || {};
      const metrics = [];

      // The real results.external shape has three groups (authority,
      // visibility, lighthouse), not the 3-metric sample this task's
      // brief sketched. This mirrors what the dashboard partial's
      // footer actually renders: same fields, same order, same labels,
      // so both products report the same live data for the same audit.
      if (external.authority) {
        metrics.push([fmtNum(external.authority.referring_domains), params.auditRefDomains]);
        metrics.push([fmtNum(external.authority.backlinks), params.auditBacklinks]);
        metrics.push([String(external.authority.rank), params.auditDomainRank]);
      }
      if (external.visibility) {
        metrics.push([fmtNum(external.visibility.keywords), params.auditKeywords]);
        metrics.push([fmtNum(external.visibility.traffic), params.auditTraffic]);
      }
      if (external.lighthouse) {
        // The dashboard colors this one metric by a plain 90/50 split
        // that is NOT one of triage()'s named tones -- it is computed
        // inline there too. Simplest is to leave it uncolored here
        // rather than re-encode that threshold a second time.
        metrics.push([String(external.lighthouse.performance), params.auditLighthouse]);
        metrics.push([(Number(external.lighthouse.lcp_ms || 0) / 1000).toFixed(1) + 's', params.auditLcp]);
        metrics.push([Number(external.lighthouse.cls || 0).toFixed(2), params.auditCls]);
      }

      let html = '<div class="cbl-audit-footer">';

      html +=
        '<div class="cbl-card cbl-audit-passed">' +
        '<div class="cbl-audit-passed-head" id="wpcbl-audit-passed-toggle">' +
        '<span><i class="cbl-audit-tick">&#10003;</i> ' + esc(fmt(params.auditChecksPassed, [passed.length])) + '</span>' +
        '<em>' + esc(passedOpen ? params.auditHide : params.auditShowAll) + '</em>' +
        '</div>' +
        (passedOpen
          ? '<div class="cbl-audit-chips">' + passed.map(p => '<span>' + esc(p.label) + '</span>').join('') + '</div>'
          : '') +
        '</div>';

      if (metrics.length) {
        html +=
          '<div class="cbl-card cbl-audit-metrics">' +
          '<b>' + esc(params.auditLiveData) + '</b>' +
          '<div class="cbl-audit-metric-grid">' +
          metrics.map(m => '<div><b>' + esc(m[0]) + '</b><span>' + esc(m[1]) + '</span></div>').join('') +
          '</div></div>';
      }

      return html + '</div>';
    };

    // A compact score-and-date strip mirroring the dashboard's Audit
    // history panel (resources/views/projects/tool-seo-audit.blade.php).
    // Same 90/60 tone rule as everywhere else in this report.
    const renderHistory = history => {
      if (history.length < 2) {
        return '';
      }

      const items = history
        .map(past => {
          const score = Number(past.score);
          const tone = score >= 90 ? TONE.good : (score >= 60 ? TONE.warn : TONE.bad);
          const created = new Date(past.created_at);
          const short = isNaN(created.getTime())
            ? ''
            : created.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
          const full = isNaN(created.getTime())
            ? ''
            : created.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

          return '<span class="cbl-audit-history-item" title="' + esc(full) + '">' +
            '<b style="color:' + tone + '">' + esc(Math.round(score)) + '</b> ' + esc(short) + '</span>';
        })
        .join('');

      return (
        '<div class="cbl-card cbl-audit-history">' +
        '<b class="cbl-audit-history-title">' + esc(params.auditHistory) + '</b>' +
        '<div class="cbl-audit-history-list">' + items + '</div>' +
        '</div>'
      );
    };

    const renderReport = state => {
      const latest = state.latest;
      const quota = state.quota || { used: 0, limit: 0 };
      const spent = quota.used >= quota.limit;

      let html = '';

      if (auditNotice) {
        html += '<div class="cbl-audit-notice cbl-audit-notice-warn">' + esc(auditNotice) + '</div>';
      }

      // Quota lives in the topbar beside the page title, not in the report
      // body. Plain state. The upgrade link appears ONLY for a free account
      // that is out of audits: a paying customer must never be asked to
      // upgrade for something their plan already covers.
      $('#wpcbl-audit-quota').html(
        esc(quota.used) + ' of ' + esc(quota.limit) + ' ' + esc(params.auditQuotaUsed) +
        (spent && state.plan === 'free'
          ? ' <a class="cbl-btn cbl-btn-sm" href="' + esc(params.upgradeUrl) + '">' + esc(params.auditUpgrade) + '</a>'
          : '')
      );

      if (!latest) {
        auditContent.html(html + '<div class="cbl-card cbl-audit-empty">' + esc(params.auditNone) + '</div>');
        return;
      }

      if (latest.status === 'pending' || latest.status === 'running') {
        // An audit takes a minute or two and polling backs off to 15s, so the
        // card has to look alive on its own between refreshes.
        auditContent.html(
          html +
          '<div class="cbl-card cbl-audit-running">' +
          '<div class="cbl-audit-running-head">' +
          '<span class="cbl-audit-running-dot" aria-hidden="true"></span>' +
          '<div class="cbl-audit-running-text">' +
          '<b>' + esc(params.auditRunning) + '</b>' +
          '<span>' + esc(params.auditRunningHint) + '</span>' +
          '</div></div>' +
          '<div class="cbl-audit-running-bar" role="progressbar" aria-label="' +
          esc(params.auditRunning) + '"><span></span></div>' +
          '</div>'
        );
        return;
      }

      if (latest.status === 'failed') {
        html += '<div class="cbl-audit-notice cbl-audit-notice-warn">' + esc(params.auditFailed) + '</div>';
        auditContent.html(html);
        return;
      }

      const results = latest.results || {};
      const triage = latest.triage || { bars: [], groups: [], lever: null, total_lost: 0, issue_types: 0, score_tone: 'good' };

      // One bar: who and when on the left, what you can do with it on the
      // right. The domain leads because a shared report can be read by
      // someone who does not know which site it describes.
      html += '<div class="cbl-audit-bar">';
      html += '<div class="cbl-audit-bar-id">';
      html += '<b>' + esc(state.project.domain) + '</b>';
      html += '<span>' +
        esc(params.auditLastAudit) + ' ' + esc(auditTimeAgo(latest.created_at)) +
        ' &middot; ' + esc(fmtNum(latest.pages_audited)) + ' ' +
        esc(auditPlural(latest.pages_audited, params.auditPageUnit, params.auditPagesUnit)) +
        ' &middot; ' + esc(fmtNum(triage.issue_types)) + ' ' +
        esc(auditPlural(triage.issue_types, params.auditIssueType, params.auditIssueTypes)) +
        '</span>';

      if (latest.pages_submitted > latest.pages_audited) {
        html += '<span class="cbl-audit-capped">' +
          esc(fmt(params.auditPagesCapped, [latest.pages_audited, latest.pages_submitted])) + '</span>';
      }

      html += '</div>';

      html += '<div class="cbl-audit-bar-actions">';
      html += '<a class="cbl-btn" href="' + esc(pdfBase + '&audit_id=' + encodeURIComponent(latest.id)) + '">' + esc(params.auditPdf) + '</a>';
      html += '<button type="button" class="cbl-btn" id="wpcbl-audit-share" data-audit="' + esc(latest.id) + '">' +
        esc(latest.share_url ? params.auditShareOff : params.auditShareOn) + '</button>';
      html += '</div></div>';

      if (latest.share_url) {
        html +=
          '<div class="cbl-card cbl-audit-share-box">' +
          '<input type="text" readonly value="' + esc(latest.share_url) + '" id="wpcbl-audit-share-link" />' +
          '<button type="button" class="cbl-btn" id="wpcbl-audit-copy">' + esc(params.auditCopy) + '</button>' +
          '</div>';
      }

      html += renderHero(latest, triage);
      html += renderBars(triage);
      html += renderQueue(triage, latest.pages_audited);
      html += renderFooter(latest, results);
      html += renderHistory(state.history || []);

      auditContent.html(html);
    };

    const auditRender = state => {
      const incomingId = state.latest ? state.latest.id : null;
      if (incomingId !== renderedAuditId) {
        issuePages = {};
        issueFixes = {};
        openRows = {};
        renderedAuditId = incomingId;
      }

      auditState = state;
      auditSkeleton.hide();
      auditErrorBox.hide();
      auditControls.show();
      auditContent.show();
      renderReport(state);

      const status = state.latest && state.latest.status;
      if (status === 'pending' || status === 'running') {
        $('#wpcbl-audit-run').prop('disabled', true).text(params.auditRunningLabel);
        auditSchedule();
      } else {
        auditStop();
        auditPolls = 0;
        const spent = state.quota && state.quota.used >= state.quota.limit;
        $('#wpcbl-audit-run').prop('disabled', !!spent).text(params.auditRunLabel);
      }
    };

    const auditLoad = fresh => {
      auditPost('wpcbl_seo_audit_state', fresh ? { fresh: 1 } : {})
        .done(response => auditRender(response.data))
        .fail(jqXHR => {
          auditStop();
          auditSkeleton.hide();
          auditContent.hide();
          auditErrorBox.find('p').text(auditErrorText(jqXHR));
          auditErrorBox.show();
        });
    };

    // wp_kses() in layout-open.php strips the `style` attribute from the
    // topbar controls markup (span/input aren't allowed to carry it), so
    // the server-rendered `display:none` on #wpcbl-audit-controls and
    // #wpcbl-audit-url never reaches the browser. Set the real initial
    // state here instead of trusting the markup.
    auditControls.hide();

    const auditSyncMode = () => {
      const page = $('#wpcbl-audit-mode').val() === 'page';
      $('#wpcbl-audit-url').toggle(page);
      $('#wpcbl-audit-params-wrap').toggle(!page);
    };
    auditSyncMode();

    $('#wpcbl-audit-mode').on('change', auditSyncMode);

    // The skip option renders as a bordered chip, so it reads as clickable.
    // Remove URL parameters is the Settings page option, saved as soon as
    // it changes. wp_kses strips <label> from the topbar, so the whole chip
    // toggles the box here. The info icon only explains.
    function saveUrlParams(enabled) {
      auditPost('wpcbl_save_url_params', { enabled: enabled ? 1 : 0 });
    }

    $('#wpcbl-audit-params-wrap').on('click', function (event) {
      if (event.target.id === 'wpcbl-audit-params' || $(event.target).closest('.cbl-info').length) {
        return;
      }
      const box = $('#wpcbl-audit-params');
      box.prop('checked', !box.prop('checked'));
      saveUrlParams(box.prop('checked'));
    });

    $('#wpcbl-audit-params').on('change', function () {
      saveUrlParams($(this).prop('checked'));
    });

    $('#wpcbl-audit-run').on('click', function () {
      const mode = $('#wpcbl-audit-mode').val();
      const data = { mode: mode };

      if (mode === 'page') {
        const url = $.trim($('#wpcbl-audit-url').val());
        if (!url) {
          $('#wpcbl-audit-url').focus();
          return;
        }
        data.url = url;
      }

      auditNotice = null;
      $(this).prop('disabled', true).text(params.auditRunningLabel);

      auditPost('wpcbl_seo_audit_run', data)
        .done(() => {
          auditPolls = 0;
          auditLoad(true);
        })
        .fail(jqXHR => {
          auditNotice = auditErrorText(jqXHR);
          $('#wpcbl-audit-run').prop('disabled', false).text(params.auditRunLabel);
          if (auditState) {
            renderReport(auditState);
          }
        });
    });

    auditContent.on('click', '#wpcbl-audit-share', function () {
      const id = $(this).data('audit');
      $(this).prop('disabled', true);
      auditPost('wpcbl_seo_audit_share', { audit_id: id })
        .done(() => auditLoad(true))
        .fail(jqXHR => {
          auditNotice = auditErrorText(jqXHR);
          $(this).prop('disabled', false);
          if (auditState) {
            renderReport(auditState);
          }
        });
    });

    auditContent.on('click', '#wpcbl-audit-copy', function () {
      const field = document.getElementById('wpcbl-audit-share-link');
      field.select();
      document.execCommand('copy');
      $(this).text(params.auditCopied);
      setTimeout(() => $(this).text(params.auditCopy), 1600);
    });

    auditContent.on('click', '.cbl-audit-row', function () {
      const id = $(this).data('issue');
      openRows[id] = !openRows[id];
      // Local state only. Re-render from cache, never refetch.
      if (auditState) {
        renderReport(auditState);
      }
    });

    auditContent.on('click', '#wpcbl-audit-passed-toggle', function () {
      passedOpen = !passedOpen;
      if (auditState) {
        renderReport(auditState);
      }
    });

    // Fetch every affected URL for one issue, once, then re-render from cache.
    const auditLoadPages = (issueId, done) => {
      if (issuePages[issueId]) {
        done(issuePages[issueId]);
        return;
      }
      auditPost('wpcbl_seo_audit_issue_pages', {
        audit_id: auditState.latest.id,
        issue_id: issueId
      })
        .done(response => {
          issuePages[issueId] = response.data;
          done(response.data);
        })
        .fail(jqXHR => {
          auditNotice = auditErrorText(jqXHR);
          renderReport(auditState);
        });
    };

    auditContent.on('click', '.cbl-audit-aifix', function (event) {
      event.stopPropagation();
      const id = $(this).data('issue');
      const btn = $(this);
      btn.prop('disabled', true).text(params.auditFixWorking);

      auditPost('wpcbl_seo_audit_ai_fix', {
        audit_id: auditState.latest.id,
        issue_id: id
      })
        .done(response => {
          issueFixes[id] = response.data;
          renderReport(auditState);
        })
        .fail(jqXHR => {
          // A refusal here is informative (quota, nothing left, not fixable),
          // so it belongs on the row rather than as a page-level error.
          btn.prop('disabled', false).text(params.auditFixWithAi);
          btn.siblings('.cbl-audit-sug-msg').remove();
          btn.after('<span class="cbl-audit-sug-msg is-warn">' + esc(auditErrorText(jqXHR)) + '</span>');
        });
    });

    auditContent.on('click', '.cbl-audit-sug-apply', function (event) {
      event.stopPropagation();
      const btn = $(this);
      const card = btn.closest('.cbl-audit-sug');
      const msg = card.find('.cbl-audit-sug-msg');

      btn.prop('disabled', true).text(params.auditFixWorking);
      msg.removeClass('is-warn').text('');

      auditPost('wpcbl_seo_audit_ai_apply', {
        audit_id: auditState.latest.id,
        fix_id: btn.data('fix'),
        url: btn.data('url'),
        field: btn.data('field'),
        // The reader can edit before applying, so send what is on screen.
        value: card.find('.cbl-audit-sug-text').val()
      })
        .done(response => {
          card.addClass('is-applied');
          card.find('.cbl-audit-sug-actions button').remove();
          msg.text((response.data && response.data.message) || params.auditFixApplied);
        })
        .fail(jqXHR => {
          btn.prop('disabled', false).text(params.auditFixApply);
          msg.addClass('is-warn').text(auditErrorText(jqXHR));
        });
    });

    auditContent.on('click', '.cbl-audit-sug-dismiss', function (event) {
      event.stopPropagation();
      const btn = $(this);
      const card = btn.closest('.cbl-audit-sug');

      auditPost('wpcbl_seo_audit_ai_apply', {
        audit_id: auditState.latest.id,
        fix_id: btn.data('fix'),
        dismiss: 1
      }).always(() => card.slideUp(120, () => card.remove()));
    });

    auditContent.on('click', '.cbl-audit-showall', function (event) {
      event.stopPropagation();
      const id = $(this).data('issue');
      $(this).prop('disabled', true).text(params.auditRunningLabel);
      auditLoadPages(id, () => renderReport(auditState));
    });

    auditContent.on('click', '.cbl-audit-copy-urls', function (event) {
      event.stopPropagation();
      const id = $(this).data('issue');
      const btn = $(this);
      // Copy every affected URL, not the preview. Copying 10 while the panel
      // said "and 308 more" was simply wrong.
      auditLoadPages(id, data => {
        const field = $('<textarea>').val((data.pages || []).join('\n')).appendTo('body').select();
        document.execCommand('copy');
        field.remove();
        btn.text(params.auditCopied);
        setTimeout(() => btn.text(params.auditCopyUrls), 1600);
      });
    });

    $('#wpcbl-audit-retry').on('click', () => {
      auditErrorBox.hide();
      auditSkeleton.show();
      auditLoad(true);
    });

    auditLoad(false);
  }
})(jQuery);
