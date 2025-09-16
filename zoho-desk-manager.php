<?php
/**
 * Plugin Name: Zoho Desk Manager
 * Plugin URI: https://wordpress.org/plugins/zoho-desk-manager/
 * Description: Professional WordPress plugin for managing Zoho Desk support tickets with AI-powered response generation directly from your admin dashboard
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: WBComDesigns
 * Author URI: https://wbcomdesigns.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zoho-desk-manager
 * Domain Path: /languages
 *
 * @package ZohoDeskManager
 * @author WBComDesigns
 * @copyright 2024 WBComDesigns
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZDM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZDM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ZDM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once ZDM_PLUGIN_PATH . 'includes/class-rate-limiter.php';
require_once ZDM_PLUGIN_PATH . 'includes/class-zoho-api.php';
require_once ZDM_PLUGIN_PATH . 'includes/class-dashboard-widget.php';
require_once ZDM_PLUGIN_PATH . 'includes/class-ai-assistant.php';
require_once ZDM_PLUGIN_PATH . 'includes/class-subscription-ai.php';
require_once ZDM_PLUGIN_PATH . 'includes/class-browser-ai.php';
require_once ZDM_PLUGIN_PATH . 'includes/class-template-manager.php';
require_once ZDM_PLUGIN_PATH . 'includes/admin-menu.php';
require_once ZDM_PLUGIN_PATH . 'includes/settings.php';
require_once ZDM_PLUGIN_PATH . 'includes/ai-settings.php';
require_once ZDM_PLUGIN_PATH . 'includes/tickets-list.php';
require_once ZDM_PLUGIN_PATH . 'includes/help-page.php';

// Include WP-CLI commands if available
if (defined('WP_CLI') && WP_CLI) {
    require_once ZDM_PLUGIN_PATH . 'includes/class-cli-processor.php';
    require_once ZDM_PLUGIN_PATH . 'includes/wp-cli-commands.php';
}

// Activation hook
register_activation_hook(__FILE__, 'zdm_activate');
function zdm_activate() {
    // Create database table for storing tokens if needed
    $options = array(
        'zdm_client_id' => '',
        'zdm_client_secret' => '',
        'zdm_org_id' => '',
        'zdm_refresh_token' => '',
        'zdm_access_token' => '',
        'zdm_token_expires' => ''
    );

    foreach ($options as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'zdm_deactivate');
function zdm_deactivate() {
    // Clean up if needed
}

// Initialize plugin
add_action('init', 'zdm_init');
function zdm_init() {
    // Load text domain for translations
    load_plugin_textdomain('zoho-desk-manager', false, dirname(ZDM_PLUGIN_BASENAME) . '/languages');

    // Initialize dashboard widget
    ZDM_Dashboard_Widget::init();

    // Initialize AI Assistant
    ZDM_AI_Assistant::init();

    // Initialize Template Manager
    ZDM_Template_Manager::init();
}

// Add settings link on plugin page
add_filter('plugin_action_links_' . ZDM_PLUGIN_BASENAME, 'zdm_settings_link');
function zdm_settings_link($links) {
    $settings_link = '<a href="admin.php?page=zoho-desk-manager">' . __('Settings', 'zoho-desk-manager') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Enqueue admin scripts and styles
add_action('admin_enqueue_scripts', 'zdm_admin_assets');
function zdm_admin_assets($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'zoho-desk') === false) {
        return;
    }

    // CSS
    wp_enqueue_style(
        'zdm-admin-style',
        ZDM_PLUGIN_URL . 'assets/css/admin-style.css',
        array(),
        '1.1.0'
    );

    // JavaScript
    wp_enqueue_script(
        'zdm-admin-script',
        ZDM_PLUGIN_URL . 'assets/js/admin-script.js',
        array('jquery'),
        '1.1.0',
        true
    );

    // Localize script for AJAX
    wp_localize_script('zdm-admin-script', 'zdm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('zdm_ajax_nonce')
    ));

    // Draft handler script
    wp_enqueue_script(
        'zdm-draft-handler',
        ZDM_PLUGIN_URL . 'assets/js/draft-handler.js',
        array('jquery'),
        '1.1.0',
        true
    );

    // Localize script for AJAX
    wp_localize_script('zdm-admin-script', 'zdm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('zdm_ajax_nonce')
    ));

    // Also localize for draft handler
    wp_localize_script('zdm-draft-handler', 'zdm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('zdm_ai_nonce')
    ));
}

// AJAX handler for status update
add_action('wp_ajax_zdm_update_status', 'zdm_ajax_update_status');
function zdm_ajax_update_status() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'zdm_ajax_nonce')) {
        wp_die('Security check failed');
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $ticket_id = sanitize_text_field($_POST['ticket_id']);
    $status = sanitize_text_field($_POST['status']);

    $api = new ZDM_Zoho_API();
    $result = $api->update_ticket_status($ticket_id, $status);

    if ($result) {
        wp_send_json_success('Status updated successfully');
    } else {
        wp_send_json_error('Failed to update status');
    }
}

// AJAX handler for connection test
add_action('wp_ajax_zdm_test_connection', 'zdm_ajax_test_connection');
function zdm_ajax_test_connection() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'zdm_ajax_nonce')) {
        wp_die('Security check failed');
    }

    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $api = new ZDM_Zoho_API();
    $tickets = $api->get_tickets(array('limit' => 1));

    if ($tickets && isset($tickets['data'])) {
        wp_send_json_success('Connection successful');
    } else {
        wp_send_json_error('Connection failed');
    }
}

// Schedule token refresh
add_action('init', 'zdm_schedule_token_refresh');
function zdm_schedule_token_refresh() {
    if (!wp_next_scheduled('zdm_refresh_token_cron')) {
        wp_schedule_event(time(), 'hourly', 'zdm_refresh_token_cron');
    }
}

add_action('zdm_refresh_token_cron', 'zdm_cron_refresh_token');
function zdm_cron_refresh_token() {
    $api = new ZDM_Zoho_API();
    $api->get_access_token(); // This will auto-refresh if needed
}

// AJAX handler for saving drafts
add_action('wp_ajax_zdm_save_draft', 'zdm_ajax_save_draft');
function zdm_ajax_save_draft() {
    check_ajax_referer('zdm_ai_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $ticket_id = sanitize_text_field($_POST['ticket_id']);
    $draft_content = wp_kses_post($_POST['draft_content']);

    if (empty($ticket_id) || empty($draft_content)) {
        wp_send_json_error('Missing required data');
    }

    // Save draft
    set_transient('zdm_draft_' . $ticket_id, $draft_content, 7 * DAY_IN_SECONDS);

    // Save metadata
    $metadata = array(
        'generated_at' => current_time('mysql'),
        'generated_by' => 'Web Interface',
        'status' => 'draft',
        'user_id' => get_current_user_id()
    );
    set_transient('zdm_draft_meta_' . $ticket_id, $metadata, 7 * DAY_IN_SECONDS);

    wp_send_json_success(array(
        'message' => 'Draft saved successfully',
        'timestamp' => current_time('mysql')
    ));
}

// AJAX handler for loading drafts
add_action('wp_ajax_zdm_load_draft', 'zdm_ajax_load_draft');
function zdm_ajax_load_draft() {
    check_ajax_referer('zdm_ai_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $ticket_id = sanitize_text_field($_POST['ticket_id']);

    if (empty($ticket_id)) {
        wp_send_json_error('Missing ticket ID');
    }

    $draft = get_transient('zdm_draft_' . $ticket_id);
    $meta = get_transient('zdm_draft_meta_' . $ticket_id);

    if ($draft) {
        wp_send_json_success(array(
            'draft' => $draft,
            'meta' => $meta
        ));
    } else {
        wp_send_json_error('No draft found');
    }
}

// AJAX handler for re-tagging templates
add_action('wp_ajax_zdm_retag_template', 'zdm_ajax_retag_template');
function zdm_ajax_retag_template() {
    check_ajax_referer('zdm_retag_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $template_id = intval($_POST['template_id']);

    if (empty($template_id)) {
        wp_send_json_error('Invalid template ID');
    }

    // Re-run auto-tagging for this template
    ZDM_Template_Manager::auto_tag_template($template_id);

    wp_send_json_success('Template re-tagged successfully');
}

// AJAX handler for auto-tagging Zoho tickets
add_action('wp_ajax_zdm_auto_tag_ticket', 'zdm_ajax_auto_tag_ticket');
function zdm_ajax_auto_tag_ticket() {
    check_ajax_referer('zdm_ai_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $ticket_id = sanitize_text_field($_POST['ticket_id']);
    $template_key = sanitize_text_field($_POST['template_key'] ?? '');
    $custom_tags = array_filter(array_map('sanitize_text_field', $_POST['custom_tags'] ?? array()));

    if (empty($ticket_id)) {
        wp_send_json_error('Invalid ticket ID');
    }

    $api = new ZDM_Zoho_API();
    $result = $api->auto_tag_ticket($ticket_id, $template_key, $custom_tags);

    if ($result) {
        wp_send_json_success(array(
            'message' => 'Ticket tagged successfully',
            'tags_applied' => $result
        ));
    } else {
        wp_send_json_error('Failed to tag ticket');
    }
}

// AJAX handler for getting ticket tags
add_action('wp_ajax_zdm_get_ticket_tags', 'zdm_ajax_get_ticket_tags');
function zdm_ajax_get_ticket_tags() {
    check_ajax_referer('zdm_ai_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $ticket_id = sanitize_text_field($_POST['ticket_id']);

    if (empty($ticket_id)) {
        wp_send_json_error('Invalid ticket ID');
    }

    $api = new ZDM_Zoho_API();
    $tags = $api->get_ticket_tags_by_id($ticket_id);

    if ($tags !== false) {
        wp_send_json_success($tags);
    } else {
        wp_send_json_error('Failed to fetch ticket tags');
    }
}

// AJAX handler for manually adding tags to tickets
add_action('wp_ajax_zdm_add_ticket_tags', 'zdm_ajax_add_ticket_tags');
function zdm_ajax_add_ticket_tags() {
    check_ajax_referer('zdm_ai_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    $ticket_id = sanitize_text_field($_POST['ticket_id']);
    $tags = array_filter(array_map('sanitize_text_field', $_POST['tags'] ?? array()));

    if (empty($ticket_id) || empty($tags)) {
        wp_send_json_error('Missing ticket ID or tags');
    }

    $api = new ZDM_Zoho_API();
    $result = $api->add_ticket_tags($ticket_id, $tags);

    if ($result) {
        wp_send_json_success(array(
            'message' => 'Tags added successfully',
            'result' => $result
        ));
    } else {
        wp_send_json_error('Failed to add tags to ticket');
    }
}