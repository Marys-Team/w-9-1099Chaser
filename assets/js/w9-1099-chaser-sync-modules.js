jQuery(document).ready(function ($) {

    var $activeCard = null;
    var activePayload = null;
    var activeNonce = '';
    var ajaxUrl = typeof w91099chSyncModules !== 'undefined' && w91099chSyncModules.ajax_url ? w91099chSyncModules.ajax_url : (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector.ajaxurl ? window.w91099chConnector.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'));
    var ajaxNonce = typeof w91099chSyncModules !== 'undefined' && w91099chSyncModules.nonce ? w91099chSyncModules.nonce : (typeof window.w91099chConnector !== 'undefined' && window.w91099chConnector.nonce ? window.w91099chConnector.nonce : '');

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getActionForModule(moduleId) {
        var actions = {
            'website-content-media-assets': 'w91099ch_sync_website_content',
            'analytics-customer-relationship': 'w91099ch_sync_analytics',
            'system-configuration-design': 'w91099ch_sync_system_config',
            'user-security-system-access': 'w91099ch_sync_security_access',
            'payment-gateway-third-party-integrations': 'w91099ch_sync_payments',
        };
        return actions[moduleId] || null;
    }

    function getPayload($card) {
        var moduleId = String($card.data('module-id') || '');
        var payload = { module: moduleId, selected: {} };
        var count = 0;
        $card.find('.w91099ch-sync-item:checked').each(function () {
            var group = String($(this).data('group') || '');
            var item  = String($(this).data('item')  || '');
            if (!group || !item) return;
            if (!payload.selected[group]) payload.selected[group] = [];
            payload.selected[group].push(item);
            count++;
        });
        return { payload: payload, count: count };
    }

    function updateCard($card) {
        var hasConsent = $card.find('.w91099ch-sync-consent').is(':checked');
        var $btn    = $card.find('.w91099ch-sync-btn');
        var $status = $card.find('.w91099ch-sync-status');
        var info    = getPayload($card);
        var action  = getActionForModule(String($card.data('module-id') || ''));

        $card.find('.w91099ch-sync-option').each(function () {
            var checked = $(this).find('.w91099ch-sync-item').is(':checked');
            $(this).toggleClass('is-excluded', !checked);
        });

        $card.find('.w91099ch-sync-group').each(function () {
            var $items = $(this).find('.w91099ch-sync-item');
            var $groupCheck = $(this).find('.w91099ch-sync-group-check');
            var checkedCount = $items.filter(':checked').length;
            var total = $items.length;
            $groupCheck.prop('checked', checkedCount === total);
            $groupCheck.prop('indeterminate', checkedCount > 0 && checkedCount < total);
            $groupCheck.closest('.w91099ch-sync-group-toggle').toggleClass('is-excluded', checkedCount === 0);
        });

        $card.find('.w91099ch-sync-payload-pre').text(JSON.stringify(info.payload, null, 2));
        $card.find('.w91099ch-sync-payload-count').text(info.count + ' selected');

        $btn.prop('disabled', !hasConsent);
        if (!hasConsent) {
            $status.removeClass('is-success is-error is-loading').text('Check consent to enable sync');
        } else if (info.count === 0) {
            $status.removeClass('is-success is-loading').addClass('is-error').text('Select at least one item');
        } else if (action) {
            $status.removeClass('is-success is-error is-loading').text('Ready to sync');
        } else {
            $status.removeClass('is-success is-error is-loading').text('Preview only (no sync action)');
        }
    }

    // Init all cards
    $('.w91099ch-sync-card').each(function () { updateCard($(this)); });

    // Checkbox change
    $(document).on('change', '.w91099ch-sync-card input[type="checkbox"]', function () {
        updateCard($(this).closest('.w91099ch-sync-card'));
    });

    // Group heading checkbox → toggle all items in group
    $(document).on('change', '.w91099ch-sync-group-check', function () {
        var $group = $(this).closest('.w91099ch-sync-group');
        var checked = $(this).is(':checked');
        $group.find('.w91099ch-sync-item').prop('checked', checked);
        updateCard($(this).closest('.w91099ch-sync-card'));
    });

    // Expand / collapse group
    $(document).on('click', '.w91099ch-sync-group-expand', function () {
        var $btn = $(this);
        var $body = $btn.closest('.w91099ch-sync-group').find('.w91099ch-sync-group-body');
        var expanded = $btn.attr('aria-expanded') === 'true';
        $btn.attr('aria-expanded', String(!expanded));
        $btn.toggleClass('is-collapsed', expanded);
        $body.toggleClass('is-collapsed', expanded);
    });

    // Sync button click
    $(document).on('click', '.w91099ch-sync-btn', function () {
        var $btn  = $(this);
        var $card = $btn.closest('.w91099ch-sync-card');
        var $status = $card.find('.w91099ch-sync-status');
        var action  = getActionForModule(String($card.data('module-id') || ''));

        if (!$card.find('.w91099ch-sync-consent').is(':checked')) {
            updateCard($card);
            return;
        }

        if (!action) {
            $status.removeClass('is-success is-loading').addClass('is-error').text('No sync handler for this module');
            return;
        }

        $activeCard  = $card;
        activePayload = getPayload($card);
        activeNonce = $card.find('.w91099ch-sync-nonce').val() || $card.attr('data-nonce') || ajaxNonce;

        if (activePayload.count === 0) {
            $status.removeClass('is-success is-loading').addClass('is-error').text('Select at least one item');
            $btn.prop('disabled', false);
            return;
        }

        $btn.prop('disabled', true);
        $status.removeClass('is-success is-error').addClass('is-loading').text('Preparing payload...');

        setTimeout(function () {
            $status.removeClass('is-loading').text('Waiting for confirmation...');
            showModal($card, activePayload);
        }, 650);
    });

    function showModal($card, info) {
        var confirmMsg = String($card.data('confirm-message') || 'Are you sure you want to sync selected data?');
        $('#w91099ch-sc-modal').find('.w91099ch-modal-confirm-msg').text(confirmMsg);
        $('#w91099ch-sc-modal').find('.w91099ch-modal-payload').text(JSON.stringify(info.payload, null, 2));
        $('#w91099ch-sc-modal').fadeIn(180);
    }

    function closeModal() {
        $('#w91099ch-sc-modal').fadeOut(160);
    }

    // Cancel
    $(document).on('click', '#w91099ch-sc-cancel', function () {
        closeModal();
        if ($activeCard) {
            $activeCard.find('.w91099ch-sync-btn').prop('disabled', false);
            $activeCard.find('.w91099ch-sync-status').removeClass('is-success is-error is-loading').text('Sync cancelled');
        }
        $activeCard = null;
    });

    // OK
    $(document).on('click', '#w91099ch-sc-ok', function () {
        closeModal();
        if (!$activeCard) return;
        var $card   = $activeCard;
        var $btn    = $card.find('.w91099ch-sync-btn');
        var $status = $card.find('.w91099ch-sync-status');
        var info    = activePayload;
        var action  = getActionForModule(String($card.data('module-id') || ''));
        var nonce   = activeNonce || $card.find('.w91099ch-sync-nonce').val() || $card.attr('data-nonce') || ajaxNonce;
        $activeCard = null;

        if (!action || info.count === 0) {
            $btn.prop('disabled', false);
            $status.removeClass('is-success is-loading').addClass('is-error').text('Nothing to sync');
            return;
        }

        $btn.prop('disabled', true);
        $status.removeClass('is-success is-error').addClass('is-loading').text('Syncing...');

        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            dataType: 'json',
            data: {
                action: action,
                nonce: nonce,
                payload: JSON.stringify(info.payload)
            },
            success: function (response) {
                $btn.prop('disabled', false);
                if (response && response.success) {
                    var msg = response.data && response.data.message ? response.data.message : 'Synced successfully';
                    var details = [];
                    if (response.data && typeof response.data.sent !== 'undefined') details.push('sent: ' + response.data.sent);
                    if (response.data && typeof response.data.items_count !== 'undefined') details.push('items: ' + response.data.items_count);
                    if (details.length) msg += ' (' + details.join(', ') + ')';
                    $status.removeClass('is-error is-loading').addClass('is-success').text(msg);
                } else {
                    var errMsg = 'Sync failed';
                    if (response && response.data && typeof response.data === 'string') errMsg = response.data;
                    $status.removeClass('is-success is-loading').addClass('is-error').text(errMsg);
                }
            },
            error: function () {
                $btn.prop('disabled', false);
                $status.removeClass('is-success is-loading').addClass('is-error').text('Connection error');
            }
        });
    });

    // Backdrop click closes modal
    $(document).on('click', '#w91099ch-sc-modal', function (e) {
        if (e.target === this) $('#w91099ch-sc-cancel').trigger('click');
    });

    // ESC key closes modal
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') $('#w91099ch-sc-cancel').trigger('click');
    });
});
