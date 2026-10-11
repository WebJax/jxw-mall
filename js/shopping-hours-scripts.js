// Scripts for shopping hours admin functionality
// Integrated from shoppinghours plugin into centershop

jQuery(document).ready(function($) {
    'use strict';

    var ajaxUrl = shoppinghours_ajax_data.url; // Use the localized variable for AJAX URL
    var weekdayNames = shoppinghours_ajax_data.weekdays || ['Søndag', 'Mandag', 'Tirsdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lørdag'];

    /**
     * Enable or disable the time fields in one row.
     *
     * @param {JQuery} $row
     */
    function toggleRow($row) {
        var closed = $row.find('.centershop-closed-toggle').is(':checked');
        var $timeInputs = $row.find('.centershop-time-input');

        $timeInputs.prop('disabled', closed).toggleClass('disabled', closed);
        $row.toggleClass('disabled', closed);
    }

    /**
     * @param {string} value Y-m-d
     * @returns {string}
     */
    function weekdayLabel(value) {
        if (!value) {
            return '';
        }
        var parts = value.split('-');
        if (parts.length !== 3) {
            return '';
        }
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return weekdayNames[date.getDay()] || '';
    }

    /**
     * @returns {string} Y-m-d in the browser's local calendar
     */
    function todayStamp() {
        var now = new Date();
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var day = String(now.getDate()).padStart(2, '0');
        return now.getFullYear() + '-' + month + '-' + day;
    }

    /**
     * Show which weekday a date replaces, and fade dates that have passed.
     *
     * @param {JQuery} $row
     */
    function updateExtraDateRow($row) {
        var value = $row.find('.centershop-ekstra-dato').val();
        $row.find('.centershop-weekday-hint').text(weekdayLabel(value));
        $row.toggleClass('is-past', value !== '' && value < todayStamp());
    }

    $('.centershop-closed-toggle').each(function() {
        toggleRow($(this).closest('tr'));
    });

    $(document).on('change', '.centershop-closed-toggle', function() {
        toggleRow($(this).closest('tr'));
    });

    $(document).on('focus', '.centershop-time-input', function() {
        $(this).closest('tr').addClass('editing');
    }).on('blur', '.centershop-time-input', function() {
        $(this).closest('tr').removeClass('editing');
    });

    $(document).on('change', '.centershop-ekstra-dato', function() {
        updateExtraDateRow($(this).closest('tr'));
    });

    $(document).on('click', '.centershop-remove-ekstra-dato', function() {
        $(this).closest('tr').remove();
    });

    $('#centershop-add-ekstra-dato').on('click', function() {
        var table = document.getElementById('centershop-ekstra-datoer');
        var template = document.getElementById('centershop-ekstra-dato-template');
        var tbody = document.getElementById('centershop-ekstra-datoer-body');
        if (!table || !template || !tbody) {
            return;
        }

        var index = parseInt(table.getAttribute('data-next-index'), 10);
        if (Number.isNaN(index)) {
            index = tbody.querySelectorAll('tr').length;
        }

        var html = template.innerHTML.split('__INDEX__').join(String(index));
        tbody.insertAdjacentHTML('beforeend', html);
        table.setAttribute('data-next-index', String(index + 1));

        var $row = $(tbody).children('tr').last();
        toggleRow($row);
        $row.find('.centershop-ekstra-dato').trigger('focus');
    });
});
