"use strict";

/* jshint esversion: 6 */

/**
 * Back-end scripts.
 *
 * Scripts to run on the WordPress dashboard.
 */

($ => {
  /**
   * Get app state and continue creating the account.
   */
  $(document).ready(function () {
    // Disable the email addresses` field if enable email notifications is unchecked.
    $('#email_notifications').change(function () {
      $('#email_addresses').prop('disabled', !$(this).is(':checked'));
    });

    // Disable the number field if number of links to scan is set to all.
    $('#all_links, #set_number').change(function () {
      $('#number_of_links').prop('disabled', $('#all_links').is(':checked'));
    });
  });

  /**
   * Live progress polling while a scan runs.
   */
  let progressTimer = null;

  const renderProgress = data => {
    if (!data || !data.total) {
      return;
    }
    var current = parseInt(data.current, 10) || 0;
    var total = parseInt(data.total, 10) || 1;
    var links = parseInt(data.links, 10) || 0;
    var percent = Math.min(100, Math.round(current / total * 100));
    $('#wpcbl-scan-progress-fill').css('width', percent + '%');
    $('#wpcbl-scan-progress-text').text(wpcbl_check_for_broken_links_params.progressText.replace('%1$s', current).replace('%2$s', total).replace('%3$s', links));
    // Fill the Links checked stat card live.
    $('#wpcbl-last-scan-total-visible').text(links);
  };

  const progressPoll = () => {
    $.post(wpcbl_check_for_broken_links_params.ajaxUrl, {
      action: 'wpcbl_scan_progress',
      nonce: wpcbl_check_for_broken_links_params.nonce
    }).done(function (result) {
      if (!result || !result.success) {
        return;
      }
      renderProgress(result.data);
    });
  };

  const progressStart = () => {
    $('#wpcbl-scan-progress-fill').css('width', '0%');
    $('#wpcbl-scan-progress-text').html('&nbsp;');
    // Reveal the stat values so the live counter is visible on a first scan.
    $('.cbl-stat-pending').hide();
    $('.cbl-stat-value').css('display', '');
    progressTimer = setInterval(progressPoll, 2000);
  };

  const progressStop = () => {
    if (progressTimer) {
      clearInterval(progressTimer);
      progressTimer = null;
    }
  };

  /**
   * On click of the manual scan button (topbar) or the first-scan button
   * (empty state), start the manual scan.
   */
  $(document).on('click', '#wpcbl-manual-scan, #wpcbl-first-scan, .wpcbl-scan-trigger', function (event) {
    event.preventDefault();
    console.log('Manual scan started...');
    // Which page this trigger fired on decides redirect vs. in-place
    // refresh. #wpcbl-scan-results-page is an explicit marker Broken link
    // scan renders on purpose (both its empty and its with-results state) --
    // deliberately not a check for the results-table element itself, which
    // exists only when a scan has already run, so this cannot silently
    // break the next time that page's markup changes.
    let onResultsPage = $('#wpcbl-scan-results-page').length > 0;
    // Whether that page's own results table is on the DOM right now (its
    // first-ever scan has none to refresh, everywhere else it does).
    let hasResultsTable = $('.wpcbl-check-for-broken-links-links-table').length > 0;

    // Call the manual scan function.
    manualScan().then(result => {
      console.log(result);
      progressStop();

      let ok = typeof result === 'object' && result.hasOwnProperty('success') && result.success;

      // Not on Broken link scan (the Dashboard): it has no table to refresh,
      // so send the admin there for the details once the scan finishes.
      if (!onResultsPage && ok) {
        window.location.href = wpcbl_check_for_broken_links_params.scanResultsUrl;
        return;
      }

      // On Broken link scan, but nothing rendered yet to refresh in place
      // (its own first scan): reload so the page renders its normal
      // with-results state, same reasoning as the Dashboard case above.
      if (onResultsPage && !hasResultsTable && ok) {
        window.location.reload();
        return;
      }

      // Validate the AJAX response
      if (typeof result === 'object' && result.hasOwnProperty('success') && result.success) {
        // Check if result.data is a string before using it as HTML
        if (typeof result.data === 'string') {
          // Replace the table's HTML with the new HTML
          $('.wpcbl-check-for-broken-links-links-table').html(result.data);
          $('.wpcbl_export_csv_wrap').show();

          
        } else if (typeof result.data === 'object' && result.data !== null && result.data.table_html) {
          $('.wpcbl-check-for-broken-links-links-table').html(result.data.table_html);
          $('.wpcbl_export_csv_wrap').show();

          // Update last scan summary (no page refresh needed)
          if (result.data.summary) {
            if (result.data.summary.time && $('#wpcbl-last-scan-time-visible').length) {
              // Keep the format consistent with WordPress settings-scan.php output by using locale string.
              $('#wpcbl-last-scan-time-visible').text(result.data.summary.time_str ? result.data.summary.time_str : new Date(result.data.summary.time * 1000).toLocaleString());
            }
            if (typeof result.data.summary.duration !== 'undefined' && $('#wpcbl-last-scan-duration-visible').length) {
              $('#wpcbl-last-scan-duration-visible').text(parseFloat(result.data.summary.duration).toFixed(1) + 's');
            }
            if (typeof result.data.summary.total !== 'undefined' && $('#wpcbl-last-scan-total-visible').length) {
              $('#wpcbl-last-scan-total-visible').text(result.data.summary.total);
            }
            if (typeof result.data.summary.broken !== 'undefined' && $('#wpcbl-last-scan-broken-visible').length) {
              const brokenCount = parseInt(result.data.summary.broken, 10) || 0;
              $('#wpcbl-last-scan-broken-visible').text(result.data.summary.broken);
              $('#wpcbl-last-scan-broken-visible').closest('.cbl-stat-value')
                .toggleClass('is-broken', brokenCount > 0)
                .toggleClass('is-ok', brokenCount === 0);
              // Track in-page rescans: the bulk AI-fix card only makes
              // sense once the fresh results have broken links again.
              $('#wpcbl-ai-fix-batch-bar').toggle(brokenCount > 0);
            }
          }
// Modify the pagination URLs (the results table only ever lives on
// Broken link scan, so pagination links point there).
          $('.wpcbl-check-for-broken-links-links-table .tablenav-pages a').each(function () {
            var oldUrl = new URL($(this).attr('href'));
            var paged = oldUrl.searchParams.get('paged');
            var newUrl = new URL(wpcbl_check_for_broken_links_params.scanResultsUrl);
            newUrl.searchParams.set('paged', paged);
            $(this).attr('href', newUrl.toString());
          });
        } else {
          console.error('Error: Unexpected AJAX response', result);
          actionFailed(result);
        }
      } else {
        console.error('Error: AJAX request failed', result);
        actionFailed(result);
      }
    });
  });

  /**
   * On click of the clear results button, delete the stored scan results.
   */
  $(document).on('click', '#wpcbl-clear-results', function (event) {
    event.preventDefault();
    cblModal({
      title: wpcbl_check_for_broken_links_params.clearTitle,
      message: wpcbl_check_for_broken_links_params.clearConfirm,
      confirmText: wpcbl_check_for_broken_links_params.clearTitle,
      danger: true
    }).then(function (modal) {
      if (!modal.confirmed) {
        return;
      }
      linkAction('wpcbl_clear_scan_results', {}).done(function (result) {
        if (result && result.success) {
          // Clear Results only lives on Broken link scan (3.0.8): reload it
          // so the page renders its normal empty state, staying put rather
          // than bouncing back to the Dashboard.
          window.location.reload();
        } else {
          actionFailed(result);
        }
      });
    });
  });

  /**
   * Settings: show the "set number" input only while its radio is selected.
   * Initial visibility is rendered server-side.
   */
  $(document).on('change', 'input[name="wpcbl_check_for_broken_links_settings[number_of_links]"]', function () {
    const setNumber = $('#set_number').is(':checked');
    $('#number_of_links').toggle(setNumber).prop('disabled', !setNumber);
  });

  /**
   * Universal modal. Returns a Promise resolving to
   * { confirmed: bool, value: string|null }.
   *
   * opts: title, message, input (bool), inputValue, confirmText,
   *       cancelText (falsy hides the cancel button), danger (bool),
   *       checkbox (label string; adds { checked } to the result).
   */
  const cblModal = opts => {
    return new Promise(resolve => {
      const params = wpcbl_check_for_broken_links_params;
      const previousFocus = document.activeElement;
      const overlay = $(
        '<div class="cbl-modal-overlay" role="presentation">' +
          '<div class="cbl-modal" role="dialog" aria-modal="true" aria-labelledby="cbl-modal-title">' +
            '<h2 class="cbl-modal-title" id="cbl-modal-title"></h2>' +
            '<p class="cbl-modal-message"></p>' +
            '<input type="text" class="cbl-modal-input" />' +
            '<label class="cbl-modal-checkbox"><input type="checkbox" /><span></span></label>' +
            '<div class="cbl-modal-actions">' +
              '<button type="button" class="cbl-btn cbl-modal-cancel"></button>' +
              '<button type="button" class="cbl-btn cbl-btn-primary cbl-modal-confirm"></button>' +
            '</div>' +
          '</div>' +
        '</div>'
      );

      overlay.find('.cbl-modal-title').text(opts.title || '');
      if (opts.message) {
        overlay.find('.cbl-modal-message').text(opts.message);
      } else {
        overlay.find('.cbl-modal-message').hide();
      }

      const input = overlay.find('.cbl-modal-input');
      if (opts.input) {
        input.val(opts.inputValue || '');
      } else {
        input.hide();
      }

      const checkboxWrap = overlay.find('.cbl-modal-checkbox');
      const checkbox = checkboxWrap.find('input');
      if (opts.checkbox) {
        checkboxWrap.find('span').text(opts.checkbox);
      } else {
        checkboxWrap.hide();
      }

      overlay.find('.cbl-modal-confirm').text(opts.confirmText || params.modalConfirm);
      if (opts.cancelText === false) {
        overlay.find('.cbl-modal-cancel').hide();
      } else {
        overlay.find('.cbl-modal-cancel').text(opts.cancelText || params.modalCancel);
      }
      if (opts.danger) {
        overlay.find('.cbl-modal-confirm').addClass('cbl-btn-danger').removeClass('cbl-btn-primary');
      }

      const close = confirmed => {
        overlay.remove();
        $(document).off('keydown.cblModal');
        if (previousFocus && previousFocus.focus) {
          previousFocus.focus();
        }
        resolve({ confirmed: confirmed, value: opts.input ? input.val() : null, checked: opts.checkbox ? checkbox.is(':checked') : false });
      };

      overlay.on('click', function (event) {
        if (event.target === this) {
          close(false);
        }
      });
      overlay.find('.cbl-modal-cancel').on('click', () => close(false));
      overlay.find('.cbl-modal-confirm').on('click', () => close(true));
      $(document).on('keydown.cblModal', event => {
        if ('Escape' === event.key) {
          close(false);
        }
        if ('Enter' === event.key && opts.input && input.is(':focus')) {
          close(true);
        }
      });

      $('body').append(overlay);
      if (opts.input) {
        input.trigger('focus').get(0).select();
      } else {
        overlay.find('.cbl-modal-confirm').trigger('focus');
      }
    });
  };

  /**
   * Link row actions: Edit URL, Unlink, Not broken, Dismiss.
   */
  const linkAction = (action, data) => {
    return $.post(wpcbl_check_for_broken_links_params.ajaxUrl, $.extend({
      action: action,
      nonce: wpcbl_check_for_broken_links_params.nonce
    }, data));
  };

  const actionFailed = result => {
    cblModal({
      title: wpcbl_check_for_broken_links_params.errorTitle,
      message: result && result.data ? result.data : wpcbl_check_for_broken_links_params.errorGeneric,
      confirmText: wpcbl_check_for_broken_links_params.modalOk,
      cancelText: false
    });
  };

  /**
   * Fix with AI suggestions modal. Resolves with
   * { action: 'apply'|'unlink'|'cancel', url: string|null, fixAll: bool }.
   */
  const aiFixModal = data => {
    return new Promise(resolve => {
      const params = wpcbl_check_for_broken_links_params;
      const candidates = data.candidates || [];
      const hasCandidates = candidates.length > 0;
      const previousFocus = document.activeElement;
      const overlay = $(
        '<div class="cbl-modal-overlay" role="presentation">' +
          '<div class="cbl-modal cbl-modal-ai" role="dialog" aria-modal="true" aria-labelledby="cbl-modal-title">' +
            '<h2 class="cbl-modal-title" id="cbl-modal-title"></h2>' +
            '<p class="cbl-modal-message"></p>' +
            '<div class="cbl-ai-fix-list"></div>' +
            '<label class="cbl-modal-checkbox"><input type="checkbox" /><span></span></label>' +
            '<p class="cbl-ai-fix-quota"></p>' +
            '<div class="cbl-modal-actions">' +
              '<button type="button" class="cbl-btn cbl-modal-cancel"></button>' +
              '<button type="button" class="cbl-btn cbl-ai-unlink"></button>' +
              '<button type="button" class="cbl-btn cbl-btn-primary cbl-modal-confirm"></button>' +
            '</div>' +
          '</div>' +
        '</div>'
      );

      overlay.find('.cbl-modal-title').text(params.aiFixTitle);
      overlay.find('.cbl-modal-message').text(hasCandidates ? params.aiFixPickMessage : params.aiFixNoneMessage);

      const list = overlay.find('.cbl-ai-fix-list');
      candidates.forEach(function (candidate, index) {
        const row = $(
          '<label class="cbl-ai-fix-option">' +
            '<input type="radio" name="cbl-ai-fix-choice" />' +
            '<span class="cbl-ai-fix-url"></span>' +
            '<span class="cbl-ai-fix-conf"></span>' +
            '<span class="cbl-ai-fix-reason"></span>' +
          '</label>'
        );
        row.find('input').val(candidate.url).prop('checked', 0 === index);
        row.find('.cbl-ai-fix-url').text(candidate.url);
        row.find('.cbl-ai-fix-conf').addClass('cbl-ai-conf-' + candidate.confidence).text(candidate.confidence);
        row.find('.cbl-ai-fix-reason').text(candidate.reason || '');
        list.append(row);
      });
      if (data.wayback_url) {
        const wayback = $('<a class="cbl-ai-fix-wayback" target="_blank" rel="noopener"></a>');
        wayback.attr('href', data.wayback_url).text(params.aiFixWayback);
        list.append(wayback);
      }

      const checkboxWrap = overlay.find('.cbl-modal-checkbox');
      if (hasCandidates) {
        checkboxWrap.find('span').text(params.fixAllLabel);
      } else {
        checkboxWrap.hide();
      }

      if (data.quota && data.quota.limit) {
        overlay.find('.cbl-ai-fix-quota').text(
          params.aiFixQuotaText.replace('%1$s', data.quota.used).replace('%2$s', data.quota.limit)
        );
      } else {
        overlay.find('.cbl-ai-fix-quota').hide();
      }

      overlay.find('.cbl-modal-cancel').text(params.modalCancel);
      overlay.find('.cbl-ai-unlink').text(params.aiFixUnlink);
      const confirmBtn = overlay.find('.cbl-modal-confirm').text(params.aiFixApply);
      if (!hasCandidates) {
        confirmBtn.hide();
        overlay.find('.cbl-ai-unlink').addClass('cbl-btn-danger');
      }

      const close = result => {
        overlay.remove();
        $(document).off('keydown.cblAiFix');
        if (previousFocus && previousFocus.focus) {
          previousFocus.focus();
        }
        resolve(result);
      };

      overlay.on('click', function (event) {
        if (event.target === this) {
          close({ action: 'cancel', url: null, fixAll: false });
        }
      });
      overlay.find('.cbl-modal-cancel').on('click', () => close({ action: 'cancel', url: null, fixAll: false }));
      overlay.find('.cbl-ai-unlink').on('click', () => close({ action: 'unlink', url: null, fixAll: false }));
      confirmBtn.on('click', () => close({
        action: 'apply',
        url: overlay.find('input[name="cbl-ai-fix-choice"]:checked').val() || null,
        fixAll: checkboxWrap.find('input').is(':checked')
      }));
      $(document).on('keydown.cblAiFix', event => {
        if ('Escape' === event.key) {
          close({ action: 'cancel', url: null, fixAll: false });
        }
      });

      $('body').append(overlay);
      (hasCandidates ? confirmBtn : overlay.find('.cbl-ai-unlink')).trigger('focus');
    });
  };

  $(document).on('click', '.wpcbl-action-edit-url', function (event) {
    event.preventDefault();
    var url = $(this).data('wpcbl-url');
    var postId = $(this).data('wpcbl-post');
    cblModal({
      title: wpcbl_check_for_broken_links_params.editUrlTitle,
      message: wpcbl_check_for_broken_links_params.editUrlPrompt,
      input: true,
      inputValue: url,
      checkbox: wpcbl_check_for_broken_links_params.fixAllLabel,
      confirmText: wpcbl_check_for_broken_links_params.modalSave
    }).then(function (modal) {
      if (!modal.confirmed || !modal.value || modal.value === url) {
        return;
      }
      linkAction('wpcbl_edit_link_url', { post_id: postId, old_url: url, new_url: modal.value, fix_all: modal.checked ? '1' : '0' }).done(function (result) {
        if (result && result.success) {
          window.location.reload();
        } else {
          actionFailed(result);
        }
      });
    });
  });

  $(document).on('click', '.wpcbl-action-unlink', function (event) {
    event.preventDefault();
    var row = $(this).closest('tr');
    var postId = $(this).data('wpcbl-post');
    var url = $(this).data('wpcbl-url');
    cblModal({
      title: wpcbl_check_for_broken_links_params.unlinkTitle,
      message: wpcbl_check_for_broken_links_params.unlinkConfirm,
      confirmText: wpcbl_check_for_broken_links_params.unlinkTitle,
      danger: true
    }).then(function (modal) {
      if (!modal.confirmed) {
        return;
      }
      linkAction('wpcbl_unlink', { post_id: postId, url: url }).done(function (result) {
        if (result && result.success) {
          row.fadeOut(200, function () { $(this).remove(); });
        } else {
          actionFailed(result);
        }
      });
    });
  });

  $(document).on('click', '.wpcbl-action-not-broken', function (event) {
    event.preventDefault();
    var row = $(this).closest('tr');
    linkAction('wpcbl_not_broken', { url: $(this).data('wpcbl-url') }).done(function (result) {
      if (result && result.success) {
        row.fadeOut(200, function () { $(this).remove(); });
      } else {
        actionFailed(result);
      }
    });
  });

  $(document).on('click', '.wpcbl-action-dismiss', function (event) {
    event.preventDefault();
    var row = $(this).closest('tr');
    linkAction('wpcbl_dismiss_link', { url: $(this).data('wpcbl-url') }).done(function (result) {
      if (result && result.success) {
        row.fadeOut(200, function () { $(this).remove(); });
      } else {
        actionFailed(result);
      }
    });
  });

  $(document).on('click', '.wpcbl-action-fix-redirect', function (event) {
    event.preventDefault();
    linkAction('wpcbl_fix_redirect', { post_id: $(this).data('wpcbl-post'), url: $(this).data('wpcbl-url') }).done(function (result) {
      if (result && result.success) {
        window.location.reload();
      } else {
        actionFailed(result);
      }
    });
  });

  $(document).on('click', '.wpcbl-action-wayback', function (event) {
    event.preventDefault();
    linkAction('wpcbl_wayback_lookup', { url: $(this).data('wpcbl-url') }).done(function (result) {
      if (result && result.success && result.data && result.data.archived_url) {
        window.open(result.data.archived_url, '_blank');
      } else {
        actionFailed(result);
      }
    });
  });

  $(document).on('click', '.wpcbl-action-fix-ai', function (event) {
    event.preventDefault();
    const linkEl = $(this);
    if (linkEl.data('wpcblBusy')) {
      return;
    }
    const originalText = linkEl.text();
    const postId = linkEl.data('wpcbl-post');
    const brokenUrl = linkEl.data('wpcbl-url');
    linkEl.data('wpcblBusy', true).text(wpcbl_check_for_broken_links_params.aiFixWorking);

    linkAction('wpcbl_ai_fix', { post_id: postId, url: brokenUrl, code: linkEl.data('wpcbl-code') }).done(function (result) {
      if (!result || !result.success) {
        actionFailed(result);
        return;
      }
      aiFixModal(result.data).then(function (choice) {
        if ('apply' === choice.action && choice.url) {
          linkAction('wpcbl_edit_link_url', { post_id: postId, old_url: brokenUrl, new_url: choice.url, fix_all: choice.fixAll ? '1' : '0' }).done(function (applied) {
            if (applied && applied.success) {
              window.location.reload();
            } else {
              actionFailed(applied);
            }
          });
        } else if ('unlink' === choice.action) {
          linkAction('wpcbl_unlink', { post_id: postId, url: brokenUrl }).done(function (unlinked) {
            if (unlinked && unlinked.success) {
              window.location.reload();
            } else {
              actionFailed(unlinked);
            }
          });
        }
      });
    }).fail(function (jqXHR) {
      actionFailed(jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : null);
    }).always(function () {
      linkEl.data('wpcblBusy', false).text(originalText);
    });
  });

  /**
   * Re-check all links (Settings > Scan): clear cached statuses, then run a
   * fresh scan on the dashboard via the autostart flag.
   */
  $(document).on('click', '#wpcbl-recheck-all', function (event) {
    event.preventDefault();
    cblModal({
      title: wpcbl_check_for_broken_links_params.recheckTitle,
      message: wpcbl_check_for_broken_links_params.recheckConfirm,
      confirmText: wpcbl_check_for_broken_links_params.recheckTitle
    }).then(function (modal) {
      if (!modal.confirmed) {
        return;
      }
      linkAction('wpcbl_recheck_all', {}).done(function (result) {
        if (result && result.success) {
          window.location.href = wpcbl_check_for_broken_links_params.scanPageUrl + '&wpcbl_autostart=1';
        } else {
          actionFailed(result);
        }
      });
    });
  });

  $(document).ready(function () {
    if (window.location.search.indexOf('wpcbl_autostart=1') !== -1) {
      $('#wpcbl-manual-scan, #wpcbl-first-scan, .wpcbl-scan-trigger').first().trigger('click');
    }
  });

  /**
   * Results filters apply on change; no Filter button click needed.
   */
  $(document).on('change', 'select[name="wpcbl_status_filter"], select[name="wpcbl_type_filter"], select[name="wpcbl_location_filter"]', function () {
    $(this).closest('form').trigger('submit');
  });

  $(document).on('click', '.wpcbl-check-for-broken-links-faq-item input', function (event) {
    if ($(this).is(':checked')) {
      $(this).siblings('label').find('.sign').text('-');
    } else {
      $(this).siblings('label').find('.sign').text('+');
    }
  });

  /**
   * Manually scan for broken links.
   *
   * @param {object} data 
   * @returns 
   */
  /**
   * Disable the scan buttons and swap their label for a spinner +
   * "Scanning…" while a scan runs (covers the hero and topbar buttons).
   */
  const setScanButtonsScanning = scanning => {
    const $buttons = $('#wpcbl-manual-scan, #wpcbl-first-scan, .wpcbl-scan-trigger');
    $buttons.prop('disabled', scanning).toggleClass('is-scanning', scanning);
    $buttons.find('.cbl-btn-label').each(function () {
      const $label = $(this);
      if (scanning) {
        $label.data('cblLabel', $label.text()).text(wpcbl_check_for_broken_links_params.scanningLabel);
      } else if ($label.data('cblLabel')) {
        $label.text($label.data('cblLabel'));
      }
    });
  };

  const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

  /**
   * Run a scan as a series of short requests: wpcbl_scan_start lists the
   * work, then wpcbl_scan_step checks links for a few seconds at a time and
   * saves where it got to, until it reports done. One request per scan used
   * to be killed by the host after a minute or two on bigger sites. A step
   * that fails on the wire (504, dropped connection) is retried, since the
   * server saved its progress before answering.
   */
  const manualScan = async () => {
    const params = wpcbl_check_for_broken_links_params;
    const post = body => $.post(params.ajaxUrl, Object.assign({ nonce: params.nonce }, body));

    console.log('Sending data...');
    loader('show');
    progressStart();
    setScanButtonsScanning(true);
    $('.wpcbl_export_csv_wrap').hide();
    $('.wpcbl-check-for-broken-links-links-table').html('<div class="notice notice-info" style="margin:12px 0; padding:10px;">Scanning…</div>');

    try {
      const started = await post({ action: 'wpcbl_scan_start' });
      if (!started || !started.success) {
        return started;
      }
      renderProgress(started.data);

      let failures = 0;
      for (;;) {
        let step;
        try {
          step = await post({ action: 'wpcbl_scan_step' });
        } catch (error) {
          failures++;
          console.error('Scan step failed (' + failures + '):', error.statusText);
          if (failures >= 3) {
            return undefined;
          }
          await sleep(2000);
          continue;
        }
        failures = 0;

        if (!step || !step.success) {
          return step;
        }
        renderProgress(step.data);
        if (step.data && step.data.done) {
          return step;
        }
      }
    } catch (error) {
      console.error('Error:', error.statusText);
      return undefined;
    } finally {
      console.log('Data sent.');
      progressStop();
      loader('hide');
      setScanButtonsScanning(false);
    }
  };

  /**
   * Hide/show the loader.
   *
   * @param {string} action 
   */
  const loader = action => {
    let loader = $('.wpcbl-is-scanning');
    if (action === 'show') {
      loader.show();
    } else if (action === 'hide') {
      loader.hide();
    }
  };

  /**
   * Bulk Fix with AI: start a batch, poll it, render the review list, and
   * apply fixes through the existing edit/unlink actions.
   */
  let aiBatchTimer = null;

  const aiBatchStop = () => {
    if (aiBatchTimer) {
      clearInterval(aiBatchTimer);
      aiBatchTimer = null;
    }
  };

  const aiBatchRowSuggestion = row => {
    if ('done' === row.status && row.candidates.length) {
      return { kind: 'replace', url: row.candidates[0].url, confidence: row.candidates[0].confidence, reason: row.candidates[0].reason || '' };
    }
    if ('done' === row.status) {
      return { kind: 'unlink' };
    }
    if ('failed' === row.status) {
      return { kind: 'failed' };
    }
    if ('skipped_quota' === row.status) {
      return { kind: 'quota' };
    }
    return { kind: 'pending' };
  };

  const aiBatchApplyRow = (rowEl, row, suggestion, quiet) => {
    const params = wpcbl_check_for_broken_links_params;
    const request = 'replace' === suggestion.kind
      ? linkAction('wpcbl_edit_link_url', { post_id: row.post_id, old_url: row.url, new_url: suggestion.url, fix_all: '0' })
      : linkAction('wpcbl_unlink', { post_id: row.post_id, url: row.url });

    rowEl.addClass('cbl-ai-row-busy');

    return request.then(function (result) {
      rowEl.removeClass('cbl-ai-row-busy');
      if (result && result.success) {
        rowEl.removeClass('cbl-ai-row-failed').addClass('cbl-ai-row-applied').find('.cbl-ai-row-actions').empty().append($('<span class="cbl-ai-row-state"></span>').text(params.aiBatchApplied));
        return true;
      }
      if (quiet) {
        rowEl.addClass('cbl-ai-row-failed');
      } else {
        actionFailed(result);
      }
      return false;
    }, function (jqXHR) {
      rowEl.removeClass('cbl-ai-row-busy');
      if (quiet) {
        rowEl.addClass('cbl-ai-row-failed');
      } else {
        actionFailed(jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : null);
      }
      return false;
    });
  };

  const aiBatchRender = data => {
    const params = wpcbl_check_for_broken_links_params;
    const box = $('#wpcbl-ai-fix-review');
    box.empty().show();

    const counters = data.counters || {};
    const total = parseInt(counters.total, 10) || 0;
    const settled = (parseInt(counters.done, 10) || 0) + (parseInt(counters.failed, 10) || 0) + (parseInt(counters.skipped_quota, 10) || 0);

    if ('processing' === data.status) {
      const pct = total > 0 ? Math.round((settled / total) * 100) : 0;
      const prog = $('<div class="cbl-ai-progress"></div>');
      const progTop = $('<div class="cbl-ai-progress-top"></div>');
      progTop.append(
        $('<span class="cbl-ai-progress-label"></span>')
          .append('<span class="cbl-ai-progress-dot"></span>')
          .append(document.createTextNode(params.aiBatchAnalyzingLabel || 'Analyzing broken links'))
      );
      progTop.append($('<span class="cbl-ai-progress-count"></span>').text(params.aiBatchAnalyzing.replace('%1$s', settled).replace('%2$s', total)));
      prog.append(progTop);
      prog.append($('<div class="cbl-ai-progress-track"></div>').append($('<div class="cbl-ai-progress-fill"></div>').css('width', Math.max(4, pct) + '%')));
      box.append(prog);
      return;
    }

    const head = $('<div class="cbl-ai-review-head"></div>');
    head.append($('<span class="cbl-ai-review-title"></span>').text(params.aiBatchReviewTitle));
    const headActions = $('<span class="cbl-ai-review-head-actions"></span>');
    const applyAllBtn = $('<button type="button" class="cbl-btn cbl-btn-primary"></button>').text(params.aiBatchApplyAll);
    const closeBtn = $('<button type="button" class="cbl-btn"></button>').text(params.aiBatchClose);
    headActions.append(applyAllBtn).append(closeBtn);
    head.append(headActions);
    box.append(head);

    if (data.quota && data.quota.limit) {
      box.append($('<p class="cbl-ai-fix-quota"></p>').text(params.aiFixQuotaText.replace('%1$s', data.quota.used).replace('%2$s', data.quota.limit)));
    }

    const list = $('<div class="cbl-ai-review-list"></div>');
    const applicable = [];
    let quotaRows = 0;

    (data.rows || []).forEach(function (row) {
      const suggestion = aiBatchRowSuggestion(row);
      if ('quota' === suggestion.kind) {
        quotaRows++;
        return;
      }
      const rowEl = $(
        '<div class="cbl-ai-review-row">' +
          '<span class="cbl-ai-row-urls"><span class="cbl-ai-row-old"></span><span class="cbl-ai-row-new"></span></span>' +
          '<span class="cbl-ai-row-meta"></span>' +
          '<span class="cbl-ai-row-actions"></span>' +
        '</div>'
      );
      rowEl.find('.cbl-ai-row-old').text(row.url);

      if ('replace' === suggestion.kind) {
        rowEl.find('.cbl-ai-row-new').text(suggestion.url);
        rowEl.find('.cbl-ai-row-meta').append($('<span class="cbl-ai-fix-conf"></span>').addClass('cbl-ai-conf-' + suggestion.confidence).text(suggestion.confidence)).append($('<span class="cbl-ai-fix-reason"></span>').text(suggestion.reason));
      } else if ('unlink' === suggestion.kind) {
        rowEl.find('.cbl-ai-row-new').text(params.aiBatchRemoveLabel);
        if (row.wayback_url && 'string' === typeof row.wayback_url) {
          rowEl.find('.cbl-ai-row-meta').append($('<a target="_blank" rel="noopener"></a>').attr('href', row.wayback_url).text(params.aiFixWayback));
        }
      } else {
        // 'failed' and any unresolved 'pending' row render identically:
        // never leave a row blank.
        rowEl.find('.cbl-ai-row-new').text(params.aiBatchFailedRow);
      }

      const actions = rowEl.find('.cbl-ai-row-actions');
      if ('replace' === suggestion.kind || 'unlink' === suggestion.kind) {
        const applyBtn = $('<button type="button" class="cbl-btn cbl-btn-primary cbl-btn-small"></button>').text(params.aiBatchApply);
        const skipBtn = $('<button type="button" class="cbl-btn cbl-btn-small"></button>').text(params.aiBatchSkipRow);
        applyBtn.on('click', function () {
          aiBatchApplyRow(rowEl, row, suggestion);
        });
        skipBtn.on('click', function () {
          rowEl.addClass('cbl-ai-row-skipped');
          actions.empty().append($('<span class="cbl-ai-row-state"></span>').text(params.aiBatchSkipped));
        });
        actions.append(applyBtn).append(skipBtn);
        applicable.push({ rowEl: rowEl, row: row, suggestion: suggestion });
      }

      list.append(rowEl);
    });
    box.append(list);

    if (quotaRows > 0) {
      const note = $('<p class="cbl-ai-batch-quota-note"></p>').text(params.aiBatchQuotaRows.replace('%1$s', quotaRows));
      if (params.aiBatchCanUpgrade) {
        note.append(' ').append($('<a target="_blank" rel="noopener"></a>').attr('href', params.aiBatchAccountUrl).text(params.aiBatchUpgrade));
      }
      box.append(note);
    }

    if (data.skipped_local && data.skipped_local.length) {
      const capSkipped = data.skipped_local.filter(function (s) { return 'cap' === s.reason; });
      const manualSkipped = data.skipped_local.filter(function (s) { return 'cap' !== s.reason; });
      if (manualSkipped.length) {
        box.append($('<p class="cbl-ai-batch-manual-note"></p>').text(params.aiBatchManualRow + ' (' + manualSkipped.length + ')'));
      }
      if (capSkipped.length) {
        box.append($('<p class="cbl-ai-batch-manual-note"></p>').text(params.aiBatchCapRows.replace('%1$s', capSkipped.length)));
      }
    }

    applyAllBtn.on('click', function () {
      applyAllBtn.prop('disabled', true);
      let failCount = 0;
      let chain = $.Deferred().resolve().promise();
      applicable.forEach(function (item) {
        chain = chain.then(function () {
          if (item.rowEl.hasClass('cbl-ai-row-applied') || item.rowEl.hasClass('cbl-ai-row-skipped')) {
            return true;
          }
          return aiBatchApplyRow(item.rowEl, item.row, item.suggestion, true).then(function (ok) {
            if (!ok) {
              failCount++;
            }
            return ok;
          });
        });
      });
      chain.then(function () {
        if (0 === failCount) {
          box.append($('<p class="cbl-ai-batch-done"></p>').text(params.aiBatchDone));
          linkAction('wpcbl_ai_fix_batch_dismiss', {}).always(function () {
            $('#wpcbl-ai-fix-batch-start').prop('disabled', false);
            window.location.reload();
          });
          return;
        }
        // Keep the review open so the failed rows stay actionable.
        applyAllBtn.prop('disabled', false);
        actionFailed({ data: params.aiBatchApplyFailures.replace('%1$s', failCount) });
      });
    });

    closeBtn.on('click', function () {
      linkAction('wpcbl_ai_fix_batch_dismiss', {}).always(function () {
        $('#wpcbl-ai-fix-batch-start').prop('disabled', false);
        window.location.reload();
      });
    });
  };

  let aiBatchFailures = 0;

  const aiBatchPoll = () => {
    linkAction('wpcbl_ai_fix_batch_status', {}).done(function (result) {
      if (!result || !result.success) {
        aiBatchFailures++;
        if (aiBatchFailures >= 3) {
          aiBatchStop();
          actionFailed(result);
        }
        return;
      }
      aiBatchFailures = 0;
      if (!result.data) {
        return;
      }
      if ('none' === result.data.status) {
        aiBatchStop();
        $('#wpcbl-ai-fix-review').hide().empty();
        $('#wpcbl-ai-fix-batch-start').prop('disabled', false);
        return;
      }
      aiBatchRender(result.data);
      if ('done' === result.data.status) {
        aiBatchStop();
      }
    }).fail(function (jqXHR) {
      aiBatchFailures++;
      if (aiBatchFailures >= 3) {
        aiBatchStop();
        actionFailed(jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : null);
      }
    });
  };

  const aiBatchBegin = () => {
    aiBatchStop();
    aiBatchPoll();
    aiBatchTimer = setInterval(aiBatchPoll, 3000);
  };

  $(document).on('click', '#wpcbl-ai-fix-batch-start', function () {
    const params = wpcbl_check_for_broken_links_params;
    const btn = $(this);
    const brokenCount = $('#wpcbl-last-scan-broken-visible').text() || '';
    cblModal({
      title: params.aiBatchConfirmTitle,
      message: params.aiBatchConfirmMsg.replace('%1$s', brokenCount),
      confirmText: params.aiBatchStart
    }).then(function (choice) {
      if (!choice.confirmed) {
        return;
      }
      btn.prop('disabled', true);
      linkAction('wpcbl_ai_fix_batch_start', {}).done(function (result) {
        if (!result || !result.success) {
          btn.prop('disabled', false);
          actionFailed(result);
          return;
        }
        // Success keeps the button disabled: polling is now active.
        aiBatchBegin();
      }).fail(function (jqXHR) {
        btn.prop('disabled', false);
        actionFailed(jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : null);
      });
    });
  });

  if (wpcbl_check_for_broken_links_params.aiBatchJob && $('#wpcbl-ai-fix-review').length) {
    aiBatchBegin();
  }

  // ===== Rank Tracker =====
  const rankApp = $('#wpcbl-rank-app');
  if (rankApp.length) {
    const params = wpcbl_check_for_broken_links_params;
    const rankView = rankApp.data('wpcbl-rank-view') || 'tracker';
    const rankSkeleton = $('#wpcbl-rank-skeleton');
    const rankErrorBox = $('#wpcbl-rank-error');
    const rankContent = $('#wpcbl-rank-content');
    const rankAddBtn = $('#wpcbl-rank-add');

    let rankState = null;
    let rankPolls = 0;
    let rankPollTimer = null;
    let rankSelected = new Set();
    let rankActiveEngine = null;
    let rankStatusMessage = null;
    let rankStatusWarning = false;

    const rankPost = (action, data) =>
      $.post(params.ajaxUrl, Object.assign({ action: action, nonce: params.nonce }, data || {}));

    const rankResponseError = jqXHR =>
      jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data ? jqXHR.responseJSON.data : params.rankLoadError;

    const rankShowStatus = (message, isWarning) => {
      rankStatusMessage = message || null;
      rankStatusWarning = !!isWarning;
      const status = $('#wpcbl-rank-status');
      if (!status.length) {
        return;
      }
      status.text(message || '').toggleClass('cbl-rank-status-warning', !!isWarning);
    };

    // Clears the persisted status message. Call this when a new user
    // action starts so a stale message from a previous action doesn't
    // reappear after the next renderRank().
    const rankClearStatus = () => {
      rankStatusMessage = null;
      rankStatusWarning = false;
    };

    const rankDeviceLabel = device => {
      if ('mobile' === device) {
        return params.rankDeviceMobile;
      }
      if ('both' === device) {
        return params.rankDeviceBoth;
      }
      return params.rankDeviceDesktop;
    };

    // Static markup only; no server strings are interpolated here.
    const RANK_DEVICE_SVG = {
      mobile:
        '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="2"/><path d="M11 18h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
      desktop:
        '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5h18v11H3z" stroke="currentColor" stroke-width="2"/><path d="M9 20h6M12 16v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
    };

    const rankDeviceIcon = device => {
      const key = 'mobile' === device ? 'mobile' : 'desktop';
      return $('<span></span>')
        .addClass('cbl-rank-device-ico cbl-rank-device-ico-' + key)
        .attr('title', rankDeviceLabel(device))
        .attr('role', 'img')
        .attr('aria-label', rankDeviceLabel(device))
        .html(RANK_DEVICE_SVG[key]);
    };

    const rankEngineLabel = engine => {
      if ('bing' === engine) {
        return params.rankEngineBing;
      }
      if ('both' === engine) {
        return params.rankEngineBoth;
      }
      if ('google' === engine) {
        return params.rankEngineGoogle;
      }
      return engine;
    };

    const showRankError = message => {
      if (rankPollTimer) {
        clearTimeout(rankPollTimer);
        rankPollTimer = null;
      }
      rankSkeleton.hide();
      rankContent.empty();
      rankAddBtn.hide();
      rankErrorBox.find('p').text(message || params.rankLoadError);
      rankErrorBox.show();
    };

    // ---- Charts ----

    const RANK_CHART_SVG_NS = 'http://www.w3.org/2000/svg';

    const rankChartFormatThousands = n => {
      const rounded = Math.round(Number(n) || 0);
      const sign = rounded < 0 ? '-' : '';
      return sign + Math.abs(rounded).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };

    const rankChartDefs = () => [
      { key: 'visibility', title: params.rankChartVisibility, color: '#F59E0B', invert: false, format: v => Number(v) + '%' },
      {
        key: 'traffic',
        title: params.rankChartTraffic,
        color: '#2563EB',
        invert: false,
        format: v => rankChartFormatThousands(v) + ' /mo'
      },
      {
        key: 'avg_position',
        title: params.rankChartAvgPosition,
        color: '#15803D',
        invert: true,
        format: v => '#' + Number(v)
      }
    ];

    const rankChartSvgEl = (tag, attrs) => {
      const el = document.createElementNS(RANK_CHART_SVG_NS, tag);
      Object.keys(attrs || {}).forEach(name => {
        el.setAttribute(name, String(attrs[name]));
      });
      return el;
    };

    const buildRankChartCard = (def, rawValues, dates) => {
      const w = 300;
      const h = 80;
      const pad = 6;
      const values = rawValues.map(v => Number(v));

      const card = $('<div class="cbl-rank-chart-card"></div>');
      const head = $('<div class="cbl-rank-chart-head"></div>');
      head.append($('<span class="cbl-rank-chart-title"></span>').text(def.title));
      head.append($('<span class="cbl-rank-chart-current"></span>').text(def.format(values[values.length - 1])));
      card.append(head);

      const svg = rankChartSvgEl('svg', {
        viewBox: '0 0 ' + w + ' ' + h,
        preserveAspectRatio: 'none',
        class: 'cbl-rank-chart-svg',
        role: 'img',
        'aria-label': def.title
      });

      if (values.length === 1) {
        // Single-point series: render a dot instead of a degenerate line.
        svg.appendChild(rankChartSvgEl('circle', { cx: w / 2, cy: h / 2, r: 3, fill: def.color }));
      } else {
        let min = Math.min.apply(null, values);
        let max = Math.max.apply(null, values);
        if (max === min) {
          // Division-by-zero guard: flat series renders as a centered line.
          max = min + 1;
        }
        const points = values.map((value, i) => {
          const x = (i * w) / (values.length - 1);
          let norm = (value - min) / (max - min);
          if (def.invert) {
            // Average position: lower is better, so invert the mapping.
            norm = 1 - norm;
          }
          const y = pad + (1 - norm) * (h - 2 * pad);
          return Number(x.toFixed(1)) + ',' + Number(y.toFixed(1));
        });
        const lineStr = points.join(' ');
        const areaStr = lineStr + ' ' + w + ',' + h + ' 0,' + h;

        svg.appendChild(rankChartSvgEl('polygon', { points: areaStr, fill: def.color, opacity: '0.08' }));
        svg.appendChild(
          rankChartSvgEl('polyline', {
            points: lineStr,
            fill: 'none',
            stroke: def.color,
            'stroke-width': '2.5',
            'stroke-linejoin': 'round',
            'stroke-linecap': 'round',
            'vector-effect': 'non-scaling-stroke'
          })
        );
      }

      card.append(svg);

      const labels = $('<div class="cbl-rank-chart-labels"></div>');
      labels.append($('<span></span>').text(dates[0] || ''));
      labels.append($('<span></span>').text(dates.length > 1 ? dates[dates.length - 1] : ''));
      card.append(labels);

      return card;
    };

    function renderRankCharts(containerJq, charts) {
      containerJq.empty();

      if (!charts || !charts.dates || !charts.dates.length) {
        containerJq.hide();
        return;
      }

      let rendered = 0;
      rankChartDefs().forEach(def => {
        const values = charts[def.key];
        if (!values || !values.length) {
          return;
        }
        containerJq.append(buildRankChartCard(def, values, charts.dates));
        rendered++;
      });

      containerJq.toggle(rendered > 0);
    }

    const scheduleRankPoll = () => {
      if (rankPollTimer) {
        clearTimeout(rankPollTimer);
        rankPollTimer = null;
      }
      // Never schedule a poll while the settings view is open: a re-fetch
      // and re-render 15s later would blow away half-typed settings input.
      if ('settings' === rankView) {
        return;
      }
      if (rankState && rankState.pending && rankPolls < 40) {
        rankPollTimer = setTimeout(() => {
          rankPolls++;
          loadRank(true);
        }, 15000);
      }
    };

    const loadRank = fresh => {
      rankPost('wpcbl_rank_state', fresh ? { fresh: '1' } : {})
        .done(res => {
          if (!res || !res.success) {
            return showRankError(res && res.data ? res.data : params.rankLoadError);
          }
          rankState = res.data;
          renderRank();
          scheduleRankPoll();
        })
        .fail(jqXHR => {
          showRankError(rankResponseError(jqXHR));
        });
    };

    // ---- Table building ----

    const buildRankTableHead = () => {
      const thead = $('<thead></thead>');
      const tr = $('<tr></tr>');
      tr.append($('<th class="cbl-rank-col-check"></th>').append($('<input type="checkbox" id="wpcbl-rank-select-all" />')));
      const cols = params.rankColumns || {};
      ['keyword', 'position', 'change', 'best', 'volume', 'cpc', 'intent', 'competition', 'page', 'checked'].forEach(key => {
        tr.append($('<th></th>').addClass('cbl-rank-col-' + key).text(cols[key] || key));
      });
      thead.append(tr);
      return thead;
    };

    const buildRankRow = kw => {
      const comboKey = rankActiveEngine + ':' + kw.device;
      const check = (kw.checks && kw.checks[comboKey]) || null;
      const tr = $('<tr></tr>').attr('data-wpcbl-rank-id', kw.id);

      const tdCheck = $('<td class="cbl-rank-col-check"></td>');
      tdCheck.append(
        $('<input type="checkbox" class="cbl-rank-row-check" />').val(kw.id).prop('checked', rankSelected.has(kw.id))
      );
      tdCheck.append(
        $('<button type="button" class="cbl-rank-row-delete"></button>')
          .attr('aria-label', params.rankDeleteTitle)
          .attr('title', params.rankDeleteTitle)
          .text('×')
      );
      tr.append(tdCheck);

      const tdKeyword = $('<td class="cbl-rank-col-keyword"></td>');
      const marketEntry = (rankState.markets || []).find(market => market.key === kw.market);
      const kwCountry = String(kw.market || '').split('-')[0].toLowerCase();
      if (kwCountry && params.rankFlagsBase) {
        tdKeyword.append(
          $('<img class="cbl-rank-flag" alt="" width="18" height="14" />')
            .attr('src', params.rankFlagsBase + encodeURIComponent(kwCountry) + '.svg')
            .attr('title', marketEntry ? marketEntry.label : kw.market)
            .on('error', function () {
              $(this).hide();
            })
        );
      }
      tdKeyword.append(rankDeviceIcon(kw.device));
      tdKeyword.append($('<span class="cbl-rank-keyword-text"></span>').text(kw.keyword));
      tr.append(tdKeyword);

      const tdPos = $('<td class="cbl-rank-col-position"></td>');
      if (kw.pending) {
        tdPos.append($('<span class="cbl-rank-pending"></span>').text('…'));
      } else if (check && null !== check.position && undefined !== check.position) {
        tdPos.append(
          $('<span class="cbl-rank-pos"></span>').addClass('cbl-rank-pos-' + (check.position_color || 'bad')).text(check.position)
        );
      } else {
        tdPos.append($('<span class="cbl-rank-pos cbl-rank-pos-bad"></span>').text('–'));
      }
      tr.append(tdPos);

      const tdChange = $('<td class="cbl-rank-col-change"></td>');
      const change = check ? check.change : null;
      if (change > 0) {
        tdChange.append($('<span class="cbl-rank-change-up"></span>').text('▲ ' + change));
      } else if (change < 0) {
        tdChange.append($('<span class="cbl-rank-change-down"></span>').text('▼ ' + Math.abs(change)));
      } else {
        tdChange.append($('<span class="cbl-rank-change-flat"></span>').text('–'));
      }
      tr.append(tdChange);

      // Bing has its own search volume/CPC/competition metrics; fall back
      // to the Google fields for every other engine (mirrors the SaaS
      // dashboard's tool-rank-tracker.blade.php).
      const isBing = 'bing' === rankActiveEngine;
      const kwVolume = isBing ? kw.bing_search_volume : kw.search_volume;
      const kwCpc = isBing ? kw.bing_cpc : kw.cpc;
      const kwCompetition = isBing ? kw.bing_competition : kw.competition;

      tr.append($('<td class="cbl-rank-col-best"></td>').text(check && (check.best || 0 === check.best) ? check.best : '–'));
      tr.append($('<td class="cbl-rank-col-volume"></td>').text(kwVolume || 0 === kwVolume ? kwVolume : '–'));
      tr.append($('<td class="cbl-rank-col-cpc"></td>').text(kwCpc ? kwCpc : '–'));

      const tdIntent = $('<td class="cbl-rank-col-intent"></td>');
      if (kw.intent) {
        String(kw.intent).split(',').forEach(part => {
          const value = part.trim();
          if (!value) {
            return;
          }
          tdIntent.append(
            $('<span class="cbl-rank-intent-chip"></span>').attr('title', value).text(value.charAt(0).toUpperCase())
          );
        });
      } else {
        tdIntent.text('–');
      }
      tr.append(tdIntent);

      const tdCompetition = $('<td class="cbl-rank-col-competition"></td>');
      if (kwCompetition) {
        tdCompetition.append($('<span class="cbl-rank-competition-pill"></span>').text(kwCompetition));
      } else {
        tdCompetition.text('–');
      }
      tr.append(tdCompetition);

      const tdPage = $('<td class="cbl-rank-col-page"></td>');
      if (check && check.found_url) {
        let path = check.found_url;
        try {
          path = new URL(check.found_url).pathname;
        } catch (e) {
          path = check.found_url;
        }
        tdPage.append(
          $('<a target="_blank" rel="noopener"></a>').attr('href', check.found_url).attr('title', check.found_url).text(path)
        );
      } else {
        tdPage.text('–');
      }
      tr.append(tdPage);

      tr.append($('<td class="cbl-rank-col-checked"></td>').text(check && check.checked_on ? check.checked_on : '–'));

      return tr;
    };

    const updateBulkBar = () => {
      const bar = $('#wpcbl-rank-bulkbar');
      const count = rankSelected.size;
      bar.find('.cbl-rank-bulk-count').text(count > 0 ? params.rankBulkSelected.replace('%1$s', count) : '');
      bar.toggle(count > 0);
    };

    // ---- Renderers ----

    const renderTrackerView = () => {
      // Drop selections for keywords that no longer exist.
      const currentIds = new Set((rankState.keywords || []).map(kw => kw.id));
      rankSelected.forEach(id => {
        if (!currentIds.has(id)) {
          rankSelected.delete(id);
        }
      });

      const quota = rankState.quota || { positions_used: 0, positions_limit: 0 };
      const quotaFull = 'number' === typeof quota.positions_limit && quota.positions_limit > 0 && quota.positions_used >= quota.positions_limit;
      rankAddBtn.toggle(!quotaFull);

      const noKeywords = !rankState.keywords || !rankState.keywords.length;

      // Header.
      const head = $('<div class="cbl-card cbl-rank-head"></div>');
      const headTop = $('<div class="cbl-rank-head-top"></div>');
      headTop.append(
        $('<span class="cbl-rank-badge"></span>').text(
          params.rankQuotaText.replace('%1$s', quota.positions_used).replace('%2$s', quota.positions_limit)
        )
      );

      if (rankState.next_check) {
        headTop.append(
          $('<span class="cbl-rank-next-check"></span>').text(
            params.rankNextUpdate.replace('%1$s', rankState.next_check)
          )
        );
      }

      const headActions = $('<div class="cbl-rank-head-actions"></div>');
      const refreshes = rankState.refreshes || { used: 0, limit: 0 };
      const refreshBtn = $('<button type="button" id="wpcbl-rank-refresh" class="cbl-btn"></button>').text(params.rankRefreshNow);
      const refreshDisabled = refreshes.used >= refreshes.limit || noKeywords;
      refreshBtn.prop('disabled', refreshDisabled);
      refreshBtn.attr(
        'title',
        params.rankRefreshesText.replace('%1$s', Math.max(0, refreshes.limit - refreshes.used)).replace('%2$s', refreshes.limit)
      );
      headActions.append(refreshBtn);
      headActions.append($('<button type="button" id="wpcbl-rank-export" class="cbl-btn"></button>').text(params.rankExportCsv));
      if (quotaFull && params.rankUpgradeUrl) {
        headActions.append(
          $('<a class="cbl-btn cbl-btn-primary"></a>')
            .attr('href', params.rankUpgradeUrl)
            .text(params.rankGetMore)
        );
      }
      headTop.append(headActions);
      head.append(headTop);
      head.append($('<p id="wpcbl-rank-status" class="cbl-rank-status"></p>'));
      rankContent.append(head);

      // Engine tabs.
      const engines = rankState.engines && rankState.engines.length ? rankState.engines : ['google'];
      if (!rankActiveEngine || -1 === engines.indexOf(rankActiveEngine)) {
        rankActiveEngine = engines[0];
      }
      if (engines.length > 1) {
        const tabs = $('<div class="cbl-rank-tabs"></div>');
        engines.forEach(engine => {
          tabs.append(
            $('<button type="button" class="cbl-rank-tab"></button>')
              .toggleClass('is-active', engine === rankActiveEngine)
              .attr('data-wpcbl-rank-engine', engine)
              .text(rankEngineLabel(engine))
          );
        });
        rankContent.append(tabs);
      }

      // Bulk bar.
      const bulkBar = $('<div id="wpcbl-rank-bulkbar" class="cbl-rank-bulkbar" style="display:none;"></div>');
      bulkBar.append($('<span class="cbl-rank-bulk-count"></span>'));
      bulkBar.append(
        $('<button type="button" id="wpcbl-rank-bulk-delete" class="cbl-btn cbl-btn-danger"></button>').text(params.rankDeleteSelected)
      );
      rankContent.append(bulkBar);

      // Table or empty state.
      if (noKeywords) {
        const empty = $('<div class="cbl-card cbl-rank-empty"></div>');
        empty.append($('<h2></h2>').text(params.rankEmptyTitle));
        empty.append($('<p></p>').text(params.rankEmptyMessage));
        empty.append(
          $('<button type="button" id="wpcbl-rank-empty-add" class="cbl-btn cbl-btn-primary"></button>').text(params.rankAddButton)
        );
        rankContent.append(empty);
      } else {
        const wrap = $('<div class="cbl-rank-table-wrap"></div>');
        const table = $('<table class="cbl-rank-table"></table>');
        table.append(buildRankTableHead());
        const tbody = $('<tbody></tbody>');
        rankState.keywords.forEach(kw => tbody.append(buildRankRow(kw)));
        table.append(tbody);
        wrap.append(table);
        rankContent.append(wrap);

        if (rankState.keywords.some(kw => kw.pending)) {
          rankContent.append($('<p class="cbl-rank-pending-note"></p>').text(params.rankPendingNote));
        }
      }

      updateBulkBar();

      // Charts.
      const chartsWrap = $('<div id="wpcbl-rank-charts" class="cbl-rank-charts"></div>');
      rankContent.append(chartsWrap);
      if ('function' === typeof renderRankCharts) {
        renderRankCharts(chartsWrap, rankState.charts);
      }

      // Daily upsell, pointing at the plugin's own Upgrade page so the
      // purchase never requires a dashboard login.
      if (!rankState.has_daily && params.rankUpgradeUrl) {
        const upsell = $('<div class="cbl-card cbl-rank-upsell"></div>');
        upsell.append($('<h2></h2>').text(params.rankDailyTitle));
        upsell.append($('<p></p>').text(params.rankDailyMessage));
        upsell.append(
          $('<a class="cbl-btn cbl-btn-primary"></a>')
            .attr('href', params.rankUpgradeUrl)
            .text(params.rankDailyButton)
        );
        rankContent.append(upsell);
      }
    };

    const renderSettingsView = () => {
      const settings = rankState.settings || {};
      const card = $('<div class="cbl-card cbl-rank-settings-card"></div>');
      card.append($('<h2></h2>').text(params.rankSettingsTitle));
      card.append($('<p id="wpcbl-rank-status" class="cbl-rank-status"></p>'));

      const grid = $('<div class="cbl-rank-settings-grid"></div>');

      // Device.
      const deviceField = $('<div class="cbl-rank-settings-field"></div>');
      deviceField.append($('<label for="wpcbl-rank-settings-device"></label>').text(params.rankSettingsDevice));
      const deviceSelect = $('<select id="wpcbl-rank-settings-device"></select>');
      [
        ['desktop', params.rankDeviceDesktop],
        ['mobile', params.rankDeviceMobile],
        ['both', params.rankDeviceBoth]
      ].forEach(pair => {
        deviceSelect.append($('<option></option>').val(pair[0]).text(pair[1]));
      });
      deviceSelect.val(settings.device || 'desktop');
      deviceField.append(deviceSelect);
      grid.append(deviceField);

      // Engine.
      const engineField = $('<div class="cbl-rank-settings-field"></div>');
      engineField.append($('<label for="wpcbl-rank-settings-engine"></label>').text(params.rankSettingsEngine));
      const engineSelect = $('<select id="wpcbl-rank-settings-engine"></select>');
      [
        ['google', params.rankEngineGoogle],
        ['bing', params.rankEngineBing],
        ['both', params.rankEngineBoth]
      ].forEach(pair => {
        engineSelect.append($('<option></option>').val(pair[0]).text(pair[1]));
      });
      engineSelect.val(settings.engine || 'google');
      engineField.append(engineSelect);
      grid.append(engineField);

      // Email.
      const emailField = $('<div class="cbl-rank-settings-field"></div>');
      const emailToggleLabel = $('<label class="cbl-rank-settings-toggle"></label>');
      const emailToggle = $('<input type="checkbox" id="wpcbl-rank-settings-email" />').prop('checked', !!settings.email_enabled);
      emailToggleLabel.append(emailToggle).append($('<span></span>').text(params.rankSettingsEmailLabel));
      emailField.append(emailToggleLabel);
      const emailRecipients = $('<input type="text" id="wpcbl-rank-settings-email-recipients" />')
        .attr('placeholder', params.rankSettingsEmailPlaceholder)
        .val(settings.email_recipients || '')
        .prop('disabled', !settings.email_enabled);
      emailField.append(emailRecipients);
      grid.append(emailField);
      emailToggle.on('change', function () {
        emailRecipients.prop('disabled', !$(this).is(':checked'));
      });

      // Share.
      const shareField = $('<div class="cbl-rank-settings-field cbl-rank-settings-share"></div>');
      const shareToggleLabel = $('<label class="cbl-rank-settings-toggle"></label>');
      const shareToggle = $('<input type="checkbox" id="wpcbl-rank-settings-share" />').prop('checked', !!settings.share_enabled);
      shareToggleLabel.append(shareToggle).append($('<span></span>').text(params.rankSettingsShareLabel));
      shareField.append(shareToggleLabel);

      const sharePassword = $('<input type="password" id="wpcbl-rank-settings-share-password" autocomplete="new-password" />')
        .attr('placeholder', settings.share_has_password ? '••••••••' : params.rankSettingsPasswordPlaceholder)
        .prop('disabled', !settings.share_enabled);
      shareField.append(sharePassword);
      shareToggle.on('change', function () {
        sharePassword.prop('disabled', !$(this).is(':checked'));
      });

      const shareUrlWrap = $('<div class="cbl-rank-settings-share-url"></div>');
      const shareUrlInput = $('<input type="text" id="wpcbl-rank-settings-share-url" readonly="readonly" />').val(settings.share_url || '');
      const shareCopyBtn = $('<button type="button" id="wpcbl-rank-settings-share-copy" class="cbl-btn"></button>').text(params.rankSettingsCopyLink);
      shareUrlWrap.append(shareUrlInput).append(shareCopyBtn);
      shareUrlWrap.toggle(!!settings.share_url);
      shareField.append(shareUrlWrap);

      const shareError = $('<p class="cbl-rank-settings-error" data-wpcbl-field="share"></p>').hide();
      shareField.append(shareError);
      grid.append(shareField);

      card.append(grid);

      const saveBtn = $('<button type="button" id="wpcbl-rank-settings-save" class="cbl-btn cbl-btn-primary"></button>').text(params.rankSettingsSave);
      card.append(saveBtn);

      rankContent.append(card);

      const initialShareEnabled = !!settings.share_enabled;

      saveBtn.on('click', function (event) {
        event.preventDefault();
        shareError.text('').hide();
        saveBtn.prop('disabled', true);
        rankPolls = 0;
        rankClearStatus();

        const shareEnabled = shareToggle.is(':checked');
        const passwordVal = sharePassword.val();
        const shareChanged = shareEnabled !== initialShareEnabled || !!passwordVal;

        const finishSaveError = (message, field) => {
          saveBtn.prop('disabled', false);
          if ('share' === field) {
            shareError.text(message || params.rankLoadError).show();
          } else {
            rankShowStatus(message || params.rankLoadError, true);
          }
        };

        const finishSaveSuccess = () => {
          saveBtn.prop('disabled', false);
          rankShowStatus(params.rankSettingsSaved, false);
          loadRank(true);
        };

        const doShareSave = () => {
          if (!shareChanged) {
            return finishSaveSuccess();
          }
          rankPost('wpcbl_rank_share', { sharing: shareEnabled ? 'on' : 'off', password: passwordVal || '' })
            .done(shareRes => {
              if (!shareRes || !shareRes.success) {
                return finishSaveError(shareRes && shareRes.data ? shareRes.data : params.rankLoadError, 'share');
              }
              finishSaveSuccess();
            })
            .fail(jqXHR => {
              finishSaveError(rankResponseError(jqXHR), 'share');
            });
        };

        rankPost('wpcbl_rank_settings', {
          device: deviceSelect.val(),
          engine: engineSelect.val(),
          email_enabled: emailToggle.is(':checked') ? '1' : '0',
          email_recipients: emailRecipients.val()
        })
          .done(res => {
            if (!res || !res.success) {
              return finishSaveError(res && res.data ? res.data : params.rankLoadError, null);
            }
            doShareSave();
          })
          .fail(jqXHR => {
            finishSaveError(rankResponseError(jqXHR), null);
          });
      });

      shareCopyBtn.on('click', function (event) {
        event.preventDefault();
        const url = shareUrlInput.val();
        if (!url) {
          return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(() => {
            rankShowStatus(params.rankShareCopied, false);
          });
        } else {
          shareUrlInput.trigger('select');
          document.execCommand('copy');
          rankShowStatus(params.rankShareCopied, false);
        }
      });
    };

    const renderRank = () => {
      rankSkeleton.hide();
      rankErrorBox.hide();
      rankContent.empty().show();
      if ('settings' === rankView) {
        rankAddBtn.hide();
        renderSettingsView();
      } else {
        renderTrackerView();
      }
      // #wpcbl-rank-status is rebuilt by the view render above, which wipes
      // out any message an action just showed. Re-apply the last message
      // (if any) so it survives the loadRank(true) re-render that follows
      // every action.
      if (rankStatusMessage) {
        rankShowStatus(rankStatusMessage, rankStatusWarning);
      }
    };

    // ---- Add keywords modal ----
    // cblModal() only supports a single text input + checkbox, not the
    // textarea/select/radio-group form this needs, so this follows the
    // same bespoke-overlay pattern as aiFixModal() above (reuses the
    // .cbl-modal-overlay / .cbl-modal / .cbl-modal-actions classes).
    const rankAddModal = () => {
      return new Promise(resolve => {
        const previousFocus = document.activeElement;
        const overlay = $(
          '<div class="cbl-modal-overlay" role="presentation">' +
            '<div class="cbl-modal cbl-modal-rank-add" role="dialog" aria-modal="true" aria-labelledby="cbl-rank-add-title">' +
              '<h2 class="cbl-modal-title" id="cbl-rank-add-title"></h2>' +
              '<p class="cbl-modal-message"></p>' +
              '<textarea class="cbl-rank-add-textarea" rows="5"></textarea>' +
              '<select class="cbl-rank-add-market"></select>' +
              '<div class="cbl-rank-add-devices"></div>' +
              '<div class="cbl-modal-actions">' +
                '<button type="button" class="cbl-btn cbl-modal-cancel"></button>' +
                '<button type="button" class="cbl-btn cbl-btn-primary cbl-modal-confirm"></button>' +
              '</div>' +
            '</div>' +
          '</div>'
        );

        overlay.find('.cbl-modal-title').text(params.rankAddTitle);
        overlay.find('.cbl-modal-message').text(params.rankAddMessage);

        const marketSelect = overlay.find('.cbl-rank-add-market');
        (rankState.markets || []).forEach(market => {
          marketSelect.append($('<option></option>').val(market.key).text(market.label));
        });
        const defaultMarket = (rankState.project && rankState.project.market) || '';
        if (defaultMarket) {
          marketSelect.val(defaultMarket);
        }

        const devicesWrap = overlay.find('.cbl-rank-add-devices');
        const defaultDevice = (rankState.project && rankState.project.device) || 'desktop';
        [
          ['desktop', params.rankDeviceDesktop],
          ['mobile', params.rankDeviceMobile],
          ['both', params.rankDeviceBoth]
        ].forEach(pair => {
          const label = $('<label class="cbl-rank-add-device"></label>');
          const radio = $('<input type="radio" name="cbl-rank-add-device" />').val(pair[0]).prop('checked', pair[0] === defaultDevice);
          label.append(radio).append($('<span></span>').text(pair[1]));
          devicesWrap.append(label);
        });

        overlay.find('.cbl-modal-cancel').text(params.modalCancel);
        const confirmBtn = overlay.find('.cbl-modal-confirm').text(params.rankAddButton);

        const close = confirmed => {
          overlay.remove();
          $(document).off('keydown.cblRankAdd');
          if (previousFocus && previousFocus.focus) {
            previousFocus.focus();
          }
          resolve({
            confirmed: confirmed,
            keywords: overlay.find('.cbl-rank-add-textarea').val(),
            market: marketSelect.val(),
            device: overlay.find('input[name="cbl-rank-add-device"]:checked').val()
          });
        };

        overlay.on('click', function (event) {
          if (event.target === this) {
            close(false);
          }
        });
        overlay.find('.cbl-modal-cancel').on('click', () => close(false));
        confirmBtn.on('click', () => close(true));
        $(document).on('keydown.cblRankAdd', event => {
          if ('Escape' === event.key) {
            close(false);
          }
        });

        $('body').append(overlay);
        overlay.find('.cbl-rank-add-textarea').trigger('focus');
      });
    };

    // ---- Actions ----

    $(document).on('click', '#wpcbl-rank-add, #wpcbl-rank-empty-add', function (event) {
      event.preventDefault();
      if (!rankState) {
        return;
      }
      rankAddModal().then(choice => {
        if (!choice.confirmed) {
          return;
        }
        const keywords = (choice.keywords || '').trim();
        if (!keywords) {
          return;
        }
        rankPolls = 0;
        rankClearStatus();
        rankPost('wpcbl_rank_add_keywords', { keywords: keywords, market: choice.market || '', device: choice.device || '' })
          .done(res => {
            if (!res || !res.success) {
              rankShowStatus(res && res.data ? res.data : params.rankLoadError, true);
              return;
            }
            if (res.data && res.data.message) {
              rankShowStatus(res.data.message, false);
            }
            loadRank(true);
          })
          .fail(jqXHR => {
            rankShowStatus(rankResponseError(jqXHR), true);
          });
      });
    });

    $(document).on('click', '.cbl-rank-row-delete', function (event) {
      event.preventDefault();
      const id = $(this).closest('tr').data('wpcbl-rank-id');
      cblModal({
        title: params.rankDeleteTitle,
        message: params.rankDeleteConfirm,
        confirmText: params.rankDeleteTitle,
        danger: true
      }).then(modal => {
        if (!modal.confirmed) {
          return;
        }
        rankPolls = 0;
        rankClearStatus();
        rankSelected.delete(id);
        rankPost('wpcbl_rank_delete', { id: id })
          .done(res => {
            if (!res || !res.success) {
              rankShowStatus(res && res.data ? res.data : params.rankLoadError, true);
            }
          })
          .fail(jqXHR => {
            rankShowStatus(rankResponseError(jqXHR), true);
          })
          .always(() => {
            loadRank(true);
          });
      });
    });

    $(document).on('click', '#wpcbl-rank-bulk-delete', function (event) {
      event.preventDefault();
      if (!rankSelected.size) {
        return;
      }
      cblModal({
        title: params.rankDeleteTitle,
        message: params.rankDeleteConfirm,
        confirmText: params.rankDeleteTitle,
        danger: true
      }).then(modal => {
        if (!modal.confirmed) {
          return;
        }
        const ids = Array.from(rankSelected);
        rankPolls = 0;
        rankClearStatus();
        rankPost('wpcbl_rank_bulk_delete', { ids: ids })
          .done(res => {
            if (!res || !res.success) {
              rankShowStatus(res && res.data ? res.data : params.rankLoadError, true);
            }
          })
          .fail(jqXHR => {
            rankShowStatus(rankResponseError(jqXHR), true);
          })
          .always(() => {
            rankSelected.clear();
            loadRank(true);
          });
      });
    });

    $(document).on('click', '#wpcbl-rank-refresh', function (event) {
      event.preventDefault();
      if ($(this).prop('disabled')) {
        return;
      }
      const ids = rankSelected.size > 0 ? Array.from(rankSelected) : [];
      rankPolls = 0;
      rankClearStatus();
      rankPost('wpcbl_rank_refresh', ids.length ? { ids: ids } : {})
        .done(res => {
          if (!res || !res.success) {
            rankShowStatus(res && res.data ? res.data : params.rankLoadError, true);
            return;
          }
          if (res.data && false === res.data.ok) {
            rankShowStatus(res.data.message || '', true);
          } else if (res.data && res.data.message) {
            rankShowStatus(res.data.message, false);
          }
        })
        .fail(jqXHR => {
          rankShowStatus(rankResponseError(jqXHR), true);
        })
        .always(() => {
          loadRank(true);
        });
    });

    $(document).on('click', '#wpcbl-rank-export', function (event) {
      event.preventDefault();
      const ids = rankSelected.size > 0 ? Array.from(rankSelected) : [];
      rankPost('wpcbl_rank_export', ids.length ? { ids: ids } : {})
        .done(res => {
          if (!res || !res.success || !res.data) {
            rankShowStatus(res && res.data ? res.data : params.rankLoadError, true);
            return;
          }
          const blob = new Blob([res.data.csv], { type: 'text/csv' });
          const a = document.createElement('a');
          const url = URL.createObjectURL(blob);
          a.href = url;
          a.download = res.data.filename;
          document.body.appendChild(a);
          a.click();
          a.remove();
          // Firefox/Safari need the object URL to stay alive until the
          // download has actually started; revoking it synchronously can
          // cancel the download in those browsers.
          setTimeout(() => URL.revokeObjectURL(url), 0);
        })
        .fail(jqXHR => {
          rankShowStatus(rankResponseError(jqXHR), true);
        });
    });

    $(document).on('click', '.cbl-rank-tab', function (event) {
      event.preventDefault();
      rankActiveEngine = $(this).data('wpcbl-rank-engine');
      renderRank();
    });

    $(document).on('change', '#wpcbl-rank-select-all', function () {
      const checked = $(this).is(':checked');
      $('.cbl-rank-row-check').each(function () {
        const id = parseInt($(this).val(), 10);
        $(this).prop('checked', checked);
        if (checked) {
          rankSelected.add(id);
        } else {
          rankSelected.delete(id);
        }
      });
      updateBulkBar();
    });

    $(document).on('change', '.cbl-rank-row-check', function () {
      const id = parseInt($(this).val(), 10);
      if ($(this).is(':checked')) {
        rankSelected.add(id);
      } else {
        rankSelected.delete(id);
        $('#wpcbl-rank-select-all').prop('checked', false);
      }
      updateBulkBar();
    });

    $(document).on('click', '#wpcbl-rank-retry', function (event) {
      event.preventDefault();
      rankErrorBox.hide();
      rankSkeleton.show();
      loadRank(false);
    });

    loadRank(false);
  }

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
          '<div class="cbl-audit-block" style="flex:' + b.weight + ';background:' +
          (b.lost > 0 ? 'var(--cbl-red-bg)' : 'var(--cbl-bg-tertiary)') + '" title="' +
          esc(b.label + ' ' + b.score + '/100') + '">' +
          '<div style="height:' + b.score + '%;background:' + TONE[b.tone] + '"></div></div>'
        )
        .join('');

      const labels = triage.bars
        .map(b =>
          '<div class="cbl-audit-block-label" style="flex:' + b.weight + '">' +
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
          '<span class="cbl-audit-mini"><i style="width:' + g.score + '%;background:' + TONE[g.tone] + '"></i></span>' +
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
      $('#wpcbl-audit-skipquery-wrap').toggle(!page);
    };
    auditSyncMode();

    $('#wpcbl-audit-mode').on('change', auditSyncMode);

    // The skip option renders as a bordered chip, so it reads as clickable.
    // wp_kses strips <label> from the topbar, so bind the whole chip here.
    $('#wpcbl-audit-skipquery-wrap').on('click', function (event) {
      if (event.target.id === 'wpcbl-audit-skipquery') {
        return;
      }
      const box = $('#wpcbl-audit-skipquery');
      box.prop('checked', !box.prop('checked'));
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
      } else if ($('#wpcbl-audit-skipquery').is(':checked')) {
        data.skip_query = 1;
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

  // ===== Plans & upgrades page =====
  const planToggle = $('#wpcbl-plan-toggle');
  if (planToggle.length) {
    let planInterval = 'yearly';

    // The current subscription's shape, for enabling Update subscription
    // only when something actually changed.
    const currentCard = $('[data-wpcbl-current="1"]').first();
    const initialShape = currentCard.length
      ? {
          interval: planToggle.attr('data-wpcbl-initial-interval') || 'yearly',
          kw: String(currentCard.attr('data-wpcbl-initial-kw') || '0'),
          daily: String(currentCard.attr('data-wpcbl-initial-daily') || '0'),
          ai: String(currentCard.attr('data-wpcbl-initial-ai') || '0')
        }
      : null;

    const cardSelections = card => ({
      kw: String(card.find('.wpcbl-sel-kw').val() || '0'),
      daily: String(card.find('.wpcbl-sel-daily').val() || '0'),
      ai: String(card.find('.wpcbl-sel-ai').val() || '0')
    });

    const refreshDirtyState = () => {
      if (!initialShape || !currentCard.length) {
        return;
      }
      const now = cardSelections(currentCard);
      const dirty =
        planInterval !== initialShape.interval ||
        now.kw !== initialShape.kw ||
        now.daily !== initialShape.daily ||
        now.ai !== initialShape.ai;
      $('#wpcbl-update-current').prop('disabled', !dirty);
    };

    const applyPlanInterval = interval => {
      planInterval = interval;
      planToggle.find('[data-wpcbl-interval]').each(function () {
        $(this).toggleClass('is-active', $(this).data('wpcbl-interval') === interval);
      });
      $('.wpcbl-plan-amount, .wpcbl-plan-period, .wpcbl-daily-label, .cbl-plan-permonth').each(function () {
        const value = $(this).attr('data-' + interval);
        if (null !== value && undefined !== value) {
          $(this).text(value);
        }
      });
      $('option[data-label-yearly]').each(function () {
        $(this).text($(this).attr('monthly' === interval ? 'data-label-monthly' : 'data-label-yearly'));
      });
      $('.wpcbl-interval-input').val(interval);
      refreshDirtyState();
    };

    planToggle.on('click', '[data-wpcbl-interval]', function () {
      applyPlanInterval($(this).data('wpcbl-interval'));
    });

    $(document).on('change', '.wpcbl-sel-kw, .wpcbl-sel-daily, .wpcbl-sel-ai', refreshDirtyState);

    applyPlanInterval(planToggle.attr('data-wpcbl-initial-interval') || 'yearly');

    const planStatus = message => $('#wpcbl-plan-status').text(message || '');

    const planFail = jqXHR =>
      planStatus((jqXHR.responseJSON && jqXHR.responseJSON.data) || params.errorGeneric);

    $('.wpcbl-change-plan').on('click', function () {
      const btn = $(this);
      if (!window.confirm(params.bilChangeConfirm.replace('%1$s', btn.data('wpcbl-plan-name')))) {
        return;
      }
      const card = btn.closest('[data-wpcbl-plan-card]');
      const picks = cardSelections(card);
      btn.prop('disabled', true);
      planStatus(params.bilWorking);
      $.post(params.ajaxUrl, {
        action: 'wpcbl_billing_change_plan',
        nonce: params.nonce,
        plan: btn.data('wpcbl-plan'),
        interval: planInterval,
        keywords: picks.kw,
        daily: picks.daily,
        ai: picks.ai
      })
        .done(res => {
          const data = res && res.data ? res.data : {};
          if (res.success && data.ok) {
            planStatus(data.message || '');
            window.setTimeout(() => window.location.reload(), 2500);
            return;
          }
          planStatus(data.message || ('string' === typeof res.data ? res.data : params.errorGeneric));
          btn.prop('disabled', false);
        })
        .fail(jqXHR => {
          planFail(jqXHR);
          btn.prop('disabled', false);
        });
    });
  }
})(jQuery);
