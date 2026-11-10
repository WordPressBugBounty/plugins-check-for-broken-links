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

  // Page scripts that live in their own files (cbl-uptime.js) reuse the
  // universal modal instead of shipping a second copy of it.
  window.wpcblModal = cblModal;
})(jQuery);
