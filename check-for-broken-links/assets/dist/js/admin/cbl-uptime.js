"use strict";

/* jshint esversion: 6 */

/**
 * Uptime Monitor page.
 *
 * Split out of check-for-broken-links-admin-scripts.js in 3.1.7, so the
 * other plugin pages stop loading it. Depends on that script for the
 * universal modal (window.wpcblModal).
 */

($ => {
  const wpcbl_check_for_broken_links_params = window.wpcblUptime || {};
  const cblModal = window.wpcblModal;

  // ===== Uptime Monitor =====
  const uptimeApp = $('#wpcbl-uptime-app');
  if (uptimeApp.length) {
    const params = wpcbl_check_for_broken_links_params;
    const uptimeSkeleton = $('#wpcbl-uptime-skeleton');
    const uptimeErrorBox = $('#wpcbl-uptime-error');
    const uptimeContent = $('#wpcbl-uptime-content');

    let uptimeState = null;
    let uptimePolls = 0;
    let uptimePollTimer = null;
    let uptimeEditingId = null;
    let uptimeStatusMessage = null;
    let uptimeStatusWarning = false;

    const uptimePost = (action, data) =>
      $.post(params.ajaxUrl, Object.assign({ action: action, nonce: params.nonce }, data || {}));

    const uptimeResponseError = jqXHR =>
      jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : params.uptimeLoadError;

    const uptimeShowStatus = (message, isWarning) => {
      uptimeStatusMessage = message || null;
      uptimeStatusWarning = !!isWarning;
      const status = $('#wpcbl-uptime-status');
      if (!status.length) {
        return;
      }
      status.text(message || '').toggleClass('cbl-rank-status-warning', !!isWarning);
    };

    // Clears the persisted status message. Call this when a new user
    // action starts so a stale message from a previous action doesn't
    // reappear after the next renderUptime().
    const uptimeClearStatus = () => {
      uptimeStatusMessage = null;
      uptimeStatusWarning = false;
    };

    const showUptimeError = message => {
      if (uptimePollTimer) {
        clearTimeout(uptimePollTimer);
        uptimePollTimer = null;
      }
      uptimeSkeleton.hide();
      uptimeContent.empty();
      uptimeErrorBox.find('p').text(message || params.uptimeLoadError);
      uptimeErrorBox.show();
    };

    // ---- Formatting helpers ----

    // Largest whole unit (day/hour/minute/second) that divides evenly,
    // falling back to seconds. Used both for the interval <select>
    // options (exact values from the payload) and for "ago"/duration text.
    const uptimeDurationParts = seconds => {
      const n = Math.max(0, Math.round(Number(seconds) || 0));
      if (n > 0 && n % 86400 === 0) {
        const v = n / 86400;
        return { value: v, unit: 1 === v ? params.uptimeIntervalDay : params.uptimeIntervalDays };
      }
      if (n > 0 && n % 3600 === 0) {
        const v = n / 3600;
        return { value: v, unit: 1 === v ? params.uptimeIntervalHour : params.uptimeIntervalHours };
      }
      if (n > 0 && n % 60 === 0) {
        const v = n / 60;
        return { value: v, unit: 1 === v ? params.uptimeIntervalMinute : params.uptimeIntervalMinutes };
      }
      return { value: n, unit: 1 === n ? params.uptimeIntervalSecond : params.uptimeIntervalSeconds };
    };

    const uptimeFormatInterval = seconds => {
      const parts = uptimeDurationParts(seconds);
      return parts.value + ' ' + parts.unit;
    };

    // Largest whole unit only, rounded down. Incident durations and
    // relative-time text don't need sub-unit precision.
    const uptimeFormatDuration = seconds => {
      const n = Math.max(0, Math.round(Number(seconds) || 0));
      if (n >= 86400) {
        const v = Math.floor(n / 86400);
        return v + ' ' + (1 === v ? params.uptimeIntervalDay : params.uptimeIntervalDays);
      }
      if (n >= 3600) {
        const v = Math.floor(n / 3600);
        return v + ' ' + (1 === v ? params.uptimeIntervalHour : params.uptimeIntervalHours);
      }
      if (n >= 60) {
        const v = Math.floor(n / 60);
        return v + ' ' + (1 === v ? params.uptimeIntervalMinute : params.uptimeIntervalMinutes);
      }
      return n + ' ' + (1 === n ? params.uptimeIntervalSecond : params.uptimeIntervalSeconds);
    };

    const uptimeRelativeTime = iso => {
      if (!iso) {
        return params.uptimeNeverChecked;
      }
      const then = new Date(iso).getTime();
      if (isNaN(then)) {
        return params.uptimeNeverChecked;
      }
      const seconds = Math.max(0, Math.round((Date.now() - then) / 1000));
      if (seconds < 30) {
        return params.uptimeJustNow;
      }
      return params.uptimeAgo.replace('%1$s', uptimeFormatDuration(seconds));
    };

    const uptimePercent = value =>
      null === value || undefined === value || '' === value ? '–' : Math.round(Number(value)) + '%';

    const uptimeNormalizeUrl = url =>
      String(url || '')
        .trim()
        .toLowerCase()
        .replace(/^https?:\/\//, '')
        .replace(/\/+$/, '');

    const uptimeHasSiteMonitor = () => {
      if (!uptimeState) {
        return false;
      }
      const site = uptimeNormalizeUrl(uptimeState.site_url);
      return (uptimeState.monitors || []).some(m => 'http' === m.type && uptimeNormalizeUrl(m.url) === site);
    };

    const uptimeHeartbeatMonitor = () => (uptimeState.monitors || []).find(m => 'heartbeat' === m.type) || null;

    const uptimeTypeLabel = type => {
      if ('heartbeat' === type) {
        return params.uptimeTypeHeartbeat;
      }
      if ('http' === type) {
        return params.uptimeTypeHttp;
      }
      if ('ssl' === type) {
        return params.uptimeTypeSsl;
      }
      if ('domain' === type) {
        return params.uptimeTypeDomain;
      }
      return type;
    };

    // ssl/domain monitors don't poll uptime at all, they watch a
    // certificate/registration date, so their row shows an expiry date
    // (from expires_at) instead of the uptime %/avg response cells.
    const uptimeIsExpiryType = type => 'ssl' === type || 'domain' === type;

    const uptimeExpiryText = monitor => {
      if (!monitor.expires_at) {
        return params.uptimeExpiryChecking;
      }
      const expires = new Date(monitor.expires_at);
      if (isNaN(expires.getTime())) {
        return params.uptimeExpiryChecking;
      }
      return expires.toLocaleDateString();
    };

    const uptimeStatusLabel = status => {
      if ('up' === status) {
        return params.uptimeStatusUp;
      }
      if ('down' === status) {
        return params.uptimeStatusDown;
      }
      if ('paused' === status) {
        return params.uptimeStatusPaused;
      }
      return params.uptimeStatusPending;
    };

    // ---- Loading ----

    const scheduleUptimePoll = () => {
      if (uptimePollTimer) {
        clearTimeout(uptimePollTimer);
        uptimePollTimer = null;
      }
      const pending = uptimeState && (uptimeState.monitors || []).some(m => 'pending' === m.status);
      if (pending && uptimePolls < 40) {
        uptimePollTimer = setTimeout(() => {
          uptimePolls++;
          loadUptime(true);
        }, 15000);
      }
    };

    const loadUptime = fresh => {
      uptimePost('wpcbl_uptime_state', fresh ? { fresh: '1' } : {})
        .done(res => {
          if (!res || !res.success) {
            return showUptimeError(res && res.data ? res.data : params.uptimeLoadError);
          }
          uptimeState = res.data;
          renderUptime();
          scheduleUptimePoll();
        })
        .fail(jqXHR => {
          showUptimeError(uptimeResponseError(jqXHR));
        });
    };

    // ---- Monitors table ----

    const buildUptimeTableHead = () => {
      const thead = $('<thead></thead>');
      const tr = $('<tr></tr>');
      const cols = params.uptimeColumns || {};
      ['name', 'uptime_day', 'uptime_month', 'avg_ms', 'last_checked'].forEach(key => {
        tr.append($('<th></th>').addClass('cbl-uptime-col-' + key.replace(/_/g, '-')).text(cols[key] || key));
      });
      tr.append($('<th class="cbl-uptime-col-actions"></th>'));
      thead.append(tr);
      return thead;
    };

    const buildUptimeRow = monitor => {
      const tr = $('<tr></tr>').attr('data-wpcbl-uptime-id', monitor.id);

      const tdName = $('<td class="cbl-uptime-col-name"></td>');
      tdName.append(
        $('<span class="cbl-uptime-dot"></span>')
          .addClass('cbl-uptime-dot-' + monitor.status)
          .attr('title', uptimeStatusLabel(monitor.status))
          .attr('role', 'img')
          .attr('aria-label', uptimeStatusLabel(monitor.status))
      );
      const nameWrap = $('<span class="cbl-uptime-name-wrap"></span>');
      nameWrap.append($('<span class="cbl-uptime-name"></span>').text(monitor.name || ''));
      nameWrap.append($('<span class="cbl-uptime-type"></span>').text(uptimeTypeLabel(monitor.type)));
      tdName.append(nameWrap);
      tr.append(tdName);

      if (uptimeIsExpiryType(monitor.type)) {
        tr.append(
          $('<td class="cbl-uptime-col-expiry" colspan="3"></td>').text(uptimeExpiryText(monitor))
        );
      } else {
        tr.append($('<td class="cbl-uptime-col-uptime-day"></td>').text(uptimePercent(monitor.uptime_day)));
        tr.append($('<td class="cbl-uptime-col-uptime-month"></td>').text(uptimePercent(monitor.uptime_month)));
        tr.append(
          $('<td class="cbl-uptime-col-avg-ms"></td>').text(
            null === monitor.avg_ms || undefined === monitor.avg_ms || '' === monitor.avg_ms
              ? '–'
              : Math.round(Number(monitor.avg_ms)) + ' ms'
          )
        );
      }
      tr.append(
        $('<td class="cbl-uptime-col-last-checked"></td>').text(uptimeRelativeTime(monitor.last_checked_at))
      );

      const tdActions = $('<td class="cbl-uptime-col-actions"></td>');
      const actionsWrap = $('<div class="cbl-uptime-actions"></div>');
      actionsWrap.append(
        $('<button type="button" class="cbl-btn cbl-btn-small cbl-uptime-edit"></button>').text(params.uptimeActionEdit)
      );
      actionsWrap.append(
        $('<button type="button" class="cbl-btn cbl-btn-small cbl-uptime-toggle"></button>').text(
          'paused' === monitor.status ? params.uptimeActionResume : params.uptimeActionPause
        )
      );
      actionsWrap.append(
        $('<button type="button" class="cbl-btn cbl-btn-small cbl-btn-danger cbl-uptime-delete"></button>').text(
          params.uptimeActionDelete
        )
      );
      tdActions.append(actionsWrap);
      tr.append(tdActions);

      return tr;
    };

    const buildUptimeEditRow = monitor => {
      const tr = $('<tr class="cbl-uptime-edit-row"></tr>').attr('data-wpcbl-uptime-id', monitor.id);
      const td = $('<td colspan="6"></td>');
      const form = $('<div class="cbl-uptime-edit-form"></div>');

      const nameField = $('<div class="cbl-uptime-edit-field"></div>');
      nameField.append($('<label></label>').text(params.uptimeEditName));
      nameField.append($('<input type="text" class="cbl-uptime-edit-name" />').val(monitor.name || ''));
      form.append(nameField);

      // ssl/domain monitors never get a url field here: their target
      // (the certificate host / registered domain) is set up web-side,
      // not editable from the plugin. Editing them is limited to name
      // plus the generic alert fields below.
      if ('http' === monitor.type) {
        const urlField = $('<div class="cbl-uptime-edit-field"></div>');
        urlField.append($('<label></label>').text(params.uptimeEditUrl));
        urlField.append($('<input type="url" class="cbl-uptime-edit-url" />').val(monitor.url || ''));
        form.append(urlField);
      }

      if ('http' === monitor.type || 'heartbeat' === monitor.type) {
        const options = 'heartbeat' === monitor.type ? uptimeState.heartbeat_intervals : uptimeState.intervals;
        const intervalField = $('<div class="cbl-uptime-edit-field"></div>');
        intervalField.append($('<label></label>').text(params.uptimeEditInterval));
        const intervalSelect = $('<select class="cbl-uptime-edit-interval"></select>');
        (options || []).forEach(seconds => {
          intervalSelect.append($('<option></option>').val(seconds).text(uptimeFormatInterval(seconds)));
        });
        intervalSelect.val(monitor.interval_seconds);
        intervalField.append(intervalSelect);
        form.append(intervalField);
      }

      // heartbeat/ssl/domain monitors don't have a failure-count threshold
      // to tune: a heartbeat incident fires the moment the deadline passes,
      // and ssl/domain incidents fire on the expiry check itself. Only
      // http monitors poll repeatedly and need "alert after N failures".
      if ('http' === monitor.type) {
        const afterField = $('<div class="cbl-uptime-edit-field"></div>');
        afterField.append($('<label></label>').text(params.uptimeEditAlertAfter));
        const afterSelect = $('<select class="cbl-uptime-edit-after"></select>');
        [1, 2, 3, 5].forEach(n => {
          afterSelect.append($('<option></option>').val(n).text(n));
        });
        afterSelect.val(monitor.alert_after_failures || 1);
        afterField.append(afterSelect);
        afterField.append($('<span class="cbl-uptime-edit-after-suffix"></span>').text(params.uptimeEditAlertAfterSuffix));
        form.append(afterField);
      }

      const emailsField = $('<div class="cbl-uptime-edit-field cbl-uptime-edit-field-wide"></div>');
      emailsField.append($('<label></label>').text(params.uptimeEditAlertEmails));
      emailsField.append(
        $('<input type="text" class="cbl-uptime-edit-emails" />')
          .attr('placeholder', params.uptimeEditAlertEmailsPlaceholder)
          .val((monitor.alert_emails || []).join(', '))
      );
      form.append(emailsField);

      const actions = $('<div class="cbl-uptime-edit-actions"></div>');
      actions.append(
        $('<button type="button" class="cbl-btn cbl-btn-primary cbl-btn-small cbl-uptime-save"></button>').text(
          params.uptimeActionSave
        )
      );
      actions.append(
        $('<button type="button" class="cbl-btn cbl-btn-small cbl-uptime-cancel"></button>').text(params.uptimeActionCancel)
      );
      form.append(actions);

      td.append(form);
      tr.append(td);
      return tr;
    };

    // ---- Renderers ----

    const renderMonitorsCard = () => {
      const monitors = uptimeState.monitors || [];
      const quota = uptimeState.quota || { used: 0, limit: 0 };
      const quotaFull = 'number' === typeof quota.limit && quota.limit > 0 && quota.used >= quota.limit;

      const head = $('<div class="cbl-card cbl-rank-head"></div>');
      const headTop = $('<div class="cbl-rank-head-top"></div>');
      headTop.append(
        $('<span class="cbl-rank-badge"></span>').text(
          params.uptimeQuotaText.replace('%1$s', quota.used).replace('%2$s', quota.limit)
        )
      );

      const headActions = $('<div class="cbl-rank-head-actions"></div>');
      if (!uptimeHasSiteMonitor() && !quotaFull) {
        headActions.append(
          $('<button type="button" id="wpcbl-uptime-monitor-site" class="cbl-btn cbl-btn-primary"></button>').text(
            params.uptimeMonitorThis
          )
        );
      }
      // The Roxi rule: an upgrade prompt only ever appears for a plan the
      // upgrade would actually help. Agency is the top tier, so it never
      // sees this even at quota.
      if (quotaFull && 'agency' !== uptimeState.plan && params.uptimeUpgradeUrl) {
        headActions.append(
          $('<a class="cbl-btn cbl-btn-primary"></a>').attr('href', params.uptimeUpgradeUrl).text(params.uptimeGetMore)
        );
      }
      headTop.append(headActions);
      head.append(headTop);
      head.append($('<p id="wpcbl-uptime-status" class="cbl-rank-status"></p>'));
      uptimeContent.append(head);

      if (!monitors.length) {
        const empty = $('<div class="cbl-card cbl-rank-empty"></div>');
        empty.append($('<h2></h2>').text(params.uptimeEmptyTitle));
        empty.append($('<p></p>').text(params.uptimeEmptyMessage));
        uptimeContent.append(empty);
        return;
      }

      const wrap = $('<div class="cbl-rank-table-wrap"></div>');
      const table = $('<table class="cbl-rank-table cbl-uptime-table"></table>');
      table.append(buildUptimeTableHead());
      const tbody = $('<tbody></tbody>');
      monitors.forEach(monitor => {
        tbody.append(uptimeEditingId === monitor.id ? buildUptimeEditRow(monitor) : buildUptimeRow(monitor));
      });
      table.append(tbody);
      wrap.append(table);
      uptimeContent.append(wrap);
    };

    // Verbatim from the plan: a WP-cron hook that pings the monitor's
    // ping_url on an hourly schedule. GET on purpose, the reliable verb.
    const UPTIME_SNIPPET_TEMPLATE =
      "add_action( 'init', function () {\n" +
      "    if ( ! wp_next_scheduled( 'my_uptime_heartbeat' ) ) {\n" +
      "        wp_schedule_event( time(), 'hourly', 'my_uptime_heartbeat' );\n" +
      '    }\n' +
      '} );\n' +
      "add_action( 'my_uptime_heartbeat', function () {\n" +
      "    wp_remote_get( 'PING_URL_HERE', array( 'blocking' => false, 'timeout' => 5 ) );\n" +
      '} );';

    const renderHeartbeatCard = () => {
      const card = $('<div class="cbl-card cbl-uptime-heartbeat"></div>');
      const monitor = uptimeHeartbeatMonitor();
      card.append($('<h2></h2>').text(params.uptimeHeartbeatTitle));

      if (!monitor) {
        card.append($('<p></p>').text(params.uptimeHeartbeatMessage));
        card.append(
          $('<button type="button" id="wpcbl-uptime-watch-cron" class="cbl-btn cbl-btn-primary"></button>').text(
            params.uptimeHeartbeatButton
          )
        );
      } else {
        card.append($('<p></p>').text(params.uptimeSnippetIntro));

        const urlWrap = $('<div class="cbl-rank-settings-share-url"></div>');
        urlWrap.append($('<input type="text" class="cbl-uptime-ping-url" readonly="readonly" />').val(monitor.ping_url || ''));
        urlWrap.append(
          $('<button type="button" class="cbl-btn cbl-uptime-copy-ping"></button>').text(params.uptimeCopyLink)
        );
        card.append(urlWrap);

        const snippet = UPTIME_SNIPPET_TEMPLATE.replace('PING_URL_HERE', monitor.ping_url || '');
        card.append($('<pre class="cbl-uptime-snippet"></pre>').text(snippet));
      }

      uptimeContent.append(card);
    };

    const buildIncidentRow = incident => {
      const tr = $('<tr></tr>');
      tr.append(
        $('<td></td>').text(incident.started_at ? new Date(incident.started_at).toLocaleString() : '–')
      );
      tr.append($('<td></td>').text(incident.monitor_name || '–'));
      tr.append($('<td></td>').text(incident.cause || '–'));

      let duration = params.uptimeIncidentOngoing;
      if (incident.ended_at) {
        const start = new Date(incident.started_at).getTime();
        const end = new Date(incident.ended_at).getTime();
        duration = !isNaN(start) && !isNaN(end) && end > start ? uptimeFormatDuration((end - start) / 1000) : '–';
      }
      tr.append($('<td></td>').text(duration));

      return tr;
    };

    const renderIncidentsCard = () => {
      const card = $('<div class="cbl-card cbl-uptime-incidents"></div>');
      card.append($('<h2></h2>').text(params.uptimeIncidentsTitle));
      const incidents = uptimeState.incidents || [];

      if (!incidents.length) {
        card.append($('<p class="cbl-uptime-incidents-empty"></p>').text(params.uptimeIncidentsEmpty));
      } else {
        const wrap = $('<div class="cbl-rank-table-wrap"></div>');
        const table = $('<table class="cbl-rank-table cbl-uptime-incidents-table"></table>');
        const thead = $('<thead></thead>');
        const headRow = $('<tr></tr>');
        [
          params.uptimeIncidentsStarted,
          params.uptimeIncidentsMonitor,
          params.uptimeIncidentsCause,
          params.uptimeIncidentsDuration
        ].forEach(label => headRow.append($('<th></th>').text(label)));
        thead.append(headRow);
        table.append(thead);
        const tbody = $('<tbody></tbody>');
        incidents.forEach(incident => tbody.append(buildIncidentRow(incident)));
        table.append(tbody);
        wrap.append(table);
        card.append(wrap);
      }

      uptimeContent.append(card);
    };

    const renderUptime = () => {
      uptimeSkeleton.hide();
      uptimeErrorBox.hide();
      uptimeContent.empty().show();
      renderMonitorsCard();
      renderHeartbeatCard();
      renderIncidentsCard();
      // #wpcbl-uptime-status is rebuilt by renderMonitorsCard() above,
      // which wipes out any message an action just showed. Re-apply the
      // last message (if any) so it survives the loadUptime(true)
      // re-render that follows every action.
      if (uptimeStatusMessage) {
        uptimeShowStatus(uptimeStatusMessage, uptimeStatusWarning);
      }
    };

    // ---- Actions ----

    $(document).on('click', '#wpcbl-uptime-monitor-site', function (event) {
      event.preventDefault();
      if (!uptimeState) {
        return;
      }
      uptimePolls = 0;
      uptimeClearStatus();
      const intervals = uptimeState.intervals || [];
      uptimePost('wpcbl_uptime_create', { type: 'http', interval_seconds: intervals[0] || '' })
        .done(res => {
          if (!res || !res.success) {
            uptimeShowStatus(res && res.data ? res.data : params.uptimeLoadError, true);
          }
        })
        .fail(jqXHR => {
          uptimeShowStatus(uptimeResponseError(jqXHR), true);
        })
        .always(() => {
          loadUptime(true);
        });
    });

    $(document).on('click', '#wpcbl-uptime-watch-cron', function (event) {
      event.preventDefault();
      if (!uptimeState) {
        return;
      }
      uptimePolls = 0;
      uptimeClearStatus();
      uptimePost('wpcbl_uptime_create', {
        type: 'heartbeat',
        name: params.uptimeHeartbeatMonitorName,
        interval_seconds: 3600
      })
        .done(res => {
          if (!res || !res.success) {
            uptimeShowStatus(res && res.data ? res.data : params.uptimeLoadError, true);
          }
        })
        .fail(jqXHR => {
          uptimeShowStatus(uptimeResponseError(jqXHR), true);
        })
        .always(() => {
          loadUptime(true);
        });
    });

    $(document).on('click', '.cbl-uptime-edit', function (event) {
      event.preventDefault();
      uptimeEditingId = $(this).closest('tr').data('wpcbl-uptime-id');
      renderUptime();
    });

    $(document).on('click', '.cbl-uptime-cancel', function (event) {
      event.preventDefault();
      uptimeEditingId = null;
      renderUptime();
    });

    $(document).on('click', '.cbl-uptime-save', function (event) {
      event.preventDefault();
      const tr = $(this).closest('tr');
      const id = tr.data('wpcbl-uptime-id');
      const monitor = (uptimeState.monitors || []).find(m => m.id === id);
      if (!monitor) {
        return;
      }

      const body = {
        id: id,
        name: tr.find('.cbl-uptime-edit-name').val()
      };

      const afterSelect = tr.find('.cbl-uptime-edit-after');
      if (afterSelect.length) {
        body.alert_after_failures = afterSelect.val();
      }

      const urlInput = tr.find('.cbl-uptime-edit-url');
      if (urlInput.length) {
        body.url = urlInput.val();
      } else if (uptimeIsExpiryType(monitor.type)) {
        // ssl/domain rows render no url input (the target is set up
        // web-side, not editable here), but the SaaS update rules still
        // require url for those types. Carry the monitor's existing value
        // through unchanged rather than rendering a field for it.
        body.url = monitor.url;
      }

      const intervalSelect = tr.find('.cbl-uptime-edit-interval');
      if (intervalSelect.length) {
        body.interval_seconds = intervalSelect.val();
      }

      const emails = tr
        .find('.cbl-uptime-edit-emails')
        .val()
        .split(',')
        .map(email => email.trim())
        .filter(email => email.length > 0);
      // Always send the key, even empty, so clearing the field actually
      // clears alert_emails server-side: jQuery drops an empty array from
      // the POST body entirely, which the AJAX handler would then read as
      // "field not sent" and leave the existing recipients untouched.
      body.alert_emails = emails.length ? emails : [''];

      uptimePolls = 0;
      uptimeClearStatus();
      uptimePost('wpcbl_uptime_update', body)
        .done(res => {
          if (!res || !res.success) {
            uptimeShowStatus(res && res.data ? res.data : params.uptimeLoadError, true);
            return;
          }
          uptimeEditingId = null;
        })
        .fail(jqXHR => {
          uptimeShowStatus(uptimeResponseError(jqXHR), true);
        })
        .always(() => {
          loadUptime(true);
        });
    });

    $(document).on('click', '.cbl-uptime-toggle', function (event) {
      event.preventDefault();
      const id = $(this).closest('tr').data('wpcbl-uptime-id');
      uptimePolls = 0;
      uptimeClearStatus();
      uptimePost('wpcbl_uptime_toggle', { id: id })
        .done(res => {
          if (!res || !res.success) {
            uptimeShowStatus(res && res.data ? res.data : params.uptimeLoadError, true);
          }
        })
        .fail(jqXHR => {
          uptimeShowStatus(uptimeResponseError(jqXHR), true);
        })
        .always(() => {
          loadUptime(true);
        });
    });

    $(document).on('click', '.cbl-uptime-delete', function (event) {
      event.preventDefault();
      const id = $(this).closest('tr').data('wpcbl-uptime-id');
      cblModal({
        title: params.uptimeDeleteTitle,
        message: params.uptimeDeleteConfirm,
        confirmText: params.uptimeDeleteTitle,
        danger: true
      }).then(modal => {
        if (!modal.confirmed) {
          return;
        }
        uptimePolls = 0;
        uptimeClearStatus();
        if (uptimeEditingId === id) {
          uptimeEditingId = null;
        }
        uptimePost('wpcbl_uptime_delete', { id: id })
          .done(res => {
            if (!res || !res.success) {
              uptimeShowStatus(res && res.data ? res.data : params.uptimeLoadError, true);
            }
          })
          .fail(jqXHR => {
            uptimeShowStatus(uptimeResponseError(jqXHR), true);
          })
          .always(() => {
            loadUptime(true);
          });
      });
    });

    $(document).on('click', '.cbl-uptime-copy-ping', function (event) {
      event.preventDefault();
      const input = $(this).siblings('.cbl-uptime-ping-url');
      const url = input.val();
      if (!url) {
        return;
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
          uptimeShowStatus(params.uptimeCopied, false);
        });
      } else {
        input.trigger('select');
        document.execCommand('copy');
        uptimeShowStatus(params.uptimeCopied, false);
      }
    });

    $(document).on('click', '#wpcbl-uptime-retry', function (event) {
      event.preventDefault();
      uptimeErrorBox.hide();
      uptimeSkeleton.show();
      loadUptime(false);
    });

    loadUptime(false);
  }
})(jQuery);
