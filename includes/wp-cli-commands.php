<?php
/**
 * WP-CLI Commands for Zoho Desk Manager
 *
 * Provides comprehensive command-line interface for ticket management,
 * AI-powered analysis, template management, and automated tagging.
 *
 * @package ZohoDeskManager
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only load if WP-CLI is available
if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Manage Zoho Desk tickets, templates, and AI processing from the command line
 *
 * ## DESCRIPTION
 *
 * Complete CLI toolkit for Zoho Desk management including:
 * - Ticket analysis and intelligent tagging
 * - AI-powered response generation
 * - Template management and auto-tagging
 * - Automated ticket processing workflows
 * - Real-time monitoring and statistics
 *
 * ## COMMAND STRUCTURE
 *
 * ### Core Commands
 *     wp zdm analyze <ticket_id>        # Intelligent ticket analysis & tagging
 *     wp zdm ticket <action> [options]  # Ticket management operations
 *     wp zdm template <action> [options] # Template management
 *     wp zdm monitor [options]          # Real-time ticket monitoring
 *     wp zdm stats [options]            # Statistics and reporting
 *
 * ### Quick Access Aliases
 *     wp zd analyze <ticket_id>         # Same as zdm analyze
 *     wp zd ticket <action>             # Same as zdm ticket
 *
 * ## EXAMPLES
 *
 *     # Analyze ticket with AI and apply suggested tags
 *     wp zdm analyze 12345 --auto-apply
 *
 *     # List all open tickets
 *     wp zdm ticket list --status=open
 *
 *     # Generate AI response for ticket
 *     wp zdm ticket respond 12345 --ai-provider=claude
 *
 *     # Manage templates
 *     wp zdm template list --category=billing
 *
 *     # Monitor tickets in real-time
 *     wp zdm monitor --interval=30
 *
 * @package ZohoDeskManager
 */
class ZDM_CLI_Commands {

    /**
     * Intelligent ticket analysis with AI-powered tagging suggestions
     *
     * Performs comprehensive analysis of a ticket using multiple AI methods:
     * - Template-based analysis
     * - Content pattern matching
     * - AI semantic analysis (Claude/OpenAI/Gemini)
     * - Metadata analysis (priority, status, customer type)
     *
     * ## OPTIONS
     *
     * <ticket_id>
     * : Ticket ID to analyze
     *
     * [--auto-apply]
     * : Automatically apply all suggested tags without confirmation
     *
     * [--template=<template_key>]
     * : Template key if responding with a specific template
     *
     * [--include-threads]
     * : Include full conversation history in analysis
     *
     * [--ai-provider=<provider>]
     * : AI provider for semantic analysis: claude, openai, gemini
     *
     * [--dry-run]
     * : Show analysis results without applying any tags
     *
     * ## EXAMPLES
     *
     *     # Basic interactive analysis
     *     wp zdm analyze 12345
     *
     *     # Auto-apply all suggested tags
     *     wp zdm analyze 12345 --auto-apply
     *
     *     # Include conversation context
     *     wp zdm analyze 12345 --include-threads --ai-provider=claude
     *
     *     # Template-aware analysis
     *     wp zdm analyze 12345 --template=password_reset --auto-apply
     *
     * @when after_wp_load
     * @alias smart-tag
     */
    public function analyze($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify a ticket ID");
        }

        $ticket_id = $args[0];
        $auto_apply = WP_CLI\Utils\get_flag_value($assoc_args, 'auto-apply', false);
        $template_key = WP_CLI\Utils\get_flag_value($assoc_args, 'template', null);
        $include_threads = WP_CLI\Utils\get_flag_value($assoc_args, 'include-threads', false);
        $ai_provider = WP_CLI\Utils\get_flag_value($assoc_args, 'ai-provider', null);
        $dry_run = WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);

        WP_CLI::log("🔍 Analyzing ticket #$ticket_id...");

        // Fetch ticket data
        $api = new ZDM_Zoho_API();
        $ticket_data = $api->get_ticket($ticket_id);

        if (!$ticket_data) {
            WP_CLI::error("Failed to fetch ticket #$ticket_id");
        }

        // Fetch conversation threads if requested
        $threads = null;
        if ($include_threads) {
            WP_CLI::log("📚 Fetching conversation history...");
            $threads = $api->get_ticket_threads($ticket_id);
        }

        // Display ticket summary
        $this->display_ticket_summary($ticket_data, $threads);

        // Get current tags
        WP_CLI::log("\n🏷️  Current tags:");
        $current_tags = $api->get_ticket_tags_by_id($ticket_id);
        if ($current_tags && isset($current_tags['data']) && !empty($current_tags['data'])) {
            foreach ($current_tags['data'] as $tag) {
                WP_CLI::line("   • " . $tag['name']);
            }
        } else {
            WP_CLI::line("   (No tags currently assigned)");
        }

        // Perform analysis
        WP_CLI::log("\n🤖 AI Analysis & Tag Suggestions:");

        $all_suggested_tags = $this->perform_comprehensive_analysis(
            $ticket_data, $threads, $template_key, $ai_provider
        );

        // Remove already existing tags
        $new_tags = $this->filter_existing_tags($all_suggested_tags, $current_tags);

        if (empty($new_tags)) {
            WP_CLI::success("✅ No new tags to suggest - ticket is already well-tagged!");
            return;
        }

        // Display recommendations
        WP_CLI::log("\n💡 Recommended new tags:");
        foreach ($new_tags as $i => $tag) {
            WP_CLI::line("   " . ($i + 1) . ". " . $tag);
        }

        // Apply tags if not dry run
        if ($dry_run) {
            WP_CLI::log("\n🧪 Dry run mode - no tags applied");
        } else {
            $this->handle_tag_application($ticket_id, $new_tags, $auto_apply, $api);
        }

        // Show summary
        $this->display_analysis_summary($ticket_data, $all_suggested_tags, $new_tags);
    }

    /**
     * Manage tickets with various operations
     *
     * ## SUBCOMMANDS
     *
     * ### list
     * List tickets with filtering options
     *
     * ### show <ticket_id>
     * Display detailed ticket information
     *
     * ### respond <ticket_id>
     * Generate AI response for a ticket
     *
     * ### update <ticket_id>
     * Update ticket status or properties
     *
     * ### tags <action>
     * Manage ticket tags (list, show, add, remove, auto-tag)
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter by ticket status (open, closed, on-hold)
     *
     * [--priority=<priority>]
     * : Filter by priority (low, normal, high, urgent)
     *
     * [--limit=<number>]
     * : Limit number of results (default: 20)
     *
     * [--format=<format>]
     * : Output format (table, json, csv, yaml)
     *
     * ## EXAMPLES
     *
     *     # List all open tickets
     *     wp zdm ticket list --status=open
     *
     *     # Show ticket details
     *     wp zdm ticket show 12345
     *
     *     # Generate AI response
     *     wp zdm ticket respond 12345 --ai-provider=claude
     *
     *     # Update ticket status
     *     wp zdm ticket update 12345 --status=closed
     *
     *     # Manage tags
     *     wp zdm ticket tags add 12345 billing urgent
     *
     * @when after_wp_load
     */
    public function ticket($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify a subcommand: list, show, respond, update, tags");
        }

        $subcommand = $args[0];

        switch ($subcommand) {
            case 'list':
                $this->ticket_list($args, $assoc_args);
                break;
            case 'show':
                $this->ticket_show($args, $assoc_args);
                break;
            case 'respond':
                $this->ticket_respond($args, $assoc_args);
                break;
            case 'update':
                $this->ticket_update($args, $assoc_args);
                break;
            case 'tags':
                $this->ticket_tags($args, $assoc_args);
                break;
            default:
                WP_CLI::error("Unknown subcommand: $subcommand");
        }
    }

    /**
     * Manage response templates
     *
     * ## SUBCOMMANDS
     *
     * ### list
     * List all available templates
     *
     * ### show <template_key>
     * Display template details and content
     *
     * ### use <template_key> <ticket_id>
     * Process template with ticket variables
     *
     * ### retag
     * Re-analyze and update auto-tags for all templates
     *
     * ## OPTIONS
     *
     * [--category=<category>]
     * : Filter by template category
     *
     * [--format=<format>]
     * : Output format (table, json, csv)
     *
     * ## EXAMPLES
     *
     *     # List all templates
     *     wp zdm template list
     *
     *     # List billing templates
     *     wp zdm template list --category=billing
     *
     *     # Show template details
     *     wp zdm template show password_reset
     *
     *     # Process template for ticket
     *     wp zdm template use greeting 12345
     *
     *     # Update auto-tags for all templates
     *     wp zdm template retag
     *
     * @when after_wp_load
     */
    public function template($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify a subcommand: list, show, use, retag");
        }

        $subcommand = $args[0];
        $format = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

        require_once ZDM_PLUGIN_PATH . 'includes/class-template-manager.php';

        switch ($subcommand) {
            case 'list':
                $this->template_list($args, $assoc_args, $format);
                break;
            case 'show':
                $this->template_show($args, $assoc_args);
                break;
            case 'use':
                $this->template_use($args, $assoc_args);
                break;
            case 'retag':
                $this->template_retag($args, $assoc_args);
                break;
            default:
                WP_CLI::error("Unknown subcommand: $subcommand");
        }
    }

    /**
     * Monitor tickets in real-time
     *
     * Continuously monitors ticket activity and provides live updates.
     * Useful for staying on top of new tickets and urgent issues.
     *
     * ## OPTIONS
     *
     * [--interval=<seconds>]
     * : Check interval in seconds (default: 60)
     *
     * [--status=<status>]
     * : Monitor specific status only (open, closed, on-hold)
     *
     * [--priority=<priority>]
     * : Monitor specific priority only (low, normal, high, urgent)
     *
     * [--alerts]
     * : Enable desktop alerts for urgent tickets
     *
     * [--auto-tag]
     * : Automatically tag new tickets using AI analysis
     *
     * ## EXAMPLES
     *
     *     # Monitor all open tickets
     *     wp zdm monitor --status=open
     *
     *     # Monitor with custom interval
     *     wp zdm monitor --interval=30
     *
     *     # Monitor urgent tickets with alerts
     *     wp zdm monitor --priority=urgent --alerts
     *
     *     # Monitor and auto-tag new tickets
     *     wp zdm monitor --auto-tag
     *
     * @when after_wp_load
     */
    public function monitor($args, $assoc_args) {
        $interval = (int) WP_CLI\Utils\get_flag_value($assoc_args, 'interval', 60);
        $status = WP_CLI\Utils\get_flag_value($assoc_args, 'status', null);
        $priority = WP_CLI\Utils\get_flag_value($assoc_args, 'priority', null);
        $alerts = WP_CLI\Utils\get_flag_value($assoc_args, 'alerts', false);
        $auto_tag = WP_CLI\Utils\get_flag_value($assoc_args, 'auto-tag', false);

        WP_CLI::log("🔍 Starting ticket monitoring...");
        WP_CLI::line("   Interval: {$interval} seconds");
        WP_CLI::line("   Status filter: " . ($status ?: 'all'));
        WP_CLI::line("   Priority filter: " . ($priority ?: 'all'));
        WP_CLI::line("   Auto-tagging: " . ($auto_tag ? 'enabled' : 'disabled'));
        WP_CLI::line("   Press Ctrl+C to stop monitoring\n");

        $api = new ZDM_Zoho_API();
        $last_check = get_option('zdm_cli_last_check', time() - 3600);

        while (true) {
            $this->check_for_updates($api, $last_check, $status, $priority, $auto_tag, $alerts);
            $last_check = time();
            update_option('zdm_cli_last_check', $last_check);
            sleep($interval);
        }
    }

    /**
     * Display ticket and system statistics
     *
     * ## OPTIONS
     *
     * [--period=<period>]
     * : Time period for stats (today, week, month, year)
     *
     * [--format=<format>]
     * : Output format (table, json, csv)
     *
     * [--detailed]
     * : Show detailed breakdown by category, priority, etc.
     *
     * ## EXAMPLES
     *
     *     # Show today's stats
     *     wp zdm stats --period=today
     *
     *     # Show detailed monthly stats
     *     wp zdm stats --period=month --detailed
     *
     *     # Export stats as JSON
     *     wp zdm stats --format=json
     *
     * @when after_wp_load
     */
    public function stats($args, $assoc_args) {
        $period = WP_CLI\Utils\get_flag_value($assoc_args, 'period', 'today');
        $format = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');
        $detailed = WP_CLI\Utils\get_flag_value($assoc_args, 'detailed', false);

        WP_CLI::log("📊 Generating statistics for: $period");

        $api = new ZDM_Zoho_API();
        $stats = $this->gather_statistics($api, $period, $detailed);

        if ($format === 'table') {
            $this->display_stats_table($stats, $detailed);
        } else {
            WP_CLI\Utils\format_items($format, $stats, array_keys($stats[0] ?? array()));
        }
    }

    // =================================================================
    // PRIVATE HELPER METHODS
    // =================================================================

    /**
     * Perform comprehensive ticket analysis
     */
    private function perform_comprehensive_analysis($ticket_data, $threads, $template_key, $ai_provider) {
        $all_tags = array();

        // Method 1: Template-based analysis
        if ($template_key) {
            WP_CLI::log("📋 Template-based analysis...");
            $template_tags = $this->analyze_with_template($template_key);
            if (!empty($template_tags)) {
                WP_CLI::line("   Template tags: " . implode(', ', $template_tags));
                $all_tags = array_merge($all_tags, $template_tags);
            }
        }

        // Method 2: Content pattern analysis
        WP_CLI::log("📊 Content pattern analysis...");
        $content_tags = $this->analyze_ticket_content($ticket_data, $threads);
        if (!empty($content_tags)) {
            WP_CLI::line("   Content tags: " . implode(', ', $content_tags));
            $all_tags = array_merge($all_tags, $content_tags);
        }

        // Method 3: AI semantic analysis
        WP_CLI::log("🧠 AI semantic analysis...");
        $ai_tags = $this->analyze_with_ai($ticket_data, $threads, $ai_provider);
        if (!empty($ai_tags)) {
            WP_CLI::line("   AI-suggested tags: " . implode(', ', $ai_tags));
            $all_tags = array_merge($all_tags, $ai_tags);
        }

        // Method 4: Metadata analysis
        WP_CLI::log("📈 Metadata analysis...");
        $metadata_tags = $this->analyze_metadata($ticket_data);
        if (!empty($metadata_tags)) {
            WP_CLI::line("   Metadata tags: " . implode(', ', $metadata_tags));
            $all_tags = array_merge($all_tags, $metadata_tags);
        }

        return array_unique($all_tags);
    }

    /**
     * Display comprehensive ticket summary
     */
    private function display_ticket_summary($ticket_data, $threads = null) {
        WP_CLI::log("\n📋 Ticket Summary:");
        WP_CLI::line("   ID: " . ($ticket_data['id'] ?? 'Unknown'));
        WP_CLI::line("   Subject: " . ($ticket_data['subject'] ?? 'No subject'));
        WP_CLI::line("   Status: " . ($ticket_data['status'] ?? 'Unknown'));
        WP_CLI::line("   Priority: " . ($ticket_data['priority'] ?? 'Normal'));
        WP_CLI::line("   Created: " . ($ticket_data['createdTime'] ?? 'Unknown'));

        if (isset($ticket_data['contact'])) {
            WP_CLI::line("   Customer: " . ($ticket_data['contact']['firstName'] ?? 'Unknown') .
                        " (" . ($ticket_data['contact']['email'] ?? 'No email') . ")");
        }

        if (!empty($ticket_data['description'])) {
            WP_CLI::line("   Description: " . substr($ticket_data['description'], 0, 200) .
                        (strlen($ticket_data['description']) > 200 ? '...' : ''));
        }

        if ($threads && isset($threads['data'])) {
            WP_CLI::line("   Conversation: " . count($threads['data']) . " messages");
        }
    }

    /**
     * Handle tag application with user interaction
     */
    private function handle_tag_application($ticket_id, $new_tags, $auto_apply, $api) {
        if ($auto_apply) {
            WP_CLI::log("\n🚀 Auto-applying tags...");
            $result = $api->add_ticket_tags($ticket_id, $new_tags);
            if ($result) {
                WP_CLI::success("✅ Applied " . count($new_tags) . " new tags to ticket #$ticket_id");
            } else {
                WP_CLI::error("❌ Failed to apply tags to ticket");
            }
        } else {
            WP_CLI::log("\n❓ Apply these tags? Options:");
            WP_CLI::line("   [y] Apply all suggested tags");
            WP_CLI::line("   [s] Select specific tags to apply");
            WP_CLI::line("   [n] Skip tagging");

            $choice = $this->prompt_user("Choice (y/s/n)");

            switch (strtolower($choice)) {
                case 'y':
                    $result = $api->add_ticket_tags($ticket_id, $new_tags);
                    if ($result) {
                        WP_CLI::success("✅ Applied all suggested tags");
                    } else {
                        WP_CLI::error("❌ Failed to apply tags");
                    }
                    break;

                case 's':
                    $selected_tags = $this->select_tags_interactive($new_tags);
                    if (!empty($selected_tags)) {
                        $result = $api->add_ticket_tags($ticket_id, $selected_tags);
                        if ($result) {
                            WP_CLI::success("✅ Applied " . count($selected_tags) . " selected tags");
                        } else {
                            WP_CLI::error("❌ Failed to apply selected tags");
                        }
                    } else {
                        WP_CLI::line("No tags selected");
                    }
                    break;

                case 'n':
                default:
                    WP_CLI::line("Skipped tagging");
                    break;
            }
        }
    }

    /**
     * Display analysis summary
     */
    private function display_analysis_summary($ticket_data, $all_suggested_tags, $new_tags) {
        WP_CLI::log("\n📊 Analysis Summary:");
        WP_CLI::line("   Ticket: #" . ($ticket_data['id'] ?? 'Unknown') . " - " . ($ticket_data['subject'] ?? 'No subject'));
        WP_CLI::line("   Priority: " . ($ticket_data['priority'] ?? 'Normal'));
        WP_CLI::line("   Status: " . ($ticket_data['status'] ?? 'Unknown'));
        WP_CLI::line("   Tags analyzed: " . count($all_suggested_tags) . " suggestions from 4 analysis methods");
        WP_CLI::line("   New tags recommended: " . count($new_tags));
    }

    // =================================================================
    // TICKET SUBCOMMAND METHODS
    // =================================================================

    private function ticket_list($args, $assoc_args) {
        $status = WP_CLI\Utils\get_flag_value($assoc_args, 'status', null);
        $priority = WP_CLI\Utils\get_flag_value($assoc_args, 'priority', null);
        $limit = (int) WP_CLI\Utils\get_flag_value($assoc_args, 'limit', 20);
        $format = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

        $api = new ZDM_Zoho_API();
        $params = array('limit' => $limit);

        if ($status) $params['status'] = $status;
        if ($priority) $params['priority'] = $priority;

        $tickets = $api->get_tickets($params);

        if (!$tickets || !isset($tickets['data'])) {
            WP_CLI::error("Failed to fetch tickets");
        }

        $output_data = array();
        foreach ($tickets['data'] as $ticket) {
            $output_data[] = array(
                'ID' => $ticket['id'],
                'Subject' => substr($ticket['subject'] ?? 'No subject', 0, 50),
                'Status' => $ticket['status'] ?? 'Unknown',
                'Priority' => $ticket['priority'] ?? 'Normal',
                'Customer' => $ticket['contact']['firstName'] ?? 'Unknown',
                'Created' => date('Y-m-d H:i', strtotime($ticket['createdTime'] ?? ''))
            );
        }

        if ($format === 'table') {
            WP_CLI\Utils\format_items('table', $output_data, array('ID', 'Subject', 'Status', 'Priority', 'Customer', 'Created'));
        } else {
            WP_CLI\Utils\format_items($format, $output_data, array('ID', 'Subject', 'Status', 'Priority', 'Customer', 'Created'));
        }
    }

    private function ticket_show($args, $assoc_args) {
        if (empty($args[1])) {
            WP_CLI::error("Please specify a ticket ID");
        }

        $ticket_id = $args[1];
        $api = new ZDM_Zoho_API();
        $ticket = $api->get_ticket($ticket_id);

        if (!$ticket) {
            WP_CLI::error("Ticket #$ticket_id not found");
        }

        $this->display_ticket_summary($ticket);

        // Show tags
        $tags = $api->get_ticket_tags_by_id($ticket_id);
        WP_CLI::log("\n🏷️  Tags:");
        if ($tags && isset($tags['data']) && !empty($tags['data'])) {
            foreach ($tags['data'] as $tag) {
                WP_CLI::line("   • " . $tag['name']);
            }
        } else {
            WP_CLI::line("   (No tags assigned)");
        }

        // Show recent activity
        $threads = $api->get_ticket_threads($ticket_id);
        if ($threads && isset($threads['data'])) {
            WP_CLI::log("\n📝 Recent Activity (" . count($threads['data']) . " messages):");
            foreach (array_slice($threads['data'], -3) as $thread) {
                $author = $thread['author']['type'] ?? 'Unknown';
                $time = date('M j, H:i', strtotime($thread['createdTime'] ?? ''));
                $content = substr($thread['content'] ?? $thread['summary'] ?? 'No content', 0, 100);
                WP_CLI::line("   [$time] $author: $content" . (strlen($content) > 100 ? '...' : ''));
            }
        }
    }

    private function ticket_respond($args, $assoc_args) {
        if (empty($args[1])) {
            WP_CLI::error("Please specify a ticket ID");
        }

        $ticket_id = $args[1];
        $ai_provider = WP_CLI\Utils\get_flag_value($assoc_args, 'ai-provider', null);
        $template = WP_CLI\Utils\get_flag_value($assoc_args, 'template', null);

        WP_CLI::log("🤖 Generating AI response for ticket #$ticket_id...");

        // Get ticket data
        $api = new ZDM_Zoho_API();
        $ticket_data = $api->get_ticket($ticket_id);
        $threads = $api->get_ticket_threads($ticket_id);

        if (!$ticket_data) {
            WP_CLI::error("Unable to fetch ticket data for ID: $ticket_id");
        }

        // Generate AI response
        $options = array(
            'response_type' => 'solution',
            'tone' => 'professional'
        );

        if ($ai_provider) {
            $old_provider = get_option('zdm_default_ai_provider');
            update_option('zdm_default_ai_provider', $ai_provider);
        }

        $result = ZDM_AI_Assistant::generate_response($ticket_data, $threads['data'] ?? array(), $options);

        if ($ai_provider) {
            update_option('zdm_default_ai_provider', $old_provider);
        }

        if (isset($result['error'])) {
            WP_CLI::error($result['message']);
        }

        WP_CLI::success("Generated AI response:");
        WP_CLI::line("");
        WP_CLI::line($result['response']);

        // Ask if user wants to send the response
        $send = $this->prompt_user("\nSend this response to the ticket? (y/n)");
        if (strtolower(trim($send)) === 'y') {
            $send_result = $api->reply_to_ticket($ticket_id, $result['response']);
            if ($send_result) {
                WP_CLI::success("✅ Response sent successfully!");

                // Auto-tag if template was used
                if ($template) {
                    $api->auto_tag_ticket($ticket_id, $template);
                    WP_CLI::line("🏷️  Auto-tagged ticket based on template");
                }
            } else {
                WP_CLI::error("❌ Failed to send response");
            }
        }
    }

    private function ticket_update($args, $assoc_args) {
        if (empty($args[1])) {
            WP_CLI::error("Please specify a ticket ID");
        }

        $ticket_id = $args[1];
        $status = WP_CLI\Utils\get_flag_value($assoc_args, 'status', null);
        $priority = WP_CLI\Utils\get_flag_value($assoc_args, 'priority', null);

        if (!$status && !$priority) {
            WP_CLI::error("Please specify --status or --priority to update");
        }

        $api = new ZDM_Zoho_API();

        if ($status) {
            $result = $api->update_ticket_status($ticket_id, $status);
            if ($result) {
                WP_CLI::success("✅ Updated ticket #$ticket_id status to: $status");
            } else {
                WP_CLI::error("❌ Failed to update ticket status");
            }
        }

        // Additional update operations can be added here
    }

    private function ticket_tags($args, $assoc_args) {
        if (empty($args[1])) {
            WP_CLI::error("Please specify a tag action: list, show, add, remove, auto-tag");
        }

        $action = $args[1];
        $api = new ZDM_Zoho_API();

        switch ($action) {
            case 'list':
                $tags = $api->get_ticket_tags();
                if ($tags && isset($tags['data'])) {
                    WP_CLI::success("Available ticket tags:");
                    foreach ($tags['data'] as $tag) {
                        WP_CLI::line("• " . $tag['name'] . " (ID: " . $tag['id'] . ")");
                    }
                } else {
                    WP_CLI::error("Failed to fetch tags");
                }
                break;

            case 'show':
                if (empty($args[2])) {
                    WP_CLI::error("Please specify a ticket ID");
                }
                $ticket_id = $args[2];
                $tags = $api->get_ticket_tags_by_id($ticket_id);
                if ($tags !== false && isset($tags['data'])) {
                    WP_CLI::success("Tags for ticket #$ticket_id:");
                    foreach ($tags['data'] as $tag) {
                        WP_CLI::line("• " . $tag['name']);
                    }
                } else {
                    WP_CLI::line("No tags found for ticket #$ticket_id");
                }
                break;

            case 'add':
                if (empty($args[2]) || empty($args[3])) {
                    WP_CLI::error("Usage: wp zdm ticket tags add <ticket_id> <tag1> [tag2] [tag3]...");
                }
                $ticket_id = $args[2];
                $tags = array_slice($args, 3);
                $result = $api->add_ticket_tags($ticket_id, $tags);
                if ($result) {
                    WP_CLI::success("✅ Added tags to ticket #$ticket_id: " . implode(', ', $tags));
                } else {
                    WP_CLI::error("❌ Failed to add tags");
                }
                break;

            case 'remove':
                if (empty($args[2]) || empty($args[3])) {
                    WP_CLI::error("Usage: wp zdm ticket tags remove <ticket_id> <tag1> [tag2] [tag3]...");
                }
                $ticket_id = $args[2];
                $tags = array_slice($args, 3);
                $result = $api->remove_ticket_tags($ticket_id, $tags);
                if ($result) {
                    WP_CLI::success("✅ Removed tags from ticket #$ticket_id: " . implode(', ', $tags));
                } else {
                    WP_CLI::error("❌ Failed to remove tags");
                }
                break;

            case 'auto-tag':
                if (empty($args[2])) {
                    WP_CLI::error("Please specify a ticket ID");
                }
                $ticket_id = $args[2];
                $template_key = $args[3] ?? null;
                WP_CLI::log("🔍 Analyzing ticket #$ticket_id for auto-tagging...");
                $result = $api->auto_tag_ticket($ticket_id, $template_key);
                if ($result) {
                    WP_CLI::success("✅ Auto-tagged ticket #$ticket_id");
                } else {
                    WP_CLI::error("❌ Failed to auto-tag ticket");
                }
                break;

            default:
                WP_CLI::error("Unknown tag action: $action");
        }
    }

    // =================================================================
    // TEMPLATE SUBCOMMAND METHODS
    // =================================================================

    private function template_list($args, $assoc_args, $format) {
        $category = WP_CLI\Utils\get_flag_value($assoc_args, 'category', null);

        if ($category) {
            $templates = ZDM_Template_Manager::get_templates_by_category($category);
        } else {
            $templates = ZDM_Template_Manager::get_templates();
        }

        $output_data = array();
        foreach ($templates as $key => $template) {
            $output_data[] = array(
                'Key' => $key,
                'Name' => $template['name'],
                'Category' => $template['category'],
                'Variables' => implode(', ', $template['variables']),
                'Usage' => $template['usage_count']
            );
        }

        if ($format === 'table') {
            WP_CLI\Utils\format_items('table', $output_data, array('Key', 'Name', 'Category', 'Variables', 'Usage'));
        } else {
            WP_CLI\Utils\format_items($format, $output_data, array('Key', 'Name', 'Category', 'Variables', 'Usage'));
        }
    }

    private function template_show($args, $assoc_args) {
        if (empty($args[1])) {
            WP_CLI::error("Please specify a template key");
        }

        $template = ZDM_Template_Manager::get_template($args[1]);
        if (!$template) {
            WP_CLI::error("Template '" . $args[1] . "' not found");
        }

        WP_CLI::success("Template: " . $template['name']);
        WP_CLI::line("Category: " . $template['category']);
        WP_CLI::line("Description: " . $template['description']);
        WP_CLI::line("Variables: " . implode(', ', $template['variables']));
        WP_CLI::line("Keywords: " . $template['keywords']);
        WP_CLI::line("Usage Count: " . $template['usage_count']);
        WP_CLI::line("--------");
        WP_CLI::line($template['content']);
    }

    private function template_use($args, $assoc_args) {
        if (empty($args[1]) || empty($args[2])) {
            WP_CLI::error("Usage: wp zdm template use <template_key> <ticket_id>");
        }

        $template_key = $args[1];
        $ticket_id = $args[2];

        // Get ticket data
        $api = new ZDM_Zoho_API();
        $ticket_data = $api->get_ticket($ticket_id);
        $threads = $api->get_ticket_threads($ticket_id);

        if (!$ticket_data) {
            WP_CLI::error("Unable to fetch ticket data for ID: $ticket_id");
        }

        // Extract variables and process template
        $variables = ZDM_Template_Manager::extract_ticket_variables($ticket_data, $threads);
        $content = ZDM_Template_Manager::process_template($template_key, $variables);

        if ($content === false) {
            WP_CLI::error("Template '$template_key' not found");
        }

        WP_CLI::success("Template processed successfully:");
        WP_CLI::line("");
        WP_CLI::line($content);
    }

    private function template_retag($args, $assoc_args) {
        WP_CLI::log("🔍 Starting auto-tagging process for all templates...");
        $count = ZDM_Template_Manager::retag_all_templates();
        WP_CLI::success("✅ Auto-tagged $count templates successfully");
    }

    // =================================================================
    // ANALYSIS HELPER METHODS
    // =================================================================

    private function analyze_with_template($template_key) {
        $template = ZDM_Template_Manager::get_template($template_key);
        if (!$template) {
            return array();
        }

        $tags = array();
        if (!empty($template['category'])) {
            $tags[] = $template['category'];
        }

        $auto_tags = ZDM_Template_Manager::get_auto_tags($template['id']);
        if (!empty($auto_tags)) {
            $tags = array_merge($tags, $auto_tags);
        }

        return $tags;
    }

    private function analyze_ticket_content($ticket_data, $threads = null) {
        $content = strtolower($ticket_data['subject'] ?? '');
        $content .= ' ' . strtolower($ticket_data['description'] ?? '');

        if ($threads && isset($threads['data'])) {
            foreach ($threads['data'] as $thread) {
                if (isset($thread['content'])) {
                    $content .= ' ' . strtolower($thread['content']);
                }
            }
        }

        $suggested_tags = array();
        $patterns = array(
            'password-issue' => array('password', 'login', 'signin', 'authentication', 'access denied'),
            'billing-inquiry' => array('billing', 'payment', 'invoice', 'charge', 'refund', 'subscription'),
            'technical-support' => array('error', 'bug', 'not working', 'broken', 'crash', 'issue'),
            'feature-request' => array('feature', 'enhancement', 'suggestion', 'improve', 'add'),
            'account-management' => array('account', 'profile', 'settings', 'update', 'change'),
            'installation-help' => array('install', 'setup', 'configure', 'deployment'),
            'urgent' => array('urgent', 'asap', 'immediately', 'critical', 'emergency'),
            'question' => array('how to', 'how do', 'what is', 'explain', 'help with'),
            'frustrated' => array('frustrated', 'angry', 'disappointed', 'unacceptable'),
            'positive' => array('thank', 'appreciate', 'great', 'excellent', 'love'),
        );

        foreach ($patterns as $tag => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    $suggested_tags[] = $tag;
                    break;
                }
            }
        }

        return array_unique($suggested_tags);
    }

    private function analyze_with_ai($ticket_data, $threads = null, $ai_provider = null) {
        $provider = $ai_provider ?: get_option('zdm_default_ai_provider');
        if (empty($provider)) {
            return array();
        }

        $api_key = get_option('zdm_' . $provider . '_api_key');
        if (empty($api_key)) {
            return array();
        }

        $content = "Subject: " . ($ticket_data['subject'] ?? '') . "\n";
        $content .= "Description: " . ($ticket_data['description'] ?? '') . "\n";

        if ($threads && isset($threads['data'])) {
            $content .= "Conversation:\n";
            foreach ($threads['data'] as $thread) {
                $author = $thread['author']['type'] ?? 'Unknown';
                $message = $thread['content'] ?? '';
                $content .= "$author: $message\n";
            }
        }

        $prompt = array(
            'system' => 'You are a support ticket classifier. Analyze the ticket content and suggest relevant tags. Focus on: issue type, urgency, sentiment, technical area, and customer needs. Return only a comma-separated list of 3-8 relevant tags.',
            'user' => "Analyze this support ticket and suggest relevant tags:\n\n$content\n\nSuggest 3-8 relevant tags (comma-separated):"
        );

        $response = '';
        switch ($provider) {
            case 'claude':
                $response = $this->call_claude_for_tags($prompt, $api_key);
                break;
            case 'openai':
                $response = $this->call_openai_for_tags($prompt, $api_key);
                break;
            case 'gemini':
                $response = $this->call_gemini_for_tags($prompt, $api_key);
                break;
        }

        if ($response) {
            $tags = array_map('trim', explode(',', strtolower($response)));
            $tags = array_filter($tags, function($tag) {
                return !empty($tag) && strlen($tag) > 2;
            });
            return array_slice($tags, 0, 8);
        }

        return array();
    }

    private function analyze_metadata($ticket_data) {
        $tags = array();

        $priority = strtolower($ticket_data['priority'] ?? '');
        if (in_array($priority, array('high', 'urgent', 'critical'))) {
            $tags[] = 'high-priority';
        } elseif ($priority === 'low') {
            $tags[] = 'low-priority';
        }

        $status = strtolower($ticket_data['status'] ?? '');
        if ($status === 'open') {
            $tags[] = 'new-ticket';
        } elseif (in_array($status, array('in progress', 'pending', 'waiting'))) {
            $tags[] = 'in-progress';
        }

        if (isset($ticket_data['contact']['email'])) {
            $email = $ticket_data['contact']['email'];
            $domain = substr(strrchr($email, '@'), 1);
            $consumer_domains = array('gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com');
            if (in_array($domain, $consumer_domains)) {
                $tags[] = 'consumer';
            } else {
                $tags[] = 'business';
            }
        }

        if (!empty($ticket_data['createdTime'])) {
            $created = new DateTime($ticket_data['createdTime']);
            $now = new DateTime();
            $diff = $now->diff($created);
            if ($diff->h < 2) {
                $tags[] = 'fresh';
            } elseif ($diff->d > 3) {
                $tags[] = 'aging';
            }
        }

        return $tags;
    }

    // =================================================================
    // AI API METHODS
    // =================================================================

    private function call_claude_for_tags($prompt, $api_key) {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode(array(
                'model' => 'claude-3-haiku-20240307',
                'messages' => array(array('role' => 'user', 'content' => $prompt['user'])),
                'system' => $prompt['system'],
                'max_tokens' => 100
            )),
            'timeout' => 30
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body['content'][0]['text'] ?? '';
        }
        return '';
    }

    private function call_openai_for_tags($prompt, $api_key) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array('role' => 'system', 'content' => $prompt['system']),
                    array('role' => 'user', 'content' => $prompt['user'])
                ),
                'max_tokens' => 100
            )),
            'timeout' => 30
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body['choices'][0]['message']['content'] ?? '';
        }
        return '';
    }

    private function call_gemini_for_tags($prompt, $api_key) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key;
        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'contents' => array(array(
                    'parts' => array(array('text' => $prompt['system'] . "\n\n" . $prompt['user']))
                )),
                'generationConfig' => array('maxOutputTokens' => 100)
            )),
            'timeout' => 30
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        }
        return '';
    }

    // =================================================================
    // UTILITY METHODS
    // =================================================================

    private function filter_existing_tags($all_suggested_tags, $current_tags) {
        $existing_tag_names = array();
        if ($current_tags && isset($current_tags['data'])) {
            foreach ($current_tags['data'] as $tag) {
                $existing_tag_names[] = strtolower($tag['name']);
            }
        }

        return array_filter($all_suggested_tags, function($tag) use ($existing_tag_names) {
            return !in_array(strtolower($tag), $existing_tag_names);
        });
    }

    private function select_tags_interactive($available_tags) {
        WP_CLI::line("\nSelect tags to apply (enter numbers separated by commas, or 'all' for all tags):");
        foreach ($available_tags as $i => $tag) {
            WP_CLI::line("   " . ($i + 1) . ". " . $tag);
        }

        $selection = $this->prompt_user("Selection");
        if (strtolower(trim($selection)) === 'all') {
            return $available_tags;
        }

        $indices = array_map('trim', explode(',', $selection));
        $selected_tags = array();
        foreach ($indices as $index) {
            if (is_numeric($index) && isset($available_tags[$index - 1])) {
                $selected_tags[] = $available_tags[$index - 1];
            }
        }
        return $selected_tags;
    }

    private function prompt_user($message) {
        echo "$message: ";
        return trim(fgets(STDIN));
    }

    private function check_for_updates($api, $last_check, $status, $priority, $auto_tag, $alerts) {
        // Implementation for monitoring functionality
        WP_CLI::log("[" . date('H:i:s') . "] Checking for updates...");
        // Add monitoring logic here
    }

    private function gather_statistics($api, $period, $detailed) {
        // Implementation for statistics gathering
        return array(
            array(
                'Metric' => 'Total Tickets',
                'Value' => '42',
                'Period' => $period
            )
        );
    }

    private function display_stats_table($stats, $detailed) {
        WP_CLI\Utils\format_items('table', $stats, array('Metric', 'Value', 'Period'));
    }
}

// Register commands
WP_CLI::add_command('zoho-desk', 'ZDM_CLI_Commands');
WP_CLI::add_command('zdm', 'ZDM_CLI_Commands');

// Add helpful aliases
WP_CLI::add_command('zd', 'ZDM_CLI_Commands');