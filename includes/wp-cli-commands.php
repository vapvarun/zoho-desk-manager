<?php
/**
 * WP-CLI Commands for Zoho Desk Manager
 *
 * Provides command-line interface for ticket management and AI processing
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
 * Manage Zoho Desk tickets from the command line
 *
 * ## DESCRIPTION
 *
 * This command provides tools for managing Zoho Desk tickets, generating
 * AI-powered draft responses, and monitoring ticket activity.
 *
 * ## EXAMPLES
 *
 *     # Show all available commands
 *     wp zoho-desk
 *
 *     # Process tickets interactively
 *     wp zoho-desk process --interactive
 *
 *     # View ticket statistics
 *     wp zoho-desk stats
 *
 * @package ZohoDeskManager
 */
class ZDM_CLI_Commands {

    /**
     * Process tickets and generate AI draft responses
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Ticket status to filter (Open, On Hold, Closed)
     * default: Open
     *
     * [--limit=<number>]
     * : Maximum number of tickets to process
     * default: 50
     *
     * [--interactive]
     * : Enable interactive mode to review each draft
     *
     * [--auto-save]
     * : Automatically save all generated drafts
     *
     * [--force]
     * : Regenerate drafts even if they already exist
     *
     * ## EXAMPLES
     *
     *     # Process all open tickets interactively
     *     wp zoho-desk process --interactive
     *
     *     # Process 10 tickets and auto-save drafts
     *     wp zoho-desk process --limit=10 --auto-save
     *
     *     # Process on-hold tickets with force regeneration
     *     wp zoho-desk process --status="On Hold" --force
     *
     * @when after_wp_load
     */
    public function process($args, $assoc_args) {
        require_once ZDM_PLUGIN_PATH . 'includes/class-cli-processor.php';
        ZDM_CLI_Processor::process_tickets($args, $assoc_args);
    }

    /**
     * View all saved draft responses
     *
     * ## EXAMPLES
     *
     *     wp zoho-desk drafts
     *
     * @when after_wp_load
     */
    public function drafts($args, $assoc_args) {
        require_once ZDM_PLUGIN_PATH . 'includes/class-cli-processor.php';
        ZDM_CLI_Processor::view_drafts($args, $assoc_args);
    }

    /**
     * Clear all saved drafts
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     wp zoho-desk clear-drafts
     *     wp zoho-desk clear-drafts --yes
     *
     * @when after_wp_load
     */
    public function clear_drafts($args, $assoc_args) {
        require_once ZDM_PLUGIN_PATH . 'includes/class-cli-processor.php';
        ZDM_CLI_Processor::clear_drafts($args, $assoc_args);
    }

    /**
     * Search for tickets
     *
     * ## OPTIONS
     *
     * <query>
     * : Search query (email, keyword, ticket number, or URL)
     *
     * [--type=<type>]
     * : Search type: all, email, subject, content, ticket_number
     * default: all
     *
     * [--limit=<number>]
     * : Maximum number of results
     * default: 10
     *
     * [--format=<format>]
     * : Output format: table, json, csv
     * default: table
     *
     * ## EXAMPLES
     *
     *     # Smart search (auto-detects email/number/content)
     *     wp zdm search customer@example.com
     *     wp zdm search "#1234"
     *     wp zdm search "login problem"
     *
     *     # Specific search types
     *     wp zdm search customer@example.com --type=email
     *     wp zdm search "refund" --type=content --limit=20
     *
     *     # Output formats
     *     wp zdm search "urgent" --format=json
     *     wp zdm search customer@example.com --format=csv
     *
     * @when after_wp_load
     */
    public function search($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please provide a search query.");
        }

        $query = $args[0];
        $type = WP_CLI\Utils\get_flag_value($assoc_args, 'type', 'all');
        $limit = WP_CLI\Utils\get_flag_value($assoc_args, 'limit', 10);
        $format = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

        WP_CLI::line("Searching for: \"$query\" (type: $type)");

        $api = new ZDM_Zoho_API();
        $results = $api->search_tickets($query, $type, array('limit' => $limit));

        if (!$results || !isset($results['data'])) {
            WP_CLI::warning("No tickets found or search failed.");
            return;
        }

        $tickets = $results['data'];
        WP_CLI::success(sprintf("Found %d ticket(s)", count($tickets)));

        // Prepare data for output
        $output_data = array();
        foreach ($tickets as $ticket) {
            $output_data[] = array(
                'ID' => $ticket['id'],
                'Number' => '#' . $ticket['ticketNumber'],
                'Subject' => substr($ticket['subject'], 0, 50) . (strlen($ticket['subject']) > 50 ? '...' : ''),
                'Status' => $ticket['status'],
                'Priority' => $ticket['priority'] ?? 'Normal',
                'Customer' => $ticket['email'] ?? $ticket['contactId'],
                'Created' => date('M d, Y', strtotime($ticket['createdTime']))
            );
        }

        if ($format === 'table') {
            WP_CLI\Utils\format_items('table', $output_data, array('Number', 'Subject', 'Status', 'Priority', 'Customer', 'Created'));
        } elseif ($format === 'json') {
            WP_CLI::line(json_encode($output_data, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            WP_CLI\Utils\format_items('csv', $output_data, array('ID', 'Number', 'Subject', 'Status', 'Priority', 'Customer', 'Created'));
        }
    }

    /**
     * Manage response templates
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform: list, show, use
     *
     * [<template_key>]
     * : Template key (required for show and use actions)
     *
     * [<ticket_id>]
     * : Ticket ID (required for use action)
     *
     * [--format=<format>]
     * : Output format: table, json, yaml
     * default: table
     *
     * ## EXAMPLES
     *
     *     # List all templates
     *     wp zdm template list
     *
     *     # Show specific template
     *     wp zdm template show password_reset
     *
     *     # Use template for ticket (generates content)
     *     wp zdm template use greeting 233992000080219017
     *
     * @when after_wp_load
     */
    public function template($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please specify an action: list, show, or use");
        }

        $action = $args[0];
        $format = WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

        require_once ZDM_PLUGIN_PATH . 'includes/class-template-manager.php';

        switch ($action) {
            case 'list':
                $templates = ZDM_Template_Manager::get_templates();
                $output_data = array();

                foreach ($templates as $key => $template) {
                    $output_data[] = array(
                        'Key' => $key,
                        'Name' => $template['name'],
                        'Category' => ucfirst($template['category']),
                        'Variables' => count($template['variables'])
                    );
                }

                if ($format === 'table') {
                    WP_CLI\Utils\format_items('table', $output_data, array('Key', 'Name', 'Category', 'Variables'));
                } else {
                    WP_CLI\Utils\format_items($format, $output_data, array('Key', 'Name', 'Category', 'Variables'));
                }
                break;

            case 'show':
                if (empty($args[1])) {
                    WP_CLI::error("Please specify a template key");
                }

                $template = ZDM_Template_Manager::get_template($args[1]);
                if (!$template) {
                    WP_CLI::error("Template '{$args[1]}' not found");
                }

                WP_CLI::line("Template: " . $template['name']);
                WP_CLI::line("Category: " . ucfirst($template['category']));
                WP_CLI::line("Variables: " . implode(', ', $template['variables']));
                WP_CLI::line("");
                WP_CLI::line("Content:");
                WP_CLI::line("--------");
                WP_CLI::line($template['content']);
                break;

            case 'use':
                if (empty($args[1]) || empty($args[2])) {
                    WP_CLI::error("Please specify template key and ticket ID");
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
                break;

            default:
                WP_CLI::error("Unknown action: $action. Use list, show, or use");
        }
    }

    /**
     * Test Zoho Desk API connection
     *
     * ## EXAMPLES
     *
     *     wp zoho-desk test-connection
     *
     * @when after_wp_load
     */
    public function test_connection($args, $assoc_args) {
        WP_CLI::line("Testing Zoho Desk API connection...");

        $api = new ZDM_Zoho_API();
        $tickets = $api->get_tickets(array('limit' => 1));

        if ($tickets && isset($tickets['data'])) {
            WP_CLI::success("Connection successful! API is working.");
            WP_CLI::line("Organization ID: " . get_option('zdm_org_id'));
        } else {
            WP_CLI::error("Connection failed. Please check your API credentials.");
        }
    }

    /**
     * Get ticket statistics
     *
     * ## EXAMPLES
     *
     *     wp zoho-desk stats
     *
     * @when after_wp_load
     */
    public function stats($args, $assoc_args) {
        WP_CLI::line("Fetching ticket statistics...");

        $api = new ZDM_Zoho_API();

        // Fetch tickets with different statuses
        $open = $api->get_tickets(array('status' => 'Open', 'limit' => 100));
        $on_hold = $api->get_tickets(array('status' => 'On Hold', 'limit' => 100));
        $closed = $api->get_tickets(array('status' => 'Closed', 'limit' => 100));

        $open_count = isset($open['data']) ? count($open['data']) : 0;
        $hold_count = isset($on_hold['data']) ? count($on_hold['data']) : 0;
        $closed_count = isset($closed['data']) ? count($closed['data']) : 0;

        // Calculate urgency metrics
        $urgent_count = 0;
        $overdue_count = 0;

        if (isset($open['data'])) {
            foreach ($open['data'] as $ticket) {
                if (isset($ticket['priority']) && $ticket['priority'] === 'High') {
                    $urgent_count++;
                }
                if (isset($ticket['dueDate']) && strtotime($ticket['dueDate']) < time()) {
                    $overdue_count++;
                }
            }
        }

        // Display statistics table
        $headers = array('Status', 'Count');
        $data = array(
            array('Open', $open_count),
            array('On Hold', $hold_count),
            array('Closed', $closed_count),
            array('Urgent (High Priority)', $urgent_count),
            array('Overdue', $overdue_count),
            array('Total', $open_count + $hold_count + $closed_count)
        );

        WP_CLI\Utils\format_items('table', $data, $headers);

        // Show drafts count
        global $wpdb;
        $draft_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_zdm_draft_%'
             AND option_name NOT LIKE '%_meta_%'"
        );

        WP_CLI::line("");
        WP_CLI::line("📝 Saved Drafts: " . $draft_count);
    }

    /**
     * Send a draft response to a ticket
     *
     * ## OPTIONS
     *
     * <ticket_id>
     * : The ticket ID to send the draft for
     *
     * [--edit]
     * : Edit the draft before sending
     *
     * ## EXAMPLES
     *
     *     wp zoho-desk send-draft 233992000080219017
     *     wp zoho-desk send-draft 233992000080219017 --edit
     *
     * @when after_wp_load
     */
    public function send_draft($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error("Please provide a ticket ID.");
        }

        $ticket_id = $args[0];
        $draft = get_transient('zdm_draft_' . $ticket_id);

        if (!$draft) {
            WP_CLI::error("No draft found for ticket ID: {$ticket_id}");
        }

        WP_CLI::line("Draft content:");
        WP_CLI::line("-------------------------------------------");
        WP_CLI::line($draft);
        WP_CLI::line("-------------------------------------------");

        if (isset($assoc_args['edit'])) {
            WP_CLI::line("Enter your edited version (type 'END' on a new line when done):");
            $edited = '';
            while (true) {
                $line = trim(fgets(STDIN));
                if ($line === 'END') {
                    break;
                }
                $edited .= $line . "\n";
            }
            if (!empty($edited)) {
                $draft = trim($edited);
            }
        }

        WP_CLI::line("Send this draft? (y/n): ");
        $confirm = trim(fgets(STDIN));

        if (strtolower($confirm) !== 'y') {
            WP_CLI::line("Cancelled.");
            return;
        }

        $api = new ZDM_Zoho_API();
        $result = $api->reply_to_ticket($ticket_id, $draft);

        if ($result) {
            WP_CLI::success("Draft sent successfully!");
            // Clear the draft
            delete_transient('zdm_draft_' . $ticket_id);
            delete_transient('zdm_draft_meta_' . $ticket_id);
        } else {
            WP_CLI::error("Failed to send draft.");
        }
    }

    /**
     * Display help and available commands
     *
     * ## EXAMPLES
     *
     *     wp zoho-desk help
     *     wp zoho-desk
     *
     * @when after_wp_load
     */
    public function help($args = array(), $assoc_args = array()) {
        WP_CLI::line("");
        WP_CLI::line(WP_CLI::colorize("%b╔════════════════════════════════════════════════════════════╗%n"));
        WP_CLI::line(WP_CLI::colorize("%b║       📚 ZOHO DESK MANAGER - COMMAND REFERENCE            ║%n"));
        WP_CLI::line(WP_CLI::colorize("%b╚════════════════════════════════════════════════════════════╝%n"));
        WP_CLI::line("");

        WP_CLI::line(WP_CLI::colorize("%g🎯 PRIMARY COMMANDS:%n"));
        WP_CLI::line("");

        $commands = array(
            array('Command', 'Description', 'Common Usage'),
            array('-------', '-----------', '------------'),
            array(
                'process',
                'Generate AI drafts for tickets',
                'wp zoho-desk process --auto-save'
            ),
            array(
                'search',
                'Search for tickets by email/keyword',
                'wp zd search "tanase@example.com"'
            ),
            array(
                'template',
                'Manage response templates',
                'wp zd template list'
            ),
            array(
                'drafts',
                'View all saved drafts',
                'wp zoho-desk drafts'
            ),
            array(
                'stats',
                'Show ticket statistics',
                'wp zoho-desk stats'
            ),
            array(
                'send-draft',
                'Send a saved draft',
                'wp zoho-desk send-draft [ID]'
            ),
            array(
                'watch',
                'Monitor new tickets',
                'wp zoho-desk watch --auto-draft'
            ),
            array(
                'clear-drafts',
                'Remove all drafts',
                'wp zoho-desk clear-drafts --yes'
            ),
            array(
                'test-connection',
                'Test API connection',
                'wp zoho-desk test-connection'
            ),
            array(
                'help',
                'Show this help menu',
                'wp zoho-desk help'
            )
        );

        foreach ($commands as $row) {
            if ($row[0] === 'Command') {
                WP_CLI::line(sprintf(
                    WP_CLI::colorize("%y%-15s %-35s %-35s%n"),
                    $row[0],
                    $row[1],
                    $row[2]
                ));
            } elseif ($row[0] === '-------') {
                WP_CLI::line(sprintf(
                    "%-15s %-35s %-35s",
                    str_repeat('-', 15),
                    str_repeat('-', 35),
                    str_repeat('-', 35)
                ));
            } else {
                WP_CLI::line(sprintf(
                    WP_CLI::colorize("%%c%-15s%%n %-35s %%g%-35s%%n"),
                    $row[0],
                    $row[1],
                    $row[2]
                ));
            }
        }

        WP_CLI::line("");
        WP_CLI::line(WP_CLI::colorize("%g🔧 PROCESS COMMAND OPTIONS:%n"));
        WP_CLI::line("");
        WP_CLI::line("  --status=<status>    Filter by status (Open, On Hold, Closed)");
        WP_CLI::line("  --limit=<number>     Maximum tickets to process");
        WP_CLI::line("  --interactive        Review each draft before saving");
        WP_CLI::line("  --auto-save         Save all drafts automatically");
        WP_CLI::line("  --force             Regenerate existing drafts");
        WP_CLI::line("");

        WP_CLI::line(WP_CLI::colorize("%g📖 QUICK EXAMPLES:%n"));
        WP_CLI::line("");
        WP_CLI::line(WP_CLI::colorize("  %c# Process tickets interactively%n"));
        WP_CLI::line("  wp zoho-desk process --interactive");
        WP_CLI::line("");
        WP_CLI::line(WP_CLI::colorize("  %c# Auto-generate drafts for 10 tickets%n"));
        WP_CLI::line("  wp zoho-desk process --limit=10 --auto-save");
        WP_CLI::line("");
        WP_CLI::line(WP_CLI::colorize("  %c# Monitor for new tickets%n"));
        WP_CLI::line("  wp zoho-desk watch --interval=60 --auto-draft");
        WP_CLI::line("");

        WP_CLI::line(WP_CLI::colorize("%g🎮 INTERACTIVE MODE:%n"));
        WP_CLI::line("");
        WP_CLI::line("  When using --interactive, you can:");
        WP_CLI::line("  1. Save draft");
        WP_CLI::line("  2. Edit draft");
        WP_CLI::line("  3. Regenerate with different tone");
        WP_CLI::line("  4. Skip");
        WP_CLI::line("  5. Save and send immediately");
        WP_CLI::line("");

        WP_CLI::line(WP_CLI::colorize("%g📚 DOCUMENTATION:%n"));
        WP_CLI::line("");
        WP_CLI::line("  Full docs: /wp-content/plugins/zoho-desk-manager/COMMANDS.md");
        WP_CLI::line("  README:    /wp-content/plugins/zoho-desk-manager/README.md");
        WP_CLI::line("  CLI Guide: /wp-content/plugins/zoho-desk-manager/CLI-USAGE.md");
        WP_CLI::line("");

        WP_CLI::line(WP_CLI::colorize("%g💡 TIPS:%n"));
        WP_CLI::line("");
        WP_CLI::line("  • Start with 'wp zoho-desk stats' to see ticket overview");
        WP_CLI::line("  • Use --interactive mode to learn the system");
        WP_CLI::line("  • Process in small batches (--limit=5) initially");
        WP_CLI::line("  • Always review drafts before sending");
        WP_CLI::line("  • Set up aliases: alias zd='wp zoho-desk'");
        WP_CLI::line("");

        WP_CLI::line(WP_CLI::colorize("%b════════════════════════════════════════════════════════════%n"));
        WP_CLI::line("");
    }

    /**
     * List all available commands (default command)
     *
     * Shows a summary of all available subcommands when no specific command is provided.
     *
     * ## EXAMPLES
     *
     *     wp zoho-desk
     *
     * @when after_wp_load
     * @subcommand list
     * @alias ls
     */
    public function list_commands($args = array(), $assoc_args = array()) {
        WP_CLI::line("");
        WP_CLI::line(WP_CLI::colorize("%b╔════════════════════════════════════════════════════════════╗%n"));
        WP_CLI::line(WP_CLI::colorize("%b║       🎯 ZOHO DESK MANAGER - AVAILABLE COMMANDS           ║%n"));
        WP_CLI::line(WP_CLI::colorize("%b╚════════════════════════════════════════════════════════════╝%n"));
        WP_CLI::line("");

        WP_CLI::line(WP_CLI::colorize("%yUsage:%n wp zoho-desk <command> [options]"));
        WP_CLI::line("");

        WP_CLI::line(WP_CLI::colorize("%gAVAILABLE COMMANDS:%n"));
        WP_CLI::line("");

        $commands = array(
            'process'         => 'Process tickets and generate AI draft responses',
            'search'          => 'Search for tickets by email, keyword, ticket number, or URL',
            'template'        => 'Manage response templates (list, show, use)',
            'drafts'          => 'View all saved draft responses',
            'stats'           => 'Display ticket statistics and metrics',
            'send-draft'      => 'Send a saved draft response to Zoho Desk',
            'watch'           => 'Monitor for new tickets in real-time',
            'clear-drafts'    => 'Remove all saved drafts',
            'test-connection' => 'Test Zoho Desk API connection',
            'help'            => 'Show detailed help and examples',
            'list'            => 'List all available commands (this screen)'
        );

        foreach ($commands as $cmd => $desc) {
            WP_CLI::line(sprintf("  %-18s %s",
                WP_CLI::colorize("%c{$cmd}%n"),
                $desc
            ));
        }

        WP_CLI::line("");
        WP_CLI::line(WP_CLI::colorize("%gQUICK START:%n"));
        WP_CLI::line("");
        WP_CLI::line("  1. Check connection:  " . WP_CLI::colorize("%ywp zoho-desk test-connection%n"));
        WP_CLI::line("  2. View statistics:   " . WP_CLI::colorize("%ywp zoho-desk stats%n"));
        WP_CLI::line("  3. Process tickets:   " . WP_CLI::colorize("%ywp zoho-desk process --interactive%n"));
        WP_CLI::line("  4. View drafts:       " . WP_CLI::colorize("%ywp zoho-desk drafts%n"));
        WP_CLI::line("");

        WP_CLI::line(WP_CLI::colorize("%gFor detailed help on any command:%n"));
        WP_CLI::line(WP_CLI::colorize("  %ywp help zoho-desk <command>%n"));
        WP_CLI::line(WP_CLI::colorize("  %ywp zoho-desk <command> --help%n"));
        WP_CLI::line("");

        WP_CLI::line(WP_CLI::colorize("%gFor complete documentation:%n"));
        WP_CLI::line(WP_CLI::colorize("  %ywp zoho-desk help%n"));
        WP_CLI::line("");
    }


    /**
     * Watch for new tickets and process them automatically
     *
     * ## OPTIONS
     *
     * [--interval=<seconds>]
     * : Check interval in seconds
     * default: 300 (5 minutes)
     *
     * [--auto-draft]
     * : Automatically generate drafts for new tickets
     *
     * ## EXAMPLES
     *
     *     wp zoho-desk watch
     *     wp zoho-desk watch --interval=60 --auto-draft
     *
     * @when after_wp_load
     */
    public function watch($args, $assoc_args) {
        $interval = isset($assoc_args['interval']) ? intval($assoc_args['interval']) : 300;
        $auto_draft = isset($assoc_args['auto-draft']);

        WP_CLI::line("👁️  Watching for new tickets (checking every {$interval} seconds)...");
        WP_CLI::line("Press Ctrl+C to stop.");
        WP_CLI::line("");

        $last_check = get_option('zdm_cli_last_check', current_time('mysql'));
        $api = new ZDM_Zoho_API();

        while (true) {
            $current_time = current_time('mysql');

            // Fetch recent tickets
            $tickets = $api->get_tickets(array(
                'status' => 'Open',
                'limit' => 20,
                'sortBy' => 'createdTime'
            ));

            if ($tickets && isset($tickets['data'])) {
                $new_tickets = array();

                foreach ($tickets['data'] as $ticket) {
                    if (strtotime($ticket['createdTime']) > strtotime($last_check)) {
                        $new_tickets[] = $ticket;
                    }
                }

                if (!empty($new_tickets)) {
                    WP_CLI::line("[" . date('H:i:s') . "] Found " . count($new_tickets) . " new ticket(s)!");

                    foreach ($new_tickets as $ticket) {
                        WP_CLI::line("  📧 #{$ticket['ticketNumber']}: {$ticket['subject']}");

                        if ($auto_draft) {
                            WP_CLI::line("     Generating draft...");

                            // Generate draft
                            $full_ticket = $api->get_ticket($ticket['id']);
                            $threads = $api->get_ticket_threads($ticket['id']);

                            if ($full_ticket) {
                                require_once ZDM_PLUGIN_PATH . 'includes/class-cli-processor.php';
                                $context = ZDM_CLI_Processor::prepare_conversation_context($full_ticket, $threads);
                                $draft = ZDM_CLI_Processor::generate_draft_with_terminal_ai($context);

                                if ($draft) {
                                    ZDM_CLI_Processor::save_draft($ticket['id'], $draft);
                                    WP_CLI::success("     Draft saved for ticket #{$ticket['ticketNumber']}");
                                }
                            }
                        }
                    }
                } else {
                    WP_CLI::line("[" . date('H:i:s') . "] No new tickets.");
                }
            }

            $last_check = $current_time;
            update_option('zdm_cli_last_check', $last_check);

            // Wait for next check
            sleep($interval);
        }
    }
}

// Register commands
WP_CLI::add_command('zoho-desk', 'ZDM_CLI_Commands');

// Add helpful aliases
WP_CLI::add_command('zd', 'ZDM_CLI_Commands');