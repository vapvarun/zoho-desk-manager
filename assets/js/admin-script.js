/**
 * Zoho Desk Manager Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Refresh ticket list
         */
        $('#zdm-refresh-tickets').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var originalText = $button.text();

            $button.text('Refreshing...').prop('disabled', true);

            // Add force_refresh parameter
            var currentUrl = window.location.href;
            var separator = currentUrl.indexOf('?') !== -1 ? '&' : '?';
            window.location.href = currentUrl + separator + 'force_refresh=1';
        });

        /**
         * Quick status update via AJAX
         */
        $('.zdm-quick-status').on('change', function() {
            var $select = $(this);
            var ticketId = $select.data('ticket-id');
            var newStatus = $select.val();
            var $row = $select.closest('tr');

            // Show loading
            $select.prop('disabled', true);
            $row.css('opacity', '0.5');

            $.ajax({
                url: zdm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'zdm_update_status',
                    ticket_id: ticketId,
                    status: newStatus,
                    nonce: zdm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update status badge
                        var $badge = $row.find('.zdm-status-badge');
                        $badge.removeClass('zdm-status-open zdm-status-onhold zdm-status-closed');
                        $badge.addClass('zdm-status-' + newStatus.toLowerCase().replace(' ', ''));
                        $badge.text(newStatus);

                        // Show success message
                        zdmShowNotice('Status updated successfully', 'success');
                    } else {
                        zdmShowNotice('Failed to update status: ' + response.data, 'error');
                        // Revert select
                        $select.val($select.data('original-value'));
                    }
                },
                error: function() {
                    zdmShowNotice('Network error. Please try again.', 'error');
                    // Revert select
                    $select.val($select.data('original-value'));
                },
                complete: function() {
                    $select.prop('disabled', false);
                    $row.css('opacity', '1');
                }
            });
        });

        /**
         * Store original status value
         */
        $('.zdm-quick-status').each(function() {
            $(this).data('original-value', $(this).val());
        });

        /**
         * Reply form validation
         */
        $('#zdm-reply-form').on('submit', function(e) {
            var content = '';

            // Get content from TinyMCE or textarea
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('reply_content')) {
                content = tinyMCE.get('reply_content').getContent();
            } else {
                content = $('#reply_content').val();
            }

            if (!content.trim()) {
                e.preventDefault();
                zdmShowNotice('Please enter a reply message', 'error');
                return false;
            }

            // Show loading state
            $(this).find('input[type="submit"]').val('Sending...').prop('disabled', true);
        });

        /**
         * Auto-save draft replies
         */
        if ($('#reply_content').length) {
            var draftTimer;
            var $textarea = $('#reply_content');
            var ticketId = $('input[name="ticket_id"]').val();

            // Load draft if exists
            var savedDraft = localStorage.getItem('zdm_draft_' + ticketId);
            if (savedDraft && !$textarea.val()) {
                $textarea.val(savedDraft);
                zdmShowNotice('Draft reply loaded', 'info');
            }

            // Save draft on change
            $textarea.on('input', function() {
                clearTimeout(draftTimer);
                draftTimer = setTimeout(function() {
                    var content = $textarea.val();
                    if (content) {
                        localStorage.setItem('zdm_draft_' + ticketId, content);
                    }
                }, 1000);
            });

            // Clear draft on successful submit
            $('#zdm-reply-form').on('submit', function() {
                if (!$(this).data('error')) {
                    localStorage.removeItem('zdm_draft_' + ticketId);
                }
            });
        }

        /**
         * Connection test
         */
        $('#zdm-test-connection').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var originalText = $button.text();

            $button.text('Testing...').prop('disabled', true);

            $.ajax({
                url: zdm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'zdm_test_connection',
                    nonce: zdm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        zdmShowNotice('Connection successful!', 'success');
                    } else {
                        zdmShowNotice('Connection failed: ' + response.data, 'error');
                    }
                },
                error: function() {
                    zdmShowNotice('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });

        /**
         * Show notification
         */
        function zdmShowNotice(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap > h1').after($notice);

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Make WordPress dismiss button work
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        }

        /**
         * Confirm before disconnecting
         */
        $('#zdm-disconnect').on('click', function(e) {
            if (!confirm('Are you sure you want to disconnect from Zoho Desk? This will remove all stored tokens.')) {
                e.preventDefault();
            }
        });

        /**
         * Toggle debug info
         */
        $('#zdm-toggle-debug').on('click', function(e) {
            e.preventDefault();
            $('#zdm-debug-info').slideToggle();
            $(this).text($(this).text() === 'Show Debug Info' ? 'Hide Debug Info' : 'Show Debug Info');
        });

        /**
         * Copy ticket ID to clipboard
         */
        $('.zdm-copy-ticket-id').on('click', function(e) {
            e.preventDefault();
            var ticketId = $(this).data('ticket-id');

            // Create temporary input
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(ticketId).select();
            document.execCommand('copy');
            $temp.remove();

            zdmShowNotice('Ticket ID copied to clipboard', 'success');
        });

    });

})(jQuery);