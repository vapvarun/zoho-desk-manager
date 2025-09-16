<?php
/**
 * AI Assistant for Generating Ticket Responses
 *
 * Integrates with Claude API to generate intelligent ticket responses
 *
 * @package ZohoDeskManager
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZDM_AI_Assistant {

    /**
     * Claude API endpoint
     */
    const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';

    /**
     * Maximum tokens for response
     */
    const MAX_TOKENS = 1000;

    /**
     * Initialize AI Assistant
     */
    public static function init() {
        add_action('wp_ajax_zdm_generate_ai_response', array(__CLASS__, 'ajax_generate_response'));
        add_action('wp_ajax_zdm_improve_response', array(__CLASS__, 'ajax_improve_response'));
        add_action('wp_ajax_zdm_get_response_templates', array(__CLASS__, 'ajax_get_templates'));
        add_action('wp_ajax_zdm_preview_template', array(__CLASS__, 'ajax_preview_template'));
        add_action('wp_ajax_zdm_process_template', array(__CLASS__, 'ajax_process_template'));
    }

    /**
     * Generate AI response for ticket
     */
    public static function generate_response($ticket_data, $conversation_history, $options = array()) {
        // Check if browser AI is enabled (using existing subscriptions)
        if (get_option('zdm_use_browser_ai') == '1') {
            // Return prompt for browser-based generation
            return self::generate_browser_prompt($ticket_data, $conversation_history, $options);
        }

        // Check if subscription service is enabled (deprecated)
        if (get_option('zdm_use_subscription') == '1') {
            // Include subscription AI handler if not already loaded
            if (!class_exists('ZDM_Subscription_AI')) {
                require_once ZDM_PLUGIN_PATH . 'includes/class-subscription-ai.php';
            }

            // Use subscription service for AI generation
            return ZDM_Subscription_AI::generate_response($ticket_data, $conversation_history, $options);
        }

        // Otherwise use direct API keys
        // Get the default AI provider
        $provider = get_option('zdm_default_ai_provider');

        if (empty($provider)) {
            return array(
                'error' => true,
                'message' => 'No AI provider configured. Please configure an AI provider in AI Settings.'
            );
        }

        // Check if the selected provider is enabled and has an API key
        $is_enabled = get_option('zdm_' . $provider . '_enabled');
        $api_key = get_option('zdm_' . $provider . '_api_key');

        if (!$is_enabled || empty($api_key)) {
            return array(
                'error' => true,
                'message' => ucfirst($provider) . ' is not properly configured. Please check AI Settings.'
            );
        }

        // Prepare context for AI
        $context = self::prepare_context($ticket_data, $conversation_history, $options);

        // Build the prompt
        $prompt = self::build_prompt($context, $options);

        // Call the appropriate AI API
        switch ($provider) {
            case 'openai':
                $response = self::call_openai_api($prompt, $api_key, get_option('zdm_openai_model', 'gpt-3.5-turbo'));
                break;
            case 'claude':
                $response = self::call_claude_api($prompt, $api_key, get_option('zdm_claude_model', 'claude-3-haiku-20240307'));
                break;
            case 'gemini':
                $response = self::call_gemini_api($prompt, $api_key, get_option('zdm_gemini_model', 'gemini-pro'));
                break;
            default:
                return array(
                    'error' => true,
                    'message' => 'Unknown AI provider: ' . $provider
                );
        }

        if (isset($response['error'])) {
            return $response;
        }

        // Process and format the response
        return self::process_ai_response($response, $context);
    }

    /**
     * Prepare context from ticket data
     */
    private static function prepare_context($ticket_data, $conversation_history, $options) {
        $context = array(
            'ticket_subject' => $ticket_data['subject'],
            'customer_name' => $ticket_data['contact']['firstName'] ?? 'Customer',
            'priority' => $ticket_data['priority'] ?? 'Normal',
            'category' => $ticket_data['category'] ?? 'General',
            'created_time' => $ticket_data['createdTime'],
            'description' => $ticket_data['description'] ?? '',
            'conversation_count' => count($conversation_history),
            'last_message' => '',
            'full_conversation' => array(), // Add full conversation history
            'customer_messages' => array(), // All customer messages
            'agent_messages' => array(),    // All agent responses
            'customer_sentiment' => '',
            'key_issues' => array(),
            'product_area' => $ticket_data['product'] ?? ''
        );

        // Process complete conversation history
        if (!empty($conversation_history)) {
            $last_customer_message = '';

            // Check if we should include full conversation
            $include_full = get_option('zdm_include_full_conversation', '1') === '1';
            $conversation_limit = intval(get_option('zdm_conversation_limit', '20'));

            // Limit conversation history if needed
            if ($include_full && count($conversation_history) > $conversation_limit) {
                // Keep the most recent messages
                $conversation_history = array_slice($conversation_history, -$conversation_limit);
            }

            foreach ($conversation_history as $message) {
                $author_type = $message['author']['type'] ?? '';
                $author_name = $message['author']['firstName'] ?? $message['author']['name'] ?? 'Unknown';
                $content = $message['content'] ?? $message['plainText'] ?? $message['summary'] ?? '';
                $timestamp = $message['createdTime'] ?? '';

                // Build full conversation entry
                $conversation_entry = array(
                    'type' => $author_type,
                    'author' => $author_name,
                    'content' => $content,
                    'time' => $timestamp
                );

                $context['full_conversation'][] = $conversation_entry;

                // Separate customer and agent messages
                if ($author_type === 'END_USER') {
                    $context['customer_messages'][] = $content;
                    $last_customer_message = $content;
                } elseif ($author_type === 'AGENT') {
                    $context['agent_messages'][] = $content;
                }
            }

            $context['last_message'] = $last_customer_message;

            // Analyze sentiment from all customer messages
            $all_customer_text = implode(' ', $context['customer_messages']);
            $context['customer_sentiment'] = self::analyze_sentiment($all_customer_text);

            // Extract key issues from all customer messages
            $context['key_issues'] = self::extract_key_issues($all_customer_text);
        }

        // Add company knowledge base if available
        $context['knowledge_base'] = get_option('zdm_ai_knowledge_base', '');

        // Add response style preferences
        $context['response_style'] = get_option('zdm_ai_response_style', 'professional');

        return $context;
    }

    /**
     * Build AI prompt
     */
    private static function build_prompt($context, $options) {
        $response_type = $options['response_type'] ?? 'solution';
        $tone = $options['tone'] ?? $context['response_style'];

        // Use custom system prompt if configured
        $custom_system_prompt = get_option('zdm_ai_system_prompt');

        if (!empty($custom_system_prompt)) {
            // Replace placeholders in custom prompt
            $system_prompt = str_replace(
                array('{ticket_subject}', '{ticket_description}', '{customer_name}', '{tone}', '{response_type}'),
                array($context['ticket_subject'], $context['description'], $context['customer_name'], $tone, $response_type),
                $custom_system_prompt
            );
        } else {
            // Use default system prompt
            $system_prompt = "You are a professional customer support agent helping to draft responses for support tickets. ";
            $system_prompt .= "Your responses should be {$tone}, helpful, and focused on solving the customer's issue. ";
            $system_prompt .= "Use the context provided to create a personalized and relevant response.";
        }

        if (!empty($context['knowledge_base'])) {
            $system_prompt .= "\n\nCompany Knowledge Base:\n{$context['knowledge_base']}";
        }

        $user_prompt = "Please draft a response for the following support ticket:\n\n";
        $user_prompt .= "**Ticket Subject:** {$context['ticket_subject']}\n";
        $user_prompt .= "**Customer Name:** {$context['customer_name']}\n";
        $user_prompt .= "**Priority:** {$context['priority']}\n";

        if (!empty($context['description'])) {
            $user_prompt .= "\n**Initial Issue Description:**\n{$context['description']}\n";
        }

        // Include complete conversation history for full context
        $include_full = get_option('zdm_include_full_conversation', '1') === '1';

        if ($include_full && !empty($context['full_conversation'])) {
            $user_prompt .= "\n**Complete Conversation History:**\n";
            $user_prompt .= "-----------------------------------\n";

            foreach ($context['full_conversation'] as $entry) {
                $author_label = ($entry['type'] === 'END_USER') ? 'CUSTOMER' : 'AGENT';
                $timestamp = !empty($entry['time']) ? ' [' . date('Y-m-d H:i', strtotime($entry['time'])) . ']' : '';

                $user_prompt .= "\n{$author_label} ({$entry['author']}){$timestamp}:\n";
                $user_prompt .= "{$entry['content']}\n";
                $user_prompt .= "---\n";
            }
            $user_prompt .= "-----------------------------------\n";
        } elseif (!$include_full && !empty($context['last_message'])) {
            // If not including full conversation, just show the last customer message
            $user_prompt .= "\n**Customer's Latest Message:**\n{$context['last_message']}\n\n";
        }

        // Include summary of customer messages only if not showing full conversation
        if (!$include_full && !empty($context['customer_messages']) && count($context['customer_messages']) > 1) {
            $user_prompt .= "\n**Summary of All Customer Messages:**\n";
            foreach ($context['customer_messages'] as $index => $message) {
                $user_prompt .= "Message " . ($index + 1) . ": " . substr($message, 0, 200);
                if (strlen($message) > 200) {
                    $user_prompt .= "...";
                }
                $user_prompt .= "\n";
            }
        }

        if (!empty($context['customer_sentiment'])) {
            $user_prompt .= "\n**Detected Customer Sentiment:** {$context['customer_sentiment']}\n";
        }

        if (!empty($context['key_issues'])) {
            $user_prompt .= "**Key Issues Identified:** " . implode(', ', $context['key_issues']) . "\n";
        }

        // Add specific instructions based on response type
        switch ($response_type) {
            case 'solution':
                $user_prompt .= "\nProvide a detailed solution to address the customer's issue. Include step-by-step instructions if applicable.";
                break;
            case 'follow_up':
                $user_prompt .= "\nDraft a follow-up message to check on the customer's progress and offer additional assistance.";
                break;
            case 'clarification':
                $user_prompt .= "\nAsk for specific clarification needed to better understand and resolve the issue.";
                break;
            case 'escalation':
                $user_prompt .= "\nDraft a message explaining that the issue is being escalated to a senior team member or specialist.";
                break;
            case 'closing':
                $user_prompt .= "\nDraft a closing message confirming the issue has been resolved and thanking the customer.";
                break;
        }

        // Add tone-specific instructions
        switch ($tone) {
            case 'friendly':
                $user_prompt .= "\n\nUse a warm, friendly tone while maintaining professionalism.";
                break;
            case 'formal':
                $user_prompt .= "\n\nUse a formal, business-appropriate tone.";
                break;
            case 'technical':
                $user_prompt .= "\n\nProvide technical details and be precise in your explanations.";
                break;
            case 'empathetic':
                $user_prompt .= "\n\nShow understanding and empathy for the customer's frustration or concerns.";
                break;
        }

        $user_prompt .= "\n\nIMPORTANT: Address the customer by name and personalize the response based on their specific issue.";

        return array(
            'system' => $system_prompt,
            'user' => $user_prompt
        );
    }

    /**
     * Call Claude API
     */
    private static function call_claude_api($prompt, $api_key, $model = 'claude-3-haiku-20240307') {
        $headers = array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        );

        $max_tokens = get_option('zdm_ai_max_tokens', self::MAX_TOKENS);
        $temperature = get_option('zdm_ai_temperature', 0.7);

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt['user']
                )
            ),
            'system' => $prompt['system'],
            'max_tokens' => intval($max_tokens),
            'temperature' => floatval($temperature)
        );

        $response = wp_remote_post(self::CLAUDE_API_URL, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'error' => true,
                'message' => 'Failed to connect to Claude API: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $error_message = $response_body['error']['message'] ?? 'Unknown error occurred';
            return array(
                'error' => true,
                'message' => 'Claude API error: ' . $error_message
            );
        }

        // Standardize response format
        return array(
            'content' => array(
                array('text' => $response_body['content'][0]['text'] ?? '')
            ),
            'usage' => $response_body['usage'] ?? array(),
            'model' => $model
        );
    }

    /**
     * Call OpenAI API
     */
    private static function call_openai_api($prompt, $api_key, $model = 'gpt-3.5-turbo') {
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        );

        $max_tokens = get_option('zdm_ai_max_tokens', self::MAX_TOKENS);
        $temperature = get_option('zdm_ai_temperature', 0.7);

        $body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $prompt['system']
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt['user']
                )
            ),
            'max_tokens' => intval($max_tokens),
            'temperature' => floatval($temperature)
        );

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'error' => true,
                'message' => 'Failed to connect to OpenAI API: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $error_message = $response_body['error']['message'] ?? 'Unknown error occurred';
            return array(
                'error' => true,
                'message' => 'OpenAI API error: ' . $error_message
            );
        }

        // Standardize response format
        return array(
            'content' => array(
                array('text' => $response_body['choices'][0]['message']['content'] ?? '')
            ),
            'usage' => array(
                'input_tokens' => $response_body['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $response_body['usage']['completion_tokens'] ?? 0
            ),
            'model' => $model
        );
    }

    /**
     * Call Google Gemini API
     */
    private static function call_gemini_api($prompt, $api_key, $model = 'gemini-pro') {
        $max_tokens = get_option('zdm_ai_max_tokens', self::MAX_TOKENS);
        $temperature = get_option('zdm_ai_temperature', 0.7);

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt['system'] . "\n\n" . $prompt['user']
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => floatval($temperature),
                'maxOutputTokens' => intval($max_tokens)
            )
        );

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'error' => true,
                'message' => 'Failed to connect to Gemini API: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $error_message = $response_body['error']['message'] ?? 'Unknown error occurred';
            return array(
                'error' => true,
                'message' => 'Gemini API error: ' . $error_message
            );
        }

        // Standardize response format
        $generated_text = $response_body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return array(
            'content' => array(
                array('text' => $generated_text)
            ),
            'usage' => array(
                'input_tokens' => $response_body['usageMetadata']['promptTokenCount'] ?? 0,
                'output_tokens' => $response_body['usageMetadata']['candidatesTokenCount'] ?? 0
            ),
            'model' => $model
        );
    }

    /**
     * Process AI response
     */
    private static function process_ai_response($response, $context) {
        if (!isset($response['content'][0]['text'])) {
            return array(
                'error' => true,
                'message' => 'Invalid response from AI'
            );
        }

        $generated_text = $response['content'][0]['text'];

        // Add signature if configured
        $signature = get_option('zdm_email_signature', '');
        if (!empty($signature)) {
            $generated_text .= "\n\n" . $signature;
        }

        return array(
            'success' => true,
            'response' => $generated_text,
            'usage' => array(
                'input_tokens' => $response['usage']['input_tokens'] ?? 0,
                'output_tokens' => $response['usage']['output_tokens'] ?? 0
            ),
            'suggestions' => self::generate_suggestions($context),
            'metadata' => array(
                'generated_at' => current_time('mysql'),
                'model' => 'claude-3-haiku',
                'context_items' => count($context['key_issues'])
            )
        );
    }

    /**
     * Analyze sentiment from text
     */
    private static function analyze_sentiment($text) {
        $negative_words = array('angry', 'frustrated', 'disappointed', 'upset', 'terrible', 'horrible', 'worst', 'unacceptable');
        $positive_words = array('thank', 'appreciate', 'great', 'excellent', 'good', 'happy', 'satisfied');

        $text_lower = strtolower($text);
        $negative_count = 0;
        $positive_count = 0;

        foreach ($negative_words as $word) {
            if (strpos($text_lower, $word) !== false) {
                $negative_count++;
            }
        }

        foreach ($positive_words as $word) {
            if (strpos($text_lower, $word) !== false) {
                $positive_count++;
            }
        }

        if ($negative_count > $positive_count) {
            return 'negative';
        } elseif ($positive_count > $negative_count) {
            return 'positive';
        }

        return 'neutral';
    }

    /**
     * Extract key issues from text
     */
    private static function extract_key_issues($text) {
        $issues = array();

        // Common issue patterns
        $patterns = array(
            'not working' => 'functionality issue',
            'error' => 'error encountered',
            'can\'t' => 'unable to perform action',
            'cannot' => 'unable to perform action',
            'broken' => 'broken feature',
            'bug' => 'bug report',
            'slow' => 'performance issue',
            'crash' => 'application crash',
            'login' => 'authentication issue',
            'payment' => 'payment issue',
            'refund' => 'refund request',
            'cancel' => 'cancellation request'
        );

        $text_lower = strtolower($text);
        foreach ($patterns as $pattern => $issue) {
            if (strpos($text_lower, $pattern) !== false) {
                $issues[] = $issue;
            }
        }

        return array_unique($issues);
    }

    /**
     * Generate follow-up suggestions
     */
    private static function generate_suggestions($context) {
        $suggestions = array();

        // Based on sentiment
        if ($context['customer_sentiment'] === 'negative') {
            $suggestions[] = 'Consider offering a goodwill gesture or escalation to management';
            $suggestions[] = 'Follow up within 24 hours to ensure satisfaction';
        }

        // Based on priority
        if ($context['priority'] === 'High') {
            $suggestions[] = 'Prioritize immediate response and resolution';
            $suggestions[] = 'Consider scheduling a call if issue persists';
        }

        // Based on conversation length
        if ($context['conversation_count'] > 5) {
            $suggestions[] = 'Consider escalating to senior support or scheduling a call';
            $suggestions[] = 'Review entire conversation history for missed details';
        }

        // Based on key issues
        if (in_array('refund request', $context['key_issues'])) {
            $suggestions[] = 'Review refund policy and process request promptly';
        }

        if (in_array('bug report', $context['key_issues'])) {
            $suggestions[] = 'File a bug report with development team';
            $suggestions[] = 'Provide workaround if available';
        }

        return $suggestions;
    }

    /**
     * AJAX handler for generating response
     */
    public static function ajax_generate_response() {
        check_ajax_referer('zdm_ai_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $ticket_id = sanitize_text_field($_POST['ticket_id']);
        $response_type = sanitize_text_field($_POST['response_type'] ?? 'solution');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');

        // Fetch ticket data
        $api = new ZDM_Zoho_API();
        $ticket = $api->get_ticket($ticket_id);
        $threads = $api->get_ticket_threads($ticket_id);

        if (!$ticket) {
            wp_send_json_error('Unable to fetch ticket data');
            return;
        }

        // Prepare conversation history
        $conversation_history = array();
        if (isset($threads['data'])) {
            $conversation_history = $threads['data'];
        }

        // Generate response
        $options = array(
            'response_type' => $response_type,
            'tone' => $tone
        );

        $result = self::generate_response($ticket, $conversation_history, $options);

        if (isset($result['error'])) {
            wp_send_json_error($result['message']);
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Generate prompt for browser-based AI
     */
    private static function generate_browser_prompt($ticket_data, $conversation_history, $options = array()) {
        // Prepare context
        $context = self::prepare_context($ticket_data, $conversation_history, $options);

        // Build the prompt
        $prompt = self::build_prompt($context, $options);

        // Get the selected browser provider
        $browser_provider = get_option('zdm_browser_ai_provider', 'chatgpt');

        // Combine system and user prompts for browser display
        $full_prompt = $prompt['system'] . "\n\n" . $prompt['user'];

        // Return success with prompt text for browser use
        return array(
            'success' => true,
            'browser_mode' => true,
            'provider' => $browser_provider,
            'prompt' => $full_prompt,
            'response' => '', // Empty for browser mode
            'message' => 'Copy this prompt to ' . ($browser_provider === 'chatgpt' ? 'ChatGPT' : 'Claude'),
            'metadata' => array(
                'generated_at' => current_time('mysql'),
                'browser_provider' => $browser_provider
            )
        );
    }

    /**
     * AJAX handler for improving response
     */
    public static function ajax_improve_response() {
        check_ajax_referer('zdm_ai_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $current_response = sanitize_textarea_field($_POST['current_response']);
        $improvement_type = sanitize_text_field($_POST['improvement_type']);

        $api_key = get_option('zdm_claude_api_key');

        if (empty($api_key)) {
            wp_send_json_error('Claude API key not configured');
            return;
        }

        $prompt = array(
            'system' => 'You are a professional editor helping to improve customer support responses.',
            'user' => "Please improve the following response by making it more {$improvement_type}:\n\n{$current_response}"
        );

        $response = self::call_claude_api($prompt, $api_key);

        if (isset($response['error'])) {
            wp_send_json_error($response['message']);
        } else {
            wp_send_json_success(array(
                'improved_response' => $response['content'][0]['text'] ?? $current_response
            ));
        }
    }

    /**
     * AJAX handler for template preview
     */
    public static function ajax_preview_template() {
        check_ajax_referer('zdm_ai_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $template_key = sanitize_text_field($_POST['template_key']);
        $ticket_id = sanitize_text_field($_POST['ticket_id']);

        // Include template manager
        require_once ZDM_PLUGIN_PATH . 'includes/class-template-manager.php';

        // Get ticket data for variable extraction
        $api = new ZDM_Zoho_API();
        $ticket_data = $api->get_ticket($ticket_id);
        $threads = $api->get_ticket_threads($ticket_id);

        if (!$ticket_data) {
            wp_send_json_error('Unable to fetch ticket data');
            return;
        }

        // Extract variables from ticket
        $variables = ZDM_Template_Manager::extract_ticket_variables($ticket_data, $threads);

        // Get template
        $template = ZDM_Template_Manager::get_template($template_key);

        if (!$template) {
            wp_send_json_error('Template not found');
            return;
        }

        // Process template with variables for preview
        $preview = $template['content'];
        foreach ($variables as $key => $value) {
            $preview = str_replace('{' . $key . '}', $value, $preview);
        }

        // Replace any remaining variables with defaults
        $preview = ZDM_Template_Manager::process_template($template_key, $variables);

        wp_send_json_success(array(
            'preview' => $preview,
            'template_name' => $template['name'],
            'variables_used' => array_keys($variables)
        ));
    }

    /**
     * AJAX handler for processing template
     */
    public static function ajax_process_template() {
        check_ajax_referer('zdm_ai_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $template_key = sanitize_text_field($_POST['template_key']);
        $ticket_id = sanitize_text_field($_POST['ticket_id']);

        // Include template manager
        require_once ZDM_PLUGIN_PATH . 'includes/class-template-manager.php';

        // Get ticket data for variable extraction
        $api = new ZDM_Zoho_API();
        $ticket_data = $api->get_ticket($ticket_id);
        $threads = $api->get_ticket_threads($ticket_id);

        if (!$ticket_data) {
            wp_send_json_error('Unable to fetch ticket data');
            return;
        }

        // Extract variables from ticket
        $variables = ZDM_Template_Manager::extract_ticket_variables($ticket_data, $threads);

        // Process template
        $content = ZDM_Template_Manager::process_template($template_key, $variables);

        if ($content === false) {
            wp_send_json_error('Template not found or processing failed');
            return;
        }

        wp_send_json_success(array(
            'content' => $content,
            'template_key' => $template_key,
            'variables_replaced' => array_keys($variables)
        ));
    }

    /**
     * AJAX handler for getting templates
     */
    public static function ajax_get_templates() {
        check_ajax_referer('zdm_ai_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Use the new CPT-based template system
        $templates = ZDM_Template_Manager::get_templates();
        wp_send_json_success($templates);
    }
}