/**
 * Admin Tables Module
 * Handles table sorting, row selection, and bulk actions
 * Requires: admin-core.js
 */

(function($) {
    'use strict';

    // Extend AdminPanel namespace with table functionality
    $.extend(AdminPanel, {
        
        // Initialize tables
        initTables: function() {
            // Add sorting functionality
            $('.admin-table th[data-sort]').on('click', function() {
                AdminPanel.sortTable($(this));
            });

            // Add row selection
            $('.admin-table input[type="checkbox"]').on('change', function() {
                AdminPanel.updateRowSelection();
            });
        },

        // Sort table
        sortTable: function($header) {
            const $table = $header.closest('table');
            const column = $header.data('sort');
            const $rows = $table.find('tbody tr').toArray();
            
            $rows.sort(function(a, b) {
                const aVal = $(a).find(`td[data-sort="${column}"]`).text();
                const bVal = $(b).find(`td[data-sort="${column}"]`).text();
                return aVal.localeCompare(bVal);
            });
            
            $table.find('tbody').empty().append($rows);
        },

        // Update row selection
        updateRowSelection: function() {
            const $selectedRows = $('.admin-table input[type="checkbox"]:checked');
            const $bulkActions = $('.bulk-actions');
            
            if ($selectedRows.length > 0) {
                $bulkActions.show();
            } else {
                $bulkActions.hide();
            }
        }
    });

    // Initialize tables when document is ready
    $(document).ready(function() {
        if (typeof AdminPanel !== 'undefined') {
            AdminPanel.initTables();
        }
    });

})(jQuery);
