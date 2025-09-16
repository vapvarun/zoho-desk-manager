=== Zoho Desk Manager ===
Contributors: wbcomdesigns
Tags: zoho, zoho desk, support tickets, helpdesk, customer support
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional WordPress plugin for managing Zoho Desk support tickets directly from your WordPress admin dashboard.

== Description ==

Zoho Desk Manager brings your Zoho Desk support system directly into WordPress, allowing you to manage customer tickets without leaving your admin dashboard.

= Key Features =

* **Real-Time Dashboard Widget** - Monitor tickets requiring attention directly from WordPress dashboard
* **OAuth 2.0 Authentication** - Secure connection to Zoho Desk API
* **Complete Ticket Management** - View, reply to, and update ticket status
* **Full Conversation Threading** - Access complete ticket history including all replies
* **Auto-Refresh Widget** - Dashboard updates every 60 seconds with new ticket information
* **Priority Indicators** - Visual alerts for urgent, overdue, and pending tickets
* **Browser Notifications** - Optional desktop alerts for urgent tickets
* **Quick Preview** - View ticket details without leaving the dashboard
* **Status Filtering** - Quickly filter tickets by Open, On Hold, or Closed status
* **Rich Text Replies** - Reply to tickets with formatted content using WordPress editor
* **Real-time Updates** - AJAX-powered status updates without page reload
* **Smart Caching** - Reduces API calls with intelligent caching system
* **Rate Limiting** - Respects Zoho API limits to prevent throttling
* **Auto Token Refresh** - Maintains connection with automatic token renewal
* **Debug Mode** - Built-in tools for troubleshooting API issues

= Perfect For =

* WordPress agencies managing client support
* WooCommerce stores handling customer inquiries
* Membership sites with support needs
* Any WordPress site using Zoho Desk for customer service

= API Integration =

The plugin integrates with multiple Zoho Desk API endpoints:
* Tickets listing and details
* Thread conversations
* Comments and internal notes
* Status updates
* Reply sending

== Installation ==

1. Upload the `zoho-desk-manager` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Zoho Desk Manager → Settings** to configure

= Configuration Steps =

1. **Create Zoho API Credentials:**
   - Visit [Zoho API Console](https://api-console.zoho.com/)
   - Add a new Server-based Application
   - Note your Client ID and Client Secret

2. **Get Organization ID:**
   - Log into Zoho Desk
   - Go to Setup → Developer Space → API
   - Copy your Organization ID

3. **Connect to Zoho:**
   - Enter credentials in plugin settings
   - Click "Connect to Zoho Desk"
   - Authorize the application

== Frequently Asked Questions ==

= What permissions does the plugin need? =

The plugin requires these Zoho Desk API scopes:
* Desk.tickets.ALL - Full ticket access
* Desk.basic.READ - Read basic information
* Desk.search.READ - Search capabilities

= Is my data secure? =

Yes. The plugin uses OAuth 2.0 for authentication and all API credentials are stored securely in your WordPress database. No data is sent to third parties.

= Can multiple users access the tickets? =

Yes, any WordPress user with 'manage_options' capability (administrators) can access and manage tickets.

= Does it support multiple Zoho Desk organizations? =

Currently, the plugin supports one Zoho Desk organization per WordPress installation.

= How often does the cache refresh? =

* Ticket list: 5 minutes
* Individual tickets: 1 minute
* Conversations: 2 minutes
* You can force refresh anytime

= What happens if I exceed API limits? =

The plugin includes rate limiting to prevent exceeding Zoho's API limits (50 requests/minute). It will automatically pause requests if limits are approached.

== Screenshots ==

1. Ticket list view with status filters
2. Individual ticket details with full conversation history
3. Reply to ticket interface with rich text editor
4. Settings page for API configuration
5. Connection status and quick actions

== Changelog ==

= 1.0.0 =
* Initial release
* OAuth 2.0 authentication
* Ticket viewing and management
* Reply functionality
* Status updates
* Conversation threading
* Caching system
* Rate limiting
* AJAX updates
* Debug mode

== Upgrade Notice ==

= 1.0.0 =
First release of Zoho Desk Manager. Please backup your site before installation.

== Developer Information ==

= Hooks and Filters =

**Actions:**
* `zdm_after_ticket_reply` - After reply is sent
* `zdm_after_status_update` - After status is updated
* `zdm_after_token_refresh` - After OAuth token refresh

**Filters:**
* `zdm_ticket_list_params` - Modify ticket list parameters
* `zdm_reply_content` - Filter reply content before sending
* `zdm_ticket_statuses` - Customize available statuses

= Requirements =

* WordPress 5.0+
* PHP 7.2+
* SSL certificate (for OAuth)
* Zoho Desk account with API access

== Support ==

For support, feature requests, or bug reports, please visit our [support forum](https://wordpress.org/support/plugin/zoho-desk-manager/) or [GitHub repository](https://github.com/wbcomdesigns/zoho-desk-manager).

== Privacy Policy ==

This plugin connects to Zoho Desk API (external service) to fetch and manage support tickets. Data transmission occurs between your WordPress site and Zoho's servers. Please review [Zoho's Privacy Policy](https://www.zoho.com/privacy.html) for information on how they handle data.