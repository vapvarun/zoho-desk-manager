# Zoho Desk Manager - AI Prompt Generation Guide

## Overview
The Zoho Desk Manager plugin supports two AI modes:
1. **Browser AI Mode** - Uses your existing ChatGPT Plus or Claude Pro subscription
2. **API Mode** - Direct API integration with OpenAI, Claude, or Gemini

## How to Generate AI Prompts

### Method 1: From Individual Ticket View

1. **Navigate to a Ticket**
   - Go to WordPress Admin → Zoho Desk → All Tickets
   - Click on any ticket to view its details

2. **Find the Draft Section**
   - Scroll down to the blue "Draft Response" section
   - You'll see a button labeled "Generate AI Draft"

3. **Click Generate AI Draft**
   - The AI options panel will appear
   - Select your preferences:
     - **Response Type**: Solution, Follow-up, Clarification, etc.
     - **Tone**: Professional, Friendly, Empathetic, etc.

4. **Generate the Prompt**
   - Click "Generate with Options"
   - If using Browser AI mode, a modal will appear with:
     - The generated prompt
     - Copy button
     - Link to open ChatGPT/Claude
     - Field to paste the AI response

### Method 2: Using WP-CLI (Batch Processing)

For processing multiple tickets at once:

```bash
# Generate draft for a specific ticket
wp zdm draft generate 233992000080219017

# Process all open tickets
wp zdm draft batch --status=open

# Process with specific tone
wp zdm draft generate 233992000080219017 --tone=empathetic

# Process with specific response type
wp zdm draft generate 233992000080219017 --type=solution
```

### Method 3: Keyboard Shortcuts

When viewing a ticket:
- **Ctrl/Cmd + G** - Generate AI draft
- **Ctrl/Cmd + S** - Save draft

## Browser AI Workflow

### Step 1: Enable Browser AI
1. Go to WordPress Admin → Zoho Desk → AI Settings
2. Click on "Browser AI" tab
3. Toggle "Use Browser AI" to ON
4. Select your provider (ChatGPT or Claude)
5. Save settings

### Step 2: Generate Prompt
1. Open a ticket
2. Click "Generate AI Draft"
3. Select options and click "Generate with Options"

### Step 3: Use the Modal
When the modal appears:

1. **Copy the Prompt**
   - Click "📋 Copy Prompt" button
   - The prompt is now in your clipboard

2. **Open AI Provider**
   - Click "🔗 Open ChatGPT" or "🔗 Open Claude"
   - This opens in a new tab

3. **Get AI Response**
   - Paste the prompt in ChatGPT/Claude
   - Press Enter to generate response
   - Copy the entire AI response

4. **Use the Response**
   - Return to WordPress tab
   - Paste the response in the text area
   - Click "Use This Response"
   - The response is now in your draft

## API Mode Workflow

### Step 1: Configure API
1. Go to WordPress Admin → Zoho Desk → AI Settings
2. Click on "API Keys" tab
3. Enter your API key for OpenAI, Claude, or Gemini
4. Select default provider
5. Save settings

### Step 2: Generate Response
1. Open a ticket
2. Click "Generate AI Draft"
3. Select options
4. Click "Generate with Options"
5. Response appears directly in draft area

## Available Options

### Response Types
- **Solution/Resolution** - Provides fix for the issue
- **Follow-up** - Checking on previous interaction
- **Clarification** - Requesting more information
- **Escalation** - Elevating to higher support
- **Closing/Resolved** - Closing ticket message

### Tone Options
- **Professional** - Formal business tone
- **Friendly** - Warm and approachable
- **Empathetic** - Understanding and caring
- **Concise** - Brief and to the point
- **Technical** - Detailed technical language

## Customizing Prompts

### Edit System Prompts
1. Go to AI Settings → System Prompts tab
2. Modify templates for different response types
3. Use variables:
   - `{customer_name}` - Customer's name
   - `{ticket_subject}` - Ticket subject
   - `{priority}` - Ticket priority
   - `{product_area}` - Product/service area

### Example Custom Prompt Template
```
You are a senior support agent for {company_name}.
Customer {customer_name} has reported: {ticket_subject}
Priority: {priority}

Craft a {tone} response that:
1. Acknowledges the issue
2. Provides clear next steps
3. Sets expectations for resolution
4. Maintains our brand voice
```

## Tips for Better Prompts

1. **Include Context**
   - The plugin automatically includes ticket history
   - Add company-specific knowledge in System Prompts

2. **Be Specific**
   - Use detailed response types
   - Select appropriate tone for customer segment

3. **Iterate and Improve**
   - Use "Improve Draft" button after generation
   - Options: more concise, detailed, friendly, professional

4. **Save Templates**
   - Frequently used responses can be saved
   - Access via "Load Saved Draft" button

## Troubleshooting

### "No AI provider configured" Error
- Enable Browser AI mode in settings, OR
- Add API keys in API Keys tab

### Modal Doesn't Appear
- Check JavaScript console for errors
- Ensure Browser AI is enabled
- Clear browser cache

### Prompt Too Long for AI
- Reduce conversation history in settings
- Summarize ticket manually first

### API Rate Limits
- Switch to Browser AI mode
- Implement caching in settings
- Use batch processing during off-hours

## Advanced Features

### Batch Processing
Process multiple tickets efficiently:
```bash
# Generate drafts for all tickets
wp zdm draft batch

# Only high priority
wp zdm draft batch --priority=high

# Specific date range
wp zdm draft batch --since="2024-01-01"
```

### Auto-Save
- Drafts auto-save every 2 seconds while typing
- Browser localStorage backup
- Recover unsaved drafts on page reload

### Keyboard Navigation
- Tab through options
- Enter to submit
- Escape to close modals

## Security Notes

- API keys are encrypted in database
- Browser AI doesn't store credentials
- Prompts are not logged by default
- Enable debug mode only for troubleshooting

## Support

For issues or questions:
- Check help documentation: WordPress Admin → Zoho Desk → Help
- Review error logs: `wp-content/plugins/zoho-desk-manager/logs/`
- Contact support with ticket ID and error message

---

*Last Updated: September 2024*
*Version: 1.0*