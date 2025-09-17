<?php
/**
 * Help Page
 *
 * @package ZohoDesk
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function zdm_help_page() {
    ?>
    <div class="wrap">
        <h1>Zoho Desk Manager - Help & Commands</h1>

        <div class="zdm-help-container" style="max-width: 1200px; margin-top: 20px;">

            <!-- Quick Start Section -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Quick Start</h2>
                <p>Get started with Zoho Desk Manager in minutes:</p>
                <ol>
                    <li><strong>Configure Settings:</strong> Go to Settings page and enter your Zoho API credentials</li>
                    <li><strong>Connect to Zoho:</strong> Click "Connect with Zoho Desk" to authorize the application</li>
                    <li><strong>Setup Templates:</strong> Create response templates in Zoho Desk → Templates</li>
                    <li><strong>Generate Drafts:</strong> Use <code>wp zdm draft &lt;ticket_id&gt;</code> to create draft responses</li>
                    <li><strong>Check Customer History:</strong> Use <code>wp zdm customer &lt;email&gt;</code> for context</li>
                </ol>
            </div>

            <!-- Main Commands Section -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">WP-CLI Commands</h2>
                <p>All commands start with <code>wp zdm</code> (or use alias <code>wp zd</code>)</p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Command</th>
                            <th style="width: 45%;">Description</th>
                            <th style="width: 30%;">Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>draft</code></td>
                            <td>Generate AI draft response (includes conversation by default)</td>
                            <td><code>wp zdm draft 123456</code></td>
                        </tr>
                        <tr>
                            <td><code>batch-draft</code></td>
                            <td>Process multiple tickets at once</td>
                            <td><code>wp zdm batch-draft --status=Open</code></td>
                        </tr>
                        <tr>
                            <td><code>customer</code></td>
                            <td>Get customer history and context</td>
                            <td><code>wp zdm customer john@example.com</code></td>
                        </tr>
                        <tr>
                            <td><code>ticket</code></td>
                            <td>Manage tickets (list, show, update, tags)</td>
                            <td><code>wp zdm ticket list --status=Open</code></td>
                        </tr>
                        <tr>
                            <td><code>template</code></td>
                            <td>Manage response templates</td>
                            <td><code>wp zdm template list</code></td>
                        </tr>
                        <tr>
                            <td><code>analyze</code></td>
                            <td>Analyze ticket and suggest tags</td>
                            <td><code>wp zdm analyze 123456 --auto-apply</code></td>
                        </tr>
                        <tr>
                            <td><code>stats</code></td>
                            <td>View ticket statistics</td>
                            <td><code>wp zdm stats --period=today</code></td>
                        </tr>
                        <tr>
                            <td><code>monitor</code></td>
                            <td>Real-time ticket monitoring</td>
                            <td><code>wp zdm monitor --interval=30</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Draft Command Options -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Draft Generation Options</h2>
                <p>The <code>draft</code> command is the primary tool for generating responses. <strong>Conversation threads are included by default!</strong></p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Option</th>
                            <th style="width: 45%;">Description</th>
                            <th style="width: 30%;">Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>--tone</code></td>
                            <td>Response tone: professional, friendly, empathetic</td>
                            <td><code>--tone=friendly</code></td>
                        </tr>
                        <tr>
                            <td><code>--template</code></td>
                            <td>Use specific template as base</td>
                            <td><code>--template=greeting</code></td>
                        </tr>
                        <tr>
                            <td><code>--auto-tag</code></td>
                            <td>Automatically tag ticket based on content</td>
                            <td><code>--auto-tag</code></td>
                        </tr>
                        <tr>
                            <td><code>--skip-threads</code></td>
                            <td>Skip conversation history (not recommended)</td>
                            <td><code>--skip-threads</code></td>
                        </tr>
                        <tr>
                            <td><code>--ai-provider</code></td>
                            <td>Choose AI provider: claude, openai, gemini</td>
                            <td><code>--ai-provider=claude</code></td>
                        </tr>
                    </tbody>
                </table>

                <div style="background: #fff9e6; padding: 10px; margin-top: 15px; border-left: 4px solid #ffc107;">
                    <strong>⚠️ Important:</strong> Drafts are ALWAYS saved as internal comments only. They are never auto-sent to customers. Support agents must review and manually send responses.
                </div>
            </div>

            <!-- Batch Processing Options -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Batch Processing</h2>
                <p>Process multiple tickets efficiently with <code>batch-draft</code>:</p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Option</th>
                            <th style="width: 45%;">Description</th>
                            <th style="width: 30%;">Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>--status</code></td>
                            <td>Filter by status (Open, Closed, On Hold)</td>
                            <td><code>--status=Open</code></td>
                        </tr>
                        <tr>
                            <td><code>--priority</code></td>
                            <td>Filter by priority (Low, Normal, High)</td>
                            <td><code>--priority=High</code></td>
                        </tr>
                        <tr>
                            <td><code>--limit</code></td>
                            <td>Maximum tickets to process (default: 10)</td>
                            <td><code>--limit=5</code></td>
                        </tr>
                        <tr>
                            <td><code>--skip-existing</code></td>
                            <td>Skip tickets with existing drafts</td>
                            <td><code>--skip-existing</code></td>
                        </tr>
                        <tr>
                            <td><code>--dry-run</code></td>
                            <td>Preview what would be done</td>
                            <td><code>--dry-run</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Customer History -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Customer History Analysis</h2>
                <p>Get complete customer context with the <code>customer</code> command:</p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Option</th>
                            <th style="width: 45%;">Description</th>
                            <th style="width: 30%;">Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>--format</code></td>
                            <td>Output format: summary, detailed, table, json</td>
                            <td><code>--format=detailed</code></td>
                        </tr>
                        <tr>
                            <td><code>--show-patterns</code></td>
                            <td>Analyze customer behavior patterns</td>
                            <td><code>--show-patterns</code></td>
                        </tr>
                        <tr>
                            <td><code>--include-closed</code></td>
                            <td>Include closed tickets in history</td>
                            <td><code>--include-closed</code></td>
                        </tr>
                        <tr>
                            <td><code>--limit</code></td>
                            <td>Maximum tickets to retrieve</td>
                            <td><code>--limit=50</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Special Features -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Special Features</h2>

                <h3>🚫 Marketing Inquiry Detection</h3>
                <p>The system automatically detects marketing/collaboration requests and:</p>
                <ul>
                    <li>Generates response directing to <strong>shashank@wbcomdesigns.com</strong></li>
                    <li>Flags ticket for closure</li>
                    <li>Adds warning for support agents</li>
                </ul>

                <h3>🏷️ Template Auto-Tagging</h3>
                <p>Run <code>wp zdm template retag</code> to automatically tag all templates based on:</p>
                <ul>
                    <li>Content keywords and patterns</li>
                    <li>Sentiment analysis</li>
                    <li>27+ predefined categories</li>
                </ul>

                <h3>👤 Customer Context in Drafts</h3>
                <p>Draft generation automatically includes:</p>
                <ul>
                    <li>Customer's complete ticket history</li>
                    <li>Detection of new vs returning customers</li>
                    <li>Identification of repeat issues</li>
                    <li>Customized greetings based on history</li>
                </ul>

                <h3>💬 Full Conversation Context</h3>
                <p><strong>ENABLED BY DEFAULT:</strong> Conversation threads are automatically included when generating drafts!</p>
                <ul>
                    <li>Reads entire ticket conversation automatically</li>
                    <li>Ensures responses have complete context</li>
                    <li>Prevents missing important customer information</li>
                    <li>Use <code>--skip-threads</code> only if you explicitly don't need conversation history</li>
                </ul>
            </div>

            <!-- Common Examples -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Common Workflows</h2>

                <h3>Morning Ticket Processing</h3>
                <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
# Check overnight tickets
wp zdm stats --period=today

# Process all open high priority tickets
wp zdm batch-draft --priority=High --status=Open

# Review specific customer's history
wp zdm customer john@example.com --show-patterns

# Generate draft for specific ticket
wp zdm draft 123456 --tone=friendly --auto-tag</pre>

                <h3>Customer Issue Investigation</h3>
                <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
# Get customer's full history
wp zdm customer 123456 --format=detailed --show-patterns

# Analyze their current ticket
wp zdm analyze 123456

# Generate context-aware response with full conversation history
wp zdm draft 123456</pre>

                <h3>Template Management</h3>
                <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
# List all templates
wp zdm template list

# Auto-tag all templates
wp zdm template retag

# Test template with ticket data
wp zdm template process greeting 123456</pre>

                <h3>Quick Response for Specific Issues</h3>
                <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
# Marketing inquiry - will auto-detect and redirect
wp zdm draft 123456

# Password reset issue
wp zdm draft 123456 --template=password_reset

# Billing inquiry with empathetic tone
wp zdm draft 123456 --template=billing --tone=empathetic</pre>
            </div>

            <!-- Ticket Management -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Ticket Management Commands</h2>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Command</th>
                            <th style="width: 70%;">Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>List tickets</td>
                            <td><code>wp zdm ticket list --status=Open --limit=20</code></td>
                        </tr>
                        <tr>
                            <td>Show ticket details</td>
                            <td><code>wp zdm ticket show 123456</code></td>
                        </tr>
                        <tr>
                            <td>Update ticket status</td>
                            <td><code>wp zdm ticket update 123456 --status=Closed</code></td>
                        </tr>
                        <tr>
                            <td>Add tags</td>
                            <td><code>wp zdm ticket tags add 123456 billing urgent</code></td>
                        </tr>
                        <tr>
                            <td>Remove tags</td>
                            <td><code>wp zdm ticket tags remove 123456 resolved</code></td>
                        </tr>
                        <tr>
                            <td>List all tags</td>
                            <td><code>wp zdm ticket tags list</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Troubleshooting -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Troubleshooting</h2>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Issue</th>
                            <th style="width: 70%;">Solution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Draft generation fails</td>
                            <td>Check AI API keys in settings. System will use fallback templates if AI unavailable.</td>
                        </tr>
                        <tr>
                            <td>Customer not found</td>
                            <td>System searches recent tickets first, then uses search API. Very old customers may need manual lookup.</td>
                        </tr>
                        <tr>
                            <td>Templates not showing</td>
                            <td>Run <code>wp zdm template retag</code> to rebuild template database.</td>
                        </tr>
                        <tr>
                            <td>Missing context in responses</td>
                            <td>Conversation threads are included by default. Only use <code>--skip-threads</code> for standalone tickets without history.</td>
                        </tr>
                        <tr>
                            <td>Marketing tickets not detected</td>
                            <td>Check ticket content for keywords like "guest post", "affiliate", "partnership", "collaboration".</td>
                        </tr>
                        <tr>
                            <td>Commands not found</td>
                            <td>Ensure WP-CLI is installed and you're in the WordPress root directory.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Support Info -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Support & Contact</h2>
                <ul>
                    <li><strong>Technical Support:</strong> support@wbcomdesigns.com</li>
                    <li><strong>Marketing Inquiries:</strong> shashank@wbcomdesigns.com</li>
                    <li><strong>Documentation:</strong> Check the README.md file in the plugin directory</li>
                    <li><strong>Version:</strong> 2.0.0</li>
                    <li><strong>Last Updated:</strong> <?php echo date('F Y'); ?></li>
                </ul>

                <h3>Quick Command Reference</h3>
                <pre style="background: #f5f5f5; padding: 10px;">
# Show all commands
wp zdm

# Get help for specific command
wp help zdm draft

# Use shorter alias
wp zd draft 123456

# Check plugin version
wp plugin get zoho-desk-manager --field=version</pre>
            </div>

        </div>
    </div>

    <style>
        .zdm-help-container .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .zdm-help-container h3 {
            margin-top: 20px;
            color: #23282d;
        }
        .zdm-help-container code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .zdm-help-container pre {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .zdm-help-container table {
            margin-top: 15px;
        }
    </style>
    <?php
}