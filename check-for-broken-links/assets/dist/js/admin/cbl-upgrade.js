"use strict";

/* jshint esversion: 6 */

/**
 * Plans & upgrades page.
 *
 * Split out of check-for-broken-links-admin-scripts.js in 3.1.7, so the
 * other plugin pages stop loading it. Self-contained: needs jQuery only.
 */

($ => {
  // This block read `params` without ever defining it, so Update
  // subscription stopped with a ReferenceError before 3.1.7.
  const params = window.wpcblUpgrade || {};

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

    // Whole dollars stay whole, cents always show two digits. Mirrors
    // $wpcbl_money in templates/admin/upgrade.php.
    const planMoney = amount => {
      const rounded = Math.round(amount * 100) / 100;
      return rounded % 1 === 0
        ? rounded.toLocaleString('en-US')
        : rounded.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const optionAmount = (select, interval) =>
      parseFloat(select.find('option:selected').attr('data-' + interval)) || 0;

    // Daily updates are priced by keyword count, so the daily option's
    // price follows the keyword pick.
    const refreshDailyOption = card => {
      const daily = card.find('.wpcbl-sel-daily');
      const option = daily.find('option[value="1"]');
      if (!option.length) {
        return;
      }
      let prices = {};
      try {
        prices = JSON.parse(daily.attr('data-prices') || '{}') || {};
      } catch (e) {
        prices = {};
      }
      const kw = String(card.find('.wpcbl-sel-kw').val() || '0');
      const price = prices[kw] || prices['0'] || { yearly: 0, monthly: 0 };
      const isCurrent =
        initialShape &&
        '1' === card.attr('data-wpcbl-current') &&
        '1' === initialShape.daily &&
        kw === initialShape.kw;
      const name = option.attr('data-name') || '';
      const current = option.attr('data-current') || '';
      option.attr('data-label-yearly', name + (isCurrent ? current : '+$' + planMoney(parseFloat(price.yearly) || 0) + '/yr'));
      option.attr('data-label-monthly', name + (isCurrent ? current : '+$' + planMoney(parseFloat(price.monthly) || 0) + '/mo'));
      option.attr('data-yearly', price.yearly);
      option.attr('data-monthly', price.monthly);
    };

    // Headline price, billed line, keyword count and add-on hint for one
    // card, from the base price plus every picked add-on.
    const refreshCard = card => {
      refreshDailyOption(card);
      const kwSel = card.find('.wpcbl-sel-kw');
      const dailySel = card.find('.wpcbl-sel-daily');
      const aiSel = card.find('.wpcbl-sel-ai');
      const dailyOn = '1' === String(dailySel.val() || '0');
      const sum = interval =>
        (parseFloat(card.attr('data-wpcbl-base-' + interval)) || 0) +
        optionAmount(kwSel, interval) +
        (dailyOn ? optionAmount(dailySel, interval) : 0) +
        optionAmount(aiSel, interval);

      const yearly = sum('yearly');
      const monthly = sum('monthly');
      const billed = card.find('.wpcbl-plan-billed');
      if ('monthly' === planInterval) {
        card.find('.wpcbl-plan-amount').text('$' + monthly.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        billed.text(billed.attr('data-text-monthly') || '');
      } else {
        card.find('.wpcbl-plan-amount').text('$' + (yearly / 12).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        billed.text((billed.attr('data-tpl-yearly') || '').replace('%s', planMoney(yearly)));
      }

      const count = kwSel.find('option:selected').attr('data-count');
      if (count) {
        card.find('.wpcbl-kw-count').text(count);
      }

      const picked =
        ('0' !== String(kwSel.val() || '0') ? 1 : 0) + (dailyOn ? 1 : 0) + ('0' !== String(aiSel.val() || '0') ? 1 : 0);
      const hint = card.find('.wpcbl-addons-hint');
      hint.text(picked ? (hint.attr('data-tpl-some') || '').replace('%s', picked) : hint.attr('data-text-none') || '');

      card.find('option[data-label-yearly]').each(function () {
        $(this).text($(this).attr('monthly' === planInterval ? 'data-label-monthly' : 'data-label-yearly'));
      });
    };

    const applyPlanInterval = interval => {
      planInterval = interval;
      planToggle.find('[data-wpcbl-interval]').each(function () {
        const on = $(this).data('wpcbl-interval') === interval;
        $(this).toggleClass('is-active', on).attr('aria-pressed', on ? 'true' : 'false');
      });
      $('.wpcbl-interval-input').val(interval);
      $('[data-wpcbl-plan-card]').each(function () {
        refreshCard($(this));
      });
      refreshDirtyState();
    };

    planToggle.on('click', '[data-wpcbl-interval]', function () {
      applyPlanInterval($(this).data('wpcbl-interval'));
    });

    $(document).on('change', '.wpcbl-sel-kw, .wpcbl-sel-daily, .wpcbl-sel-ai', function () {
      refreshCard($(this).closest('[data-wpcbl-plan-card]'));
      refreshDirtyState();
    });

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
