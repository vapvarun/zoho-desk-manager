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
    // Ensure template CPT is registered
    ZDM_Template_Manager::ensure_cpt_registered();
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

    // Templates submenu
    add_submenu_page(
        'zoho-desk-manager',
        'Response Templates',
        'Templates',
        'manage_options',
        'edit.php?post_type=zdm_template',
        ''
    );

    // Template Categories submenu
    add_submenu_page(
        'zoho-desk-manager',
        'Template Categories',
        'Template Categories',
        'manage_options',
        'edit-tags.php?taxonomy=zdm_template_category&post_type=zdm_template',
        ''
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

