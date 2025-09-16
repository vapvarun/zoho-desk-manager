<?php
/**
 * Zoho Desk Manager Uninstall
 *
 * Removes all plugin data when uninstalled
 *
 * @package ZohoDeskManager
 * @since 1.0.0
 */

// Exit if accessed directly or not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove all plugin options
 */
$zdm_options = array(
    'zdm_client_id',
    'zdm_client_secret',
    'zdm_org_id',
    'zdm_refresh_token',
    'zdm_access_token',
    'zdm_token_expires',
    'zdm_api_cache',
    'zdm_last_sync',
    'zdm_rate_limit_remaining',
    'zdm_rate_limit_reset'
);

foreach ($zdm_options as $option) {
    delete_option($option);
}

/**
 * Clear any transients
 */
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_zdm_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_zdm_%'");

/**
 * Clear any scheduled cron jobs
 */
wp_clear_scheduled_hook('zdm_refresh_token_cron');
wp_clear_scheduled_hook('zdm_sync_tickets_cron');