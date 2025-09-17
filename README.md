# Zoho Desk Manager

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-2.0.0-orange)](https://wbcomdesigns.com)

Advanced Zoho Desk integration for WordPress with AI-powered draft responses, customer history tracking, and comprehensive CLI tools.

## Features

- **Ticket Management**: View, respond, and manage Zoho Desk tickets directly from WordPress
- **AI-Powered Drafts**: Generate intelligent draft responses with customer context
- **Customer History**: Pull complete customer ticket history for context-aware support
- **Template System**: Custom Post Type based template management with auto-tagging
- **Marketing Detection**: Auto-detect and route marketing inquiries
- **Batch Processing**: Process multiple tickets at once
- **Advanced Analytics**: Track ticket stats and customer patterns
- **WP-CLI Integration**: Comprehensive command-line tools for automation

## Quick Start

### Installation
1. Upload to `/wp-content/plugins/zoho-desk-manager/`
2. Activate plugin
3. Configure at **Zoho Desk → Settings**
4. Add OAuth credentials from [Zoho API Console](https://api-console.zoho.com/)

## WP-CLI Commands

### Main Commands

```bash
# Show all available commands
wp zdm

# Use alias for shorter commands
wp zd <command>
```

### 1. Draft Generation

Generate AI-powered draft responses with full conversation context (included by default):

```bash
# Generate draft for a ticket (reads full conversation by default)
wp zdm draft <ticket_id>

# With options
wp zdm draft 123456 --tone=friendly --auto-tag

# Skip conversation threads for simple tickets
wp zdm draft 123456 --skip-threads

# Available tones: professional (default), friendly, empathetic
```

### 2. Batch Draft Processing

Process multiple tickets at once:

```bash
# Generate drafts for all open tickets
wp zdm batch-draft --status=Open

# Process high priority tickets with auto-tagging
wp zdm batch-draft --priority=High --auto-tag

# Process limited tickets with friendly tone
wp zdm batch-draft --limit=5 --tone=friendly

# Skip tickets that already have drafts
wp zdm batch-draft --skip-existing

# Dry run to preview what would be processed
wp zdm batch-draft --status=Open --dry-run
```

### 3. Customer History

Get complete customer context and ticket history:

```bash
# Look up customer by email
wp zdm customer john@example.com

# Look up customer from ticket ID
wp zdm customer 123456

# Get detailed history with behavior patterns
wp zdm customer john@example.com --format=detailed --show-patterns

# Export customer data as JSON
wp zdm customer john@example.com --format=json > customer-data.json

# Include closed tickets
wp zdm customer john@example.com --include-closed
```

### 4. Ticket Management

```bash
# List tickets with filters
wp zdm ticket list --status=Open --limit=20
wp zdm ticket list --priority=High --format=table

# Show ticket details with conversation
wp zdm ticket show 123456

# Update ticket status
wp zdm ticket update 123456 --status=Closed

# Manage ticket tags
wp zdm ticket tags add 123456 billing urgent
wp zdm ticket tags remove 123456 resolved
wp zdm ticket tags list
```

### 5. Template Management

Manage response templates:

```bash
# List all templates
wp zdm template list
wp zdm template list --category=billing --format=table

# Show specific template
wp zdm template show greeting

# Process template with ticket data
wp zdm template process greeting 123456

# Auto-tag all templates based on content
wp zdm template retag
```

### 6. Intelligent Analysis

Analyze tickets and apply smart tags:

```bash
# Comprehensive ticket analysis
wp zdm analyze 123456

# Auto-apply suggested tags
wp zdm analyze 123456 --auto-apply

# Include conversation context
wp zdm analyze 123456 --include-threads

# Use specific template for analysis
wp zdm analyze 123456 --template=password_reset

# Dry run to see what would be tagged
wp zdm analyze 123456 --dry-run
```

### 7. Statistics & Monitoring

```bash
# Get statistics for different periods
wp zdm stats --period=today
wp zdm stats --period=week --format=json
wp zdm stats --period=month --detailed

# Monitor tickets in real-time
wp zdm monitor --interval=30 --status=Open
wp zdm monitor --priority=High --alerts --auto-tag
```

## Key Features Explained

### Marketing Inquiry Detection

The system automatically detects marketing/collaboration requests and:
- Generates appropriate responses directing to shashank@wbcomdesigns.com
- Flags tickets for closure
- Prevents wasting support time on non-support inquiries

### Customer Context Awareness

When generating drafts, the system:
- Loads customer's complete ticket history
- Detects if they're new or returning customers
- Identifies repeat issues
- Customizes responses based on their history

### Template Auto-Tagging

Templates are automatically tagged based on:
- Content analysis (keywords, patterns)
- Sentiment detection
- Common issue types
- 27+ predefined categories

## Command Options Reference

### Common Options

- `--format`: Output format (table, json, csv, yaml, ids, count)
- `--limit`: Number of results to return
- `--status`: Filter by status (Open, Closed, On Hold, Escalated)
- `--priority`: Filter by priority (Low, Normal, High, Urgent)

### Draft Options

- `--tone`: Response tone (professional, friendly, empathetic)
- `--template`: Use specific template
- `--auto-tag`: Automatically tag tickets
- `--skip-threads`: Don't include conversation history
- `--ai-provider`: Specify AI provider (claude, openai, gemini)

### Analysis Options

- `--include-threads`: Include full conversation
- `--auto-apply`: Apply suggested tags automatically
- `--dry-run`: Preview without making changes

## Best Practices

1. **Always Review Drafts**: AI-generated drafts are suggestions - always review before sending
2. **Use Customer History**: Check customer context before responding to understand their journey
3. **Batch Processing**: Use batch-draft for efficient ticket handling during busy periods
4. **Template Management**: Keep templates updated and properly tagged for better suggestions
5. **Marketing Filtering**: Let the system auto-detect and route marketing inquiries

## Configuration

### Required Settings

1. **Zoho OAuth Setup**:
   - Client ID and Secret from [Zoho API Console](https://api-console.zoho.com/)
   - Authorized redirect URI: `your-site.com/wp-admin/admin.php?page=zoho-desk-settings`

2. **AI Configuration** (Optional):
   - Add API keys for Claude, OpenAI, or Gemini
   - Or use Browser AI mode with existing subscriptions

3. **Template Configuration**:
   - Templates are managed as Custom Post Types
   - Access via Zoho Desk → Templates in admin

## Troubleshooting

### Common Issues

**No tickets found for customer**:
- The system searches up to 200 tickets
- Very old tickets might not be found
- Try using the search command for extended lookups

**Draft generation fails**:
- Check if AI API keys are configured
- System will use fallback templates if AI is unavailable
- Ensure conversation threads are being loaded (default behavior)

**Templates not showing**:
- Run `wp zdm template retag` to rebuild template tags
- Check template CPT registration

## Support

- **Documentation**: `/docs` folder
- **Marketing Inquiries**: shashank@wbcomdesigns.com
- **Technical Support**: support@wbcomdesigns.com
- **Website**: https://wbcomdesigns.com

## Changelog

### Version 2.0.0
- Added customer history tracking
- Implemented batch draft processing
- Added template CPT system with auto-tagging
- Marketing inquiry auto-detection
- Conversation threads included by default in drafts
- Comprehensive CLI command structure
- Pattern analysis for customer behavior

### Version 1.0.0
- Initial release
- Basic ticket management
- AI response generation

## License

GPL v2 or later

---

**Version**: 2.0.0 | **Author**: WBComDesigns | **Maintained by**: Varun Dubey