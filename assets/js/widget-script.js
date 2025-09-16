/**
 * Zoho Desk Manager Dashboard Widget JavaScript
 *
 * Handles real-time updates and interactions for the dashboard widget
 */

(function($) {
    'use strict';

    var ZDM_Widget = {
        refreshInterval: null,
        countdownInterval: null,
        refreshTime: 60, // seconds
        currentCountdown: 60,
        isRefreshing: false,
        autoRefreshEnabled: true,

        /**
         * Initialize the widget
         */
        init: function() {
            this.bindEvents();
            this.startAutoRefresh();
            this.initTooltips();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Manual refresh button
            $(document).on('click', '#zdm-refresh-widget', function(e) {
                e.preventDefault();
                self.refreshWidget(true);
            });

            // Ticket item click for quick preview
            $(document).on('click', '.zdm-ticket-item', function(e) {
                if (!$(e.target).is('a')) {
                    e.preventDefault();
                    var ticketId = $(this).data('ticket-id');
                    self.showTicketPreview(ticketId);
                }
            });

            // Toggle auto-refresh
            $(document).on('click', '#zdm-toggle-refresh', function(e) {
                e.preventDefault();
                self.toggleAutoRefresh();
            });

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                // Alt + R for refresh
                if (e.altKey && e.keyCode === 82) {
                    e.preventDefault();
                    self.refreshWidget(true);
                }
            });

            // Page visibility change
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    self.pauseAutoRefresh();
                } else {
                    self.resumeAutoRefresh();
                }
            });
        },

        /**
         * Start auto-refresh
         */
        startAutoRefresh: function() {
            var self = this;

            // Clear existing intervals
            this.stopAutoRefresh();

            if (!this.autoRefreshEnabled) {
                return;
            }

            // Set countdown
            this.currentCountdown = this.refreshTime;
            this.updateCountdown();

            // Countdown interval (every second)
            this.countdownInterval = setInterval(function() {
                self.currentCountdown--;
                self.updateCountdown();

                if (self.currentCountdown <= 0) {
                    self.refreshWidget(false);
                    self.currentCountdown = self.refreshTime;
                }
            }, 1000);

            // Main refresh interval as backup
            this.refreshInterval = setInterval(function() {
                self.refreshWidget(false);
            }, zdm_widget.refresh_interval);
        },

        /**
         * Stop auto-refresh
         */
        stopAutoRefresh: function() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
            if (this.countdownInterval) {
                clearInterval(this.countdownInterval);
                this.countdownInterval = null;
            }
        },

        /**
         * Pause auto-refresh (when page not visible)
         */
        pauseAutoRefresh: function() {
            this.stopAutoRefresh();
        },

        /**
         * Resume auto-refresh (when page becomes visible)
         */
        resumeAutoRefresh: function() {
            if (this.autoRefreshEnabled) {
                this.refreshWidget(true);
                this.startAutoRefresh();
            }
        },

        /**
         * Toggle auto-refresh on/off
         */
        toggleAutoRefresh: function() {
            this.autoRefreshEnabled = !this.autoRefreshEnabled;

            if (this.autoRefreshEnabled) {
                this.startAutoRefresh();
                this.showNotification('Auto-refresh enabled', 'success');
            } else {
                this.stopAutoRefresh();
                $('#zdm-refresh-countdown').text('Auto-refresh disabled');
                this.showNotification('Auto-refresh disabled', 'info');
            }
        },

        /**
         * Update countdown display
         */
        updateCountdown: function() {
            if (this.autoRefreshEnabled) {
                var text = zdm_widget.strings.auto_refresh.replace('%d', this.currentCountdown);
                $('#zdm-refresh-countdown').text(text);

                // Add visual indicator when close to refresh
                if (this.currentCountdown <= 5) {
                    $('#zdm-refresh-countdown').css('color', '#dc3545');
                } else {
                    $('#zdm-refresh-countdown').css('color', '#666');
                }
            }
        },

        /**
         * Refresh the widget content
         */
        refreshWidget: function(showLoader) {
            var self = this;

            if (this.isRefreshing) {
                return;
            }

            this.isRefreshing = true;
            var $container = $('#zdm-widget-container');
            var $button = $('#zdm-refresh-widget');

            if (showLoader) {
                $container.addClass('zdm-updating');
                $button.prop('disabled', true)
                       .find('.dashicons')
                       .addClass('dashicons-update-spin');
            }

            $.ajax({
                url: zdm_widget.ajax_url,
                type: 'POST',
                data: {
                    action: 'zdm_refresh_widget',
                    nonce: zdm_widget.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $container.html(response.data.html);
                        self.animateNewContent();
                        self.checkForUrgentTickets();
                    } else {
                        self.showNotification(zdm_widget.strings.error, 'error');
                    }
                },
                error: function() {
                    self.showNotification(zdm_widget.strings.error, 'error');
                },
                complete: function() {
                    self.isRefreshing = false;
                    $container.removeClass('zdm-updating');
                    $button.prop('disabled', false)
                           .find('.dashicons')
                           .removeClass('dashicons-update-spin');
                }
            });
        },

        /**
         * Show ticket preview in modal
         */
        showTicketPreview: function(ticketId) {
            var self = this;

            // Create modal if doesn't exist
            if (!$('#zdm-ticket-modal').length) {
                var modalHtml = '<div id="zdm-ticket-modal" style="display:none;">' +
                               '<div class="zdm-modal-overlay"></div>' +
                               '<div class="zdm-modal-content">' +
                               '<span class="zdm-modal-close">&times;</span>' +
                               '<div class="zdm-modal-body">Loading...</div>' +
                               '</div></div>';
                $('body').append(modalHtml);
            }

            var $modal = $('#zdm-ticket-modal');
            var $modalBody = $modal.find('.zdm-modal-body');

            // Show modal with loading state
            $modal.fadeIn();
            $modalBody.html('<div class="zdm-widget-loading"><span class="spinner is-active"></span> Loading ticket details...</div>');

            // Fetch ticket details
            $.ajax({
                url: zdm_widget.ajax_url,
                type: 'POST',
                data: {
                    action: 'zdm_get_ticket_details',
                    ticket_id: ticketId,
                    nonce: zdm_widget.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $modalBody.html(response.data.html);
                    } else {
                        $modalBody.html('<p>Error loading ticket details</p>');
                    }
                },
                error: function() {
                    $modalBody.html('<p>Error loading ticket details</p>');
                }
            });

            // Close modal handlers
            $modal.on('click', '.zdm-modal-close, .zdm-modal-overlay', function() {
                $modal.fadeOut();
            });

            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) { // ESC key
                    $modal.fadeOut();
                }
            });
        },

        /**
         * Animate new content
         */
        animateNewContent: function() {
            $('.zdm-stat-card').each(function(index) {
                $(this).css('opacity', '0').delay(index * 50).animate({
                    opacity: 1
                }, 300);
            });

            $('.zdm-ticket-item').each(function(index) {
                $(this).css('opacity', '0').delay(index * 30).animate({
                    opacity: 1
                }, 200);
            });
        },

        /**
         * Check for urgent tickets and show browser notification
         */
        checkForUrgentTickets: function() {
            var urgentCount = parseInt($('.zdm-stat-urgent .zdm-stat-number').text());
            var overdueCount = parseInt($('.zdm-stat-overdue .zdm-stat-number').text());

            if (urgentCount > 0 || overdueCount > 0) {
                // Add pulse effect to urgent stats
                if (urgentCount > 0) {
                    $('.zdm-stat-urgent').addClass('pulse-animation');
                }
                if (overdueCount > 0) {
                    $('.zdm-stat-overdue').addClass('pulse-animation');
                }

                // Request notification permission if needed
                if ('Notification' in window && Notification.permission === 'granted') {
                    this.showBrowserNotification(urgentCount, overdueCount);
                }
            }
        },

        /**
         * Show browser notification
         */
        showBrowserNotification: function(urgentCount, overdueCount) {
            if (document.hidden) { // Only show when tab is not active
                var title = 'Zoho Desk Alert';
                var body = '';

                if (urgentCount > 0 && overdueCount > 0) {
                    body = urgentCount + ' urgent and ' + overdueCount + ' overdue tickets need attention!';
                } else if (urgentCount > 0) {
                    body = urgentCount + ' urgent ticket(s) need attention!';
                } else if (overdueCount > 0) {
                    body = overdueCount + ' overdue ticket(s) need attention!';
                }

                var notification = new Notification(title, {
                    body: body,
                    icon: zdm_widget.plugin_url + 'assets/images/icon.png',
                    tag: 'zdm-alert',
                    requireInteraction: false
                });

                notification.onclick = function() {
                    window.focus();
                    window.location.href = zdm_widget.tickets_url;
                    notification.close();
                };

                setTimeout(function() {
                    notification.close();
                }, 5000);
            }
        },

        /**
         * Show notification message
         */
        showNotification: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible zdm-widget-notice">' +
                          '<p>' + message + '</p></div>');

            $('#zdm_ticket_summary .inside').prepend($notice);

            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to stat cards
            $('.zdm-stat-card').attr('title', 'Click to view tickets');

            // Add tooltips to time badges
            $('.zdm-time-badge').each(function() {
                var $this = $(this);
                if ($this.hasClass('zdm-overdue')) {
                    $this.attr('title', 'This ticket is past its due date!');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#zdm_ticket_summary').length) {
            ZDM_Widget.init();

            // Request notification permission
            if ('Notification' in window && Notification.permission === 'default') {
                $('#zdm_ticket_summary').prepend(
                    '<div class="notice notice-info is-dismissible">' +
                    '<p>Enable browser notifications to get alerts for urgent tickets. ' +
                    '<a href="#" id="zdm-enable-notifications">Enable Notifications</a></p>' +
                    '</div>'
                );

                $('#zdm-enable-notifications').on('click', function(e) {
                    e.preventDefault();
                    Notification.requestPermission();
                    $(this).closest('.notice').fadeOut();
                });
            }
        }
    });

    // Add CSS for modal
    $('<style>').text(
        '#zdm-ticket-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 100000; }' +
        '.zdm-modal-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); }' +
        '.zdm-modal-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); ' +
        'background: white; padding: 20px; border-radius: 5px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; }' +
        '.zdm-modal-close { position: absolute; top: 10px; right: 15px; font-size: 28px; cursor: pointer; color: #999; }' +
        '.zdm-modal-close:hover { color: #333; }' +
        '.pulse-animation { animation: pulse 2s infinite; }' +
        '@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }' +
        '.zdm-widget-notice { margin: 0 0 10px 0 !important; }'
    ).appendTo('head');

})(jQuery);