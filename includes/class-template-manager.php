<?php
if (!defined('ABSPATH')) exit;

/**
 * Template Manager Class
 * Handles response templates using Custom Post Type
 */
class ZDM_Template_Manager {

    /**
     * Template CPT name
     */
    const POST_TYPE = 'zdm_template';
    const CATEGORY_TAXONOMY = 'zdm_template_category';
    const TAG_TAXONOMY = 'zdm_template_tag';

    /**
     * Initialize the template system
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'), 5); // Higher priority
        add_action('init', array(__CLASS__, 'register_taxonomies'), 5); // Higher priority
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_template_meta'));
        add_action('admin_init', array(__CLASS__, 'create_default_templates'));

        // Also register early for admin access
        add_action('admin_init', array(__CLASS__, 'ensure_cpt_registered'));

        // Flush rewrite rules when needed
        register_activation_hook(ZDM_PLUGIN_PATH . 'zoho-desk-manager.php', array(__CLASS__, 'flush_rewrite_rules_on_activation'));
    }

    /**
     * Flush rewrite rules on plugin activation
     */
    public static function flush_rewrite_rules_on_activation() {
        self::register_post_type();
        self::register_taxonomies();
        flush_rewrite_rules();
    }

    /**
     * Ensure CPT is registered for admin access
     */
    public static function ensure_cpt_registered() {
        if (!post_type_exists(self::POST_TYPE)) {
            self::register_post_type();
            self::register_taxonomies();
        }
    }

    /**
     * Register the template custom post type
     */
    public static function register_post_type() {
        $labels = array(
            'name' => 'Response Templates',
            'singular_name' => 'Response Template',
            'menu_name' => 'Templates',
            'add_new' => 'Add New Template',
            'add_new_item' => 'Add New Response Template',
            'edit_item' => 'Edit Template',
            'new_item' => 'New Template',
            'view_item' => 'View Template',
            'search_items' => 'Search Templates',
            'not_found' => 'No templates found',
            'not_found_in_trash' => 'No templates found in trash'
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // We'll add it manually to avoid duplicate menu items
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'excerpt', 'revisions'),
            'menu_icon' => 'dashicons-format-aside'
        );

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register taxonomies for templates
     */
    public static function register_taxonomies() {
        // Categories
        register_taxonomy(self::CATEGORY_TAXONOMY, self::POST_TYPE, array(
            'labels' => array(
                'name' => 'Template Categories',
                'singular_name' => 'Template Category',
                'menu_name' => 'Categories'
            ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false
        ));

        // Tags
        register_taxonomy(self::TAG_TAXONOMY, self::POST_TYPE, array(
            'labels' => array(
                'name' => 'Template Tags',
                'singular_name' => 'Template Tag',
                'menu_name' => 'Tags'
            ),
            'hierarchical' => false,
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false
        ));
    }

    /**
     * Add meta boxes for template data
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'zdm_template_variables',
            'Template Variables',
            array(__CLASS__, 'variables_meta_box'),
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'zdm_template_keywords',
            'Keywords for Smart Suggestions',
            array(__CLASS__, 'keywords_meta_box'),
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'zdm_template_usage',
            'Usage Statistics',
            array(__CLASS__, 'usage_meta_box'),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Variables meta box content
     */
    public static function variables_meta_box($post) {
        wp_nonce_field('zdm_template_meta', 'zdm_template_nonce');

        $variables = get_post_meta($post->ID, '_zdm_variables', true);
        $variables = $variables ? $variables : array();

        echo '<p><strong>Available Variables:</strong></p>';
        echo '<textarea name="zdm_variables" rows="6" style="width:100%;" placeholder="customer_name&#10;customer_email&#10;ticket_subject&#10;agent_name">';
        echo esc_textarea(implode("\n", $variables));
        echo '</textarea>';
        echo '<p><small>Enter one variable per line (without curly braces)</small></p>';

        echo '<p><strong>Common Variables:</strong></p>';
        $common_vars = array(
            'customer_name', 'customer_email', 'ticket_subject', 'ticket_number',
            'agent_name', 'company_name', 'site_url', 'today_date', 'response_time'
        );
        echo '<p><small>' . implode(', ', $common_vars) . '</small></p>';
    }

    /**
     * Keywords meta box content
     */
    public static function keywords_meta_box($post) {
        $keywords = get_post_meta($post->ID, '_zdm_keywords', true);

        echo '<textarea name="zdm_keywords" rows="4" style="width:100%;" placeholder="password, login, access, signin">';
        echo esc_textarea($keywords);
        echo '</textarea>';
        echo '<p><small>Keywords that trigger this template suggestion (comma-separated)</small></p>';
    }

    /**
     * Usage statistics meta box content
     */
    public static function usage_meta_box($post) {
        $usage_count = get_post_meta($post->ID, '_zdm_usage_count', true);
        $usage_count = $usage_count ? $usage_count : 0;

        echo '<p><strong>Times Used:</strong> ' . $usage_count . '</p>';

        $last_used = get_post_meta($post->ID, '_zdm_last_used', true);
        if ($last_used) {
            echo '<p><strong>Last Used:</strong> ' . date('M j, Y g:i a', strtotime($last_used)) . '</p>';
        } else {
            echo '<p><strong>Last Used:</strong> Never</p>';
        }
    }

    /**
     * Save template meta data
     */
    public static function save_template_meta($post_id) {
        if (!isset($_POST['zdm_template_nonce']) || !wp_verify_nonce($_POST['zdm_template_nonce'], 'zdm_template_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }

        // Save variables
        if (isset($_POST['zdm_variables'])) {
            $variables = array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['zdm_variables']))));
            update_post_meta($post_id, '_zdm_variables', $variables);
        }

        // Save keywords
        if (isset($_POST['zdm_keywords'])) {
            update_post_meta($post_id, '_zdm_keywords', sanitize_textarea_field($_POST['zdm_keywords']));
        }
    }

    /**
     * Get all templates
     */
    public static function get_templates() {
        $templates = array();

        $posts = get_posts(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        foreach ($posts as $post) {
            $templates[$post->post_name] = array(
                'id' => $post->ID,
                'name' => $post->post_title,
                'content' => $post->post_content,
                'description' => $post->post_excerpt,
                'variables' => get_post_meta($post->ID, '_zdm_variables', true) ?: array(),
                'keywords' => get_post_meta($post->ID, '_zdm_keywords', true) ?: '',
                'category' => self::get_template_category($post->ID),
                'usage_count' => get_post_meta($post->ID, '_zdm_usage_count', true) ?: 0
            );
        }

        return apply_filters('zdm_response_templates', $templates);
    }

    /**
     * Get template by key
     */
    public static function get_template($key) {
        $templates = self::get_templates();
        return isset($templates[$key]) ? $templates[$key] : false;
    }

    /**
     * Get template category
     */
    private static function get_template_category($post_id) {
        // Ensure taxonomy is registered
        if (!taxonomy_exists(self::CATEGORY_TAXONOMY)) {
            self::register_taxonomies();
        }

        $terms = wp_get_post_terms($post_id, self::CATEGORY_TAXONOMY);
        if (is_wp_error($terms) || empty($terms)) {
            return 'general';
        }
        return $terms[0]->slug;
    }

    /**
     * Process template with variables
     */
    public static function process_template($template_key, $variables = array()) {
        $template = self::get_template($template_key);

        if (!$template) {
            return false;
        }

        $content = $template['content'];

        // Replace variables
        foreach ($variables as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }

        // Replace any remaining variables with default values
        $content = self::replace_default_variables($content);

        // Track usage
        self::track_template_usage($template['id']);

        return $content;
    }

    /**
     * Track template usage
     */
    private static function track_template_usage($template_id) {
        $usage_count = get_post_meta($template_id, '_zdm_usage_count', true) ?: 0;
        update_post_meta($template_id, '_zdm_usage_count', $usage_count + 1);
        update_post_meta($template_id, '_zdm_last_used', current_time('mysql'));
    }

    /**
     * Replace variables with default values
     */
    private static function replace_default_variables($content) {
        $defaults = array(
            '{customer_name}' => 'Customer',
            '{agent_name}' => wp_get_current_user()->display_name ?: 'Support Team',
            '{company_name}' => get_bloginfo('name'),
            '{site_url}' => home_url(),
            '{today_date}' => date('F j, Y'),
            '{response_time}' => '24 hours'
        );

        foreach ($defaults as $variable => $default) {
            $content = str_replace($variable, $default, $content);
        }

        return $content;
    }

    /**
     * Get templates by category
     */
    public static function get_templates_by_category($category = '') {
        $args = array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );

        if (!empty($category)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => self::CATEGORY_TAXONOMY,
                    'field' => 'slug',
                    'terms' => $category
                )
            );
        }

        $posts = get_posts($args);
        $templates = array();

        foreach ($posts as $post) {
            $templates[$post->post_name] = array(
                'id' => $post->ID,
                'name' => $post->post_title,
                'content' => $post->post_content,
                'description' => $post->post_excerpt,
                'variables' => get_post_meta($post->ID, '_zdm_variables', true) ?: array(),
                'keywords' => get_post_meta($post->ID, '_zdm_keywords', true) ?: '',
                'category' => self::get_template_category($post->ID),
                'usage_count' => get_post_meta($post->ID, '_zdm_usage_count', true) ?: 0
            );
        }

        return $templates;
    }

    /**
     * Get all categories
     */
    public static function get_categories() {
        $terms = get_terms(array(
            'taxonomy' => self::CATEGORY_TAXONOMY,
            'hide_empty' => false
        ));

        $categories = array();
        foreach ($terms as $term) {
            $categories[] = $term->slug;
        }

        return $categories;
    }

    /**
     * Extract variables from ticket data
     */
    public static function extract_ticket_variables($ticket_data, $threads = null) {
        $variables = array();

        // Basic ticket information
        if (isset($ticket_data['contact'])) {
            $variables['customer_name'] = $ticket_data['contact']['firstName'] ?? 'Customer';
            $variables['customer_email'] = $ticket_data['contact']['email'] ?? '';
        }

        $variables['ticket_subject'] = $ticket_data['subject'] ?? '';
        $variables['ticket_number'] = $ticket_data['ticketNumber'] ?? '';
        $variables['ticket_id'] = $ticket_data['id'] ?? '';
        $variables['priority'] = $ticket_data['priority'] ?? 'Normal';
        $variables['status'] = $ticket_data['status'] ?? '';

        if (isset($ticket_data['createdTime'])) {
            $variables['created_date'] = date('F j, Y', strtotime($ticket_data['createdTime']));
        }

        // Agent information
        $current_user = wp_get_current_user();
        $variables['agent_name'] = $current_user->display_name ?: 'Support Team';
        $variables['agent_email'] = $current_user->user_email ?: '';

        // Company information
        $variables['company_name'] = get_bloginfo('name');
        $variables['site_url'] = home_url();
        $variables['support_url'] = admin_url('admin.php?page=zoho-desk-manager');

        return apply_filters('zdm_template_variables', $variables, $ticket_data, $threads);
    }

    /**
     * Get template suggestions based on ticket content
     */
    public static function suggest_templates($ticket_data, $threads = null) {
        $suggestions = array();

        // Analyze ticket content for keywords
        $content = strtolower($ticket_data['subject'] ?? '');
        if ($threads && isset($threads['data'])) {
            foreach ($threads['data'] as $thread) {
                if (isset($thread['content'])) {
                    $content .= ' ' . strtolower($thread['content']);
                }
            }
        }

        // Define keyword-to-template mapping
        $keyword_templates = array(
            'password|login|signin|sign in|access' => 'password_reset',
            'download|file|zip|install' => 'download_issue',
            'license|expired|activation|activate' => 'license_expired',
            'refund|money back|return' => 'refund_approved',
            'bug|error|not working|broken|crash' => 'technical_escalation',
            'thank|hi|hello|help' => 'first_response'
        );

        foreach ($keyword_templates as $keywords => $template_key) {
            if (preg_match('/(' . $keywords . ')/', $content)) {
                $template = self::get_template($template_key);
                if ($template) {
                    $suggestions[] = array(
                        'key' => $template_key,
                        'name' => $template['name'],
                        'reason' => 'Content match'
                    );
                }
            }
        }

        return $suggestions;
    }

    /**
     * Create default templates on plugin activation
     */
    public static function create_default_templates() {
        // Only run once
        if (get_option('zdm_default_templates_created')) {
            return;
        }

        // First create categories
        self::create_default_categories();

        $default_templates = array(
            array(
                'title' => 'Greeting',
                'content' => 'Hi {customer_name},

Thank you for contacting support. I\'m here to help you with your inquiry.',
                'excerpt' => 'Standard greeting for customer inquiries',
                'category' => 'general',
                'variables' => array('customer_name'),
                'keywords' => 'hello, hi, help, inquiry'
            ),
            array(
                'title' => 'Closing',
                'content' => 'Please let me know if you need any further assistance. I\'m happy to help!

Best regards,
{agent_name}',
                'excerpt' => 'Professional closing for support responses',
                'category' => 'general',
                'variables' => array('agent_name'),
                'keywords' => 'closing, regards, assistance'
            ),
            array(
                'title' => 'Password Reset',
                'content' => 'Hi {customer_name},

To reset your password, please follow these steps:

1. Go to the login page
2. Click "Forgot Password"
3. Enter your email address: {customer_email}
4. Check your email for the reset link
5. Follow the instructions in the email

If you don\'t receive the email within 10 minutes, please check your spam folder.

Let me know if you need any help with this process!',
                'excerpt' => 'Instructions for password reset process',
                'category' => 'account',
                'variables' => array('customer_name', 'customer_email'),
                'keywords' => 'password, reset, login, access, signin, sign in'
            ),
            array(
                'title' => 'Download Problem',
                'content' => 'Hi {customer_name},

I\'ve resolved the download issue for you. Here\'s what I\'ve done:

• Reset your download limit
• Generated a fresh download link
• Verified your license is active

You can now download {product_name} using this link:
{download_link}

This link will remain active for 72 hours. Please download and save the file to your computer.

If you continue to have issues, please don\'t hesitate to reach out.',
                'excerpt' => 'Resolution for download-related issues',
                'category' => 'downloads',
                'variables' => array('customer_name', 'product_name', 'download_link'),
                'keywords' => 'download, file, zip, install, link'
            ),
            array(
                'title' => 'License Expired',
                'content' => 'Hi {customer_name},

I see that your license for {product_name} expired on {expiry_date}.

To continue receiving updates and support, you can renew your license here:
{renewal_link}

As a valued customer, you\'re eligible for a 20% renewal discount. The discount will be automatically applied when you use the link above.

Your current license key: {license_key}

Please let me know if you have any questions about the renewal process.',
                'excerpt' => 'License expiration notification and renewal instructions',
                'category' => 'licensing',
                'variables' => array('customer_name', 'product_name', 'expiry_date', 'renewal_link', 'license_key'),
                'keywords' => 'license, expired, renewal, activation, activate, key'
            ),
            array(
                'title' => 'Refund Approved',
                'content' => 'Hi {customer_name},

I\'ve processed your refund request for {product_name} purchased on {purchase_date}.

Refund Details:
• Amount: {refund_amount}
• Processing time: 3-5 business days
• Refund method: Original payment method

You should see the refund reflected in your account within 3-5 business days. If you don\'t see it after this time, please contact your bank or payment provider.

Thank you for giving us the opportunity to serve you.',
                'excerpt' => 'Refund approval confirmation and processing details',
                'category' => 'billing',
                'variables' => array('customer_name', 'product_name', 'purchase_date', 'refund_amount'),
                'keywords' => 'refund, money back, return, billing, payment'
            ),
            array(
                'title' => 'Technical Escalation',
                'content' => 'Hi {customer_name},

Thank you for providing the detailed information about the issue you\'re experiencing with {product_name}.

I\'m escalating your ticket to our technical team for further investigation. A senior developer will review your case and provide a detailed solution.

Expected response time: 24-48 hours

In the meantime, here\'s a temporary workaround you can try:
{workaround_steps}

I\'ll keep you updated on the progress. Thank you for your patience.',
                'excerpt' => 'Escalation to technical team for complex issues',
                'category' => 'technical',
                'variables' => array('customer_name', 'product_name', 'workaround_steps'),
                'keywords' => 'bug, error, not working, broken, crash, technical, escalate'
            ),
            array(
                'title' => 'First Response',
                'content' => 'Hi {customer_name},

Thank you for reaching out to us regarding {ticket_subject}.

I\'ve received your request and I\'m looking into this for you. I\'ll have a detailed response within {response_time}.

If this is urgent, please don\'t hesitate to let me know.

Best regards,
{agent_name}',
                'excerpt' => 'Initial acknowledgment of customer inquiry',
                'category' => 'general',
                'variables' => array('customer_name', 'ticket_subject', 'response_time', 'agent_name'),
                'keywords' => 'first, acknowledgment, received, inquiry'
            )
        );

        foreach ($default_templates as $template_data) {
            self::create_template($template_data);
        }

        update_option('zdm_default_templates_created', true);
    }

    /**
     * Create default categories
     */
    private static function create_default_categories() {
        $categories = array(
            'general' => 'General',
            'account' => 'Account & Access',
            'downloads' => 'Downloads',
            'licensing' => 'Licensing',
            'billing' => 'Billing & Payments',
            'technical' => 'Technical Support'
        );

        foreach ($categories as $slug => $name) {
            if (!term_exists($slug, self::CATEGORY_TAXONOMY)) {
                wp_insert_term($name, self::CATEGORY_TAXONOMY, array('slug' => $slug));
            }
        }
    }

    /**
     * Create a template programmatically
     */
    private static function create_template($template_data) {
        $post_id = wp_insert_post(array(
            'post_title' => $template_data['title'],
            'post_content' => $template_data['content'],
            'post_excerpt' => $template_data['excerpt'],
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_name' => sanitize_title($template_data['title'])
        ));

        if ($post_id && !is_wp_error($post_id)) {
            // Set category
            if (isset($template_data['category'])) {
                wp_set_post_terms($post_id, array($template_data['category']), self::CATEGORY_TAXONOMY);
            }

            // Set variables
            if (isset($template_data['variables'])) {
                update_post_meta($post_id, '_zdm_variables', $template_data['variables']);
            }

            // Set keywords
            if (isset($template_data['keywords'])) {
                update_post_meta($post_id, '_zdm_keywords', $template_data['keywords']);
            }

            // Mark as default template
            update_post_meta($post_id, '_zdm_is_default', true);
        }

        return $post_id;
    }
}