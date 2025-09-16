<?php
/**
 * Subscription-based AI Service Handler
 *
 * Handles AI requests through the managed subscription service
 *
 * @package ZohoDeskManager
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZDM_Subscription_AI {

    /**
     * Subscription API endpoint
     */
    const API_ENDPOINT = 'https://api.zohodeskmanager.com/v1/';

    /**
     * Initialize subscription AI handler
     */
    public static function init() {
        add_action('wp_ajax_zdm_validate_subscription', array(__CLASS__, 'ajax_validate_subscription'));
        add_action('wp_ajax_zdm_check_credits', array(__CLASS__, 'ajax_check_credits'));
    }

    /**
     * Generate AI response using subscription service
     */
    public static function generate_response($ticket_data, $conversation_history, $options = array()) {
        // Check if subscription is enabled
        if (!get_option('zdm_use_subscription')) {
            return array(
                'error' => true,
                'message' => 'Subscription service is not enabled.'
            );
        }

        // Get subscription key
        $subscription_key = get_option('zdm_subscription_key');
        if (empty($subscription_key)) {
            return array(
                'error' => true,
                'message' => 'Subscription key not configured. Please add it in AI Settings.'
            );
        }

        // Check subscription status
        $status = get_option('zdm_subscription_status');
        if ($status !== 'active') {
            return array(
                'error' => true,
                'message' => 'Subscription is not active. Please check your subscription status.'
            );
        }

        // Prepare request data
        $request_data = array(
            'ticket_data' => $ticket_data,
            'conversation_history' => $conversation_history,
            'options' => $options,
            'site_url' => get_site_url(),
            'plugin_version' => '1.2.0'
        );

        // Get AI settings
        $request_data['settings'] = array(
            'max_tokens' => get_option('zdm_ai_max_tokens', 1000),
            'temperature' => get_option('zdm_ai_temperature', 0.7),
            'system_prompt' => get_option('zdm_ai_system_prompt', ''),
            'preferred_provider' => $options['provider'] ?? 'auto', // auto selects best available
            'response_type' => $options['response_type'] ?? 'solution',
            'tone' => $options['tone'] ?? 'professional'
        );

        // Make API request to subscription service
        $response = wp_remote_post(self::API_ENDPOINT . 'generate', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $subscription_key,
                'Content-Type' => 'application/json',
                'X-Subscription-Email' => get_option('zdm_subscription_email', ''),
                'X-Plugin-Version' => '1.2.0'
            ),
            'body' => json_encode($request_data),
            'timeout' => 45 // Longer timeout for AI generation
        ));

        if (is_wp_error($response)) {
            return array(
                'error' => true,
                'message' => 'Failed to connect to subscription service: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $error_message = $response_body['error'] ?? 'Unknown error occurred';

            // Handle specific error codes
            if ($response_code === 402) {
                update_option('zdm_subscription_credits', 0);
                return array(
                    'error' => true,
                    'message' => 'Subscription credits exhausted. Please upgrade your plan.'
                );
            } elseif ($response_code === 401) {
                update_option('zdm_subscription_status', 'invalid');
                return array(
                    'error' => true,
                    'message' => 'Invalid subscription key. Please check your settings.'
                );
            }

            return array(
                'error' => true,
                'message' => 'Subscription service error: ' . $error_message
            );
        }

        // Update credits if provided
        if (isset($response_body['credits_remaining'])) {
            update_option('zdm_subscription_credits', $response_body['credits_remaining']);
        }

        // Return the generated response
        return array(
            'success' => true,
            'response' => $response_body['generated_text'] ?? '',
            'usage' => array(
                'credits_used' => $response_body['credits_used'] ?? 1,
                'credits_remaining' => $response_body['credits_remaining'] ?? 0,
                'provider_used' => $response_body['provider'] ?? 'unknown',
                'model_used' => $response_body['model'] ?? 'unknown'
            ),
            'metadata' => array(
                'generated_at' => current_time('mysql'),
                'response_time' => $response_body['processing_time'] ?? 0,
                'subscription_plan' => get_option('zdm_subscription_plan', '')
            )
        );
    }

    /**
     * Validate subscription key
     */
    public static function validate_subscription($key, $email) {
        $response = wp_remote_post(self::API_ENDPOINT . 'validate', array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'key' => $key,
                'email' => $email,
                'site_url' => get_site_url(),
                'plugin_version' => '1.2.0'
            )),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Failed to validate: ' . $response->get_error_message()
            );
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_body['valid'] === true) {
            // Update subscription details
            update_option('zdm_subscription_status', 'active');
            update_option('zdm_subscription_plan', $response_body['plan'] ?? '');
            update_option('zdm_subscription_credits', $response_body['credits'] ?? 0);
            update_option('zdm_subscription_expires', $response_body['expires'] ?? '');

            return array(
                'success' => true,
                'message' => 'Subscription validated successfully',
                'data' => $response_body
            );
        }

        return array(
            'success' => false,
            'message' => $response_body['error'] ?? 'Invalid subscription'
        );
    }

    /**
     * AJAX handler for subscription validation
     */
    public static function ajax_validate_subscription() {
        check_ajax_referer('zdm_subscription_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $key = sanitize_text_field($_POST['key'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');

        if (empty($key) || empty($email)) {
            wp_send_json_error(array('message' => 'Key and email are required'));
        }

        $result = self::validate_subscription($key, $email);

        if ($result['success']) {
            // Save the validated key and email
            update_option('zdm_subscription_key', $key);
            update_option('zdm_subscription_email', $email);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Check remaining credits
     */
    public static function check_credits() {
        if (!get_option('zdm_use_subscription')) {
            return false;
        }

        $subscription_key = get_option('zdm_subscription_key');
        if (empty($subscription_key)) {
            return false;
        }

        $response = wp_remote_get(self::API_ENDPOINT . 'credits', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $subscription_key,
                'X-Subscription-Email' => get_option('zdm_subscription_email', '')
            ),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['credits'])) {
            update_option('zdm_subscription_credits', $response_body['credits']);
            return $response_body['credits'];
        }

        return false;
    }

    /**
     * AJAX handler for checking credits
     */
    public static function ajax_check_credits() {
        check_ajax_referer('zdm_subscription_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $credits = self::check_credits();

        if ($credits !== false) {
            wp_send_json_success(array('credits' => $credits));
        } else {
            wp_send_json_error(array('message' => 'Failed to check credits'));
        }
    }

    /**
     * Get usage statistics
     */
    public static function get_usage_stats() {
        $subscription_key = get_option('zdm_subscription_key');
        if (empty($subscription_key)) {
            return false;
        }

        $response = wp_remote_get(self::API_ENDPOINT . 'usage', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $subscription_key,
                'X-Subscription-Email' => get_option('zdm_subscription_email', '')
            ),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        return $response_body;
    }
}

// Initialize the subscription AI handler
ZDM_Subscription_AI::init();