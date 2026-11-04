/**
 * Resolve Facebook Page ID from the shop Facebook URL field.
 */
(function ($) {
    'use strict';

    $(function () {
        var $button = $('#centershop-fetch-fb-page-id');
        if (!$button.length || typeof centershopFbPageId === 'undefined') {
            return;
        }

        $button.on('click', function () {
            var url = $.trim($('#butik_payed_fb').val() || '');
            var $status = $('#centershop-fb-page-id-status');
            var $id = $('#butik_payed_fb_id');

            if (!url) {
                $status.text(centershopFbPageId.i18n.missingUrl).css('color', '#b32d2e');
                return;
            }

            $button.prop('disabled', true);
            $status.text(centershopFbPageId.i18n.working).css('color', '');

            $.post(centershopFbPageId.ajaxUrl, {
                action: 'centershop_resolve_facebook_page_id',
                nonce: centershopFbPageId.nonce,
                url: url
            }).done(function (response) {
                if (response && response.success && response.data && response.data.id) {
                    $id.val(response.data.id);
                    $status.text(centershopFbPageId.i18n.success).css('color', '#1a7f37');
                    return;
                }

                var message = (response && response.data && response.data.message)
                    ? response.data.message
                    : centershopFbPageId.i18n.error;
                $status.text(message).css('color', '#b32d2e');
            }).fail(function () {
                $status.text(centershopFbPageId.i18n.error).css('color', '#b32d2e');
            }).always(function () {
                $button.prop('disabled', false);
            });
        });
    });
})(jQuery);
