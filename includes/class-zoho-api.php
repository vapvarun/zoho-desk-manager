<?php
/**
 * Zoho Desk API Handler
 *
 * Handles all API interactions with Zoho Desk including authentication,
 * ticket management, and conversation threading.
 *
 * @package ZohoDeskManager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZDM_Zoho_API {

    /**
     * Zoho Desk API base URL
     * @var string
     */
    private $api_base_url = 'https://desk.zoho.com/api/v1';

    /**
     * Zoho OAuth base URL
     * @var string
     */
    private $auth_base_url = 'https://accounts.zoho.com/oauth/v2';

    /**
     * Cache expiration time in seconds
     * @var int
     */
    private $cache_time = 300; // 5 minutes

    /**
     * Log API errors for debugging
     *
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function log_error($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Zoho Desk Manager] ' . $message . ' | Context: ' . print_r($context, true));
        }
    }

    /**
     * Get access token with automatic refresh
     *
     * @return string|false Access token or false on failure
     */
    public function get_access_token() {
        $access_token = get_option('zdm_access_token');
        $expires_at = get_option('zdm_token_expires');

        // Check if token is expired or about to expire (5 minutes buffer)
        if (empty($access_token) || time() > ($expires_at - 300)) {
            if (!$this->refresh_access_token()) {
                $this->log_error('Failed to refresh access token');
                return false;
            }
            $access_token = get_option('zdm_access_token');
        }

        return $access_token;
    }

    /**
     * Refresh access token using refresh token
     *
     * @return bool True on success, false on failure
     */
    private function refresh_access_token() {
        $refresh_token = get_option('zdm_refresh_token');
        $client_id = get_option('zdm_client_id');
        $client_secret = get_option('zdm_client_secret');

        if (empty($refresh_token) || empty($client_id) || empty($client_secret)) {
            $this->log_error('Missing credentials for token refresh', array(
                'has_refresh_token' => !empty($refresh_token),
                'has_client_id' => !empty($client_id),
                'has_client_secret' => !empty($client_secret)
            ));
            return false;
        }

        $response = wp_remote_post($this->auth_base_url . '/token', array(
            'body' => array(
                'refresh_token' => $refresh_token,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'refresh_token'
            )
        ));

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['access_token'])) {
                update_option('zdm_access_token', $body['access_token']);
                update_option('zdm_token_expires', time() + 3600); // Token valid for 1 hour
                do_action('zdm_after_token_refresh', $body['access_token']);
                return true;
            } else {
                $this->log_error('Invalid token refresh response', $body);
            }
        } else {
            $this->log_error('Token refresh request failed', array('error' => $response->get_error_message()));
        }

        return false;
    }

    /**
     * Get authorization URL for initial OAuth setup
     */
    public function get_auth_url() {
        $client_id = get_option('zdm_client_id');
        $redirect_uri = admin_url('admin.php?page=zoho-desk-settings&action=oauth_callback');

        $params = array(
            'scope' => 'Desk.tickets.ALL,Desk.basic.READ,Desk.search.READ',
            'client_id' => $client_id,
            'response_type' => 'code',
            'access_type' => 'offline',
            'redirect_uri' => $redirect_uri
        );

        return $this->auth_base_url . '/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access and refresh tokens
     *
     * @param string $code Authorization code from OAuth callback
     * @return bool True on success, false on failure
     */
    public function exchange_code_for_token($code) {
        $client_id = get_option('zdm_client_id');
        $client_secret = get_option('zdm_client_secret');
        $redirect_uri = admin_url('admin.php?page=zoho-desk-settings&action=oauth_callback');

        $response = wp_remote_post($this->auth_base_url . '/token', array(
            'body' => array(
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
            )
        ));

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['access_token']) && isset($body['refresh_token'])) {
                update_option('zdm_access_token', $body['access_token']);
                update_option('zdm_refresh_token', $body['refresh_token']);
                update_option('zdm_token_expires', time() + 3600);
                $this->log_error('Successfully exchanged code for tokens');
                return true;
            } else {
                $this->log_error('Failed to exchange code for token', $body);
            }
        } else {
            $this->log_error('Code exchange request failed', array('error' => $response->get_error_message()));
        }

        return false;
    }

    /**
     * Fetch tickets from Zoho Desk with caching
     *
     * @param array $params Query parameters for filtering tickets
     * @return array|false Tickets data or false on failure
     */
    public function get_tickets($params = array()) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            $this->log_error('Missing access token or org ID for ticket fetch');
            return false;
        }

        // Generate cache key
        $cache_key = 'zdm_tickets_' . md5(serialize($params));
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false && !isset($_GET['force_refresh'])) {
            return $cached_data;
        }

        $defaults = array(
            'limit' => 50,
            'status' => 'Open'
        );

        $params = wp_parse_args($params, $defaults);
        $params = apply_filters('zdm_ticket_list_params', $params);
        $url = $this->api_base_url . '/tickets?' . http_build_query($params);

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            )
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                // Cache the results
                set_transient($cache_key, $data, $this->cache_time);

                return $data;
            } else {
                $this->log_error('API returned non-200 status', array(
                    'endpoint' => 'tickets',
                    'status_code' => $code,
                    'response' => wp_remote_retrieve_body($response)
                ));
            }
        } else {
            $this->log_error('Failed to fetch tickets', array('error' => $response->get_error_message()));
        }

        return false;
    }

    /**
     * Search tickets by query
     */
    public function search_tickets($query, $search_type = 'all', $params = array()) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $limit = isset($params['limit']) ? $params['limit'] : 50;

        // Since Zoho Desk API search endpoints are limited/unavailable,
        // use client-side filtering by fetching tickets and filtering locally

        // Fetch more tickets to ensure we find matches (use reasonable limit)
        $fetch_limit = min(max($limit * 4, 50), 100); // Between 50 and 100 tickets

        // Get tickets from the regular endpoint
        $tickets_data = $this->get_tickets(array(
            'limit' => $fetch_limit,
            'sortBy' => 'createdTime'
        ));

        if (!$tickets_data || !isset($tickets_data['data'])) {
            return false;
        }

        $matched_tickets = array();

        foreach ($tickets_data['data'] as $ticket) {
            $match = false;

            switch ($search_type) {
                case 'email':
                    $match = stripos($ticket['email'] ?? '', $query) !== false;
                    break;
                case 'subject':
                    $match = stripos($ticket['subject'] ?? '', $query) !== false;
                    break;
                case 'content':
                    $match = stripos($ticket['description'] ?? '', $query) !== false ||
                            stripos($ticket['subject'] ?? '', $query) !== false;
                    break;
                case 'ticket_number':
                    $ticket_num = ltrim($query, '#');
                    $match = ($ticket['ticketNumber'] ?? '') == $ticket_num;
                    break;
                case 'all':
                default:
                    if (is_email($query)) {
                        $match = stripos($ticket['email'] ?? '', $query) !== false;
                    } elseif (preg_match('/^#?\d+$/', $query)) {
                        $ticket_num = ltrim($query, '#');
                        $match = ($ticket['ticketNumber'] ?? '') == $ticket_num;
                    } else {
                        // Search in subject, description, and email
                        $match = stripos($ticket['subject'] ?? '', $query) !== false ||
                                stripos($ticket['description'] ?? '', $query) !== false ||
                                stripos($ticket['email'] ?? '', $query) !== false;
                    }
                    break;
            }

            if ($match) {
                $matched_tickets[] = $ticket;

                // Stop if we have enough results
                if (count($matched_tickets) >= $limit) {
                    break;
                }
            }
        }

        // Format the response similar to the regular tickets API
        $search_results = array(
            'data' => $matched_tickets,
            'count' => count($matched_tickets)
        );

        // Cache search results for 5 minutes
        $cache_key = 'zdm_search_' . md5($query . $search_type . serialize($params));
        set_transient($cache_key, $search_results, 300);

        return $search_results;
    }


    /**
     * Parse flexible ticket input (ID, URL, number)
     */
    public static function parse_ticket_input($input) {
        $input = trim($input);

        // Direct ticket ID (18 digits)
        if (preg_match('/^\d{18}$/', $input)) {
            return array('type' => 'id', 'value' => $input);
        }

        // Ticket number format (#1234 or 1234)
        if (preg_match('/^#?(\d+)$/', $input, $matches)) {
            return array('type' => 'number', 'value' => $matches[1]);
        }

        // Extract from Zoho URL
        if (preg_match('/desk\.zoho\.com.*?(\d{18})/', $input, $matches)) {
            return array('type' => 'id', 'value' => $matches[1]);
        }

        // Extract from email subject [#1234]
        if (preg_match('/\[#(\d+)\]/', $input, $matches)) {
            return array('type' => 'number', 'value' => $matches[1]);
        }

        // Check if it's an email
        if (is_email($input)) {
            return array('type' => 'email', 'value' => $input);
        }

        // Default to general search
        return array('type' => 'search', 'value' => $input);
    }

    /**
     * Get single ticket details
     */
    public function get_ticket($ticket_id) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id;

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            )
        ));

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            return json_decode($body, true);
        }

        return false;
    }

    /**
     * Get all ticket threads (the actual messages)
     */
    public function get_ticket_threads($ticket_id) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        // According to Zoho docs, threads contain the actual message content
        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/threads';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            ),
            'timeout' => 30
        ));

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Log for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Threads API Response for ticket ' . $ticket_id . ': ' . print_r($data, true));
            }

            return $data;
        }

        return false;
    }

    /**
     * Get ticket conversations (conversation metadata)
     */
    public function get_ticket_conversations($ticket_id) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        // Get conversations first
        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/conversations';

        $params = array(
            'from' => 0,
            'limit' => 100
        );

        $url .= '?' . http_build_query($params);

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            ),
            'timeout' => 30
        ));

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            return json_decode($body, true);
        }

        return false;
    }

    /**
     * Get threads for a specific conversation
     */
    public function get_conversation_threads($ticket_id, $conversation_id) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/conversations/' . $conversation_id . '/threads';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            )
        ));

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            return isset($data['data']) ? $data['data'] : false;
        }

        return false;
    }


    /**
     * Get ticket history/timeline
     */
    public function get_ticket_history($ticket_id) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/History';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            ),
            'timeout' => 30
        ));

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            return json_decode($body, true);
        }

        return false;
    }

    /**
     * Reply to a ticket
     */
    public function reply_to_ticket($ticket_id, $content, $is_public = true) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/sendReply';

        $data = array(
            'channel' => 'EMAIL',
            'content' => $content,
            'isPublic' => $is_public,
            'contentType' => 'html'
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200 || $code == 201) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update ticket status
     */
    public function update_ticket_status($ticket_id, $status) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id;

        $data = array(
            'status' => $status
        );

        $response = wp_remote_request($url, array(
            'method' => 'PATCH',
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a comment to a ticket (for internal draft/guidance)
     */
    public function add_ticket_comment($ticket_id, $content, $is_public = false) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/comments';

        $data = array(
            'content' => $content,
            'isPublic' => $is_public,
            'contentType' => 'html'
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200 || $code == 201) {
                $body = wp_remote_retrieve_body($response);
                return json_decode($body, true);
            }
        }

        return false;
    }

    /**
     * Get all comments for a ticket
     */
    public function get_ticket_comments($ticket_id, $params = array()) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/comments';

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            )
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200) {
                $body = wp_remote_retrieve_body($response);
                return json_decode($body, true);
            }
        }

        return false;
    }

    /**
     * Add AI-generated draft as internal comment with formatting
     */
    public function add_draft_comment($ticket_id, $draft_content, $metadata = array()) {
        // Format the draft with metadata
        $formatted_content = $this->format_draft_comment($draft_content, $metadata);

        // Add as internal comment (not visible to customers)
        return $this->add_ticket_comment($ticket_id, $formatted_content, false);
    }

    /**
     * Format draft comment with AI metadata and instructions
     */
    private function format_draft_comment($draft_content, $metadata = array()) {
        $html = '<div style="border: 2px solid #4CAF50; padding: 15px; background: #f9f9f9; border-radius: 8px;">';
        $html .= '<h3 style="color: #4CAF50; margin-top: 0;">📝 Draft Response</h3>';

        // Add metadata section
        if (!empty($metadata)) {
            $html .= '<div style="background: #fff; padding: 10px; border-radius: 5px; margin-bottom: 15px;">';
            if (isset($metadata['template_used']) && $metadata['template_used'] !== 'none') {
                $html .= '<p style="margin: 5px 0;"><strong>Template:</strong> ' . $metadata['template_used'] . '</p>';
            }

            if (isset($metadata['tags_suggested']) && !empty($metadata['tags_suggested'])) {
                $html .= '<p style="margin: 5px 0;"><strong>Suggested Tags:</strong> ' . implode(', ', $metadata['tags_suggested']) . '</p>';
            }

            if (isset($metadata['tone']) && $metadata['tone'] !== 'professional') {
                $html .= '<p style="margin: 5px 0;"><strong>Tone:</strong> ' . ucfirst($metadata['tone']) . '</p>';
            }

            $html .= '</div>';
        }

        // Add instructions
        $html .= '<div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #ffc107;">';
        $html .= '<p style="margin: 0;"><strong>Review Before Sending:</strong></p>';
        $html .= '<ul style="margin: 5px 0 0 20px; padding: 0;">';
        $html .= '<li>Personalize the response based on customer context</li>';
        $html .= '<li>Verify all technical information and links</li>';
        $html .= '<li>Add any additional information specific to this customer</li>';
        $html .= '</ul>';
        $html .= '</div>';

        // Add the draft content
        $html .= '<div style="background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">';
        $html .= '<div style="white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
        $html .= nl2br(htmlspecialchars($draft_content));
        $html .= '</div>';
        $html .= '</div>';


        $html .= '</div>';

        // Add timestamp
        $html .= '<p style="margin-top: 10px; color: #999; font-size: 11px; text-align: right;">';
        $html .= 'Draft created: ' . date('Y-m-d H:i:s');
        $html .= '</p>';

        return $html;
    }

    /**
     * Get all tickets for a specific customer by email or contact ID
     */
    public function get_customer_tickets($customer_identifier, $params = array()) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        // Default parameters
        $default_params = array(
            'limit' => 100,  // Increased to fetch more tickets
            'sortBy' => '-createdTime', // Most recent first
            'include' => 'contacts'
        );

        $params = array_merge($default_params, $params);

        // Check if identifier is email or contact ID
        if (strpos($customer_identifier, '@') !== false) {
            // It's an email - we need to search across all tickets
            // Use max allowed limit
            $params['limit'] = 100;  // API max limit per request
            unset($params['status']); // Search all statuses when looking by email
        } else {
            // It's a contact ID
            $params['contactId'] = $customer_identifier;
        }

        $url = $this->api_base_url . '/tickets?' . http_build_query($params);

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            ),
            'timeout' => 30
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                // Filter by email if needed (client-side)
                if (strpos($customer_identifier, '@') !== false && isset($data['data'])) {
                    $filtered_tickets = array();
                    foreach ($data['data'] as $ticket) {
                        if (strcasecmp($ticket['email'] ?? '', $customer_identifier) === 0) {
                            $filtered_tickets[] = $ticket;
                        }
                    }
                    $data['data'] = $filtered_tickets;
                }

                // Add summary statistics
                if (isset($data['data']) && is_array($data['data'])) {
                    $stats = $this->calculate_customer_stats($data['data']);
                    $data['stats'] = $stats;
                }

                return $data;
            }
        }

        return false;
    }

    /**
     * Calculate customer statistics from ticket history
     */
    public function calculate_customer_stats($tickets) {
        $stats = array(
            'total_tickets' => count($tickets),
            'open_tickets' => 0,
            'closed_tickets' => 0,
            'average_resolution_time' => 0,
            'categories' => array(),
            'products' => array(),
            'priorities' => array(),
            'first_ticket_date' => null,
            'last_ticket_date' => null,
            'most_recent_interaction' => null
        );

        $resolution_times = array();

        foreach ($tickets as $ticket) {
            // Status counts
            $status = strtolower($ticket['status'] ?? '');
            if (in_array($status, array('open', 'on hold', 'escalated'))) {
                $stats['open_tickets']++;
            } elseif ($status === 'closed') {
                $stats['closed_tickets']++;
            }

            // Categories/departments
            if (!empty($ticket['departmentId'])) {
                $dept = $ticket['department']['name'] ?? $ticket['departmentId'];
                $stats['categories'][$dept] = ($stats['categories'][$dept] ?? 0) + 1;
            }

            // Products
            if (!empty($ticket['productId'])) {
                $product = $ticket['product']['productName'] ?? $ticket['productId'];
                $stats['products'][$product] = ($stats['products'][$product] ?? 0) + 1;
            }

            // Priorities
            $priority = $ticket['priority'] ?? 'Medium';
            $stats['priorities'][$priority] = ($stats['priorities'][$priority] ?? 0) + 1;

            // Date tracking
            $created = strtotime($ticket['createdTime'] ?? '');
            if ($created) {
                if (!$stats['first_ticket_date'] || $created < strtotime($stats['first_ticket_date'])) {
                    $stats['first_ticket_date'] = $ticket['createdTime'];
                }
                if (!$stats['last_ticket_date'] || $created > strtotime($stats['last_ticket_date'])) {
                    $stats['last_ticket_date'] = $ticket['createdTime'];
                }
            }

            // Resolution time (for closed tickets)
            if ($status === 'closed' && !empty($ticket['closedTime']) && !empty($ticket['createdTime'])) {
                $created = strtotime($ticket['createdTime']);
                $closed = strtotime($ticket['closedTime']);
                if ($created && $closed) {
                    $resolution_times[] = ($closed - $created) / 3600; // In hours
                }
            }

            // Most recent interaction
            $modified = strtotime($ticket['modifiedTime'] ?? '');
            if ($modified && (!$stats['most_recent_interaction'] || $modified > strtotime($stats['most_recent_interaction']))) {
                $stats['most_recent_interaction'] = $ticket['modifiedTime'];
            }
        }

        // Calculate average resolution time
        if (!empty($resolution_times)) {
            $stats['average_resolution_time'] = round(array_sum($resolution_times) / count($resolution_times), 1);
        }

        return $stats;
    }

    /**
     * Get all available tags in the organization
     */
    public function get_ticket_tags() {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $url = $this->api_base_url . '/ticketTags';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            )
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200) {
                $body = wp_remote_retrieve_body($response);
                return json_decode($body, true);
            }
        }

        return false;
    }

    /**
     * Get tags for a specific ticket
     */
    public function get_ticket_tags_by_id($ticket_id) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/tags';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            )
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200) {
                $body = wp_remote_retrieve_body($response);
                return json_decode($body, true);
            }
        }

        return false;
    }

    /**
     * Add tags to a ticket
     */
    public function add_ticket_tags($ticket_id, $tags) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id) || empty($tags)) {
            return false;
        }

        // Ensure tags is an array
        if (!is_array($tags)) {
            $tags = array($tags);
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/tags';

        $data = array(
            'tagNames' => $tags
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200 || $code == 201) {
                $body = wp_remote_retrieve_body($response);
                return json_decode($body, true);
            }
        }

        return false;
    }

    /**
     * Remove tags from a ticket
     */
    public function remove_ticket_tags($ticket_id, $tags) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id) || empty($tags)) {
            return false;
        }

        // Ensure tags is an array
        if (!is_array($tags)) {
            $tags = array($tags);
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/tags';

        $data = array(
            'tagNames' => $tags
        );

        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200 || $code == 204) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replace all tags on a ticket with new tags
     */
    public function replace_ticket_tags($ticket_id, $tags) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        // Ensure tags is an array
        if (!is_array($tags)) {
            $tags = array($tags);
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/tags';

        $data = array(
            'tagNames' => $tags
        );

        $response = wp_remote_request($url, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200) {
                $body = wp_remote_retrieve_body($response);
                return json_decode($body, true);
            }
        }

        return false;
    }

    /**
     * Search for tags by name
     */
    public function search_tags($search_term, $limit = 50) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id) || empty($search_term)) {
            return false;
        }

        $url = $this->api_base_url . '/ticketTags/search';

        $params = array(
            'searchStr' => $search_term,
            'limit' => $limit
        );

        $url .= '?' . http_build_query($params);

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'orgId' => $org_id
            )
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            if ($code == 200) {
                $body = wp_remote_retrieve_body($response);
                return json_decode($body, true);
            }
        }

        return false;
    }

    /**
     * Auto-tag ticket based on template usage and content analysis
     */
    public function auto_tag_ticket($ticket_id, $template_key = null, $custom_tags = array()) {
        $ticket_data = $this->get_ticket($ticket_id);
        if (!$ticket_data) {
            return false;
        }

        $suggested_tags = array();
        $tag_scope = get_option('zdm_tag_scope', 'template_only');

        // Add template-based tags if template was used
        if ($template_key) {
            $template = ZDM_Template_Manager::get_template($template_key);
            if ($template) {
                // Add template category as tag
                if (!empty($template['category'])) {
                    $suggested_tags[] = $template['category'];
                }

                // Add template auto-tags
                $auto_tags = ZDM_Template_Manager::get_auto_tags($template['id']);
                if (!empty($auto_tags)) {
                    $suggested_tags = array_merge($suggested_tags, $auto_tags);
                }
            }
        }

        // Add content analysis tags based on scope setting
        if ($tag_scope === 'content_analysis' || $tag_scope === 'full_analysis') {
            $content_tags = $this->analyze_ticket_content($ticket_data);
            $suggested_tags = array_merge($suggested_tags, $content_tags);
        }

        // Add priority and status tags for full analysis
        if ($tag_scope === 'full_analysis') {
            $status_tags = $this->analyze_ticket_metadata($ticket_data);
            $suggested_tags = array_merge($suggested_tags, $status_tags);
        }

        // Add any custom tags
        if (!empty($custom_tags)) {
            $suggested_tags = array_merge($suggested_tags, $custom_tags);
        }

        // Remove duplicates and empty values
        $suggested_tags = array_unique(array_filter($suggested_tags));

        // Apply tags to ticket
        if (!empty($suggested_tags)) {
            return $this->add_ticket_tags($ticket_id, $suggested_tags);
        }

        return false;
    }

    /**
     * Analyze ticket content to suggest appropriate tags
     */
    private function analyze_ticket_content($ticket_data) {
        $content = strtolower($ticket_data['subject'] ?? '');
        $content .= ' ' . strtolower($ticket_data['description'] ?? '');

        $suggested_tags = array();

        // Priority-based tags
        $priority = strtolower($ticket_data['priority'] ?? '');
        if ($priority === 'high' || $priority === 'urgent') {
            $suggested_tags[] = 'urgent';
        }

        // Status-based tags
        $status = strtolower($ticket_data['status'] ?? '');
        if ($status === 'open') {
            $suggested_tags[] = 'new';
        } elseif ($status === 'in progress') {
            $suggested_tags[] = 'in-progress';
        }

        // Content analysis patterns
        $tag_patterns = array(
            'billing' => array('payment', 'invoice', 'billing', 'refund', 'charge'),
            'technical' => array('error', 'bug', 'not working', 'broken', 'crash'),
            'account' => array('login', 'password', 'access', 'signin', 'account'),
            'feature-request' => array('feature', 'enhancement', 'suggestion', 'improve'),
            'question' => array('how to', 'how do', 'what is', 'explain', 'help with')
        );

        foreach ($tag_patterns as $tag => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    $suggested_tags[] = $tag;
                    break;
                }
            }
        }

        return $suggested_tags;
    }

    /**
     * Analyze ticket metadata (priority, status, etc.) for additional tags
     */
    private function analyze_ticket_metadata($ticket_data) {
        $metadata_tags = array();

        // Priority-based tags
        $priority = strtolower($ticket_data['priority'] ?? '');
        if ($priority === 'high' || $priority === 'urgent') {
            $metadata_tags[] = 'high-priority';
        } elseif ($priority === 'low') {
            $metadata_tags[] = 'low-priority';
        }

        // Status-based tags
        $status = strtolower($ticket_data['status'] ?? '');
        if ($status === 'open') {
            $metadata_tags[] = 'new-ticket';
        } elseif (in_array($status, array('in progress', 'pending'))) {
            $metadata_tags[] = 'in-progress';
        } elseif ($status === 'closed') {
            $metadata_tags[] = 'resolved';
        }

        // Department/Category based tags
        if (!empty($ticket_data['department'])) {
            $department = strtolower($ticket_data['department']);
            $metadata_tags[] = 'dept-' . sanitize_title($department);
        }

        // Customer type based on contact info
        if (isset($ticket_data['contact'])) {
            $contact = $ticket_data['contact'];
            if (!empty($contact['email'])) {
                $email_domain = substr(strrchr($contact['email'], '@'), 1);
                // Tag business emails
                if (in_array($email_domain, array('gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'))) {
                    $metadata_tags[] = 'personal-email';
                } else {
                    $metadata_tags[] = 'business-email';
                }
            }
        }

        // Time-based tags
        $created_time = $ticket_data['createdTime'] ?? '';
        if ($created_time) {
            $created_date = new DateTime($created_time);
            $now = new DateTime();
            $diff = $now->diff($created_date);

            if ($diff->h < 1) {
                $metadata_tags[] = 'recent';
            } elseif ($diff->d > 7) {
                $metadata_tags[] = 'old-ticket';
            }
        }

        return $metadata_tags;
    }
}