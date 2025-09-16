<?php
/**
 * Rate Limiter for Zoho Desk API
 *
 * Implements rate limiting to prevent API abuse and respect Zoho's rate limits
 *
 * @package ZohoDeskManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZDM_Rate_Limiter {

    /**
     * Maximum API calls per minute
     * Zoho Desk allows 50 requests per minute
     */
    const MAX_CALLS_PER_MINUTE = 45; // Keep buffer for safety

    /**
     * Check if API call is allowed
     *
     * @return bool True if call is allowed, false if rate limited
     */
    public static function can_make_call() {
        $current_minute = date('Y-m-d H:i');
        $calls_this_minute = get_transient('zdm_rate_limit_' . $current_minute);

        if ($calls_this_minute === false) {
            $calls_this_minute = 0;
        }

        if ($calls_this_minute >= self::MAX_CALLS_PER_MINUTE) {
            // Log rate limit hit
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Zoho Desk Manager] Rate limit reached: ' . $calls_this_minute . ' calls in current minute');
            }
            return false;
        }

        return true;
    }

    /**
     * Record an API call
     */
    public static function record_call() {
        $current_minute = date('Y-m-d H:i');
        $calls_this_minute = get_transient('zdm_rate_limit_' . $current_minute);

        if ($calls_this_minute === false) {
            $calls_this_minute = 0;
        }

        $calls_this_minute++;
        set_transient('zdm_rate_limit_' . $current_minute, $calls_this_minute, 60);

        // Update remaining calls for display
        update_option('zdm_rate_limit_remaining', self::MAX_CALLS_PER_MINUTE - $calls_this_minute);
        update_option('zdm_rate_limit_reset', strtotime('+1 minute'));
    }

    /**
     * Get remaining API calls for current minute
     *
     * @return int Number of remaining calls
     */
    public static function get_remaining_calls() {
        $current_minute = date('Y-m-d H:i');
        $calls_this_minute = get_transient('zdm_rate_limit_' . $current_minute);

        if ($calls_this_minute === false) {
            return self::MAX_CALLS_PER_MINUTE;
        }

        return max(0, self::MAX_CALLS_PER_MINUTE - $calls_this_minute);
    }

    /**
     * Get time until rate limit reset
     *
     * @return int Seconds until reset
     */
    public static function get_reset_time() {
        return 60 - (time() % 60);
    }

    /**
     * Clear rate limit (for testing purposes)
     */
    public static function clear_rate_limit() {
        $current_minute = date('Y-m-d H:i');
        delete_transient('zdm_rate_limit_' . $current_minute);
        delete_option('zdm_rate_limit_remaining');
        delete_option('zdm_rate_limit_reset');
    }

    /**
     * Check Zoho API response headers for rate limit info
     *
     * @param array $headers Response headers from Zoho API
     */
    public static function check_response_headers($headers) {
        // Zoho sends rate limit info in headers
        if (isset($headers['x-rate-limit-limit'])) {
            update_option('zdm_rate_limit_max', $headers['x-rate-limit-limit']);
        }

        if (isset($headers['x-rate-limit-remaining'])) {
            update_option('zdm_api_rate_remaining', $headers['x-rate-limit-remaining']);
        }

        if (isset($headers['x-rate-limit-reset'])) {
            update_option('zdm_api_rate_reset', $headers['x-rate-limit-reset']);
        }
    }

    /**
     * Display rate limit status in admin
     *
     * @return string HTML output
     */
    public static function get_status_html() {
        $remaining = self::get_remaining_calls();
        $reset_time = self::get_reset_time();

        $color = 'green';
        if ($remaining < 10) {
            $color = 'orange';
        }
        if ($remaining === 0) {
            $color = 'red';
        }

        $html = '<div style="display: inline-block; padding: 5px 10px; background: #f0f0f0; border-radius: 4px; margin-left: 10px;">';
        $html .= '<span style="color: ' . $color . ';">API Calls Remaining: ' . $remaining . '/' . self::MAX_CALLS_PER_MINUTE . '</span>';
        if ($remaining === 0) {
            $html .= ' <small>(Resets in ' . $reset_time . 's)</small>';
        }
        $html .= '</div>';

        return $html;
    }
}