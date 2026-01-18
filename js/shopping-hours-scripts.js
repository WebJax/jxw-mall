// Scripts for shopping hours admin functionality
// Integrated from shoppinghours plugin into centershop

jQuery(document).ready(function($) {
    'use strict';
    
    // Handle "helt lukket" checkbox toggle
    function toggleTimeInputs() {
        $('.centershop-closed-toggle').each(function() {
            const $checkbox = $(this);
            const $row = $checkbox.closest('tr');
            const $timeInputs = $row.find('.centershop-time-input');
            
            if ($checkbox.is(':checked')) {
                $timeInputs.prop('disabled', true).addClass('disabled');
                $row.addClass('disabled');
            } else {
                $timeInputs.prop('disabled', false).removeClass('disabled');
                $row.removeClass('disabled');
            }
        });
    }
    
    // Initialize on page load
    toggleTimeInputs();
    
    // Handle checkbox change
    $('.centershop-closed-toggle').on('change', function() {
        toggleTimeInputs();
    });
    
    // Add visual feedback for time inputs
    $('.centershop-time-input').on('focus', function() {
        $(this).closest('tr').addClass('editing');
    }).on('blur', function() {
        $(this).closest('tr').removeClass('editing');
    });
});
