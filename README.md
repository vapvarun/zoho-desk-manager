# Zoho Desk Manager for WordPress

[![WordPress Version](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange)](https://wbcomdesigns.com)

A professional WordPress plugin for managing Zoho Desk support tickets with AI-powered response generation directly from your WordPress admin dashboard.

## Key Features

### Ticket Management
- **Real-Time Dashboard**: Monitor tickets and statistics from WordPress dashboard
- **OAuth 2.0 Authentication**: Secure connection to Zoho Desk API
- **Complete Conversation History**: View all messages, replies, and agent responses
- **Advanced Filtering**: Filter by status (Open, On Hold, Closed)
- **Priority Indicators**: Visual alerts for urgent and overdue tickets
- **Auto-Refresh**: Dashboard updates every 60 seconds

### AI-Powered Response Generation
- **Browser AI Mode**: Use existing ChatGPT Plus or Claude Pro subscriptions
- **Direct API Integration**: Support for OpenAI, Claude, and Gemini APIs
- **Full Context Awareness**: AI sees complete conversation history
- **Multiple Response Types**: Solutions, follow-ups, clarifications, escalations
- **Tone Control**: Professional, friendly, empathetic, or technical
- **Draft Management**: Save, edit, and improve AI-generated responses

### Developer Features
- **WP-CLI Commands**: Batch process tickets from command line
- **Rate Limiting**: Smart API call management
- **Caching System**: Reduce API calls with intelligent caching
- **Extensible Architecture**: Hooks and filters for customization
- **Debug Mode**: Built-in tools for troubleshooting

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Zoho Desk account with API access
- SSL certificate (HTTPS) for OAuth redirect

## Installation

1. Upload the `zoho-desk-manager` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Zoho Desk Manager → Settings** to configure

## Configuration

### Step 1: Create Zoho API Credentials

1. Go to [Zoho API Console](https://api-console.zoho.com/)
2. Click "ADD CLIENT" and choose "Server-based Applications"
3. Enter the following details:
   - Client Name: Your application name
   - Homepage URL: Your website URL
   - Authorized Redirect URI: `https://yoursite.com/wp-admin/admin.php?page=zoho-desk-settings&action=oauth_callback`
4. Click "CREATE" and save your Client ID and Client Secret

### Step 2: Get Organization ID

1. Log in to your Zoho Desk account
2. Go to Setup → Developer Space → API
3. Copy your Organization ID

### Step 3: Connect to Zoho Desk

1. In WordPress, go to **Zoho Desk Manager → Settings**
2. Enter your Client ID, Client Secret, and Organization ID
3. Click "Save Settings"
4. Click "Connect to Zoho Desk" button
5. Authorize the application in Zoho

## Usage

### Viewing Tickets

1. Navigate to **Zoho Desk Manager** in WordPress admin
2. Use status filters to view Open, On Hold, or Closed tickets
3. Click "View & Reply" on any ticket to see full details

### Replying to Tickets

1. Open a ticket by clicking "View & Reply"
2. Scroll to the "Send Reply" section
3. Type your response using the rich text editor
4. Click "Send Reply"

### Updating Ticket Status

1. Open a ticket
2. Scroll to "Quick Actions"
3. Select new status from dropdown
4. Click "Update Status"

## API Endpoints Used

The plugin uses the following Zoho Desk API endpoints:

- `/tickets` - List all tickets
- `/tickets/{id}` - Get ticket details
- `/tickets/{id}/threads` - Get complete message threads
- `/tickets/{id}/conversations` - Get conversation metadata
- `/tickets/{id}/comments` - Get internal comments
- `/tickets/{id}/sendReply` - Send replies
- `/tickets/{id}` (PATCH) - Update ticket status

## Permissions Required

The plugin requires the following Zoho Desk API scopes:

- `Desk.tickets.ALL` - Full ticket access
- `Desk.basic.READ` - Read basic information
- `Desk.search.READ` - Search capabilities

## Troubleshooting

### Connection Issues

1. Verify your API credentials are correct
2. Check that your redirect URI matches exactly
3. Ensure your WordPress site uses HTTPS
4. Try disconnecting and reconnecting

### Missing Conversations

The plugin fetches data from multiple sources in this priority:
1. Threads API (primary source for actual messages)
2. Conversations API (metadata and structure)
3. Comments API (internal notes)

If messages are missing, check the API Test page to see raw responses.

### Debug Mode

Enable WordPress debug mode to see detailed API responses:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## Hooks and Filters

### Actions

- `zdm_after_ticket_reply` - Fired after a reply is sent
- `zdm_after_status_update` - Fired after ticket status is updated
- `zdm_after_token_refresh` - Fired after OAuth token is refreshed

### Filters

- `zdm_ticket_list_params` - Modify ticket list API parameters
- `zdm_reply_content` - Filter reply content before sending
- `zdm_ticket_statuses` - Customize available ticket statuses

## Security

- All API credentials are stored encrypted in WordPress options
- Nonce verification on all forms
- Capability checks for admin access
- Sanitization of all user inputs
- OAuth 2.0 for secure API authentication

## Changelog

### Version 1.0.0
- Initial release
- OAuth 2.0 authentication
- Ticket viewing and replying
- Status management
- Complete conversation threading
- Debug tools

## Support

For issues, feature requests, or questions, please contact support.

## License

GPL v2 or later

## Credits

Developed for managing Zoho Desk tickets within WordPress.