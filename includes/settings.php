<?php
/**
 * Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle OAuth callback
add_action('admin_init', 'zdm_handle_oauth_callback');
function zdm_handle_oauth_callback() {
    if (isset($_GET['page']) && $_GET['page'] == 'zoho-desk-settings') {
        // Handle OAuth callback with code
        if (isset($_GET['code'])) {
            $api = new ZDM_Zoho_API();
            if ($api->exchange_code_for_token($_GET['code'])) {
                wp_redirect(admin_url('admin.php?page=zoho-desk-settings&auth=success'));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=zoho-desk-settings&auth=failed'));
                exit;
            }
        }

        // Handle disconnect action
        if (isset($_GET['action']) && $_GET['action'] == 'disconnect' && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'zdm_disconnect')) {
                delete_option('zdm_access_token');
                delete_option('zdm_refresh_token');
                delete_option('zdm_token_expires');
                wp_redirect(admin_url('admin.php?page=zoho-desk-settings&disconnected=true'));
                exit;
            }
        }
    }
}

// Settings page HTML
function zdm_settings_page() {
    // Handle form submission
    if (isset($_POST['save_settings']) && $_POST['save_settings'] == '1') {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'zdm_save_settings')) {
            echo '<div class="notice notice-error"><p>Security check failed!</p></div>';
        } else {
            // Save the settings
            $client_id = isset($_POST['client_id']) ? sanitize_text_field($_POST['client_id']) : '';
            $client_secret = isset($_POST['client_secret']) ? sanitize_text_field($_POST['client_secret']) : '';
            $org_id = isset($_POST['org_id']) ? sanitize_text_field($_POST['org_id']) : '';

            update_option('zdm_client_id', $client_id);
            update_option('zdm_client_secret', $client_secret);
            update_option('zdm_org_id', $org_id);

            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        }
    }

    // Get current values
    $client_id = get_option('zdm_client_id', '');
    $client_secret = get_option('zdm_client_secret', '');
    $org_id = get_option('zdm_org_id', '');
    $access_token = get_option('zdm_access_token', '');

    $api = new ZDM_Zoho_API();
    ?>

    <div class="wrap">
        <h1>Zoho Desk Manager Settings</h1>

        <?php if (isset($_GET['auth']) && $_GET['auth'] == 'success'): ?>
            <div class="notice notice-success is-dismissible">
                <p>Successfully connected to Zoho Desk!</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['auth']) && $_GET['auth'] == 'failed'): ?>
            <div class="notice notice-error is-dismissible">
                <p>Failed to connect to Zoho Desk. Please check your credentials and try again.</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['disconnected']) && $_GET['disconnected'] == 'true'): ?>
            <div class="notice notice-info is-dismissible">
                <p>Disconnected from Zoho Desk.</p>
            </div>
        <?php endif; ?>

        <?php
        // Debug info (remove in production)
        if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            echo '<!-- Debug: Client ID stored: ' . esc_html($client_id) . ' -->';
            echo '<!-- Debug: Org ID stored: ' . esc_html($org_id) . ' -->';
        }
        ?>

        <form method="post" action="">
            <?php wp_nonce_field('zdm_save_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="client_id">Client ID</label>
                    </th>
                    <td>
                        <input type="text" id="client_id" name="client_id"
                               value="<?php echo esc_attr($client_id); ?>"
                               class="regular-text" />
                        <p class="description">Get this from your Zoho API Console</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="client_secret">Client Secret</label>
                    </th>
                    <td>
                        <input type="password" id="client_secret" name="client_secret"
                               value="<?php echo esc_attr($client_secret); ?>"
                               class="regular-text" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="org_id">Organization ID</label>
                    </th>
                    <td>
                        <input type="text" id="org_id" name="org_id"
                               value="<?php echo esc_attr($org_id); ?>"
                               class="regular-text" />
                        <p class="description">Your Zoho Desk Organization ID</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Connection Status</th>
                    <td>
                        <?php if ($access_token): ?>
                            <span style="color: green; font-weight: bold;">✓ Connected to Zoho Desk</span>
                            <div style="margin-top: 10px;">
                                <a href="<?php echo admin_url('admin.php?page=zoho-desk-manager'); ?>"
                                   class="button button-primary">
                                    View Tickets
                                </a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zoho-desk-settings&action=disconnect'), 'zdm_disconnect'); ?>"
                                   class="button button-secondary"
                                   onclick="return confirm('Are you sure you want to disconnect from Zoho Desk?');">
                                    Disconnect
                                </a>
                            </div>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold;">✗ Not Connected</span>
                            <?php if (!$client_id || !$client_secret || !$org_id): ?>
                                <p class="description" style="color: #666;">Please save your API credentials first, then click Connect.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="hidden" name="save_settings" value="1" />
                <input type="submit" class="button-primary" value="Save Settings" />
            </p>
        </form>

        <?php if ($client_id && $client_secret && $org_id && !$access_token): ?>
            <div style="margin-top: 20px; padding: 20px; background: #f0f8ff; border-left: 4px solid #0073aa;">
                <h3 style="margin-top: 0;">Ready to Connect!</h3>
                <p>Your API credentials are saved. Now connect to Zoho Desk to start managing tickets.</p>
                <a href="<?php echo esc_url($api->get_auth_url()); ?>"
                   class="button button-primary button-large">
                    Connect to Zoho Desk →
                </a>
            </div>
        <?php endif; ?>

        <hr />

        <h2>Dashboard Widget Settings</h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="widget_refresh_interval">Auto-Refresh Interval</label>
                </th>
                <td>
                    <select id="widget_refresh_interval" name="widget_refresh_interval">
                        <option value="30">30 seconds</option>
                        <option value="60" selected>1 minute</option>
                        <option value="120">2 minutes</option>
                        <option value="300">5 minutes</option>
                        <option value="0">Disabled</option>
                    </select>
                    <p class="description">How often the dashboard widget should refresh automatically</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Widget Display</th>
                <td>
                    <label>
                        <input type="checkbox" name="show_urgent_only" value="1" />
                        Show only urgent and overdue tickets
                    </label><br>
                    <label>
                        <input type="checkbox" name="enable_notifications" value="1" checked />
                        Enable browser notifications for urgent tickets
                    </label><br>
                    <label>
                        <input type="checkbox" name="enable_sound_alert" value="1" />
                        Play sound alert for new urgent tickets
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">Ticket Priorities</th>
                <td>
                    <p class="description">Define what makes a ticket "urgent" in the widget:</p>
                    <label>
                        <input type="checkbox" name="urgent_high_priority" value="1" checked />
                        High priority tickets
                    </label><br>
                    <label>
                        <input type="checkbox" name="urgent_overdue" value="1" checked />
                        Overdue tickets
                    </label><br>
                    <label>
                        <input type="checkbox" name="urgent_no_reply_24h" value="1" />
                        Tickets without reply for 24+ hours
                    </label>
                </td>
            </tr>
        </table>

        <hr />

        <h2>Setup Instructions</h2>
        <ol>
            <li>Go to <a href="https://api-console.zoho.com/" target="_blank">Zoho API Console</a></li>
            <li>Create a new Client ID (Server-based Application)</li>
            <li>Add redirect URI: <code><?php echo admin_url('admin.php?page=zoho-desk-settings&action=oauth_callback'); ?></code></li>
            <li>Copy the Client ID and Client Secret here</li>
            <li>Get your Organization ID from Zoho Desk Settings</li>
            <li>Save settings and click "Connect to Zoho Desk"</li>
        </ol>
    </div>
    <?php
}