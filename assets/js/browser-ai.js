/**
 * Browser AI Integration
 * Handles ChatGPT and Claude integration through browser
 */

(function($) {
    'use strict';

    var ZDM_Browser_AI = {

        /**
         * Initialize browser AI functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Replace the standard AI generate button behavior when browser AI is enabled
            $(document).on('click', '#zdm-generate-ai-draft', function(e) {
                if ($('body').hasClass('zdm-browser-ai-enabled')) {
                    e.preventDefault();
                    self.showBrowserAIDialog();
                }
            });

            // Copy prompt to clipboard
            $(document).on('click', '.zdm-copy-prompt', function(e) {
                e.preventDefault();
                self.copyPromptToClipboard();
            });

            // Open AI service
            $(document).on('click', '.zdm-open-ai-service', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                window.open(url, '_blank');
            });
        },

        /**
         * Show browser AI dialog
         */
        showBrowserAIDialog: function() {
            var self = this;
            var ticketId = $('#zdm-generate-ai-draft').data('ticket-id');
            var responseType = $('#zdm-response-type').val() || 'solution';
            var tone = $('#zdm-tone').val() || 'professional';

            // Show loading
            self.showLoading();

            // Generate prompt via AJAX
            $.ajax({
                url: zdm_browser_ai.ajax_url,
                type: 'POST',
                data: {
                    action: 'zdm_browser_ai_generate',
                    ticket_id: ticketId,
                    response_type: responseType,
                    tone: tone,
                    provider: zdm_browser_ai.provider || 'chatgpt',
                    nonce: zdm_browser_ai.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displayBrowserAIInterface(response.data);
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to generate prompt'));
                    }
                },
                error: function() {
                    alert('Failed to generate prompt. Please try again.');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        /**
         * Display browser AI interface
         */
        displayBrowserAIInterface: function(data) {
            var html = '<div id="zdm-browser-ai-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; display: flex; align-items: center; justify-content: center;">';
            html += '<div style="background: white; padding: 30px; border-radius: 8px; max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto;">';

            // Header
            html += '<h2 style="margin-top: 0;">Generate AI Response with ' + (data.provider === 'chatgpt' ? 'ChatGPT' : 'Claude') + '</h2>';

            // Instructions
            html += '<div style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
            html += '<h3 style="margin-top: 0;">Instructions:</h3>';
            html += '<ol style="margin: 10px 0;">';
            if (data.instructions && data.instructions.steps) {
                data.instructions.steps.forEach(function(step) {
                    html += '<li>' + step + '</li>';
                });
            }
            html += '</ol>';
            if (data.instructions && data.instructions.requirements) {
                html += '<p style="margin-bottom: 0;"><strong>Requirements:</strong> ' + data.instructions.requirements + '</p>';
            }
            html += '</div>';

            // Prompt textarea
            html += '<div style="margin-bottom: 20px;">';
            html += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Generated Prompt:</label>';
            html += '<textarea id="zdm-ai-prompt" style="width: 100%; height: 200px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px;" readonly>' + data.prompt + '</textarea>';
            html += '</div>';

            // Action buttons
            html += '<div style="display: flex; gap: 10px; margin-bottom: 20px;">';
            html += '<button class="button button-primary zdm-copy-prompt">📋 Copy Prompt</button>';
            html += '<button class="button zdm-open-ai-service" data-url="' + data.provider_url + '">🔗 Open ' + (data.provider === 'chatgpt' ? 'ChatGPT' : 'Claude') + '</button>';
            html += '</div>';

            // Response input area
            html += '<div style="margin-bottom: 20px;">';
            html += '<label style="display: block; margin-bottom: 5px; font-weight: bold;">Paste AI Response Here:</label>';
            html += '<textarea id="zdm-ai-response-input" style="width: 100%; height: 200px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" placeholder="After generating the response in ' + (data.provider === 'chatgpt' ? 'ChatGPT' : 'Claude') + ', copy and paste it here..."></textarea>';
            html += '</div>';

            // Footer buttons
            html += '<div style="display: flex; justify-content: space-between;">';
            html += '<button class="button" onclick="document.getElementById(\'zdm-browser-ai-modal\').remove()">Cancel</button>';
            html += '<button class="button button-primary" onclick="ZDM_Browser_AI.useAIResponse()">Use This Response</button>';
            html += '</div>';

            html += '</div>';
            html += '</div>';

            // Add to page
            $('body').append(html);

            // Store prompt for clipboard
            this.currentPrompt = data.prompt;
        },

        /**
         * Copy prompt to clipboard
         */
        copyPromptToClipboard: function() {
            var promptText = $('#zdm-ai-prompt').val() || this.currentPrompt;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(promptText).then(function() {
                    $('.zdm-copy-prompt').text('✓ Copied!');
                    setTimeout(function() {
                        $('.zdm-copy-prompt').text('📋 Copy Prompt');
                    }, 2000);
                }).catch(function() {
                    // Fallback
                    var textarea = document.getElementById('zdm-ai-prompt');
                    textarea.select();
                    document.execCommand('copy');
                    $('.zdm-copy-prompt').text('✓ Copied!');
                });
            } else {
                // Fallback for older browsers
                var textarea = document.getElementById('zdm-ai-prompt');
                textarea.select();
                document.execCommand('copy');
                $('.zdm-copy-prompt').text('✓ Copied!');
            }
        },

        /**
         * Use the AI response
         */
        useAIResponse: function() {
            var response = $('#zdm-ai-response-input').val();

            if (!response.trim()) {
                alert('Please paste the AI response before continuing.');
                return;
            }

            // Insert into draft textarea
            $('#zdm-draft-response').val(response);

            // Show success message
            $('#zdm-draft-message').html('<div class="notice notice-success"><p>AI response added to draft. You can edit it before saving.</p></div>');

            // Close modal
            $('#zdm-browser-ai-modal').remove();

            // Mark draft as dirty
            if (window.ZDM_Draft) {
                window.ZDM_Draft.isDirty = true;
            }
        },

        /**
         * Show loading indicator
         */
        showLoading: function() {
            $('#zdm-generate-ai-draft').prop('disabled', true).text('Generating prompt...');
        },

        /**
         * Hide loading indicator
         */
        hideLoading: function() {
            $('#zdm-generate-ai-draft').prop('disabled', false).text('Generate AI Response');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ZDM_Browser_AI.init();

        // Add class to body if browser AI is enabled
        if (typeof zdm_browser_ai !== 'undefined' && zdm_browser_ai.browser_ai_enabled) {
            $('body').addClass('zdm-browser-ai-enabled');
        }
    });

    // Make available globally
    window.ZDM_Browser_AI = ZDM_Browser_AI;

})(jQuery);