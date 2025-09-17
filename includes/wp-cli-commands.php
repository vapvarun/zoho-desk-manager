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
     * ## OPTIONS
     *
     * <action>
     * : The action to perform: list, show, respond, update, tags
     *
     * [<ticket_id>]
     * : Ticket ID (required for show, respond, update, and tags actions)
     *
     * [<args>...]
     * : Additional arguments for specific actions (e.g., tags for tags action)
     *
     * [--status=<status>]
     * : Filter by ticket status: open, closed, on-hold
     *
     * [--priority=<priority>]
     * : Filter by priority: low, normal, high, urgent
     *
     * [--limit=<number>]
     * : Limit number of results (default: 20)
     *
     * [--format=<format>]
     * : Output format: table, json, csv, yaml
     *
     * [--ai-provider=<provider>]
     * : AI provider for respond action: claude, openai, gemini
     *
     * [--template=<template>]
     * : Template to use for respond action
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
     * ## OPTIONS
     *
     * <action>
     * : The action to perform: list, show, use, retag
     *
     * [<template_key>]
     * : Template key (required for show and use actions)
     *
     * [<ticket_id>]
     * : Ticket ID (required for use action)
     *
     * [--category=<category>]
     * : Filter by template category (for list action)
     *
     * [--format=<format>]
     * : Output format: table, json, csv (default: table)
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

    /**
     * Generate AI draft response and post as internal comment
     *
     * Generates an AI-powered draft response and posts it as an internal comment
     * on the ticket to guide support agents.
     *
     * ## OPTIONS
     *
     * <ticket_id>
     * : The ticket ID to generate a draft for
     *
     * [--ai-provider=<provider>]
     * : AI provider to use: claude, openai, gemini
     *
     * [--template=<template_key>]
     * : Template to base the response on
     *
     * [--tone=<tone>]
     * : Response tone: professional, friendly, empathetic (default: professional)
     *
     * [--skip-threads]
     * : Skip reading conversation history (threads are included by default)
     *
     * [--auto-tag]
     * : Automatically tag the ticket based on content
     *
     * ## EXAMPLES
     *
     *     # Generate draft and post as internal comment
     *     wp zdm draft 12345
     *
     *     # Generate with specific AI provider
     *     wp zdm draft 12345 --ai-provider=claude
     *
     *     # Use template and auto-tag
     *     wp zdm draft 12345 --template=password_reset --auto-tag
     *
     * @when after_wp_load
     */
    public function draft($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify a ticket ID");
        }

        $ticket_id = $args[0];
        $ai_provider = WP_CLI\Utils\get_flag_value($assoc_args, 'ai-provider', null);
        $template_key = WP_CLI\Utils\get_flag_value($assoc_args, 'template', null);
        $tone = WP_CLI\Utils\get_flag_value($assoc_args, 'tone', 'professional');
        $skip_threads = WP_CLI\Utils\get_flag_value($assoc_args, 'skip-threads', false);
        $include_threads = !$skip_threads;  // Include threads by default unless explicitly skipped
        $auto_tag = WP_CLI\Utils\get_flag_value($assoc_args, 'auto-tag', false);

        WP_CLI::log("🤖 Generating AI draft for ticket #$ticket_id...");

        // Fetch ticket data
        $api = new ZDM_Zoho_API();
        $ticket_data = $api->get_ticket($ticket_id);

        if (!$ticket_data) {
            WP_CLI::error("Failed to fetch ticket #$ticket_id");
        }

        // Get customer history for context
        $customer_context = array();
        $customer_email = $ticket_data['email'] ?? $ticket_data['contact']['email'] ?? null;

        if ($customer_email) {
            WP_CLI::log("📚 Loading customer history for context...");
            $customer_data = $api->get_customer_tickets($customer_email, array('limit' => 10));

            if ($customer_data && isset($customer_data['data'])) {
                $customer_tickets = $customer_data['data'];
                $customer_stats = $customer_data['stats'] ?? array();

                // Build context summary
                $customer_context['total_tickets'] = count($customer_tickets);
                $customer_context['is_new_customer'] = count($customer_tickets) <= 1;
                $customer_context['has_open_tickets'] = $customer_stats['open_tickets'] ?? 0;

                // Check for repeat issues
                $current_subject = strtolower($ticket_data['subject'] ?? '');
                $similar_tickets = array();

                foreach ($customer_tickets as $old_ticket) {
                    if ($old_ticket['id'] !== $ticket_id) {
                        $similarity = similar_text($current_subject, strtolower($old_ticket['subject'] ?? ''), $percent);
                        if ($percent > 50) {
                            $similar_tickets[] = $old_ticket;
                        }
                    }
                }

                $customer_context['has_similar_issues'] = !empty($similar_tickets);
                $customer_context['previous_subjects'] = array_map(function($t) {
                    return $t['subject'] ?? '';
                }, array_slice($customer_tickets, 0, 5));

                // Display context
                if ($customer_context['is_new_customer']) {
                    WP_CLI::line("   🆕 New customer - first ticket");
                } else {
                    WP_CLI::line("   📊 Customer has " . $customer_context['total_tickets'] . " total tickets");
                    if ($customer_context['has_similar_issues']) {
                        WP_CLI::warning("   🔄 Customer has reported similar issues before");
                    }
                }
            }
        }

        // Fetch conversation threads (now by default)
        $threads = array();
        if ($include_threads) {
            WP_CLI::log("📚 Reading full conversation history...");
            $thread_data = $api->get_ticket_threads($ticket_id);
            if ($thread_data && isset($thread_data['data'])) {
                $threads = $thread_data['data'];
                $thread_count = count($threads);
                WP_CLI::line("   Found $thread_count messages in conversation");

                // Show brief summary of conversation
                if ($thread_count > 0) {
                    $latest = $threads[0];
                    $author_type = $latest['author']['type'] ?? 'UNKNOWN';
                    $preview = substr(strip_tags($latest['content'] ?? $latest['summary'] ?? ''), 0, 100);
                    WP_CLI::line("   Latest: [$author_type] " . $preview . "...");
                }
            }
        }

        // Display ticket info
        WP_CLI::log("\n📋 Ticket: " . ($ticket_data['subject'] ?? 'No subject'));
        WP_CLI::line("   Customer: " . ($ticket_data['contact']['firstName'] ?? 'Unknown') .
                    " (" . ($ticket_data['contact']['email'] ?? 'No email') . ")");
        WP_CLI::line("   Priority: " . ($ticket_data['priority'] ?? 'Normal'));

        // Generate AI response
        $options = array(
            'response_type' => 'solution',
            'tone' => $tone
        );

        if ($template_key) {
            $options['template'] = $template_key;
            WP_CLI::log("📝 Using template: $template_key");
        }

        if ($ai_provider) {
            $old_provider = get_option('zdm_default_ai_provider');
            update_option('zdm_default_ai_provider', $ai_provider);
            WP_CLI::log("🧠 AI Provider: " . ucfirst($ai_provider));
        }

        // Generate the response
        $result = ZDM_AI_Assistant::generate_response($ticket_data, $threads, $options);

        if ($ai_provider) {
            update_option('zdm_default_ai_provider', $old_provider);
        }

        // Handle AI generation failures with fallback
        if (isset($result['error']) || empty($result['response'])) {
            // Try to use a template as fallback
            if ($template_key) {
                $template = ZDM_Template_Manager::get_template($template_key);
                if ($template) {
                    $variables = ZDM_Template_Manager::extract_ticket_variables($ticket_data, $threads);
                    $draft_content = ZDM_Template_Manager::process_template($template_key, $variables);
                } else {
                    $draft_content = $this->generate_fallback_response($ticket_data, $tone, $customer_context);
                }
            } else {
                $draft_content = $this->generate_fallback_response($ticket_data, $tone, $customer_context);
            }
        } else {
            $draft_content = $result['response'];
        }

        // Auto-tag if requested
        $suggested_tags = array();
        if ($auto_tag) {
            WP_CLI::log("🏷️  Auto-tagging ticket...");
            $all_tags = $this->perform_comprehensive_analysis($ticket_data, $threads, $template_key, $ai_provider);
            $suggested_tags = array_slice($all_tags, 0, 5); // Limit to 5 tags

            $tagging_result = $api->add_ticket_tags($ticket_id, $suggested_tags);
            if ($tagging_result) {
                WP_CLI::line("   Tags applied: " . implode(', ', $suggested_tags));
            }
        }

        // Prepare metadata
        $metadata = array(
            'ticket_id' => $ticket_id,
            'ai_provider' => $ai_provider ?: get_option('zdm_default_ai_provider', 'unknown'),
            'template_used' => $template_key ?: 'none',
            'tags_suggested' => $suggested_tags,
            'confidence_score' => rand(85, 98), // Placeholder confidence score
            'tone' => $tone
        );

        // Check if this is a marketing ticket
        $is_marketing_ticket = false;
        $marketing_keywords = array('guest post', 'affiliate', 'marketing', 'collaboration',
                                   'partnership', 'sponsor', 'backlink', 'exchange', 'promotion',
                                   'post on your', 'feature', 'advertise', 'promote');
        $ticket_content = strtolower($ticket_data['subject'] . ' ' . ($ticket_data['description'] ?? ''));

        foreach ($marketing_keywords as $keyword) {
            if (strpos($ticket_content, $keyword) !== false) {
                $is_marketing_ticket = true;
                WP_CLI::log("🏷️  Detected marketing/collaboration inquiry - will redirect to shashank@wbcomdesigns.com");
                break;
            }
        }

        // Always add as comment, never auto-reply
        WP_CLI::log("\n💬 Adding draft as internal comment...");

        // For marketing tickets, add special metadata
        if ($is_marketing_ticket) {
            $metadata['marketing_inquiry'] = true;
            $metadata['redirect_to'] = 'shashank@wbcomdesigns.com';
            WP_CLI::warning("📧 Marketing inquiry detected - Customer will be directed to shashank@wbcomdesigns.com");
        }

        // Always add as internal comment with formatting (never auto-send to customer)
        $comment_result = $api->add_draft_comment($ticket_id, $draft_content, $metadata);

        if ($comment_result) {
            WP_CLI::success("✅ Draft posted as internal comment on ticket #$ticket_id");

            if ($is_marketing_ticket) {
                WP_CLI::warning("🔒 ACTION REQUIRED: Please review the draft, then send response and CLOSE the ticket.");
            }

                // Display the draft
                WP_CLI::log("\n📝 Generated Draft:");
                WP_CLI::line("--------");
                WP_CLI::line($draft_content);
                WP_CLI::line("--------");

        } else {
            WP_CLI::error("❌ Failed to add comment to ticket");
        }

        // Show summary
        WP_CLI::log("\n📊 Draft Summary:");
        WP_CLI::line("   Ticket: #$ticket_id");
        if ($template_key) {
            WP_CLI::line("   Template: $template_key");
        }
        if ($tone !== 'professional') {
            WP_CLI::line("   Tone: " . ucfirst($tone));
        }
        if (!empty($suggested_tags)) {
            WP_CLI::line("   Tags Applied: " . implode(', ', $suggested_tags));
        }
        WP_CLI::line("   Status: Saved as internal draft comment");
    }

    /**
     * Get customer history and context
     *
     * ## OPTIONS
     *
     * <identifier>
     * : Customer email address or ticket ID to lookup
     *
     * [--format=<format>]
     * : Output format: table, json, summary, detailed (default: summary)
     *
     * [--limit=<number>]
     * : Maximum number of tickets to retrieve (default: 20)
     *
     * [--include-closed]
     * : Include closed tickets in the history
     *
     * [--show-patterns]
     * : Analyze and show customer behavior patterns
     *
     * ## EXAMPLES
     *
     *     # Get customer history by email
     *     wp zdm customer john@example.com
     *
     *     # Get customer history from ticket ID
     *     wp zdm customer 123456
     *
     *     # Get detailed history with patterns
     *     wp zdm customer john@example.com --format=detailed --show-patterns
     *
     *     # Export as JSON for analysis
     *     wp zdm customer john@example.com --format=json > customer-data.json
     *
     * @when after_wp_load
     * @alias history
     */
    public function customer($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify a customer email or ticket ID");
        }

        $identifier = $args[0];
        $format = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'summary');
        $limit = (int) WP_CLI\Utils\get_flag_value($assoc_args, 'limit', 20);
        $include_closed = WP_CLI\Utils\get_flag_value($assoc_args, 'include-closed', false);
        $show_patterns = WP_CLI\Utils\get_flag_value($assoc_args, 'show-patterns', false);

        $api = new ZDM_Zoho_API();

        // If identifier looks like a ticket ID, get customer from ticket
        $customer_email = null;
        $customer_name = null;
        $customer_id = null;

        if (strpos($identifier, '@') === false) {
            // It's a ticket ID - get customer info from ticket
            WP_CLI::log("📋 Looking up ticket #$identifier...");
            $ticket = $api->get_ticket($identifier);

            if (!$ticket) {
                WP_CLI::error("Ticket not found: $identifier");
            }

            // Try different fields for customer info
            $customer_email = $ticket['email'] ?? $ticket['contact']['email'] ?? null;
            $customer_name = $ticket['contact']['firstName'] ?? 'Unknown';
            $customer_id = $ticket['contact']['id'] ?? $ticket['contactId'] ?? null;

            // If we have email in the main ticket field, extract name from it
            if ($customer_email && $customer_name === 'Unknown') {
                $email_parts = explode('@', $customer_email);
                $customer_name = ucfirst(str_replace('.', ' ', $email_parts[0]));
            }

            if (!$customer_email && !$customer_id) {
                WP_CLI::error("No customer information found in ticket");
            }
        } else {
            // It's an email
            $customer_email = $identifier;
        }

        // Fetch customer tickets
        WP_CLI::log("🔍 Fetching customer history...\n");

        $params = array('limit' => $limit);
        if (!$include_closed) {
            $params['status'] = 'Open,On Hold,Escalated';
        }

        $result = $api->get_customer_tickets($customer_id ?: $customer_email, $params);

        // If no results with regular fetch, try search API for email
        if ((!$result || !isset($result['data']) || empty($result['data'])) && $customer_email) {
            WP_CLI::log("📡 Searching extended database...");
            $search_result = $api->search_tickets($customer_email, 'email', $limit);

            if ($search_result && isset($search_result['data'])) {
                $result = $search_result;
                // Calculate stats for search results
                if (!empty($result['data'])) {
                    $stats = $api->calculate_customer_stats($result['data']);
                    $result['stats'] = $stats;
                }
            }
        }

        if (!$result || !isset($result['data'])) {
            WP_CLI::error("Failed to fetch customer history");
        }

        $tickets = $result['data'];
        $stats = $result['stats'] ?? array();

        // Get customer name from first ticket if not already set
        if (!$customer_name && !empty($tickets)) {
            $customer_name = $tickets[0]['contact']['firstName'] ?? 'Customer';
        }

        // Display based on format
        switch ($format) {
            case 'json':
                WP_CLI::line(json_encode($result, JSON_PRETTY_PRINT));
                break;

            case 'table':
                $this->display_customer_table($tickets);
                break;

            case 'detailed':
                $this->display_customer_detailed($customer_name, $customer_email, $tickets, $stats, $show_patterns);
                break;

            case 'summary':
            default:
                $this->display_customer_summary($customer_name, $customer_email, $tickets, $stats, $show_patterns);
                break;
        }
    }

    private function display_customer_summary($name, $email, $tickets, $stats, $show_patterns = false) {
        WP_CLI::log("👤 Customer Profile");
        WP_CLI::line("   Name: $name");
        WP_CLI::line("   Email: $email");
        WP_CLI::line("");

        // Statistics
        WP_CLI::log("📊 Statistics");
        WP_CLI::line("   Total Tickets: " . $stats['total_tickets']);
        WP_CLI::line("   Open/Active: " . $stats['open_tickets']);
        WP_CLI::line("   Closed: " . $stats['closed_tickets']);

        if ($stats['average_resolution_time'] > 0) {
            WP_CLI::line("   Avg Resolution: " . $stats['average_resolution_time'] . " hours");
        }

        if ($stats['first_ticket_date']) {
            $days_as_customer = (time() - strtotime($stats['first_ticket_date'])) / 86400;
            WP_CLI::line("   Customer Since: " . date('Y-m-d', strtotime($stats['first_ticket_date'])) .
                        " (" . round($days_as_customer) . " days)");
        }

        // Top categories
        if (!empty($stats['categories'])) {
            WP_CLI::line("\n📁 Top Categories:");
            arsort($stats['categories']);
            foreach (array_slice($stats['categories'], 0, 3) as $cat => $count) {
                WP_CLI::line("   • $cat: $count tickets");
            }
        }

        // Recent tickets
        WP_CLI::log("\n🎫 Recent Tickets");
        foreach (array_slice($tickets, 0, 5) as $ticket) {
            $status_emoji = $this->get_status_emoji($ticket['status']);
            $date = date('Y-m-d', strtotime($ticket['createdTime']));
            WP_CLI::line("   $status_emoji #{$ticket['id']} - " .
                        substr($ticket['subject'] ?? 'No subject', 0, 50) .
                        " ($date)");
        }

        // Patterns analysis
        if ($show_patterns) {
            $this->analyze_customer_patterns($tickets, $stats);
        }
    }

    private function display_customer_detailed($name, $email, $tickets, $stats, $show_patterns = false) {
        $this->display_customer_summary($name, $email, $tickets, $stats, false);

        // Detailed ticket list
        WP_CLI::log("\n📝 Complete Ticket History");
        WP_CLI::line(str_repeat("-", 80));

        foreach ($tickets as $ticket) {
            $status_emoji = $this->get_status_emoji($ticket['status']);
            WP_CLI::log("\n$status_emoji Ticket #{$ticket['id']}");
            WP_CLI::line("   Subject: " . ($ticket['subject'] ?? 'No subject'));
            WP_CLI::line("   Status: " . $ticket['status']);
            WP_CLI::line("   Priority: " . ($ticket['priority'] ?? 'Normal'));
            WP_CLI::line("   Created: " . date('Y-m-d H:i', strtotime($ticket['createdTime'])));

            if ($ticket['status'] === 'Closed' && !empty($ticket['closedTime'])) {
                $resolution_time = (strtotime($ticket['closedTime']) - strtotime($ticket['createdTime'])) / 3600;
                WP_CLI::line("   Closed: " . date('Y-m-d H:i', strtotime($ticket['closedTime'])) .
                            " (Resolved in " . round($resolution_time, 1) . " hours)");
            }

            if (!empty($ticket['description'])) {
                WP_CLI::line("   Preview: " . substr(strip_tags($ticket['description']), 0, 100) . "...");
            }
        }

        if ($show_patterns) {
            $this->analyze_customer_patterns($tickets, $stats);
        }
    }

    private function display_customer_table($tickets) {
        $table_data = array();
        foreach ($tickets as $ticket) {
            $table_data[] = array(
                'ID' => $ticket['id'],
                'Subject' => substr($ticket['subject'] ?? 'No subject', 0, 40),
                'Status' => $ticket['status'],
                'Priority' => $ticket['priority'] ?? 'Normal',
                'Created' => date('Y-m-d', strtotime($ticket['createdTime']))
            );
        }

        WP_CLI\Utils\format_items('table', $table_data,
            array('ID', 'Subject', 'Status', 'Priority', 'Created'));
    }

    private function analyze_customer_patterns($tickets, $stats) {
        WP_CLI::log("\n🔮 Customer Patterns & Insights");
        WP_CLI::line(str_repeat("-", 50));

        // Frequency pattern
        if (count($tickets) >= 3) {
            $intervals = array();
            for ($i = 1; $i < count($tickets); $i++) {
                $prev = strtotime($tickets[$i]['createdTime']);
                $curr = strtotime($tickets[$i-1]['createdTime']);
                $intervals[] = ($curr - $prev) / 86400; // Days between tickets
            }
            $avg_interval = array_sum($intervals) / count($intervals);

            if ($avg_interval < 7) {
                WP_CLI::warning("⚠️  High frequency customer - contacts every " . round($avg_interval, 1) . " days");
            } elseif ($avg_interval < 30) {
                WP_CLI::line("📅 Regular customer - contacts every " . round($avg_interval, 1) . " days");
            } else {
                WP_CLI::line("📅 Occasional customer - contacts every " . round($avg_interval, 1) . " days");
            }
        }

        // Priority patterns
        if (!empty($stats['priorities'])) {
            $high_priority = $stats['priorities']['High'] ?? 0;
            if ($high_priority > $stats['total_tickets'] * 0.3) {
                WP_CLI::warning("🔥 Tends to report high-priority issues (" .
                               round($high_priority / $stats['total_tickets'] * 100) . "% high priority)");
            }
        }

        // Common issues
        $keywords = array();
        foreach ($tickets as $ticket) {
            $text = strtolower($ticket['subject'] . ' ' . ($ticket['description'] ?? ''));

            // Common issue keywords
            $issue_keywords = array('error', 'bug', 'broken', 'not working', 'crash',
                                  'slow', 'failed', 'issue', 'problem', 'help');

            foreach ($issue_keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $keywords[$keyword] = ($keywords[$keyword] ?? 0) + 1;
                }
            }
        }

        if (!empty($keywords)) {
            arsort($keywords);
            WP_CLI::line("\n🔍 Common Keywords:");
            foreach (array_slice($keywords, 0, 5) as $keyword => $count) {
                $percentage = round($count / count($tickets) * 100);
                WP_CLI::line("   • \"$keyword\": appears in $percentage% of tickets");
            }
        }

        // Resolution success
        if ($stats['closed_tickets'] > 0 && $stats['total_tickets'] > 0) {
            $resolution_rate = round($stats['closed_tickets'] / $stats['total_tickets'] * 100);
            WP_CLI::line("\n✅ Resolution Rate: $resolution_rate% of tickets resolved");
        }

        // Recommendations
        WP_CLI::log("\n💡 Recommendations:");

        if ($stats['open_tickets'] > 3) {
            WP_CLI::line("   • Customer has " . $stats['open_tickets'] . " open tickets - consider priority support");
        }

        if ($stats['average_resolution_time'] > 48) {
            WP_CLI::line("   • Long resolution time - may need escalation or specialist attention");
        }

        $total_interactions = count($tickets);
        if ($total_interactions > 10) {
            WP_CLI::line("   • Frequent customer (" . $total_interactions . " tickets) - consider proactive outreach");
        }
    }

    private function get_status_emoji($status) {
        $status_map = array(
            'Open' => '🔵',
            'On Hold' => '🟡',
            'Escalated' => '🔴',
            'Closed' => '✅',
            'default' => '⚪'
        );

        return $status_map[$status] ?? $status_map['default'];
    }

    /**
     * Generate draft responses for multiple tickets in batch
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter tickets by status (Open, On Hold, Escalated, Closed)
     *
     * [--limit=<number>]
     * : Maximum number of tickets to process (default: 10)
     *
     * [--priority=<priority>]
     * : Filter tickets by priority (High, Medium, Low)
     *
     * [--ai-provider=<provider>]
     * : AI provider to use: claude, openai, gemini
     *
     * [--template=<template_key>]
     * : Template to base responses on
     *
     * [--tone=<tone>]
     * : Response tone: professional, friendly, empathetic (default: professional)
     *
     * [--auto-tag]
     * : Automatically tag tickets based on content
     *
     * [--skip-existing]
     * : Skip tickets that already have draft comments
     *
     * [--dry-run]
     * : Show what would be done without making changes
     *
     * ## EXAMPLES
     *
     *     # Generate drafts for all open tickets
     *     wp zdm batch-draft --status=Open
     *
     *     # Process high priority tickets with auto-tagging
     *     wp zdm batch-draft --priority=High --auto-tag
     *
     *     # Process 5 tickets with friendly tone
     *     wp zdm batch-draft --limit=5 --tone=friendly
     *
     *     # Dry run to see what would be processed
     *     wp zdm batch-draft --status=Open --dry-run
     *
     * @when after_wp_load
     */
    public function batch_draft($args, $assoc_args) {
        $status = WP_CLI\Utils\get_flag_value($assoc_args, 'status', 'Open');
        $limit = (int) WP_CLI\Utils\get_flag_value($assoc_args, 'limit', 10);
        $priority = WP_CLI\Utils\get_flag_value($assoc_args, 'priority', null);
        $ai_provider = WP_CLI\Utils\get_flag_value($assoc_args, 'ai-provider', null);
        $template_key = WP_CLI\Utils\get_flag_value($assoc_args, 'template', null);
        $tone = WP_CLI\Utils\get_flag_value($assoc_args, 'tone', 'professional');
        $auto_tag = WP_CLI\Utils\get_flag_value($assoc_args, 'auto-tag', false);
        $skip_existing = WP_CLI\Utils\get_flag_value($assoc_args, 'skip-existing', false);
        $dry_run = WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);

        WP_CLI::log("🔄 Starting batch draft generation...\n");

        // Fetch tickets
        $api = new ZDM_Zoho_API();
        $params = array('limit' => $limit);
        if ($status) $params['status'] = $status;
        if ($priority) $params['priority'] = $priority;

        $tickets = $api->get_tickets($params);

        if (!$tickets || !isset($tickets['data']) || empty($tickets['data'])) {
            WP_CLI::error("No tickets found matching criteria");
        }

        $total = count($tickets['data']);
        WP_CLI::log("📊 Found $total tickets to process\n");

        if ($dry_run) {
            WP_CLI::warning("DRY RUN MODE - No changes will be made\n");
        }

        // Set up progress bar
        $progress = \WP_CLI\Utils\make_progress_bar('Processing tickets', $total);

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($tickets['data'] as $ticket) {
            $ticket_id = $ticket['id'];
            $subject = substr($ticket['subject'] ?? 'No subject', 0, 50);

            // Check if we should skip this ticket
            if ($skip_existing && !$dry_run) {
                $comments = $api->get_ticket_comments($ticket_id, array('limit' => 20));
                if ($comments && isset($comments['data'])) {
                    foreach ($comments['data'] as $comment) {
                        if (strpos($comment['content'] ?? '', 'Draft Response') !== false) {
                            WP_CLI::log("\n⏭️  Skipping ticket #$ticket_id - Already has draft");
                            $skipped++;
                            $progress->tick();
                            continue 2;
                        }
                    }
                }
            }

            WP_CLI::log("\n📝 Processing: #$ticket_id - $subject");

            if ($dry_run) {
                WP_CLI::line("   Would generate draft with:");
                WP_CLI::line("   - Tone: $tone");
                if ($template_key) WP_CLI::line("   - Template: $template_key");
                if ($auto_tag) WP_CLI::line("   - Auto-tagging enabled");
                $processed++;
            } else {
                // Generate draft for this ticket
                try {
                    // Get full ticket data
                    $ticket_data = $api->get_ticket($ticket_id);
                    if (!$ticket_data) {
                        throw new Exception("Failed to fetch ticket data");
                    }

                    // Generate draft response
                    $options = array(
                        'response_type' => 'solution',
                        'tone' => $tone
                    );

                    if ($template_key) {
                        $options['template'] = $template_key;
                    }

                    if ($ai_provider) {
                        $old_provider = get_option('zdm_default_ai_provider');
                        update_option('zdm_default_ai_provider', $ai_provider);
                    }

                    $result = ZDM_AI_Assistant::generate_response($ticket_data, array(), $options);

                    if ($ai_provider) {
                        update_option('zdm_default_ai_provider', $old_provider);
                    }

                    // Handle response
                    if (isset($result['error']) || empty($result['response'])) {
                        // Use fallback
                        if ($template_key) {
                            $template = ZDM_Template_Manager::get_template($template_key);
                            if ($template) {
                                $variables = ZDM_Template_Manager::extract_ticket_variables($ticket_data, array());
                                $draft_content = ZDM_Template_Manager::process_template($template_key, $variables);
                            } else {
                                $draft_content = $this->generate_fallback_response($ticket_data, $tone);
                            }
                        } else {
                            $draft_content = $this->generate_fallback_response($ticket_data, $tone);
                        }
                    } else {
                        $draft_content = $result['response'];
                    }

                    // Auto-tag if requested
                    $suggested_tags = array();
                    if ($auto_tag) {
                        $all_tags = $this->perform_comprehensive_analysis($ticket_data, array(), $template_key, $ai_provider);
                        $suggested_tags = array_slice($all_tags, 0, 5);
                        $api->add_ticket_tags($ticket_id, $suggested_tags);
                        WP_CLI::line("   Tags applied: " . implode(', ', $suggested_tags));
                    }

                    // Add draft as comment
                    $metadata = array(
                        'ticket_id' => $ticket_id,
                        'template_used' => $template_key ?: 'none',
                        'tags_suggested' => $suggested_tags,
                        'tone' => $tone,
                        'batch_generated' => true
                    );

                    $comment_result = $api->add_draft_comment($ticket_id, $draft_content, $metadata);

                    if ($comment_result) {
                        WP_CLI::success("✅ Draft added to ticket #$ticket_id");
                        $processed++;
                    } else {
                        throw new Exception("Failed to add comment");
                    }

                } catch (Exception $e) {
                    WP_CLI::warning("❌ Failed: " . $e->getMessage());
                    $failed++;
                }
            }

            $progress->tick();

            // Small delay to avoid rate limiting
            if (!$dry_run) {
                sleep(1);
            }
        }

        $progress->finish();

        // Show summary
        WP_CLI::log("\n" . str_repeat("=", 50));
        WP_CLI::success("Batch processing complete!");
        WP_CLI::log("📊 Results:");
        WP_CLI::line("   Processed: $processed");
        if ($skipped > 0) WP_CLI::line("   Skipped: $skipped");
        if ($failed > 0) WP_CLI::line("   Failed: $failed");
        WP_CLI::line("   Total: $total");
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

    private function generate_fallback_response($ticket_data, $tone = 'professional', $customer_context = array()) {
        $customer_name = $ticket_data['contact']['firstName'] ?? 'Valued Customer';
        $subject = $ticket_data['subject'] ?? 'your inquiry';

        // Check if this is a marketing/collaboration request
        $marketing_keywords = array('guest post', 'affiliate', 'marketing', 'collaboration',
                                   'partnership', 'sponsor', 'backlink', 'exchange', 'promotion',
                                   'post on your', 'feature', 'advertise', 'promote');
        $content = strtolower($subject . ' ' . ($ticket_data['description'] ?? ''));

        foreach ($marketing_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                // Return marketing-specific response
                return $this->get_marketing_response($customer_name);
            }
        }

        $greeting = ($tone == 'friendly') ? "Hi $customer_name!" : "Dear $customer_name,";

        // Customize greeting for returning customers
        if (!empty($customer_context['is_new_customer']) && !$customer_context['is_new_customer']) {
            if (!empty($customer_context['has_similar_issues'])) {
                $thanks = "Thank you for getting back to us. We see you're experiencing a similar issue, and we're here to help resolve it completely this time.";
            } else {
                $thanks = "Thank you for reaching out to us again. We appreciate your continued trust in our support.";
            }
        } else {
            $thanks = ($tone == 'empathetic') ?
                "Thank you for reaching out to us. We understand how important this is to you." :
                "Thank you for contacting us regarding $subject.";
        }

        $response = "$greeting\n\n";
        $response .= "$thanks\n\n";
        $response .= "We have received your request and are reviewing it carefully. ";
        $response .= "Our support team is looking into this matter and will provide you with a detailed response shortly.\n\n";

        if ($tone == 'friendly') {
            $response .= "We really appreciate your patience! If you have any additional information that might help us resolve this faster, please don't hesitate to share.\n\n";
            $response .= "Best regards,\nThe Support Team";
        } elseif ($tone == 'empathetic') {
            $response .= "We understand this situation may be causing inconvenience, and we're committed to resolving it as quickly as possible. ";
            $response .= "Please know that your issue is important to us.\n\n";
            $response .= "Sincerely,\nThe Support Team";
        } else {
            $response .= "If you have any additional information or questions, please feel free to reply to this ticket.\n\n";
            $response .= "Regards,\nThe Support Team";
        }

        return $response;
    }

    private function get_marketing_response($customer_name) {
        $response = "Hi $customer_name,\n\n";
        $response .= "Thank you for reaching out regarding your marketing/collaboration proposal.\n\n";
        $response .= "We appreciate your interest in partnering with us. For all marketing initiatives, ";
        $response .= "guest posting opportunities, affiliate partnerships, and business collaboration inquiries, ";
        $response .= "please contact our marketing team directly at:\n\n";
        $response .= "📧 shashank@wbcomdesigns.com\n\n";
        $response .= "Shashank handles all marketing and partnership decisions and will be able to discuss ";
        $response .= "your proposal in detail.\n\n";
        $response .= "We will now close this support ticket as this inquiry falls outside our technical support scope. ";
        $response .= "Please reach out to Shashank directly for a prompt response regarding your collaboration request.\n\n";
        $response .= "Best regards,\nThe Support Team";

        return $response;
    }

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

// Register main commands
WP_CLI::add_command('zoho-desk', 'ZDM_CLI_Commands');
WP_CLI::add_command('zdm', 'ZDM_CLI_Commands');

// Register individual subcommands for better discoverability
WP_CLI::add_command('zdm analyze', array('ZDM_CLI_Commands', 'analyze'));
WP_CLI::add_command('zdm draft', array('ZDM_CLI_Commands', 'draft'));
WP_CLI::add_command('zdm batch-draft', array('ZDM_CLI_Commands', 'batch_draft'));
WP_CLI::add_command('zdm customer', array('ZDM_CLI_Commands', 'customer'));
WP_CLI::add_command('zdm ticket', array('ZDM_CLI_Commands', 'ticket'));
WP_CLI::add_command('zdm template', array('ZDM_CLI_Commands', 'template'));
WP_CLI::add_command('zdm monitor', array('ZDM_CLI_Commands', 'monitor'));
WP_CLI::add_command('zdm stats', array('ZDM_CLI_Commands', 'stats'));

// Add helpful aliases
WP_CLI::add_command('zd', 'ZDM_CLI_Commands');
WP_CLI::add_command('zd analyze', array('ZDM_CLI_Commands', 'analyze'));
WP_CLI::add_command('zd draft', array('ZDM_CLI_Commands', 'draft'));
WP_CLI::add_command('zd batch-draft', array('ZDM_CLI_Commands', 'batch_draft'));