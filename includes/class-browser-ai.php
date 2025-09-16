<?php
/**
 * Browser-based AI Integration
 * Uses existing ChatGPT Plus or Claude Pro subscriptions through browser
 *
 * @package ZohoDeskManager
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZDM_Browser_AI {

    /**
     * Initialize browser AI integration
     */
    public static function init() {
        add_action('wp_ajax_zdm_browser_ai_generate', array(__CLASS__, 'ajax_generate_response'));
        add_action('wp_ajax_zdm_check_browser_extension', array(__CLASS__, 'ajax_check_extension'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_browser_scripts'));
    }

    /**
     * Enqueue browser integration scripts
     */
    public static function enqueue_browser_scripts($hook) {
        if (strpos($hook, 'zoho-desk') === false) {
            return;
        }

        wp_enqueue_script(
            'zdm-browser-ai',
            ZDM_PLUGIN_URL . 'assets/js/browser-ai.js',
            array('jquery'),
            '1.2.0',
            true
        );

        wp_localize_script('zdm-browser-ai', 'zdm_browser_ai', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zdm_browser_ai_nonce'),
            'extension_id' => get_option('zdm_browser_extension_id', ''),
            'chatgpt_url' => 'https://chat.openai.com',
            'claude_url' => 'https://claude.ai',
            'strings' => array(
                'checking_extension' => __('Checking browser extension...', 'zoho-desk-manager'),
                'extension_not_found' => __('Browser extension not detected. Please install it first.', 'zoho-desk-manager'),
                'generating' => __('Generating response...', 'zoho-desk-manager'),
                'copy_prompt' => __('Click to copy prompt to clipboard', 'zoho-desk-manager')
            )
        ));
    }

    /**
     * Generate prompt for browser-based AI
     */
    public static function generate_prompt($ticket_data, $conversation_history, $options = array()) {
        $response_type = $options['response_type'] ?? 'solution';
        $tone = $options['tone'] ?? 'professional';

        $prompt = "You are a customer support agent. Please generate a {$tone} response for the following support ticket:\n\n";
        $prompt .= "**Ticket Subject:** {$ticket_data['subject']}\n";

        if (isset($ticket_data['contact']['firstName'])) {
            $prompt .= "**Customer Name:** {$ticket_data['contact']['firstName']}\n";
        }

        if (!empty($ticket_data['description'])) {
            $prompt .= "**Issue Description:**\n{$ticket_data['description']}\n\n";
        }

        // Add conversation history
        if (!empty($conversation_history)) {
            $prompt .= "**Conversation History:**\n";
            foreach ($conversation_history as $message) {
                $author_type = $message['author']['type'] ?? 'AGENT';
                $author_label = ($author_type === 'END_USER') ? 'Customer' : 'Agent';
                $content = $message['content'] ?? $message['plainText'] ?? '';
                if (!empty($content)) {
                    $prompt .= "{$author_label}: {$content}\n";
                }
            }
            $prompt .= "\n";
        }

        // Add specific instructions based on response type
        switch ($response_type) {
            case 'solution':
                $prompt .= "Please provide a detailed solution to address the customer's issue.";
                break;
            case 'follow_up':
                $prompt .= "Please write a follow-up message to check on the customer's progress.";
                break;
            case 'clarification':
                $prompt .= "Please ask for clarification to better understand the issue.";
                break;
            case 'escalation':
                $prompt .= "Please write a message explaining that the issue is being escalated to senior support.";
                break;
            case 'closing':
                $prompt .= "Please write a closing message for this resolved ticket.";
                break;
        }

        $prompt .= "\n\nIMPORTANT: Keep the response professional, helpful, and personalized.";

        return $prompt;
    }

    /**
     * AJAX handler for generating response
     */
    public static function ajax_generate_response() {
        check_ajax_referer('zdm_browser_ai_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $ticket_id = sanitize_text_field($_POST['ticket_id'] ?? '');
        $provider = sanitize_text_field($_POST['provider'] ?? 'chatgpt');
        $response_type = sanitize_text_field($_POST['response_type'] ?? 'solution');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');

        // Get ticket data
        $api = new ZDM_Zoho_API();
        $ticket_data = $api->get_ticket($ticket_id);

        if (!$ticket_data) {
            wp_send_json_error(array('message' => 'Failed to fetch ticket data'));
        }

        // Get conversation history
        $conversation_history = $api->get_ticket_threads($ticket_id);

        // Generate prompt
        $prompt = self::generate_prompt($ticket_data, $conversation_history, array(
            'response_type' => $response_type,
            'tone' => $tone
        ));

        // Return prompt and URL for user to open
        $provider_url = '';
        if ($provider === 'chatgpt') {
            $provider_url = 'https://chat.openai.com';
        } elseif ($provider === 'claude') {
            $provider_url = 'https://claude.ai/new';
        }

        wp_send_json_success(array(
            'prompt' => $prompt,
            'provider_url' => $provider_url,
            'provider' => $provider,
            'instructions' => self::get_provider_instructions($provider)
        ));
    }

    /**
     * Get provider-specific instructions
     */
    private static function get_provider_instructions($provider) {
        if ($provider === 'chatgpt') {
            return array(
                'steps' => array(
                    '1. Click "Open ChatGPT" to open ChatGPT in a new tab',
                    '2. Click "Copy Prompt" to copy the generated prompt',
                    '3. Paste the prompt in ChatGPT and press Enter',
                    '4. Copy the response from ChatGPT',
                    '5. Paste it back in the draft field below'
                ),
                'requirements' => 'Requires ChatGPT Plus subscription or available GPT-4 credits'
            );
        } elseif ($provider === 'claude') {
            return array(
                'steps' => array(
                    '1. Click "Open Claude" to open Claude in a new tab',
                    '2. Click "Copy Prompt" to copy the generated prompt',
                    '3. Start a new conversation in Claude',
                    '4. Paste the prompt and press Enter',
                    '5. Copy the response from Claude',
                    '6. Paste it back in the draft field below'
                ),
                'requirements' => 'Requires Claude Pro subscription or available free messages'
            );
        }

        return array('steps' => array(), 'requirements' => '');
    }

    /**
     * Check if browser extension is installed
     */
    public static function ajax_check_extension() {
        check_ajax_referer('zdm_browser_ai_nonce', 'nonce');

        // This would check if a companion browser extension is installed
        // For now, we'll work without an extension
        wp_send_json_success(array(
            'extension_installed' => false,
            'manual_mode' => true
        ));
    }
}

// Initialize
ZDM_Browser_AI::init();