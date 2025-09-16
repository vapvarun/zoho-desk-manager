<?php
/**
 * Dashboard Widget for Zoho Desk Ticket Summary
 *
 * Displays real-time summary of tickets requiring attention
 *
 * @package ZohoDeskManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZDM_Dashboard_Widget {

    /**
     * Initialize the dashboard widget
     */
    public static function init() {
        add_action('wp_dashboard_setup', array(__CLASS__, 'add_dashboard_widget'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_widget_scripts'));
        add_action('wp_ajax_zdm_refresh_widget', array(__CLASS__, 'ajax_refresh_widget'));
        add_action('wp_ajax_zdm_get_ticket_details', array(__CLASS__, 'ajax_get_ticket_details'));
    }

    /**
     * Add the dashboard widget
     */
    public static function add_dashboard_widget() {
        // Check if user has permission and if plugin is configured
        if (!current_user_can('manage_options')) {
            return;
        }

        $access_token = get_option('zdm_access_token');
        if (empty($access_token)) {
            return;
        }

        wp_add_dashboard_widget(
            'zdm_ticket_summary',
            __('Zoho Desk - Tickets Requiring Attention', 'zoho-desk-manager'),
            array(__CLASS__, 'render_widget'),
            null, // Control callback
            null, // Callback args
            'normal', // Context
            'high' // Priority
        );
    }

    /**
     * Render the dashboard widget
     */
    public static function render_widget() {
        $cache_key = 'zdm_dashboard_summary';
        $summary_data = get_transient($cache_key);

        if ($summary_data === false || isset($_GET['force_refresh'])) {
            $summary_data = self::get_ticket_summary();
            set_transient($cache_key, $summary_data, 60); // Cache for 1 minute
        }

        ?>
        <div id="zdm-widget-container">
            <?php self::render_summary_content($summary_data); ?>
        </div>

        <div class="zdm-widget-footer" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
            <button id="zdm-refresh-widget" class="button button-secondary">
                <span class="dashicons dashicons-update" style="vertical-align: text-top;"></span>
                <?php _e('Refresh', 'zoho-desk-manager'); ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=zoho-desk-manager'); ?>" class="button button-primary" style="float: right;">
                <?php _e('View All Tickets', 'zoho-desk-manager'); ?>
            </a>
            <div style="clear: both;"></div>
        </div>

        <style>
            #zdm_ticket_summary .inside {
                padding: 12px;
                margin: 0;
            }
            .zdm-stat-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }
            .zdm-stat-card {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                text-align: center;
                border-left: 4px solid;
                transition: transform 0.2s;
            }
            .zdm-stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .zdm-stat-urgent {
                border-color: #dc3545;
                background: #fff5f5;
            }
            .zdm-stat-open {
                border-color: #28a745;
            }
            .zdm-stat-pending {
                border-color: #ffc107;
            }
            .zdm-stat-overdue {
                border-color: #dc3545;
            }
            .zdm-stat-number {
                font-size: 28px;
                font-weight: bold;
                line-height: 1;
                margin-bottom: 5px;
            }
            .zdm-stat-label {
                color: #666;
                font-size: 12px;
                text-transform: uppercase;
            }
            .zdm-ticket-item {
                padding: 10px;
                margin: 5px 0;
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s;
            }
            .zdm-ticket-item:hover {
                border-color: #0073aa;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .zdm-priority-high {
                border-left: 3px solid #dc3545 !important;
            }
            .zdm-priority-medium {
                border-left: 3px solid #ffc107 !important;
            }
            .zdm-widget-loading {
                text-align: center;
                padding: 20px;
            }
            .zdm-time-badge {
                display: inline-block;
                padding: 2px 6px;
                background: #e0e0e0;
                border-radius: 3px;
                font-size: 11px;
                color: #666;
            }
            .zdm-overdue {
                background: #dc3545;
                color: white;
            }
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }
            .zdm-updating {
                animation: pulse 1s infinite;
            }
            .zdm-auto-refresh-status {
                float: right;
                font-size: 11px;
                color: #666;
                margin-top: 5px;
            }
            .zdm-auto-refresh-active {
                color: #28a745;
            }
        </style>
        <?php
    }

    /**
     * Render summary content
     */
    private static function render_summary_content($summary_data) {
        if (empty($summary_data) || isset($summary_data['error'])) {
            echo '<p>' . __('Unable to fetch ticket data. Please check your connection.', 'zoho-desk-manager') . '</p>';
            return;
        }

        ?>
        <div class="zdm-stat-grid">
            <div class="zdm-stat-card zdm-stat-urgent">
                <div class="zdm-stat-number"><?php echo esc_html($summary_data['urgent_count']); ?></div>
                <div class="zdm-stat-label"><?php _e('Urgent', 'zoho-desk-manager'); ?></div>
            </div>
            <div class="zdm-stat-card zdm-stat-open">
                <div class="zdm-stat-number"><?php echo esc_html($summary_data['open_count']); ?></div>
                <div class="zdm-stat-label"><?php _e('Open', 'zoho-desk-manager'); ?></div>
            </div>
            <div class="zdm-stat-card zdm-stat-pending">
                <div class="zdm-stat-number"><?php echo esc_html($summary_data['pending_reply_count']); ?></div>
                <div class="zdm-stat-label"><?php _e('Awaiting Reply', 'zoho-desk-manager'); ?></div>
            </div>
            <div class="zdm-stat-card zdm-stat-overdue">
                <div class="zdm-stat-number"><?php echo esc_html($summary_data['overdue_count']); ?></div>
                <div class="zdm-stat-label"><?php _e('Overdue', 'zoho-desk-manager'); ?></div>
            </div>
        </div>

        <?php if (!empty($summary_data['recent_tickets'])): ?>
            <h4 style="margin: 15px 0 10px;"><?php _e('Tickets Requiring Immediate Attention:', 'zoho-desk-manager'); ?></h4>
            <div class="zdm-recent-tickets">
                <?php foreach ($summary_data['recent_tickets'] as $ticket):
                    $priority_class = '';
                    if ($ticket['priority'] === 'High') {
                        $priority_class = 'zdm-priority-high';
                    } elseif ($ticket['priority'] === 'Medium') {
                        $priority_class = 'zdm-priority-medium';
                    }

                    $time_ago = self::get_time_ago($ticket['modified_time']);
                    $is_overdue = isset($ticket['is_overdue']) && $ticket['is_overdue'];
                ?>
                    <div class="zdm-ticket-item <?php echo esc_attr($priority_class); ?>"
                         data-ticket-id="<?php echo esc_attr($ticket['id']); ?>">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <strong>#<?php echo esc_html($ticket['ticket_number']); ?></strong>
                                - <?php echo esc_html(wp_trim_words($ticket['subject'], 10)); ?>
                                <div style="font-size: 11px; color: #666; margin-top: 3px;">
                                    <?php echo esc_html($ticket['contact_name']); ?> •
                                    <span class="zdm-time-badge <?php echo $is_overdue ? 'zdm-overdue' : ''; ?>">
                                        <?php echo $is_overdue ? __('OVERDUE', 'zoho-desk-manager') : $time_ago; ?>
                                    </span>
                                </div>
                            </div>
                            <a href="<?php echo admin_url('admin.php?page=zoho-desk-manager&ticket_id=' . $ticket['id']); ?>"
                               class="button button-small">
                                <?php _e('View', 'zoho-desk-manager'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; padding: 20px; color: #28a745;">
                <span class="dashicons dashicons-yes" style="font-size: 30px;"></span><br>
                <?php _e('All tickets are up to date!', 'zoho-desk-manager'); ?>
            </p>
        <?php endif; ?>

        <div class="zdm-auto-refresh-status">
            <span class="dashicons dashicons-clock"></span>
            <span id="zdm-refresh-countdown"><?php _e('Auto-refresh in 60s', 'zoho-desk-manager'); ?></span>
        </div>
        <?php
    }

    /**
     * Get ticket summary data
     */
    private static function get_ticket_summary() {
        $api = new ZDM_Zoho_API();

        // Fetch open tickets
        $open_tickets = $api->get_tickets(array(
            'status' => 'Open',
            'limit' => 100,
            'sortBy' => 'modifiedTime'
        ));

        if (!$open_tickets || !isset($open_tickets['data'])) {
            return array('error' => true);
        }

        $summary = array(
            'open_count' => 0,
            'urgent_count' => 0,
            'pending_reply_count' => 0,
            'overdue_count' => 0,
            'recent_tickets' => array()
        );

        $current_time = time();
        $tickets_needing_attention = array();

        foreach ($open_tickets['data'] as $ticket) {
            $summary['open_count']++;

            // Check priority
            if (isset($ticket['priority']) && $ticket['priority'] === 'High') {
                $summary['urgent_count']++;
            }

            // Check if overdue
            if (isset($ticket['dueDate'])) {
                $due_time = strtotime($ticket['dueDate']);
                if ($due_time < $current_time) {
                    $summary['overdue_count']++;
                    $ticket['is_overdue'] = true;
                }
            }

            // Check response time (tickets modified in last 24 hours that might need reply)
            $modified_time = strtotime($ticket['modifiedTime']);
            $hours_since_modified = ($current_time - $modified_time) / 3600;

            if ($hours_since_modified < 24) {
                $summary['pending_reply_count']++;
            }

            // Prepare ticket data for display
            $ticket_data = array(
                'id' => $ticket['id'],
                'ticket_number' => $ticket['ticketNumber'],
                'subject' => $ticket['subject'],
                'priority' => $ticket['priority'] ?? 'Normal',
                'status' => $ticket['status'],
                'contact_name' => $ticket['contact']['firstName'] ?? 'Unknown',
                'modified_time' => $ticket['modifiedTime'],
                'is_overdue' => isset($ticket['is_overdue']) ? $ticket['is_overdue'] : false
            );

            // Prioritize tickets for display
            if ($ticket_data['priority'] === 'High' ||
                $ticket_data['is_overdue'] ||
                $hours_since_modified < 4) {
                $tickets_needing_attention[] = $ticket_data;
            }
        }

        // Sort tickets by priority and time
        usort($tickets_needing_attention, function($a, $b) {
            // Overdue tickets first
            if ($a['is_overdue'] !== $b['is_overdue']) {
                return $a['is_overdue'] ? -1 : 1;
            }
            // Then by priority
            $priority_order = array('High' => 1, 'Medium' => 2, 'Low' => 3, 'Normal' => 3);
            $a_priority = $priority_order[$a['priority']] ?? 3;
            $b_priority = $priority_order[$b['priority']] ?? 3;
            if ($a_priority !== $b_priority) {
                return $a_priority - $b_priority;
            }
            // Then by modified time (most recent first)
            return strtotime($b['modified_time']) - strtotime($a['modified_time']);
        });

        // Get only top 5 tickets needing attention
        $summary['recent_tickets'] = array_slice($tickets_needing_attention, 0, 5);

        return $summary;
    }

    /**
     * Get human-readable time ago
     */
    private static function get_time_ago($datetime) {
        $time = strtotime($datetime);
        $current = time();
        $diff = $current - $time;

        if ($diff < 60) {
            return __('Just now', 'zoho-desk-manager');
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return sprintf(_n('%d min ago', '%d mins ago', $mins, 'zoho-desk-manager'), $mins);
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'zoho-desk-manager'), $hours);
        } else {
            $days = floor($diff / 86400);
            return sprintf(_n('%d day ago', '%d days ago', $days, 'zoho-desk-manager'), $days);
        }
    }

    /**
     * AJAX handler to refresh widget
     */
    public static function ajax_refresh_widget() {
        check_ajax_referer('zdm_widget_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Clear cache and get fresh data
        delete_transient('zdm_dashboard_summary');
        $summary_data = self::get_ticket_summary();

        // Cache the new data
        set_transient('zdm_dashboard_summary', $summary_data, 60);

        ob_start();
        self::render_summary_content($summary_data);
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX handler to get ticket details preview
     */
    public static function ajax_get_ticket_details() {
        check_ajax_referer('zdm_widget_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $ticket_id = sanitize_text_field($_POST['ticket_id']);
        $api = new ZDM_Zoho_API();
        $ticket = $api->get_ticket($ticket_id);

        if ($ticket) {
            ob_start();
            ?>
            <div style="padding: 10px;">
                <h4><?php echo esc_html($ticket['subject']); ?></h4>
                <p><strong><?php _e('Status:', 'zoho-desk-manager'); ?></strong> <?php echo esc_html($ticket['status']); ?></p>
                <p><strong><?php _e('Priority:', 'zoho-desk-manager'); ?></strong> <?php echo esc_html($ticket['priority'] ?? 'Normal'); ?></p>
                <p><strong><?php _e('Created:', 'zoho-desk-manager'); ?></strong> <?php echo esc_html(date('M d, Y H:i', strtotime($ticket['createdTime']))); ?></p>
                <?php if (!empty($ticket['description'])): ?>
                    <p><strong><?php _e('Description:', 'zoho-desk-manager'); ?></strong><br>
                    <?php echo esc_html(wp_trim_words($ticket['description'], 50)); ?></p>
                <?php endif; ?>
                <a href="<?php echo admin_url('admin.php?page=zoho-desk-manager&ticket_id=' . $ticket_id); ?>"
                   class="button button-primary">
                    <?php _e('View Full Ticket', 'zoho-desk-manager'); ?>
                </a>
            </div>
            <?php
            $html = ob_get_clean();
            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_error('Unable to fetch ticket details');
        }
    }

    /**
     * Enqueue widget-specific scripts
     */
    public static function enqueue_widget_scripts($hook) {
        if ($hook !== 'index.php') {
            return;
        }

        // Only load if widget is active
        $access_token = get_option('zdm_access_token');
        if (empty($access_token)) {
            return;
        }

        wp_enqueue_script(
            'zdm-widget-script',
            ZDM_PLUGIN_URL . 'assets/js/widget-script.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('zdm-widget-script', 'zdm_widget', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zdm_widget_nonce'),
            'refresh_interval' => 60000, // 60 seconds
            'strings' => array(
                'refreshing' => __('Refreshing...', 'zoho-desk-manager'),
                'auto_refresh' => __('Auto-refresh in %ds', 'zoho-desk-manager'),
                'error' => __('Error loading data', 'zoho-desk-manager')
            )
        ));
    }
}