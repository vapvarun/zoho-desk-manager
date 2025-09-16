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

        // Build search parameters
        $search_params = array();

        switch ($search_type) {
            case 'email':
                $search_params['email'] = $query;
                break;
            case 'subject':
                $search_params['subject'] = $query;
                break;
            case 'content':
                $search_params['searchStr'] = $query;
                break;
            case 'ticket_number':
                $search_params['ticketNumber'] = $query;
                break;
            case 'all':
            default:
                // For 'all', we'll search in multiple ways
                if (is_email($query)) {
                    $search_params['email'] = $query;
                } elseif (preg_match('/^#?\d+$/', $query)) {
                    // Looks like a ticket number
                    $search_params['ticketNumber'] = ltrim($query, '#');
                } else {
                    // General search in content
                    $search_params['searchStr'] = $query;
                }
                break;
        }

        // Merge with additional parameters
        $search_params = array_merge($search_params, $params);

        // Add default parameters
        $defaults = array(
            'limit' => 50,
            'sortBy' => 'createdTime'
        );
        $search_params = wp_parse_args($search_params, $defaults);

        // Use search endpoint if available, otherwise filter regular tickets
        $url = $this->api_base_url . '/tickets?' . http_build_query($search_params);

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

                // Cache search results for 5 minutes
                $cache_key = 'zdm_search_' . md5($query . $search_type . serialize($params));
                set_transient($cache_key, $data, 300);

                return $data;
            } else {
                $this->log_error('Search API returned non-200 status', array(
                    'endpoint' => 'search',
                    'query' => $query,
                    'status_code' => $code,
                    'response' => wp_remote_retrieve_body($response)
                ));
            }
        } else {
            $this->log_error('Failed to search tickets', array(
                'query' => $query,
                'error' => $response->get_error_message()
            ));
        }

        return false;
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
     * Get ticket comments (alternative to conversations)
     */
    public function get_ticket_comments($ticket_id) {
        $access_token = $this->get_access_token();
        $org_id = get_option('zdm_org_id');

        if (empty($access_token) || empty($org_id)) {
            return false;
        }

        $url = $this->api_base_url . '/tickets/' . $ticket_id . '/comments';

        $params = array(
            'from' => 0,
            'limit' => 100,
            'sortBy' => 'commentedTime'
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
}