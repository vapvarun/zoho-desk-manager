/**
 * Draft Handler for Zoho Desk Manager
 * Handles AI draft generation, saving, and management
 */

(function($) {
    'use strict';

    var ZDM_Draft = {
        ticketId: null,
        isDirty: false,

        init: function() {
            this.ticketId = $('#zdm-generate-ai-draft').data('ticket-id');
            this.bindEvents();
            this.initWordCounter();
            this.checkAutoSave();
        },

        bindEvents: function() {
            var self = this;

            // Generate AI Draft button
            $('#zdm-generate-ai-draft').on('click', function(e) {
                e.preventDefault();
                self.showAIOptions();
            });

            // Generate with options
            $('#zdm-generate-with-options').on('click', function(e) {
                e.preventDefault();
                self.generateAIDraft();
            });

            // Cancel AI options
            $('#zdm-cancel-ai-options').on('click', function(e) {
                e.preventDefault();
                self.hideAIOptions();
            });

            // Save draft
            $('#zdm-save-draft').on('click', function(e) {
                e.preventDefault();
                self.saveDraft();
            });

            // Load saved draft
            $('#zdm-load-saved-draft').on('click', function(e) {
                e.preventDefault();
                self.loadSavedDraft();
            });

            // Clear draft
            $('#zdm-clear-draft').on('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to clear the draft?')) {
                    self.clearDraft();
                }
            });

            // Improve draft
            $('#zdm-improve-draft').on('click', function(e) {
                e.preventDefault();
                self.improveDraft();
            });

            // Copy to reply
            $('#zdm-copy-draft').on('click', function(e) {
                e.preventDefault();
                self.copyToReply();
            });

            // Send and close
            $('#zdm-send-and-close').on('click', function(e) {
                e.preventDefault();
                self.sendAndClose();
            });

            // Track draft changes
            $('#zdm-draft-content').on('input', function() {
                self.isDirty = true;
                self.updateWordCount();
                self.autoSaveDraft();
            });

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                // Ctrl/Cmd + G = Generate draft
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 71) {
                    e.preventDefault();
                    $('#zdm-generate-ai-draft').click();
                }
                // Ctrl/Cmd + S = Save draft
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
                    e.preventDefault();
                    self.saveDraft();
                }
            });
        },

        showAIOptions: function() {
            $('#zdm-ai-options').slideDown();
            $('#zdm-generate-ai-draft').prop('disabled', true);
        },

        hideAIOptions: function() {
            $('#zdm-ai-options').slideUp();
            $('#zdm-generate-ai-draft').prop('disabled', false);
        },

        generateAIDraft: function() {
            var self = this;
            var responseType = $('#zdm-response-type').val();
            var responseTone = $('#zdm-response-tone').val();

            // Show loading state
            self.showLoading('Generating AI draft...');
            self.hideAIOptions();

            $.ajax({
                url: zdm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'zdm_generate_ai_response',
                    ticket_id: self.ticketId,
                    response_type: responseType,
                    tone: responseTone,
                    nonce: zdm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Check if browser mode
                        if (response.data.browser_mode) {
                            self.hideLoading();
                            self.showBrowserPrompt(response.data);
                        } else {
                            // Set draft content for API mode
                            $('#zdm-draft-content').val(response.data.response);
                            self.updateWordCount();

                            // Show success status
                            self.showStatus('✓ AI draft generated successfully', 'success');

                            // Show suggestions if available
                            if (response.data.suggestions && response.data.suggestions.length > 0) {
                                self.showSuggestions(response.data.suggestions);
                            }

                            // Auto-save the generated draft
                            self.saveDraft(true);

                            // Usage stats available in response.data.usage if needed
                        }
                    } else {
                        self.showStatus('Failed to generate draft: ' + response.data, 'error');
                    }
                },
                error: function() {
                    self.showStatus('Network error. Please try again.', 'error');
                },
                complete: function() {
                    self.hideLoading();
                    $('#zdm-generate-ai-draft').prop('disabled', false);
                }
            });
        },

        saveDraft: function(silent) {
            var self = this;
            var draftContent = $('#zdm-draft-content').val();

            if (!draftContent.trim()) {
                if (!silent) {
                    self.showStatus('Draft is empty', 'error');
                }
                return;
            }

            if (!silent) {
                self.showLoading('Saving draft...');
            }

            $.ajax({
                url: zdm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'zdm_save_draft',
                    ticket_id: self.ticketId,
                    draft_content: draftContent,
                    nonce: zdm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.isDirty = false;
                        if (!silent) {
                            self.showStatus('✓ Draft saved successfully', 'success');
                        }
                        $('.zdm-draft-timestamp').text('Last saved: ' + new Date().toLocaleString());
                    } else {
                        if (!silent) {
                            self.showStatus('Failed to save draft', 'error');
                        }
                    }
                },
                error: function() {
                    if (!silent) {
                        self.showStatus('Network error while saving', 'error');
                    }
                },
                complete: function() {
                    if (!silent) {
                        self.hideLoading();
                    }
                }
            });
        },

        loadSavedDraft: function() {
            var self = this;

            self.showLoading('Loading saved draft...');

            $.ajax({
                url: zdm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'zdm_load_draft',
                    ticket_id: self.ticketId,
                    nonce: zdm_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.draft) {
                        $('#zdm-draft-content').val(response.data.draft);
                        self.updateWordCount();
                        self.showStatus('✓ Draft loaded successfully', 'success');

                        if (response.data.meta) {
                            $('.zdm-draft-timestamp').text('Generated: ' + response.data.meta.generated_at);
                        }
                    } else {
                        self.showStatus('No saved draft found', 'info');
                    }
                },
                error: function() {
                    self.showStatus('Failed to load draft', 'error');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        clearDraft: function() {
            $('#zdm-draft-content').val('');
            this.updateWordCount();
            this.showStatus('Draft cleared', 'info');
            $('#zdm-ai-suggestions').slideUp();
        },

        improveDraft: function() {
            var self = this;
            var currentDraft = $('#zdm-draft-content').val();

            if (!currentDraft.trim()) {
                self.showStatus('No draft to improve', 'error');
                return;
            }

            // Show improvement options
            var improvementTypes = [
                'more concise',
                'more detailed',
                'more friendly',
                'more professional',
                'more empathetic'
            ];

            var buttons = improvementTypes.map(function(type) {
                return '<button class="button button-small zdm-improve-type" data-type="' + type + '">' +
                       type.charAt(0).toUpperCase() + type.slice(1) + '</button>';
            }).join(' ');

            self.showStatus('Select improvement type: ' + buttons, 'info');

            // Bind improvement type clicks
            $('.zdm-improve-type').on('click', function() {
                var improvementType = $(this).data('type');
                self.executeImprovement(currentDraft, improvementType);
            });
        },

        executeImprovement: function(currentDraft, improvementType) {
            var self = this;

            self.showLoading('Improving draft to be ' + improvementType + '...');

            $.ajax({
                url: zdm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'zdm_improve_response',
                    current_response: currentDraft,
                    improvement_type: improvementType,
                    nonce: zdm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#zdm-draft-content').val(response.data.improved_response);
                        self.updateWordCount();
                        self.showStatus('✓ Draft improved successfully', 'success');
                        self.saveDraft(true);
                    } else {
                        self.showStatus('Failed to improve draft', 'error');
                    }
                },
                error: function() {
                    self.showStatus('Network error', 'error');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        copyToReply: function() {
            var draftContent = $('#zdm-draft-content').val();

            if (!draftContent.trim()) {
                this.showStatus('No draft to copy', 'error');
                return;
            }

            // Copy to TinyMCE editor if available
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('reply_content')) {
                tinyMCE.get('reply_content').setContent(draftContent);
            } else {
                // Fallback to textarea
                $('#reply_content').val(draftContent);
            }

            this.showStatus('✓ Draft copied to reply form', 'success');

            // Scroll to reply form
            $('html, body').animate({
                scrollTop: $('#zdm-reply-form').offset().top - 100
            }, 500);
        },

        sendAndClose: function() {
            var self = this;

            // First copy draft to reply
            self.copyToReply();

            // Add close ticket flag
            $('<input>').attr({
                type: 'hidden',
                name: 'close_ticket',
                value: '1'
            }).appendTo('#zdm-reply-form');

            // Submit the form
            $('#zdm-reply-form').submit();
        },

        updateWordCount: function() {
            var text = $('#zdm-draft-content').val();
            var words = text.trim() ? text.trim().split(/\s+/).length : 0;
            var chars = text.length;

            $('.word-count').text(words);
            $('.char-count').text(chars);
        },

        initWordCounter: function() {
            this.updateWordCount();
        },

        autoSaveDraft: function() {
            var self = this;

            // Clear existing timer
            if (self.autoSaveTimer) {
                clearTimeout(self.autoSaveTimer);
            }

            // Set new timer (save after 2 seconds of no typing)
            self.autoSaveTimer = setTimeout(function() {
                if (self.isDirty) {
                    self.saveDraft(true);
                }
            }, 2000);
        },

        checkAutoSave: function() {
            // Check for browser's localStorage auto-saved draft
            var localDraft = localStorage.getItem('zdm_draft_' + this.ticketId);
            if (localDraft && !$('#zdm-draft-content').val()) {
                if (confirm('Found an auto-saved draft. Would you like to restore it?')) {
                    $('#zdm-draft-content').val(localDraft);
                    this.updateWordCount();
                    this.showStatus('Auto-saved draft restored', 'info');
                }
            }

            // Save to localStorage on change
            var self = this;
            $('#zdm-draft-content').on('input', function() {
                localStorage.setItem('zdm_draft_' + self.ticketId, $(this).val());
            });
        },

        showSuggestions: function(suggestions) {
            var $suggestionsList = $('.zdm-suggestions-list');
            $suggestionsList.empty();

            suggestions.forEach(function(suggestion) {
                $suggestionsList.append('<li>' + suggestion + '</li>');
            });

            $('#zdm-ai-suggestions').slideDown();
        },

        showStatus: function(message, type) {
            var colors = {
                'success': '#46b450',
                'error': '#dc3545',
                'info': '#00a0d2'
            };

            $('#zdm-draft-status').show();
            $('.zdm-draft-status-text').html(
                '<span style="color: ' + colors[type] + ';">' + message + '</span>'
            );
        },

        showLoading: function(message) {
            message = message || 'Processing...';
            $('#zdm-draft-status').show();
            $('.zdm-draft-status-text').html(
                '<span class="spinner is-active" style="float: left; margin: 0 5px 0 0;"></span>' + message
            );
        },

        hideLoading: function() {
            $('.spinner', '#zdm-draft-status').remove();
        },

        showBrowserPrompt: function(data) {
            var self = this;
            var provider = data.provider === 'claude' ? 'Claude' : 'ChatGPT';
            var providerUrl = data.provider === 'claude' ? 'https://claude.ai/new' : 'https://chat.openai.com';

            // Create modal HTML
            var modalHtml = '<div id="zdm-browser-prompt-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; display: flex; align-items: center; justify-content: center;">';
            modalHtml += '<div style="background: white; padding: 30px; border-radius: 8px; max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto;">';

            // Header
            modalHtml += '<h2 style="margin-top: 0;">Generate Response with ' + provider + '</h2>';

            // Instructions
            modalHtml += '<div style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
            modalHtml += '<h3 style="margin-top: 0;">Instructions:</h3>';
            modalHtml += '<ol>';
            modalHtml += '<li>Click "Copy Prompt" below</li>';
            modalHtml += '<li>Click "Open ' + provider + '" to open in a new tab</li>';
            modalHtml += '<li>Paste the prompt in ' + provider + ' and press Enter</li>';
            modalHtml += '<li>Copy the response from ' + provider + '</li>';
            modalHtml += '<li>Paste it in the box below and click "Use This Response"</li>';
            modalHtml += '</ol>';
            modalHtml += '</div>';

            // Prompt display
            modalHtml += '<div style="margin-bottom: 20px;">';
            modalHtml += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Generated Prompt:</label>';
            modalHtml += '<textarea id="zdm-browser-prompt" style="width: 100%; height: 200px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;" readonly>' + data.prompt + '</textarea>';
            modalHtml += '</div>';

            // Action buttons
            modalHtml += '<div style="display: flex; gap: 10px; margin-bottom: 20px;">';
            modalHtml += '<button class="button button-primary" onclick="ZDM_Draft.copyPrompt()">📋 Copy Prompt</button>';
            modalHtml += '<button class="button" onclick="window.open(\'' + providerUrl + '\', \'_blank\')">🔗 Open ' + provider + '</button>';
            modalHtml += '</div>';

            // Response input
            modalHtml += '<div style="margin-bottom: 20px;">';
            modalHtml += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Paste ' + provider + ' Response Here:</label>';
            modalHtml += '<textarea id="zdm-browser-response" style="width: 100%; height: 200px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" placeholder="After generating the response in ' + provider + ', copy and paste it here..."></textarea>';
            modalHtml += '</div>';

            // Footer buttons
            modalHtml += '<div style="display: flex; justify-content: space-between;">';
            modalHtml += '<button class="button" onclick="document.getElementById(\'zdm-browser-prompt-modal\').remove()">Cancel</button>';
            modalHtml += '<button class="button button-primary" onclick="ZDM_Draft.useBrowserResponse()">Use This Response</button>';
            modalHtml += '</div>';

            modalHtml += '</div>';
            modalHtml += '</div>';

            // Add modal to page
            jQuery('body').append(modalHtml);
        },

        copyPrompt: function() {
            var promptText = document.getElementById('zdm-browser-prompt').value;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(promptText).then(function() {
                    jQuery('.button:contains("Copy Prompt")').text('✓ Copied!');
                    setTimeout(function() {
                        jQuery('.button:contains("Copied")').text('📋 Copy Prompt');
                    }, 2000);
                });
            } else {
                // Fallback for older browsers
                document.getElementById('zdm-browser-prompt').select();
                document.execCommand('copy');
                jQuery('.button:contains("Copy Prompt")').text('✓ Copied!');
            }
        },

        useBrowserResponse: function() {
            var response = document.getElementById('zdm-browser-response').value;

            if (!response.trim()) {
                alert('Please paste the AI response before continuing.');
                return;
            }

            // Insert response into draft
            jQuery('#zdm-draft-content').val(response);
            this.updateWordCount();
            this.showStatus('✓ AI response added to draft', 'success');

            // Close modal
            jQuery('#zdm-browser-prompt-modal').remove();

            // Auto-save
            this.saveDraft(true);
        }
    };

    // Make methods globally accessible for onclick handlers
    window.ZDM_Draft = ZDM_Draft;

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#zdm-draft-section').length) {
            ZDM_Draft.init();
        }
    });

})(jQuery);