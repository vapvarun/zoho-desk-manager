# Zoho Desk Manager

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2-green)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange)](https://wbcomdesigns.com)

Manage Zoho Desk support tickets with AI-powered responses directly from WordPress.

## Features

- **Ticket Management**: View, reply, and update Zoho Desk tickets
- **AI Responses**: Generate drafts using ChatGPT, Claude, or API integration
- **Browser AI Mode**: Use your existing ChatGPT Plus/Claude Pro subscription
- **Full Context**: AI sees complete conversation history
- **WP-CLI Support**: Batch process tickets from command line
- **OAuth 2.0**: Secure Zoho Desk integration

## Quick Start

### Installation
1. Upload to `/wp-content/plugins/zoho-desk-manager/`
2. Activate plugin
3. Configure at **Zoho Desk → Settings**

### AI Setup
1. Go to **Zoho Desk → AI Settings**
2. Enable Browser AI or add API keys
3. Click "Generate AI Response" on any ticket

### Daily Usage
```bash
# View tickets
wp zdm ticket list

# Generate AI response
wp zdm draft generate <ticket-id>

# Batch process
wp zdm draft batch
```

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Zoho Desk account
- HTTPS (for OAuth)

## Configuration

1. **Zoho API Setup**: Get credentials from [Zoho API Console](https://api-console.zoho.com/)
2. **WordPress Setup**: Enter credentials in **Zoho Desk → Settings**
3. **Connect**: Click "Connect with Zoho Desk" and authorize

## Support

- **Documentation**: See `/docs` folder
- **Email**: support@wbcomdesigns.com
- **Website**: https://wbcomdesigns.com

## License

GPL v2 or later

---

**Version**: 1.0.0 | **Author**: WBComDesigns