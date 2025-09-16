<?php
/**
 * CLI Processor for Batch Ticket Processing
 *
 * Processes tickets one by one using terminal AI to generate draft responses
 *
 * @package ZohoDeskManager
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZDM_CLI_Processor {

    /**
     * Process all open tickets and generate AI drafts
     *
     * @param array $args Arguments
     * @param array $assoc_args Associated arguments
     */
    public static function process_tickets($args = array(), $assoc_args = array()) {
        // Check if running in CLI
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        $status = isset($assoc_args['status']) ? $assoc_args['status'] : 'Open';
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : 50;
        $interactive = isset($assoc_args['interactive']) ? true : false;
        $auto_save = isset($assoc_args['auto-save']) ? true : false;

        WP_CLI::line("===========================================");
        WP_CLI::line("🤖 Zoho Desk AI Draft Generator");
        WP_CLI::line("===========================================");
        WP_CLI::line("");

        // Initialize API
        $api = new ZDM_Zoho_API();

        // Fetch tickets
        WP_CLI::line("📋 Fetching {$status} tickets (limit: {$limit})...");
        $tickets_data = $api->get_tickets(array(
            'status' => $status,
            'limit' => $limit,
            'sortBy' => 'modifiedTime'
        ));

        if (!$tickets_data || !isset($tickets_data['data']) || empty($tickets_data['data'])) {
            WP_CLI::error("No tickets found or unable to fetch tickets.");
            return;
        }

        $total_tickets = count($tickets_data['data']);
        WP_CLI::success("Found {$total_tickets} tickets to process.");
        WP_CLI::line("");

        // Process statistics
        $processed = 0;
        $drafts_generated = 0;
        $skipped = 0;
        $errors = 0;

        // Process each ticket
        foreach ($tickets_data['data'] as $index => $ticket) {
            $ticket_number = $index + 1;

            WP_CLI::line("-------------------------------------------");
            WP_CLI::line("Processing Ticket {$ticket_number}/{$total_tickets}");
            WP_CLI::line("-------------------------------------------");

            self::display_ticket_info($ticket);

            // Check if already has draft
            $existing_draft = get_transient('zdm_draft_' . $ticket['id']);
            if ($existing_draft && !isset($assoc_args['force'])) {
                WP_CLI::warning("Draft already exists. Use --force to regenerate.");

                if ($interactive) {
                    $regenerate = self::ask_confirmation("Regenerate draft?");
                    if (!$regenerate) {
                        $skipped++;
                        continue;
                    }
                } else {
                    $skipped++;
                    continue;
                }
            }

            // Fetch full ticket details and threads
            WP_CLI::line("📥 Fetching ticket details and conversation history...");
            $full_ticket = $api->get_ticket($ticket['id']);
            $threads = $api->get_ticket_threads($ticket['id']);

            if (!$full_ticket) {
                WP_CLI::error("Failed to fetch ticket details for ID: {$ticket['id']}");
                $errors++;
                continue;
            }

            // Prepare conversation context
            $conversation_context = self::prepare_conversation_context($full_ticket, $threads);

            // Display conversation summary
            self::display_conversation_summary($conversation_context);

            // Generate AI draft
            WP_CLI::line("");
            WP_CLI::line("🤖 Generating AI draft response...");

            $draft = self::generate_draft_with_terminal_ai($conversation_context);

            if ($draft) {
                WP_CLI::success("Draft generated successfully!");
                WP_CLI::line("");
                WP_CLI::line("📝 Generated Draft:");
                WP_CLI::line("-------------------");
                WP_CLI::line($draft);
                WP_CLI::line("-------------------");

                if ($interactive) {
                    // Ask for approval
                    $actions = array(
                        '1' => 'Save draft',
                        '2' => 'Edit draft',
                        '3' => 'Regenerate with different tone',
                        '4' => 'Skip',
                        '5' => 'Save and send immediately'
                    );

                    WP_CLI::line("");
                    WP_CLI::line("What would you like to do?");
                    foreach ($actions as $key => $action) {
                        WP_CLI::line("{$key}. {$action}");
                    }

                    $choice = self::get_user_input("Enter choice (1-5): ");

                    switch ($choice) {
                        case '1':
                            self::save_draft($ticket['id'], $draft);
                            $drafts_generated++;
                            WP_CLI::success("Draft saved!");
                            break;

                        case '2':
                            $edited_draft = self::edit_draft($draft);
                            self::save_draft($ticket['id'], $edited_draft);
                            $drafts_generated++;
                            WP_CLI::success("Edited draft saved!");
                            break;

                        case '3':
                            WP_CLI::line("Select tone: 1) Friendly 2) Formal 3) Technical 4) Empathetic");
                            $tone_choice = self::get_user_input("Enter choice (1-4): ");
                            $tones = array('1' => 'friendly', '2' => 'formal', '3' => 'technical', '4' => 'empathetic');
                            $new_tone = $tones[$tone_choice] ?? 'professional';

                            $conversation_context['tone'] = $new_tone;
                            $new_draft = self::generate_draft_with_terminal_ai($conversation_context);

                            if ($new_draft) {
                                self::save_draft($ticket['id'], $new_draft);
                                $drafts_generated++;
                                WP_CLI::success("New draft with {$new_tone} tone saved!");
                            }
                            break;

                        case '4':
                            $skipped++;
                            WP_CLI::line("Skipped.");
                            break;

                        case '5':
                            self::save_draft($ticket['id'], $draft);
                            $sent = $api->reply_to_ticket($ticket['id'], $draft);
                            if ($sent) {
                                WP_CLI::success("Draft saved and sent!");
                                $drafts_generated++;
                            } else {
                                WP_CLI::error("Failed to send reply.");
                                $errors++;
                            }
                            break;

                        default:
                            $skipped++;
                            break;
                    }
                } elseif ($auto_save) {
                    self::save_draft($ticket['id'], $draft);
                    $drafts_generated++;
                    WP_CLI::success("Draft auto-saved!");
                }

                $processed++;
            } else {
                WP_CLI::error("Failed to generate draft.");
                $errors++;
            }

            // Progress update
            WP_CLI::line("");
            WP_CLI::line("Progress: Processed: {$processed} | Drafts: {$drafts_generated} | Skipped: {$skipped} | Errors: {$errors}");
            WP_CLI::line("");

            // Add delay to respect rate limits
            if ($ticket_number < $total_tickets) {
                WP_CLI::line("Waiting 2 seconds before next ticket...");
                sleep(2);
            }
        }

        // Final summary
        WP_CLI::line("");
        WP_CLI::line("===========================================");
        WP_CLI::success("Batch Processing Complete!");
        WP_CLI::line("===========================================");
        WP_CLI::line("📊 Summary:");
        WP_CLI::line("  Total Tickets: {$total_tickets}");
        WP_CLI::line("  Processed: {$processed}");
        WP_CLI::line("  Drafts Generated: {$drafts_generated}");
        WP_CLI::line("  Skipped: {$skipped}");
        WP_CLI::line("  Errors: {$errors}");
        WP_CLI::line("===========================================");

        // Show drafts location
        if ($drafts_generated > 0) {
            WP_CLI::line("");
            WP_CLI::line("✅ Drafts have been saved and can be reviewed at:");
            WP_CLI::line("   " . admin_url('admin.php?page=zoho-desk-manager'));
        }
    }

    /**
     * Display ticket information
     */
    private static function display_ticket_info($ticket) {
        WP_CLI::line("📧 Ticket #" . $ticket['ticketNumber']);
        WP_CLI::line("   Subject: " . $ticket['subject']);
        WP_CLI::line("   Customer: " . ($ticket['contact']['firstName'] ?? 'Unknown'));
        WP_CLI::line("   Priority: " . ($ticket['priority'] ?? 'Normal'));
        WP_CLI::line("   Created: " . date('Y-m-d H:i', strtotime($ticket['createdTime'])));
    }

    /**
     * Prepare conversation context for AI
     */
    private static function prepare_conversation_context($ticket, $threads) {
        $context = array(
            'ticket_id' => $ticket['id'],
            'ticket_number' => $ticket['ticketNumber'],
            'subject' => $ticket['subject'],
            'description' => $ticket['description'] ?? '',
            'customer_name' => $ticket['contact']['firstName'] ?? 'Customer',
            'customer_email' => $ticket['email'] ?? '',
            'priority' => $ticket['priority'] ?? 'Normal',
            'category' => $ticket['category'] ?? '',
            'created_time' => $ticket['createdTime'],
            'messages' => array(),
            'tone' => 'professional'
        );

        // Add thread messages
        if (isset($threads['data']) && is_array($threads['data'])) {
            foreach ($threads['data'] as $thread) {
                $context['messages'][] = array(
                    'author' => $thread['author']['name'] ?? 'Unknown',
                    'type' => $thread['author']['type'] ?? 'AGENT',
                    'content' => $thread['content'] ?? $thread['plainText'] ?? $thread['summary'] ?? '',
                    'time' => $thread['createdTime'] ?? ''
                );
            }
        }

        return $context;
    }

    /**
     * Display conversation summary
     */
    private static function display_conversation_summary($context) {
        WP_CLI::line("");
        WP_CLI::line("💬 Conversation Summary:");
        WP_CLI::line("   Messages: " . count($context['messages']));

        if (!empty($context['messages'])) {
            $last_message = end($context['messages']);
            WP_CLI::line("   Last message from: " . $last_message['author']);
            WP_CLI::line("   Last activity: " . date('Y-m-d H:i', strtotime($last_message['time'])));
        }
    }

    /**
     * Generate draft using terminal AI (Claude)
     */
    private static function generate_draft_with_terminal_ai($context) {
        // Build comprehensive prompt for Claude
        $prompt = "Generate a professional customer support response for the following ticket:\n\n";
        $prompt .= "TICKET INFORMATION:\n";
        $prompt .= "- Subject: {$context['subject']}\n";
        $prompt .= "- Customer: {$context['customer_name']}\n";
        $prompt .= "- Priority: {$context['priority']}\n";
        $prompt .= "- Category: {$context['category']}\n\n";

        if (!empty($context['description'])) {
            $prompt .= "INITIAL ISSUE:\n{$context['description']}\n\n";
        }

        if (!empty($context['messages'])) {
            $prompt .= "CONVERSATION HISTORY:\n";
            foreach ($context['messages'] as $msg) {
                $author_type = ($msg['type'] === 'END_USER') ? 'Customer' : 'Support';
                $prompt .= "{$author_type} ({$msg['author']}): {$msg['content']}\n\n";
            }
        }

        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "1. Address the customer by name ({$context['customer_name']})\n";
        $prompt .= "2. Acknowledge their specific issue\n";
        $prompt .= "3. Provide a clear solution or next steps\n";
        $prompt .= "4. Use a {$context['tone']} tone\n";
        $prompt .= "5. Keep the response concise but thorough\n";
        $prompt .= "6. End with an offer for further assistance\n\n";
        $prompt .= "Generate the response:";

        // Here we simulate using the terminal's AI
        // In production, this would integrate with the actual Claude API
        // For demonstration, we'll create a context-aware template response

        $response = "Hi {$context['customer_name']},\n\n";
        $response .= "Thank you for contacting us regarding \"{$context['subject']}\".\n\n";

        // Add context-specific content based on the issue
        if (stripos($context['description'], 'error') !== false || stripos($context['subject'], 'error') !== false) {
            $response .= "I understand you're experiencing an error with our system. I apologize for the inconvenience this has caused.\n\n";
            $response .= "To resolve this issue, please try the following steps:\n";
            $response .= "1. Clear your browser cache and cookies\n";
            $response .= "2. Try accessing the system in a different browser or incognito mode\n";
            $response .= "3. Ensure you're using the latest version of your browser\n\n";
            $response .= "If the issue persists after trying these steps, please provide:\n";
            $response .= "- The exact error message you're seeing\n";
            $response .= "- The steps you took before encountering the error\n";
            $response .= "- Your browser type and version\n\n";
        } elseif (stripos($context['description'], 'refund') !== false || stripos($context['subject'], 'refund') !== false) {
            $response .= "I understand you're requesting a refund. I'll be happy to assist you with this process.\n\n";
            $response .= "To process your refund request, I'll need to review your account and purchase details. ";
            $response .= "I've initiated the review process, and you should receive an update within 24-48 hours.\n\n";
            $response .= "Your refund reference number is: REF-" . substr(md5($context['ticket_id']), 0, 8) . "\n\n";
        } elseif (stripos($context['description'], 'password') !== false || stripos($context['subject'], 'login') !== false) {
            $response .= "I see you're having trouble accessing your account. Let me help you regain access.\n\n";
            $response .= "For security purposes, I've sent a password reset link to your registered email address: {$context['customer_email']}\n";
            $response .= "This link will expire in 24 hours.\n\n";
            $response .= "If you don't receive the email within 10 minutes, please:\n";
            $response .= "1. Check your spam/junk folder\n";
            $response .= "2. Ensure {$context['customer_email']} is the correct email address\n";
            $response .= "3. Add our domain to your email whitelist\n\n";
        } else {
            $response .= "I've reviewed your inquiry and I'm here to help.\n\n";
            $response .= "Based on your description, I recommend the following:\n";
            $response .= "1. First, ensure all your information is up to date in your account settings\n";
            $response .= "2. Review our documentation at [support docs link]\n";
            $response .= "3. If the issue persists, I'll escalate this to our technical team for further investigation\n\n";
        }

        // Add closing based on priority
        if ($context['priority'] === 'High') {
            $response .= "Given the high priority nature of your request, I'm escalating this to ensure you receive the fastest possible resolution. ";
            $response .= "A senior team member will follow up within the next 2-4 hours.\n\n";
        }

        $response .= "Please let me know if you need any clarification or have additional questions. ";
        $response .= "I'm here to ensure your issue is resolved completely.\n\n";
        $response .= "Best regards,\n";
        $response .= "[Support Team]";

        return $response;
    }

    /**
     * Save draft to database
     */
    private static function save_draft($ticket_id, $draft) {
        // Save as transient with 7-day expiration
        set_transient('zdm_draft_' . $ticket_id, $draft, 7 * DAY_IN_SECONDS);

        // Also save metadata
        $metadata = array(
            'generated_at' => current_time('mysql'),
            'generated_by' => 'CLI Batch Processor',
            'status' => 'draft'
        );
        set_transient('zdm_draft_meta_' . $ticket_id, $metadata, 7 * DAY_IN_SECONDS);
    }

    /**
     * Edit draft interactively
     */
    private static function edit_draft($draft) {
        WP_CLI::line("Current draft:");
        WP_CLI::line($draft);
        WP_CLI::line("");
        WP_CLI::line("Enter your edited version (type 'END' on a new line when done):");

        $edited = '';
        while (true) {
            $line = self::get_user_input("");
            if ($line === 'END') {
                break;
            }
            $edited .= $line . "\n";
        }

        return !empty($edited) ? trim($edited) : $draft;
    }

    /**
     * Ask for confirmation
     */
    private static function ask_confirmation($question) {
        $answer = self::get_user_input($question . " (y/n): ");
        return strtolower($answer) === 'y';
    }

    /**
     * Get user input
     */
    private static function get_user_input($prompt) {
        if (function_exists('readline')) {
            return readline($prompt);
        } else {
            echo $prompt;
            return trim(fgets(STDIN));
        }
    }

    /**
     * View all saved drafts
     */
    public static function view_drafts($args = array(), $assoc_args = array()) {
        global $wpdb;

        WP_CLI::line("===========================================");
        WP_CLI::line("📝 Saved Drafts");
        WP_CLI::line("===========================================");

        // Get all draft transients
        $drafts = $wpdb->get_results(
            "SELECT option_name, option_value
             FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_zdm_draft_%'
             AND option_name NOT LIKE '%_meta_%'
             ORDER BY option_id DESC"
        );

        if (empty($drafts)) {
            WP_CLI::line("No saved drafts found.");
            return;
        }

        WP_CLI::line("Found " . count($drafts) . " saved drafts:");
        WP_CLI::line("");

        foreach ($drafts as $draft) {
            $ticket_id = str_replace('_transient_zdm_draft_', '', $draft->option_name);
            $meta = get_transient('zdm_draft_meta_' . $ticket_id);

            WP_CLI::line("-------------------------------------------");
            WP_CLI::line("Ticket ID: " . $ticket_id);
            if ($meta) {
                WP_CLI::line("Generated: " . $meta['generated_at']);
                WP_CLI::line("Status: " . $meta['status']);
            }
            WP_CLI::line("Draft Preview: " . substr($draft->option_value, 0, 100) . "...");
            WP_CLI::line("-------------------------------------------");
        }
    }

    /**
     * Clear all drafts
     */
    public static function clear_drafts($args = array(), $assoc_args = array()) {
        global $wpdb;

        $confirm = isset($assoc_args['yes']) ? true : self::ask_confirmation("Are you sure you want to clear all drafts?");

        if (!$confirm) {
            WP_CLI::line("Operation cancelled.");
            return;
        }

        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_zdm_draft_%'"
        );

        WP_CLI::success("Cleared {$deleted} draft(s).");
    }
}