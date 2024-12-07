/**
 * Admin JavaScript for 410 Response Manager
 *
 * @package 410-response-manager
 * @author Harry Laurel
 * @copyright 2024 Rathly
 */

jQuery(document).ready(function($) {
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        $('.tab-content').hide();
        $($(this).attr('href')).show();
        
        // Update URL hash without page jump
        history.pushState(null, null, $(this).attr('href'));
    });
    
    // Initialize tab from URL hash
    if (window.location.hash) {
        $('a[href="' + window.location.hash + '"]').click();
    }
    
    // Delete URL pattern handler
    $('.delete-url').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this URL pattern? This action cannot be undone.')) {
            return;
        }
        
        var $row = $(this).closest('tr');
        var urlId = $(this).data('id');
        
        $row.addClass('loading');
        
        $.ajax({
            url: response410Ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_410_url',
                id: urlId,
                nonce: response410Ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                        updateEmptyTableState();
                    });
                } else {
                    alert(response.data.message || 'Error deleting URL pattern');
                    $row.removeClass('loading');
                }
            },
            error: function() {
                alert('Network error occurred. Please try again.');
                $row.removeClass('loading');
            }
        });
    });
    
    // Regex pattern preview
    $('#url_pattern, #is_regex').on('input change', function() {
        var pattern = $('#url_pattern').val();
        var isRegex = $('#is_regex').is(':checked');
        
        if (pattern && isRegex) {
            try {
                new RegExp(pattern);
                showPatternPreview(pattern);
                $('.validation-error').remove();
            } catch(e) {
                showValidationError('Invalid regular expression: ' + e.message);
                $('.pattern-preview').remove();
            }
        } else {
            $('.pattern-preview').remove();
            $('.validation-error').remove();
        }
    });
    
    // CSV file validation
    $('#csv_file').on('change', function() {
        var file = this.files[0];
        if (file) {
            if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
                alert('Please select a valid CSV file');
                this.value = '';
                return;
            }
            
            if (file.size > 5242880) { // 5MB limit
                alert('File size exceeds 5MB limit');
                this.value = '';
                return;
            }
        }
    });
    
    // Bulk action handling
    $('#bulk-action-submit').on('click', function(e) {
        e.preventDefault();
        
        var action = $('#bulk-action-selector').val();
        var selected = $('.url-checkbox:checked');
        
        if (!action) {
            alert('Please select an action');
            return;
        }
        
        if (selected.length === 0) {
            alert('Please select at least one URL pattern');
            return;
        }
        
        if (action === 'delete' && !confirm('Are you sure you want to delete the selected URL patterns? This action cannot be undone.')) {
            return;
        }
        
        var ids = selected.map(function() {
            return $(this).val();
        }).get();
        
        $('.bulk-actions').addClass('loading');
        
        $.ajax({
            url: response410Ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'bulk_410_action',
                bulk_action: action,
                ids: ids,
                nonce: response410Ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data.message || 'Unable to process bulk action');
                    $('.bulk-actions').removeClass('loading');
                }
            },
            error: function() {
                alert('Network error occurred. Please try again.');
                $('.bulk-actions').removeClass('loading');
            }
        });
    });
    
    // Helper Functions
    function showPatternPreview(pattern) {
        var $preview = $('.pattern-preview');
        if (!$preview.length) {
            $preview = $('<div class="pattern-preview"><h4>Pattern Preview</h4><code></code></div>');
            $('#url_pattern').after($preview);
        }
        $preview.find('code').text(pattern);
    }
    
    function showValidationError(message) {
        $('.validation-error').remove();
        $('<span class="validation-error">' + message + '</span>').insertAfter('#url_pattern');
    }
    
    function updateEmptyTableState() {
        if ($('.wp-list-table tbody tr').length === 0) {
            $('.wp-list-table tbody').append(
                '<tr class="no-items"><td class="colspanchange" colspan="5">No URL patterns found.</td></tr>'
            );
        }
    }
});