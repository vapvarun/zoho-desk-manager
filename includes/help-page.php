<?php
/**
 * Help & Commands Page
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the help page
 */
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
                    <li><strong>Connect to Zoho:</strong> Click "Connect to Zoho" to authorize the application</li>
                    <li><strong>Test Connection:</strong> Run <code>wp zoho-desk test-connection</code> in terminal</li>
                    <li><strong>Process Tickets:</strong> Use <code>wp zoho-desk process --auto-save</code> to generate drafts</li>
                </ol>
            </div>

            <!-- Command Reference Section -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">WP-CLI Commands</h2>
                <p>All commands start with <code>wp zoho-desk</code></p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Command</th>
                            <th style="width: 40%;">Description</th>
                            <th style="width: 30%;">Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>process</code></td>
                            <td>Process tickets and generate AI drafts</td>
                            <td><code>wp zoho-desk process --auto-save</code></td>
                        </tr>
                        <tr>
                            <td><code>drafts</code></td>
                            <td>View all saved draft responses</td>
                            <td><code>wp zoho-desk drafts</code></td>
                        </tr>
                        <tr>
                            <td><code>stats</code></td>
                            <td>Show ticket statistics and summary</td>
                            <td><code>wp zoho-desk stats</code></td>
                        </tr>
                        <tr>
                            <td><code>send-draft</code></td>
                            <td>Send a saved draft to Zoho Desk</td>
                            <td><code>wp zoho-desk send-draft [TICKET_ID]</code></td>
                        </tr>
                        <tr>
                            <td><code>watch</code></td>
                            <td>Monitor for new tickets continuously</td>
                            <td><code>wp zoho-desk watch --auto-draft</code></td>
                        </tr>
                        <tr>
                            <td><code>clear-drafts</code></td>
                            <td>Clear all saved drafts</td>
                            <td><code>wp zoho-desk clear-drafts --yes</code></td>
                        </tr>
                        <tr>
                            <td><code>test-connection</code></td>
                            <td>Test API connection to Zoho Desk</td>
                            <td><code>wp zoho-desk test-connection</code></td>
                        </tr>
                        <tr>
                            <td><code>help</code></td>
                            <td>Show detailed help information</td>
                            <td><code>wp zoho-desk help</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Process Command Options -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Process Command Options</h2>
                <p>The <code>process</code> command is the most powerful tool for batch ticket processing:</p>

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
                            <td>Filter tickets by status (Open, On Hold, Closed)</td>
                            <td><code>--status="Open"</code></td>
                        </tr>
                        <tr>
                            <td><code>--limit</code></td>
                            <td>Maximum number of tickets to process</td>
                            <td><code>--limit=10</code></td>
                        </tr>
                        <tr>
                            <td><code>--interactive</code></td>
                            <td>Review each draft before saving</td>
                            <td><code>--interactive</code></td>
                        </tr>
                        <tr>
                            <td><code>--auto-save</code></td>
                            <td>Save all drafts automatically</td>
                            <td><code>--auto-save</code></td>
                        </tr>
                        <tr>
                            <td><code>--force</code></td>
                            <td>Regenerate existing drafts</td>
                            <td><code>--force</code></td>
                        </tr>
                    </tbody>
                </table>

                <h3>Interactive Mode Options</h3>
                <p>When using <code>--interactive</code>, you'll get these choices for each ticket:</p>
                <ol>
                    <li><strong>Save draft</strong> - Save and continue to next ticket</li>
                    <li><strong>Edit draft</strong> - Manually edit the draft</li>
                    <li><strong>Regenerate</strong> - Generate with different tone</li>
                    <li><strong>Skip</strong> - Skip this ticket</li>
                    <li><strong>Save and send</strong> - Send immediately to Zoho</li>
                </ol>
            </div>

            <!-- Common Workflows -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Common Workflows</h2>

                <h3>Daily Morning Routine</h3>
                <pre style="background: #f0f0f0; padding: 10px; border-radius: 3px;">
# Check current statistics
wp zoho-desk stats

# Process overnight tickets
wp zoho-desk process --status="Open" --limit=50 --auto-save

# Review generated drafts
wp zoho-desk drafts</pre>

                <h3>Urgent Ticket Processing</h3>
                <pre style="background: #f0f0f0; padding: 10px; border-radius: 3px;">
# Process tickets interactively for immediate review
wp zoho-desk process --status="Open" --limit=10 --interactive

# Send specific draft after review
wp zoho-desk send-draft [TICKET_ID]</pre>

                <h3>Continuous Monitoring</h3>
                <pre style="background: #f0f0f0; padding: 10px; border-radius: 3px;">
# Watch for new tickets and auto-generate drafts
wp zoho-desk watch --interval=60 --auto-draft</pre>
            </div>

            <!-- Tone and Response Types -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">AI Draft Customization</h2>

                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <h3>Tone Options</h3>
                        <ul>
                            <li><strong>professional</strong> - Standard business tone</li>
                            <li><strong>friendly</strong> - Warm and conversational</li>
                            <li><strong>formal</strong> - Corporate communication</li>
                            <li><strong>technical</strong> - Detailed technical language</li>
                            <li><strong>empathetic</strong> - Understanding and supportive</li>
                        </ul>
                    </div>

                    <div style="flex: 1;">
                        <h3>Response Types</h3>
                        <ul>
                            <li><strong>solution</strong> - Provide solution/resolution</li>
                            <li><strong>follow_up</strong> - Check on progress</li>
                            <li><strong>clarification</strong> - Request more information</li>
                            <li><strong>escalation</strong> - Escalate to senior support</li>
                            <li><strong>closing</strong> - Close resolved ticket</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Shortcuts -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Terminal Shortcuts</h2>
                <p>Add these to your <code>~/.bashrc</code> or <code>~/.zshrc</code> for quick access:</p>
                <pre style="background: #f0f0f0; padding: 10px; border-radius: 3px;">
# Zoho Desk shortcuts
alias zd='wp zoho-desk'
alias zd-stats='wp zoho-desk stats'
alias zd-process='wp zoho-desk process --interactive'
alias zd-drafts='wp zoho-desk drafts'
alias zd-watch='wp zoho-desk watch --auto-draft'</pre>
                <p>Then use: <code>zd-stats</code> instead of <code>wp zoho-desk stats</code></p>
            </div>

            <!-- Interactive Script -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Interactive Menu Script</h2>
                <p>For easier command-line usage, run the interactive menu:</p>
                <pre style="background: #f0f0f0; padding: 10px; border-radius: 3px;">
cd <?php echo ZDM_PLUGIN_PATH; ?>
./process-tickets.sh</pre>
                <p>This provides a numbered menu with all options for easy selection.</p>
            </div>

            <!-- Troubleshooting -->
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">Troubleshooting</h2>

                <h3>Connection Issues</h3>
                <pre style="background: #f0f0f0; padding: 10px; border-radius: 3px;">
# Test API connection
wp zoho-desk test-connection

# Check credentials
wp option get zdm_client_id
wp option get zdm_org_id

# Force token refresh
wp option delete zdm_access_token
wp zoho-desk test-connection</pre>

                <h3>Draft Management</h3>
                <pre style="background: #f0f0f0; padding: 10px; border-radius: 3px;">
# Clear specific draft
wp transient delete zdm_draft_[TICKET_ID]

# Clear all drafts
wp zoho-desk clear-drafts --yes</pre>

                <h3>Rate Limiting</h3>
                <p>If you hit API rate limits (45 calls/minute), process tickets in smaller batches:</p>
                <pre style="background: #f0f0f0; padding: 10px; border-radius: 3px;">
wp zoho-desk process --limit=5 --auto-save
# Wait before next batch
sleep 60
wp zoho-desk process --limit=5 --auto-save</pre>
            </div>

            <!-- Getting Help -->
            <div class="card">
                <h2 style="margin-top: 0;">Getting More Help</h2>

                <h3>In Terminal</h3>
                <ul>
                    <li><code>wp zoho-desk</code> - List all commands</li>
                    <li><code>wp zoho-desk help</code> - Detailed help</li>
                    <li><code>wp help zoho-desk [command]</code> - Command-specific help</li>
                </ul>

                <h3>Documentation Files</h3>
                <ul>
                    <li><strong>COMMANDS.md</strong> - Complete command reference</li>
                    <li><strong>QUICK-REFERENCE.txt</strong> - Printable cheat sheet</li>
                    <li><strong>CLI-USAGE.md</strong> - Detailed CLI usage guide</li>
                    <li><strong>README.md</strong> - General plugin documentation</li>
                </ul>
                <p>All documentation files are located in: <code><?php echo ZDM_PLUGIN_PATH; ?></code></p>
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
    .zdm-help-container h2 {
        color: #23282d;
        font-size: 1.3em;
        font-weight: 600;
    }
    .zdm-help-container h3 {
        color: #23282d;
        font-size: 1.1em;
        font-weight: 600;
        margin-top: 15px;
    }
    .zdm-help-container code {
        background: #f3f4f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: Consolas, Monaco, monospace;
        font-size: 13px;
    }
    .zdm-help-container pre {
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    .zdm-help-container table {
        margin-top: 10px;
    }
    .zdm-help-container table th {
        font-weight: 600;
    }
    .zdm-help-container ul, .zdm-help-container ol {
        margin-left: 20px;
    }
    </style>
    <?php
}