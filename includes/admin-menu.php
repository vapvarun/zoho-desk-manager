<?php
/**
 * Admin Menu
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'zdm_add_admin_menu');
function zdm_add_admin_menu() {
    // Main menu
    add_menu_page(
        'Zoho Desk Manager',
        'Zoho Desk',
        'manage_options',
        'zoho-desk-manager',
        'zdm_tickets_page',
        'dashicons-tickets-alt',
        30
    );

    // Settings submenu
    add_submenu_page(
        'zoho-desk-manager',
        'Settings',
        'Settings',
        'manage_options',
        'zoho-desk-settings',
        'zdm_settings_page'
    );

    // AI Settings submenu
    add_submenu_page(
        'zoho-desk-manager',
        'AI Settings',
        'AI Settings',
        'manage_options',
        'zoho-desk-ai',
        'zdm_ai_settings_page'
    );

    // Help & Commands submenu
    add_submenu_page(
        'zoho-desk-manager',
        'Help & Commands',
        'Help & Commands',
        'manage_options',
        'zoho-desk-help',
        'zdm_help_page'
    );
}

