/**
 * Admin JavaScript for 410 Response Manager
 *
 * @package 410-response-manager
 * @author Rathly
 * @copyright 2024 Rathly
 */

jQuery(document).ready(function($) {
    'use strict';

    const CONFIG = {
        maxFileSize: 5242880, // 5MB
        debounceDelay: 300,
        selectors: {
            deleteUrl: '.delete-url',
            urlCheckbox: '.url-checkbox',
            selectAll: '#cb-select-all-1, #cb-select-all-2',
            bulkActionSelect: '#bulk-action-selector',
            bulkActionSubmit: '#bulk-action-submit',
            navTab: '.nav-tab',
            urlPattern: '#url_pattern',
            isRegex: '#is_regex',
            csvFile: '#csv_file'
        }
    };

    /**
     * Initialize all functionality
     */
    function init() {
        initTabs();
        initDeleteHandlers();
        initPatternValidation();
        initFileUpload();
        initBulkActions();
        initSelectAll();
    }

    /**
     * Initialize tab navigation
     */
    function initTabs() {
        $(CONFIG.selectors.navTab).on('click', function(e) {
            e.preventDefault();
            
            $(CONFIG.selectors.navTab).removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.tab-content').hide();
            $($(this).attr('href')).show();
            
            history.pushState(null, null, $(this).attr('href'));
        });
        
        if (window.location.hash) {
            $('a[href="' + window.location.hash + '"]').trigger('click');
        }
    }

    /**
     * Initialize delete handlers
     */
    function initDeleteHandlers() {
        $(document).on('click', CONFIG.selectors.deleteUrl, function(e) {
            e.preventDefault();
            
            if (!confirm(rathly410Ajax.confirmDelete)) {
                return;
            }
            
            const $row = $(this).closest('tr');
            const urlId = $(this).data('id');
            
            $row.addClass('loading');
            
            $.ajax({
                url: rathly410Ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rathly_delete_410_url',
                    id: urlId,
                    nonce: rathly410Ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(400, function() {
                            $(this).remove();
                            updateTableState();
                        });
                    } else {
                        alert(response.data.message || rathly410Ajax.errorDeleting);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete failed:', error);
                    alert(rathly410Ajax.networkError);
                },
                complete: function() {
                    $row.removeClass('loading');
                }
            });
        });
    }

    /**
     * Initialize pattern validation
     */
    function initPatternValidation() {
        let validationTimer;
        
        $(CONFIG.selectors.urlPattern + ', ' + CONFIG.selectors.isRegex).on('input change', function() {
            clearTimeout(validationTimer);
            
            validationTimer = setTimeout(function() {
                validatePattern();
            }, CONFIG.debounceDelay);
        });
    }

    /**
     * Validate pattern
     */
    function validatePattern() {
        const pattern = $(CONFIG.selectors.urlPattern).val();
        const isRegex = $(CONFIG.selectors.isRegex).is(':checked');
        
        $('.pattern-preview, .validation-error').remove();
        
        if (!pattern) {
            return;
        }

        if (isRegex) {
            try {
                new RegExp(pattern);
                showPatternPreview(pattern);
            } catch(e) {
                showValidationError(rathly410Ajax.invalidRegex + e.message);
            }
        }
    }

    /**
     * Initialize file upload handling
     */
    function initFileUpload() {
        $(CONFIG.selectors.csvFile).on('change', function() {
            const file = this.files[0];
            if (!file) {
                return;
            }

            if (!validateFileType(file)) {
                alert(rathly410Ajax.invalidFile);
                this.value = '';
                return;
            }
            
            if (!validateFileSize(file)) {
                alert(rathly410Ajax.fileSizeLimit);
                this.value = '';
                return;
            }
        });
    }

    /**
     * Initialize bulk actions
     */
    function initBulkActions() {
        $(CONFIG.selectors.bulkActionSubmit).on('click', function(e) {
            e.preventDefault();
            
            const action = $(CONFIG.selectors.bulkActionSelect).val();
            const selected = $(CONFIG.selectors.urlCheckbox + ':checked');
            
            if (!validateBulkAction(action, selected)) {
                return;
            }
            
            if (action === 'delete' && !confirm(rathly410Ajax.confirmBulkDelete)) {
                return;
            }
            
            const ids = selected.map(function() {
                return $(this).val();
            }).get();
            
            const $bulkActions = $('.bulkactions');
            $bulkActions.addClass('loading');
            
            $.ajax({
                url: rathly410Ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rathly_bulk_410_action',
                    bulk_action: action,
                    ids: ids,
                    nonce: rathly410Ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message || rathly410Ajax.bulkActionError);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Bulk action failed:', error);
                    alert(rathly410Ajax.networkError);
                },
                complete: function() {
                    $bulkActions.removeClass('loading');
                }
            });
        });
    }

    /**
     * Initialize select all functionality
     */
    function initSelectAll() {
        $(CONFIG.selectors.selectAll).on('change', function() {
            $(CONFIG.selectors.urlCheckbox).prop('checked', $(this).prop('checked'));
        });

        $(document).on('change', CONFIG.selectors.urlCheckbox, function() {
            const allChecked = $(CONFIG.selectors.urlCheckbox + ':checked').length === $(CONFIG.selectors.urlCheckbox).length;
            $(CONFIG.selectors.selectAll).prop('checked', allChecked);
        });
    }

    /**
     * Validate bulk action parameters
     */
    function validateBulkAction(action, selected) {
        if (!action) {
            alert(rathly410Ajax.selectAction);
            return false;
        }
        
        if (selected.length === 0) {
            alert(rathly410Ajax.selectItems);
            return false;
        }

        return true;
    }

    /**
     * Validate file type
     */
    function validateFileType(file) {
        return file.type === 'text/csv' || file.name.endsWith('.csv');
    }

    /**
     * Validate file size
     */
    function validateFileSize(file) {
        return file.size <= CONFIG.maxFileSize;
    }

    /**
     * Show pattern preview
     */
    function showPatternPreview(pattern) {
        $('<div class="pattern-preview">')
            .append('<h4>' + rathly410Ajax.previewTitle + '</h4>')
            .append($('<code>').text(pattern))
            .insertAfter(CONFIG.selectors.urlPattern);
    }

    /**
     * Show validation error
     */
    function showValidationError(message) {
        $('<span class="validation-error">')
            .text(message)
            .insertAfter(CONFIG.selectors.urlPattern);
    }

    /**
     * Update table state
     */
    function updateTableState() {
        const $tbody = $('.wp-list-table tbody');
        const $rows = $tbody.find('tr').not('.no-items');
        
        if ($rows.length === 0) {
            $tbody.html('<tr class="no-items"><td class="colspanchange" colspan="5">' + 
                rathly410Ajax.noPatterns + '</td></tr>');
        }

        // Update count display
        $('.displaying-num').text(
            $rows.length === 1 ? '1 item' : $rows.length + ' items'
        );
    }

    // Initialize everything when ready
    init();
});
