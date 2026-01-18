/**
 * CenterShop Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Shop access page handlers
    $(document).ready(function() {
        // Delete user
        $(document).on('click', '.centershop-delete-user', function() {
            var userId = $(this).data('user-id');
            var userName = $(this).data('user-name');
            
            if (!confirm(centershopAdmin.strings.confirm_delete + '\n\n' + userName)) {
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true);
            
            $.post(centershopAdmin.ajaxUrl, {
                action: 'centershop_delete_shop_user',
                nonce: centershopAdmin.nonce,
                user_id: userId
            }, function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data || centershopAdmin.strings.error);
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // Send credentials
        $(document).on('click', '.centershop-send-credentials', function() {
            var userId = $(this).data('user-id');
            var $btn = $(this);
            
            $btn.prop('disabled', true).text(centershopAdmin.strings.saving);
            
            $.post(centershopAdmin.ajaxUrl, {
                action: 'centershop_send_credentials',
                nonce: centershopAdmin.nonce,
                user_id: userId
            }, function(response) {
                $btn.prop('disabled', false).text('Send login');
                
                if (response.success) {
                    alert(response.data);
                } else {
                    alert(response.data || centershopAdmin.strings.error);
                }
            });
        });
    });
    
})(jQuery);
