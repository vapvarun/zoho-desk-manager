<?php
/**
 * Tickets List Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Main tickets page
function zdm_tickets_page() {
    $api = new ZDM_Zoho_API();

    // Handle ticket reply
    if (isset($_POST['reply_ticket'])) {
        check_admin_referer('zdm_reply_ticket');

        $ticket_id = sanitize_text_field($_POST['ticket_id']);
        $reply_content = wp_kses_post($_POST['reply_content']);

        if ($api->reply_to_ticket($ticket_id, $reply_content)) {
            echo '<div class="notice notice-success"><p>Reply sent successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to send reply. Please check your connection.</p></div>';
        }
    }

    // Handle status update
    if (isset($_POST['update_status'])) {
        check_admin_referer('zdm_update_status');

        $ticket_id = sanitize_text_field($_POST['ticket_id']);
        $new_status = sanitize_text_field($_POST['new_status']);

        if ($api->update_ticket_status($ticket_id, $new_status)) {
            echo '<div class="notice notice-success"><p>Status updated successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to update status.</p></div>';
        }
    }

    // Check if viewing single ticket
    if (isset($_GET['ticket_id'])) {
        zdm_single_ticket_view($api, $_GET['ticket_id']);
        return;
    }

    // Handle search
    $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $search_type = isset($_GET['search_type']) ? sanitize_text_field($_GET['search_type']) : 'all';

    // Get tickets
    if (!empty($search_query)) {
        $tickets_data = $api->search_tickets($search_query, $search_type);
    } else {
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'Open';
        $tickets_data = $api->get_tickets(array('status' => $status_filter));
    }
    ?>

    <div class="wrap">
        <h1>Zoho Desk Tickets</h1>

        <?php if (!get_option('zdm_access_token')): ?>
            <div class="notice notice-warning">
                <p>Please <a href="<?php echo admin_url('admin.php?page=zoho-desk-settings'); ?>">configure your Zoho Desk connection</a> first.</p>
            </div>
            <?php return; ?>
        <?php endif; ?>

        <!-- Search Form -->
        <div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin: 20px 0;">
            <form method="get" action="">
                <input type="hidden" name="page" value="zoho-desk-manager">
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <input type="text"
                               name="search"
                               value="<?php echo esc_attr($search_query); ?>"
                               placeholder="Search tickets by email, keyword, ticket #, or URL..."
                               style="width: 100%; padding: 8px;">
                    </div>
                    <div>
                        <select name="search_type" style="padding: 8px;">
                            <option value="all" <?php selected($search_type, 'all'); ?>>Smart Search</option>
                            <option value="email" <?php selected($search_type, 'email'); ?>>Customer Email</option>
                            <option value="subject" <?php selected($search_type, 'subject'); ?>>Subject</option>
                            <option value="content" <?php selected($search_type, 'content'); ?>>Content</option>
                            <option value="ticket_number" <?php selected($search_type, 'ticket_number'); ?>>Ticket Number</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="button button-primary">🔍 Search</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="?page=zoho-desk-manager" class="button">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php if (!empty($search_query)): ?>
                <div style="margin-top: 10px; color: #666;">
                    <strong>Search Results for:</strong> "<?php echo esc_html($search_query); ?>"
                    <span style="font-size: 11px; background: #e1e1e1; padding: 2px 6px; border-radius: 3px;">
                        <?php echo $search_type === 'all' ? 'Smart Search' : ucfirst($search_type); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Status Filter -->
        <div style="margin: 20px 0;">
            <a href="?page=zoho-desk-manager&status=Open" class="button <?php echo $status_filter == 'Open' ? 'button-primary' : ''; ?>">Open</a>
            <a href="?page=zoho-desk-manager&status=On Hold" class="button <?php echo $status_filter == 'On Hold' ? 'button-primary' : ''; ?>">On Hold</a>
            <a href="?page=zoho-desk-manager&status=Closed" class="button <?php echo $status_filter == 'Closed' ? 'button-primary' : ''; ?>">Closed</a>
            <a href="?page=zoho-desk-manager" class="button" onclick="location.reload();" style="float: right;">↻ Refresh</a>
        </div>

        <?php if ($tickets_data && isset($tickets_data['data'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="80">Ticket #</th>
                        <th>Subject</th>
                        <th width="150">Contact</th>
                        <th width="100">Status</th>
                        <th width="100">Priority</th>
                        <th width="150">Created</th>
                        <th width="100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets_data['data'] as $ticket): ?>
                        <tr>
                            <td>#<?php echo esc_html($ticket['ticketNumber']); ?></td>
                            <td>
                                <strong>
                                    <a href="?page=zoho-desk-manager&ticket_id=<?php echo esc_attr($ticket['id']); ?>">
                                        <?php echo esc_html($ticket['subject']); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php echo esc_html($ticket['email'] ?? $ticket['contactId']); ?></td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; background: <?php echo $ticket['status'] == 'Open' ? '#d4f4dd' : '#f0f0f0'; ?>;">
                                    <?php echo esc_html($ticket['status']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($ticket['priority'] ?? 'Normal'); ?></td>
                            <td><?php echo esc_html(date('M d, Y', strtotime($ticket['createdTime']))); ?></td>
                            <td>
                                <a href="?page=zoho-desk-manager&ticket_id=<?php echo esc_attr($ticket['id']); ?>"
                                   class="button button-small">View & Reply</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No tickets found or unable to fetch tickets. Please check your connection.</p>
        <?php endif; ?>
    </div>
    <?php
}

// Single ticket view with reply form
function zdm_single_ticket_view($api, $ticket_id) {
    $ticket = $api->get_ticket($ticket_id);
    $threads = $api->get_ticket_threads($ticket_id);  // Get threads (actual messages)
    $conversations = $api->get_ticket_conversations($ticket_id);
    $comments = $api->get_ticket_comments($ticket_id);

    // Debug mode - show raw API responses if WP_DEBUG is on
    if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
        echo '<!-- DEBUG: Ticket ID: ' . esc_html($ticket_id) . ' -->';
        echo '<!-- DEBUG: Threads count: ' . (isset($threads['data']) ? count($threads['data']) : 0) . ' -->';
        echo '<!-- DEBUG: Conversations count: ' . (isset($conversations['data']) ? count($conversations['data']) : 0) . ' -->';
        echo '<!-- DEBUG: Comments count: ' . (isset($comments['data']) ? count($comments['data']) : 0) . ' -->';

        // Show debug panel if requested
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px 0; border: 2px solid #333;">';
            echo '<h3>Debug Information:</h3>';
            echo '<h4>Threads API Response (/tickets/id/threads - ACTUAL MESSAGES):</h4>';
            echo '<pre style="background: yellow; padding: 10px; overflow: auto; max-height: 300px;">';
            print_r($threads);
            echo '</pre>';
            echo '<h4>Conversations API Response:</h4>';
            echo '<pre style="background: white; padding: 10px; overflow: auto; max-height: 300px;">';
            print_r($conversations);
            echo '</pre>';
            echo '<h4>Comments API Response:</h4>';
            echo '<pre style="background: white; padding: 10px; overflow: auto; max-height: 300px;">';
            print_r($comments);
            echo '</pre>';
            echo '</div>';
        }
    }

    if (!$ticket) {
        echo '<div class="notice notice-error"><p>Unable to load ticket details.</p></div>';
        return;
    }

    // Use threads as primary source since they contain actual message content
    $all_messages = array();

    // PRIORITY 1: Add threads (these are the actual messages according to Zoho docs)
    if (isset($threads['data']) && is_array($threads['data'])) {
        foreach ($threads['data'] as $thread) {
            $thread['message_type'] = 'thread';
            $thread['message_source'] = 'threads_api';
            // Threads have different field names
            $thread['createdTime'] = $thread['createdTime'] ?? $thread['postedTime'] ?? '';
            $thread['content'] = $thread['content'] ??
                                $thread['plainText'] ??
                                $thread['richText'] ??
                                $thread['summary'] ?? '';
            $all_messages[] = $thread;
        }
    }

    // PRIORITY 2: If no threads, try conversations
    if (empty($all_messages) && isset($conversations['data']) && is_array($conversations['data'])) {
        foreach ($conversations['data'] as $conv) {
            $conv['message_type'] = 'conversation';
            $conv['message_source'] = 'conversations_api';
            $all_messages[] = $conv;
        }
    }

    // PRIORITY 3: Add comments as supplemental
    if (isset($comments['data']) && is_array($comments['data'])) {
        foreach ($comments['data'] as $comment) {
            $comment['message_type'] = 'comment';
            $comment['message_source'] = 'comments_api';
            $comment['createdTime'] = $comment['commentedTime'] ?? $comment['createdTime'];
            $comment['content'] = $comment['content'] ?? $comment['comment'] ?? '';
            $all_messages[] = $comment;
        }
    }

    // Sort all messages by date
    if (!empty($all_messages)) {
        usort($all_messages, function($a, $b) {
            return strtotime($a['createdTime']) - strtotime($b['createdTime']);
        });
    }
    ?>

    <div class="wrap">
        <h1>
            Ticket #<?php echo esc_html($ticket['ticketNumber']); ?>
            <a href="?page=zoho-desk-manager" class="page-title-action">← Back to List</a>
        </h1>

        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ddd;">
            <h2><?php echo esc_html($ticket['subject']); ?></h2>

            <div style="margin: 10px 0;">
                <strong>From:</strong> <?php echo esc_html($ticket['email'] ?? 'Unknown'); ?> |
                <strong>Status:</strong> <?php echo esc_html($ticket['status']); ?> |
                <strong>Priority:</strong> <?php echo esc_html($ticket['priority'] ?? 'Normal'); ?> |
                <strong>Created:</strong> <?php echo esc_html(date('M d, Y H:i', strtotime($ticket['createdTime']))); ?>
            </div>

            <hr />

            <!-- Ticket Details -->
            <div style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                <h3>Ticket Information:</h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <div><strong>Contact Name:</strong> <?php echo esc_html($ticket['contact']['firstName'] ?? '') . ' ' . esc_html($ticket['contact']['lastName'] ?? ''); ?></div>
                    <div><strong>Email:</strong> <?php echo esc_html($ticket['email'] ?? $ticket['contact']['email'] ?? 'N/A'); ?></div>
                    <div><strong>Phone:</strong> <?php echo esc_html($ticket['phone'] ?? $ticket['contact']['phone'] ?? 'N/A'); ?></div>
                    <div><strong>Channel:</strong> <?php echo esc_html($ticket['channel'] ?? 'N/A'); ?></div>
                    <div><strong>Department:</strong> <?php echo esc_html($ticket['departmentId'] ?? 'N/A'); ?></div>
                    <div><strong>Category:</strong> <?php echo esc_html($ticket['category'] ?? 'N/A'); ?></div>
                    <div><strong>Due Date:</strong> <?php echo $ticket['dueDate'] ? esc_html(date('M d, Y', strtotime($ticket['dueDate']))) : 'N/A'; ?></div>
                    <div><strong>Assigned To:</strong> <?php echo esc_html($ticket['assignee']['firstName'] ?? 'Unassigned') . ' ' . esc_html($ticket['assignee']['lastName'] ?? ''); ?></div>
                </div>
            </div>

            <!-- Original ticket description -->
            <div style="margin: 20px 0;">
                <h3>Initial Message:</h3>
                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                        <strong style="color: #0073aa;"><?php echo esc_html($ticket['contact']['firstName'] ?? 'Customer') . ' ' . esc_html($ticket['contact']['lastName'] ?? ''); ?></strong>
                        <span style="color: #666; font-size: 12px; float: right;">
                            <?php echo esc_html(date('M d, Y at H:i', strtotime($ticket['createdTime']))); ?>
                        </span>
                    </div>
                    <div style="line-height: 1.6;">
                        <?php
                        $description = $ticket['description'] ?? 'No description available';
                        // If description contains HTML, display it properly
                        if ($description != strip_tags($description)) {
                            echo wp_kses_post($description);
                        } else {
                            // Convert plain text line breaks to HTML
                            echo nl2br(esc_html($description));
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Conversations -->
            <?php if (!empty($all_messages)): ?>
                <h3>Complete Conversation Thread (<?php echo count($all_messages); ?> messages):</h3>

                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    <p style="font-size: 11px; color: #666;">
                        Debug: <a href="?page=zoho-desk-manager&ticket_id=<?php echo esc_attr($ticket_id); ?>&debug=1">Show API responses</a>
                    </p>
                <?php endif; ?>

                <?php
                foreach ($all_messages as $index => $conv):
                    $isCustomer = isset($conv['author']['type']) && $conv['author']['type'] == 'END_USER';
                    $authorName = $conv['author']['name'] ?? ($conv['author']['firstName'] ?? '') . ' ' . ($conv['author']['lastName'] ?? '');
                ?>
                    <div style="margin: 15px 0; padding: 20px; background: <?php echo $isCustomer ? '#f0f8ff' : '#f5f5f5'; ?>; border-left: 4px solid <?php echo $isCustomer ? '#0073aa' : '#666'; ?>; border-radius: 5px;">
                        <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
                            <strong style="color: <?php echo $isCustomer ? '#0073aa' : '#333'; ?>;">
                                <?php echo esc_html($authorName ?: 'Unknown'); ?>
                                <?php if (!$isCustomer): ?>
                                    <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 5px;">SUPPORT</span>
                                <?php endif; ?>
                            </strong>
                            <span style="color: #666; font-size: 12px; float: right;">
                                <?php echo esc_html(date('M d, Y at H:i', strtotime($conv['createdTime']))); ?>
                                <?php if ($conv['type'] == 'private'): ?>
                                    <span style="background: #ffb900; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">INTERNAL</span>
                                <?php endif; ?>
                                <?php if (isset($conv['message_source'])): ?>
                                    <span style="background: #17a2b8; color: white; padding: 2px 6px; border-radius: 3px; font-size: 9px; margin-left: 5px;"><?php echo strtoupper(str_replace('_api', '', $conv['message_source'])); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div style="line-height: 1.6;">
                            <?php
                            // Try different content field names
                            $content = $conv['content'] ??
                                      $conv['comment'] ??
                                      $conv['description'] ??
                                      $conv['summary'] ??
                                      $conv['plainText'] ??
                                      $conv['htmlContent'] ?? '';

                            // Debug: Show what fields are available
                            if (empty($content) && defined('WP_DEBUG') && WP_DEBUG) {
                                echo '<small style="color: red;">Debug - Available fields: ' . implode(', ', array_keys($conv)) . '</small><br>';
                                echo '<small style="color: red;">Message type: ' . ($conv['message_type'] ?? 'unknown') . '</small><br>';
                            }

                            // Display content
                            if (!empty($content)) {
                                // Check if content is HTML or plain text
                                if ($content != strip_tags($content)) {
                                    echo wp_kses_post($content);
                                } else {
                                    echo nl2br(esc_html($content));
                                }
                            } else {
                                echo '<em style="color: #999;">No content available for this message.</em>';
                            }
                            ?>
                        </div>

                        <?php if (isset($conv['threads']) && count($conv['threads']) > 0): ?>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ccc;">
                                <strong style="font-size: 12px; color: #666;">Thread Replies:</strong>
                                <?php foreach ($conv['threads'] as $thread): ?>
                                    <div style="margin: 10px 0 10px 20px; padding: 10px; background: rgba(255,255,255,0.5); border-left: 2px solid #ddd;">
                                        <div style="font-size: 12px; color: #666; margin-bottom: 5px;">
                                            <strong><?php echo esc_html($thread['author']['name'] ?? 'Unknown'); ?></strong> -
                                            <?php echo esc_html(date('M d, Y H:i', strtotime($thread['createdTime']))); ?>
                                        </div>
                                        <div><?php echo wp_kses_post($thread['content'] ?? ''); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="padding: 15px; background: #f9f9f9; border-left: 3px solid #666;">No replies yet.</p>
            <?php endif; ?>

            <!-- Draft Response Section -->
            <div id="zdm-draft-section" style="margin-top: 30px; padding: 20px; background: #e8f4f8; border: 2px solid #0073aa; border-radius: 5px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0;">
                        <span class="dashicons dashicons-edit" style="color: #0073aa;"></span>
                        Draft Response
                    </h3>
                    <div id="zdm-draft-actions">
                        <button type="button" id="zdm-generate-ai-draft" class="button button-secondary"
                                data-ticket-id="<?php echo esc_attr($ticket['id']); ?>">
                            <span class="dashicons dashicons-admin-generic"></span>
                            Generate AI Draft
                        </button>
                        <button type="button" id="zdm-use-template" class="button button-secondary">
                            <span class="dashicons dashicons-format-aside"></span>
                            Use Template
                        </button>
                        <button type="button" id="zdm-load-saved-draft" class="button button-secondary">
                            <span class="dashicons dashicons-download"></span>
                            Load Saved Draft
                        </button>
                        <button type="button" id="zdm-clear-draft" class="button button-link-delete">
                            <span class="dashicons dashicons-trash"></span>
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Draft Status -->
                <div id="zdm-draft-status" style="margin-bottom: 10px; padding: 10px; background: #fff; border-radius: 3px; display: none;">
                    <span class="zdm-draft-status-text"></span>
                    <span class="zdm-draft-timestamp" style="float: right; color: #666; font-size: 12px;"></span>
                </div>

                <!-- AI Options (shown when generating) -->
                <div id="zdm-ai-options" style="display: none; margin-bottom: 15px; padding: 15px; background: white; border-radius: 3px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <label for="zdm-response-type">Response Type:</label>
                            <select id="zdm-response-type" class="regular-text">
                                <option value="solution">Solution/Resolution</option>
                                <option value="follow_up">Follow-up</option>
                                <option value="clarification">Request Clarification</option>
                                <option value="escalation">Escalation</option>
                                <option value="closing">Closing/Resolved</option>
                            </select>
                        </div>
                        <div>
                            <label for="zdm-response-tone">Tone:</label>
                            <select id="zdm-response-tone" class="regular-text">
                                <option value="professional">Professional</option>
                                <option value="friendly">Friendly</option>
                                <option value="formal">Formal</option>
                                <option value="technical">Technical</option>
                                <option value="empathetic">Empathetic</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top: 10px;">
                        <button type="button" id="zdm-generate-with-options" class="button button-primary">
                            Generate Draft
                        </button>
                        <button type="button" id="zdm-cancel-ai-options" class="button">
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- Template Selection (shown when using templates) -->
                <div id="zdm-template-options" style="display: none; margin-bottom: 15px; padding: 15px; background: white; border-radius: 3px;">
                    <?php
                    // Include template manager and get templates
                    require_once ZDM_PLUGIN_PATH . 'includes/class-template-manager.php';
                    $templates = ZDM_Template_Manager::get_templates();
                    $categories = ZDM_Template_Manager::get_categories();
                    $suggestions = ZDM_Template_Manager::suggest_templates($ticket, $threads);
                    ?>

                    <?php if (!empty($suggestions)): ?>
                        <div style="margin-bottom: 15px; padding: 10px; background: #fffbcc; border-left: 4px solid #ffb900; border-radius: 3px;">
                            <strong>💡 Suggested Templates:</strong>
                            <div style="margin-top: 8px;">
                                <?php foreach ($suggestions as $suggestion): ?>
                                    <button type="button" class="button button-small zdm-template-suggestion"
                                            data-template="<?php echo esc_attr($suggestion['key']); ?>"
                                            style="margin-right: 8px; margin-bottom: 4px;">
                                        <?php echo esc_html($suggestion['name']); ?>
                                        <small style="opacity: 0.7;">(<?php echo esc_html($suggestion['reason']); ?>)</small>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: 200px 1fr; gap: 15px;">
                        <div>
                            <label for="zdm-template-category">Category:</label>
                            <select id="zdm-template-category" style="width: 100%; padding: 8px; margin-bottom: 10px;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category); ?>">
                                        <?php echo esc_html(ucfirst($category)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="zdm-template-select">Template:</label>
                            <select id="zdm-template-select" style="width: 100%; padding: 8px;">
                                <option value="">Select a template...</option>
                                <?php foreach ($templates as $key => $template): ?>
                                    <option value="<?php echo esc_attr($key); ?>"
                                            data-category="<?php echo esc_attr($template['category']); ?>">
                                        <?php echo esc_html($template['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Preview:</label>
                            <div id="zdm-template-preview" style="padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 3px; min-height: 100px; font-family: monospace; font-size: 12px; white-space: pre-wrap;">
                                Select a template to see the preview...
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 15px;">
                        <button type="button" id="zdm-use-selected-template" class="button button-primary" disabled>
                            Use This Template
                        </button>
                        <button type="button" id="zdm-cancel-template" class="button">
                            Cancel
                        </button>
                        <span style="margin-left: 15px; color: #666; font-size: 12px;">
                            Variables like {customer_name} will be automatically replaced
                        </span>
                    </div>
                </div>

                <!-- Draft Content Area -->
                <div id="zdm-draft-content-wrapper">
                    <?php
                    // Check if there's a saved draft
                    $saved_draft = get_transient('zdm_draft_' . $ticket['id']);
                    $draft_meta = get_transient('zdm_draft_meta_' . $ticket['id']);
                    ?>
                    <textarea id="zdm-draft-content" rows="12" style="width: 100%; padding: 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; line-height: 1.5; border: 1px solid #ddd; border-radius: 3px;"><?php echo $saved_draft ? esc_textarea($saved_draft) : ''; ?></textarea>

                    <?php if ($saved_draft && $draft_meta): ?>
                        <script>
                            jQuery(document).ready(function($) {
                                $('#zdm-draft-status').show();
                                $('.zdm-draft-status-text').html('<span style="color: #46b450;">✓ Saved draft loaded</span>');
                                $('.zdm-draft-timestamp').text('Generated: <?php echo esc_js($draft_meta['generated_at']); ?>');
                            });
                        </script>
                    <?php endif; ?>
                </div>

                <!-- Draft Action Buttons -->
                <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <button type="button" id="zdm-save-draft" class="button button-secondary">
                            <span class="dashicons dashicons-saved"></span>
                            Save Draft
                        </button>
                        <button type="button" id="zdm-improve-draft" class="button button-secondary">
                            <span class="dashicons dashicons-admin-customizer"></span>
                            Improve Draft
                        </button>
                        <button type="button" id="zdm-copy-draft" class="button button-secondary">
                            <span class="dashicons dashicons-admin-page"></span>
                            Copy to Reply
                        </button>
                    </div>
                    <div id="zdm-draft-word-count" style="color: #666; font-size: 12px;">
                        Words: <span class="word-count">0</span> | Characters: <span class="char-count">0</span>
                    </div>
                </div>

                <!-- AI Suggestions (shown after generation) -->
                <div id="zdm-ai-suggestions" style="display: none; margin-top: 15px; padding: 15px; background: #fff9e6; border-left: 4px solid #ffb900; border-radius: 3px;">
                    <h4 style="margin-top: 0; color: #826200;">
                        <span class="dashicons dashicons-lightbulb"></span>
                        AI Suggestions
                    </h4>
                    <ul class="zdm-suggestions-list" style="margin: 10px 0; padding-left: 20px;">
                    </ul>
                </div>
            </div>

            <!-- Reply Form -->
            <div style="margin-top: 30px; padding: 20px; background: #f6f7f7;">
                <h3>Send Reply:</h3>
                <form method="post" action="?page=zoho-desk-manager" id="zdm-reply-form">
                    <?php wp_nonce_field('zdm_reply_ticket'); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket['id']); ?>" />

                    <div style="margin-bottom: 15px;">
                        <?php
                        wp_editor('', 'reply_content', array(
                            'textarea_name' => 'reply_content',
                            'textarea_rows' => 10,
                            'media_buttons' => false,
                            'teeny' => true,
                            'quicktags' => false
                        ));
                        ?>
                    </div>

                    <input type="submit" name="reply_ticket" class="button button-primary" value="Send Reply" />
                    <button type="button" id="zdm-send-and-close" class="button button-secondary">
                        Send & Close Ticket
                    </button>
                </form>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ddd;">
                <h4>Quick Actions:</h4>
                <form method="post" action="?page=zoho-desk-manager" style="display: inline;">
                    <?php wp_nonce_field('zdm_update_status'); ?>
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket['id']); ?>" />

                    <select name="new_status">
                        <option value="Open" <?php selected($ticket['status'], 'Open'); ?>>Open</option>
                        <option value="On Hold" <?php selected($ticket['status'], 'On Hold'); ?>>On Hold</option>
                        <option value="Closed" <?php selected($ticket['status'], 'Closed'); ?>>Closed</option>
                    </select>

                    <input type="submit" name="update_status" class="button" value="Update Status" />
                </form>
            </div>
        </div>
    </div>
    <?php
}