/**
 * Instagram Feed Settings - Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Import now button
    $('#centershop-ig-import-now').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $result = $('#centershop-ig-import-result');
        
        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.empty();
        
        // Make AJAX request
        $.ajax({
            url: centershopIG.ajaxurl,
            type: 'POST',
            data: {
                action: 'centershop_ig_import_now',
                nonce: centershopIG.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color: #46b450;">' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color: #d63638;">Fejl: ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<span style="color: #d63638;">HTTP Fejl: ' + error + '</span>');
            },
            complete: function() {
                // Re-enable button and hide spinner
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
