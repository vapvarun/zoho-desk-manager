<?php
/**
 * Prepare Draft Response for Specific Ticket
 *
 * This script demonstrates how to prepare a draft response for ticket #35490
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. Please run this as an admin user.');
}

// Include required files
require_once('includes/class-zoho-api.php');
require_once('includes/class-ai-assistant.php');
require_once('includes/class-cli-processor.php');

// Ticket number to process
$ticket_number = '35490';

echo "=====================================\n";
echo "🤖 Preparing Draft for Ticket #$ticket_number\n";
echo "=====================================\n\n";

// Initialize API
$api = new ZDM_Zoho_API();

// Step 1: Find the ticket by number
echo "📋 Searching for ticket #$ticket_number...\n";

// Fetch recent tickets to find our ticket
$tickets_data = $api->get_tickets(array(
    'status' => 'Open',
    'limit' => 100
));

$target_ticket = null;
if ($tickets_data && isset($tickets_data['data'])) {
    foreach ($tickets_data['data'] as $ticket) {
        if ($ticket['ticketNumber'] == $ticket_number) {
            $target_ticket = $ticket;
            break;
        }
    }
}

if (!$target_ticket) {
    echo "❌ Ticket #$ticket_number not found. Please check the ticket number.\n";
    echo "\nAvailable open tickets:\n";
    if ($tickets_data && isset($tickets_data['data'])) {
        foreach (array_slice($tickets_data['data'], 0, 5) as $ticket) {
            echo "  - #{$ticket['ticketNumber']}: {$ticket['subject']}\n";
        }
    }
    exit;
}

echo "✅ Found ticket: {$target_ticket['subject']}\n\n";

// Step 2: Fetch full ticket details
echo "📥 Fetching ticket details...\n";
$ticket_id = $target_ticket['id'];
$full_ticket = $api->get_ticket($ticket_id);
$threads = $api->get_ticket_threads($ticket_id);

if (!$full_ticket) {
    echo "❌ Failed to fetch ticket details.\n";
    exit;
}

// Display ticket information
echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TICKET INFORMATION\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Ticket #: " . $full_ticket['ticketNumber'] . "\n";
echo "Subject: " . $full_ticket['subject'] . "\n";
echo "Customer: " . ($full_ticket['contact']['firstName'] ?? 'Unknown') . " " . ($full_ticket['contact']['lastName'] ?? '') . "\n";
echo "Email: " . ($full_ticket['email'] ?? 'N/A') . "\n";
echo "Priority: " . ($full_ticket['priority'] ?? 'Normal') . "\n";
echo "Status: " . $full_ticket['status'] . "\n";
echo "Created: " . date('Y-m-d H:i', strtotime($full_ticket['createdTime'])) . "\n";
echo "Category: " . ($full_ticket['category'] ?? 'General') . "\n";

// Display description
if (!empty($full_ticket['description'])) {
    echo "\nINITIAL DESCRIPTION:\n";
    echo "────────────────────\n";
    echo wordwrap(strip_tags($full_ticket['description']), 70) . "\n";
}

// Display conversation history
if (isset($threads['data']) && !empty($threads['data'])) {
    echo "\nCONVERSATION HISTORY (" . count($threads['data']) . " messages):\n";
    echo "────────────────────────────────────\n";

    foreach ($threads['data'] as $index => $thread) {
        $author_type = isset($thread['author']['type']) && $thread['author']['type'] === 'END_USER' ? '👤 Customer' : '🏢 Support';
        $author_name = $thread['author']['name'] ?? $thread['author']['email'] ?? 'Unknown';
        $time = date('Y-m-d H:i', strtotime($thread['createdTime'] ?? $thread['postedTime'] ?? ''));

        echo "\n[$author_type - $author_name] $time\n";

        // Get message content
        $content = $thread['content'] ??
                  $thread['plainText'] ??
                  $thread['richText'] ??
                  $thread['summary'] ??
                  'No content available';

        // Clean and display content
        $clean_content = strip_tags($content);
        $clean_content = wordwrap($clean_content, 70);
        echo $clean_content . "\n";
        echo "─────────────\n";
    }
}

// Step 3: Analyze the issue
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "ISSUE ANALYSIS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Prepare context for AI
$context = ZDM_CLI_Processor::prepare_conversation_context($full_ticket, $threads);

// Analyze issue type
$issue_keywords = array();
$description_lower = strtolower($full_ticket['description'] ?? '' . ' ' . $full_ticket['subject']);

if (strpos($description_lower, 'error') !== false) $issue_keywords[] = 'Error/Bug';
if (strpos($description_lower, 'login') !== false || strpos($description_lower, 'password') !== false) $issue_keywords[] = 'Login/Auth';
if (strpos($description_lower, 'payment') !== false || strpos($description_lower, 'refund') !== false) $issue_keywords[] = 'Payment/Billing';
if (strpos($description_lower, 'slow') !== false || strpos($description_lower, 'performance') !== false) $issue_keywords[] = 'Performance';
if (strpos($description_lower, 'install') !== false || strpos($description_lower, 'setup') !== false) $issue_keywords[] = 'Installation';
if (strpos($description_lower, 'update') !== false || strpos($description_lower, 'upgrade') !== false) $issue_keywords[] = 'Update/Upgrade';

echo "Issue Type(s): " . (!empty($issue_keywords) ? implode(', ', $issue_keywords) : 'General Support') . "\n";

// Determine urgency
$urgency = 'Normal';
if ($full_ticket['priority'] === 'High' || $full_ticket['priority'] === 'Urgent') {
    $urgency = 'High - Requires immediate attention';
} elseif (isset($full_ticket['dueDate']) && strtotime($full_ticket['dueDate']) < time()) {
    $urgency = 'Overdue - Past due date';
}
echo "Urgency: $urgency\n";

// Customer sentiment (basic analysis)
$last_customer_message = '';
if (isset($threads['data'])) {
    foreach ($threads['data'] as $thread) {
        if (isset($thread['author']['type']) && $thread['author']['type'] === 'END_USER') {
            $last_customer_message = $thread['content'] ?? $thread['plainText'] ?? '';
        }
    }
}

$sentiment = 'Neutral';
$negative_words = array('frustrated', 'angry', 'disappointed', 'unacceptable', 'terrible');
$positive_words = array('thank', 'appreciate', 'great', 'happy');

foreach ($negative_words as $word) {
    if (stripos($last_customer_message, $word) !== false) {
        $sentiment = 'Negative - Customer appears frustrated';
        break;
    }
}

foreach ($positive_words as $word) {
    if (stripos($last_customer_message, $word) !== false) {
        $sentiment = 'Positive';
        break;
    }
}

echo "Customer Sentiment: $sentiment\n";

// Step 4: Generate draft response
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🤖 GENERATING AI DRAFT RESPONSE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Determine appropriate response type based on analysis
$response_type = 'solution'; // Default
if (empty($threads['data']) || count($threads['data']) <= 1) {
    $response_type = 'solution'; // First response, provide solution
} elseif (count($threads['data']) > 5) {
    $response_type = 'escalation'; // Long thread, might need escalation
} elseif (stripos($last_customer_message, '?') !== false) {
    $response_type = 'clarification'; // Customer asking questions
}

// Determine tone based on sentiment
$tone = 'professional'; // Default
if ($sentiment === 'Negative - Customer appears frustrated') {
    $tone = 'empathetic';
} elseif ($full_ticket['priority'] === 'High') {
    $tone = 'formal';
}

echo "Response Type: " . ucfirst($response_type) . "\n";
echo "Tone: " . ucfirst($tone) . "\n\n";

// Generate the draft
$context['tone'] = $tone;
$draft = ZDM_CLI_Processor::generate_draft_with_terminal_ai($context);

echo "Generated Draft:\n";
echo "════════════════════════════════════════\n";
echo $draft;
echo "\n════════════════════════════════════════\n";

// Step 5: Save the draft
echo "\n💾 Saving draft...\n";
set_transient('zdm_draft_' . $ticket_id, $draft, 7 * DAY_IN_SECONDS);

$metadata = array(
    'generated_at' => current_time('mysql'),
    'generated_by' => 'Direct Script',
    'status' => 'draft',
    'ticket_number' => $ticket_number,
    'response_type' => $response_type,
    'tone' => $tone
);
set_transient('zdm_draft_meta_' . $ticket_id, $metadata, 7 * DAY_IN_SECONDS);

echo "✅ Draft saved successfully!\n\n";

// Step 6: Provide recommendations
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "💡 RECOMMENDATIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$recommendations = array();

if ($urgency === 'High - Requires immediate attention' || $urgency === 'Overdue - Past due date') {
    $recommendations[] = "⚠️  Send response immediately - ticket is " . strtolower($urgency);
}

if ($sentiment === 'Negative - Customer appears frustrated') {
    $recommendations[] = "🤝 Consider offering a goodwill gesture or discount";
    $recommendations[] = "📞 Suggest a phone call to resolve the issue faster";
}

if (count($threads['data'] ?? array()) > 5) {
    $recommendations[] = "🎯 Consider escalating to senior support";
    $recommendations[] = "📋 Review entire thread for any missed issues";
}

if (in_array('Payment/Billing', $issue_keywords)) {
    $recommendations[] = "💰 Review payment history before responding";
    $recommendations[] = "📜 Check refund policy compliance";
}

if (in_array('Error/Bug', $issue_keywords)) {
    $recommendations[] = "🐛 File a bug report with development team";
    $recommendations[] = "🔄 Provide workaround if available";
}

if (empty($recommendations)) {
    $recommendations[] = "✅ Standard response should be sufficient";
    $recommendations[] = "📊 Monitor for customer satisfaction after response";
}

foreach ($recommendations as $rec) {
    echo "  • $rec\n";
}

// Step 7: Next actions
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📌 NEXT ACTIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. Review the draft in WordPress admin:\n";
echo "   " . admin_url('admin.php?page=zoho-desk-manager&ticket_id=' . $ticket_id) . "\n\n";
echo "2. Or send directly via CLI:\n";
echo "   wp zoho-desk send-draft $ticket_id\n\n";
echo "3. Or edit and send interactively:\n";
echo "   wp zoho-desk send-draft $ticket_id --edit\n\n";

// Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ DRAFT PREPARATION COMPLETE\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Ticket #$ticket_number has been analyzed and a draft response has been generated.\n";
echo "The draft is saved and ready for review or sending.\n";

?>