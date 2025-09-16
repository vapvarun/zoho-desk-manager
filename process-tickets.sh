#!/bin/bash

# Zoho Desk Ticket Processor
# Process tickets and generate AI drafts using terminal

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Banner
echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║     🤖 Zoho Desk AI Ticket Processor      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""

# Check if WP-CLI is installed
if ! command -v wp &> /dev/null; then
    echo -e "${RED}Error: WP-CLI is not installed.${NC}"
    echo "Please install WP-CLI from: https://wp-cli.org/"
    exit 1
fi

# Set WordPress path (adjust if needed)
WP_PATH="/Users/varundubey/Local Sites/reign-demo/app/public"

# Function to show menu
show_menu() {
    echo -e "${GREEN}Select an option:${NC}"
    echo "1) Process all open tickets (interactive)"
    echo "2) Process tickets (auto-save drafts)"
    echo "3) Process specific number of tickets"
    echo "4) View saved drafts"
    echo "5) View ticket statistics"
    echo "6) Watch for new tickets (live monitoring)"
    echo "7) Send a saved draft"
    echo "8) Clear all drafts"
    echo "9) Test API connection"
    echo "0) Exit"
    echo ""
}

# Function to process tickets interactively
process_interactive() {
    echo -e "${YELLOW}Starting interactive ticket processing...${NC}"
    wp zoho-desk process --interactive --path="$WP_PATH"
}

# Function to process tickets with auto-save
process_autosave() {
    echo -e "${YELLOW}Processing tickets with auto-save...${NC}"
    wp zoho-desk process --auto-save --path="$WP_PATH"
}

# Function to process specific number of tickets
process_limited() {
    read -p "Enter number of tickets to process: " limit
    read -p "Interactive mode? (y/n): " interactive

    cmd="wp zoho-desk process --limit=$limit"
    if [ "$interactive" = "y" ]; then
        cmd="$cmd --interactive"
    else
        cmd="$cmd --auto-save"
    fi

    echo -e "${YELLOW}Processing $limit tickets...${NC}"
    eval "$cmd --path='$WP_PATH'"
}

# Function to view drafts
view_drafts() {
    echo -e "${BLUE}Viewing saved drafts...${NC}"
    wp zoho-desk drafts --path="$WP_PATH"
}

# Function to view statistics
view_stats() {
    echo -e "${BLUE}Fetching ticket statistics...${NC}"
    wp zoho-desk stats --path="$WP_PATH"
}

# Function to watch for new tickets
watch_tickets() {
    read -p "Check interval in seconds (default 300): " interval
    interval=${interval:-300}

    read -p "Auto-generate drafts? (y/n): " autodraft

    cmd="wp zoho-desk watch --interval=$interval"
    if [ "$autodraft" = "y" ]; then
        cmd="$cmd --auto-draft"
    fi

    echo -e "${YELLOW}Starting ticket monitor (Ctrl+C to stop)...${NC}"
    eval "$cmd --path='$WP_PATH'"
}

# Function to send draft
send_draft() {
    read -p "Enter ticket ID: " ticket_id
    read -p "Edit before sending? (y/n): " edit

    cmd="wp zoho-desk send-draft $ticket_id"
    if [ "$edit" = "y" ]; then
        cmd="$cmd --edit"
    fi

    eval "$cmd --path='$WP_PATH'"
}

# Function to clear drafts
clear_drafts() {
    echo -e "${RED}⚠️  Warning: This will delete all saved drafts!${NC}"
    read -p "Are you sure? (y/n): " confirm

    if [ "$confirm" = "y" ]; then
        wp zoho-desk clear-drafts --yes --path="$WP_PATH"
    else
        echo "Cancelled."
    fi
}

# Function to test connection
test_connection() {
    echo -e "${BLUE}Testing Zoho Desk API connection...${NC}"
    wp zoho-desk test-connection --path="$WP_PATH"
}

# Main loop
while true; do
    show_menu
    read -p "Enter your choice: " choice
    echo ""

    case $choice in
        1) process_interactive ;;
        2) process_autosave ;;
        3) process_limited ;;
        4) view_drafts ;;
        5) view_stats ;;
        6) watch_tickets ;;
        7) send_draft ;;
        8) clear_drafts ;;
        9) test_connection ;;
        0)
            echo -e "${GREEN}Goodbye!${NC}"
            exit 0
            ;;
        *)
            echo -e "${RED}Invalid option. Please try again.${NC}"
            ;;
    esac

    echo ""
    echo -e "${BLUE}────────────────────────────────────────────${NC}"
    echo ""
done