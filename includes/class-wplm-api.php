<?php
/**
 * API class
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class WPLM_API {
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('wp-leads-management/v1', '/leads', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_leads'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));

        register_rest_route('wp-leads-management/v1', '/leads', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_lead'),
            'permission_callback' => '__return_true' // Allow creating leads via API
        ));

        register_rest_route('wp-leads-management/v1', '/leads/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_lead'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));

        register_rest_route('wp-leads-management/v1', '/leads/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_lead'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));

        register_rest_route('wp-leads-management/v1', '/leads/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_lead'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));

        register_rest_route('wp-leads-management/v1', '/statuses', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_statuses'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));

        register_rest_route('wp-leads-management/v1', '/sources', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sources'),
            'permission_callback' => array($this, 'check_api_permissions')
        ));
    }

    /**
     * Check API permissions
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_api_permissions($request) {
        // Check for API key authentication
        $api_key = $request->get_header('X-WPLM-API-Key');
        if ($api_key) {
            $settings = get_option('wplm_settings');
            if (isset($settings['api_key']) && $settings['api_key'] === $api_key) {
                return true;
            }
        }

        // Fall back to user authentication
        return current_user_can('manage_wplm_leads');
    }

    /**
     * Get leads
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_leads($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'wplm_leads';
        $status_table = $wpdb->prefix . 'wplm_lead_statuses';
        $sources_table = $wpdb->prefix . 'wplm_lead_sources';

        // Parameters
        $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 10;
        $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
        $status = $request->get_param('status');
        $source = $request->get_param('source');
        $search = $request->get_param('search');
        $order_by = $request->get_param('order_by') ? sanitize_sql_orderby($request->get_param('order_by')) : 'l.id';
        $order = $request->get_param('order') ? (strtoupper($request->get_param('order')) === 'ASC' ? 'ASC' : 'DESC') : 'DESC';

        // Build query
        $where = array('1=1');
        $where_args = array();

        if ($status) {
            $where[] = 's.slug = %s';
            $where_args[] = $status;
        }

        if ($source) {
            $where[] = 'src.slug = %s';
            $where_args[] = $source;
        }

        if ($search) {
            $where[] = '(l.first_name LIKE %s OR l.last_name LIKE %s OR l.email LIKE %s OR l.phone LIKE %s OR l.company LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_args[] = $search_term;
            $where_args[] = $search_term;
            $where_args[] = $search_term;
            $where_args[] = $search_term;
            $where_args[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        // Count total
        $total_query = $wpdb->prepare(
            "SELECT COUNT(l.id) 
            FROM $table l 
            LEFT JOIN $status_table s ON l.status_id = s.id
            LEFT JOIN $sources_table src ON l.source_id = src.id
            WHERE $where_clause",
            $where_args
        );

        $total = $wpdb->get_var($total_query);

        // Get leads
        $offset = ($page - 1) * $per_page;

        $query = $wpdb->prepare(
            "SELECT l.*, 
                    s.name as status_name, 
                    s.slug as status_slug,
                    s.color as status_color,
                    src.name as source_name,
                    src.slug as source_slug,
                    u.display_name as assigned_name
            FROM $table l
            LEFT JOIN $status_table s ON l.status_id = s.id
            LEFT JOIN $sources_table src ON l.source_id = src.id
            LEFT JOIN {$wpdb->users} u ON l.assigned_to = u.ID
            WHERE $where_clause
            ORDER BY $order_by $order
            LIMIT %d, %d",
            array_merge($where_args, array($offset, $per_page))
        );

        $leads = $wpdb->get_results($query);

        // Get meta data for each lead
        foreach ($leads as $lead) {
            $lead->meta = WPLM()->lead->get_meta($lead->id);
        }

        // Prepare response
        $response = new WP_REST_Response($leads);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $per_page));

        return $response;
    }

    /**
     * Get a lead
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_lead($request) {
        $lead_id = $request->get_param('id');
        $lead = WPLM()->lead->get($lead_id);

        if (!$lead) {
            return new WP_Error('lead_not_found', __('Lead not found', 'wp-leads-management'), array('status' => 404));
        }

        // Get notes if requested
        if ($request->get_param('include_notes')) {
            $lead->notes = WPLM()->lead->get_notes($lead_id);
        }

        return new WP_REST_Response($lead);
    }

    /**
     * Create a lead
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_lead($request) {
        $params = $request->get_params();

        // Validate required fields
        $required_fields = array('first_name', 'last_name', 'email');
        foreach ($required_fields as $field) {
            if (empty($params[$field])) {
                return new WP_Error(
                    'missing_required_field',
                    sprintf(__('Missing required field: %s', 'wp-leads-management'), $field),
                    array('status' => 400)
                );
            }
        }

        // Validate email
        if (!is_email($params['email'])) {
            return new WP_Error('invalid_email', __('Invalid email address', 'wp-leads-management'), array('status' => 400));
        }

        // Create lead
        $lead_id = WPLM()->lead->create($params);

        if (!$lead_id) {
            return new WP_Error('lead_creation_failed', __('Failed to create lead', 'wp-leads-management'), array('status' => 500));
        }

        // Get created lead
        $lead = WPLM()->lead->get($lead_id);

        return new WP_REST_Response($lead, 201);
    }

    /**
     * Update a lead
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_lead($request) {
        $lead_id = $request->get_param('id');
        $params = $request->get_params();

        // Check if lead exists
        $lead = WPLM()->lead->get($lead_id);
        if (!$lead) {
            return new WP_Error('lead_not_found', __('Lead not found', 'wp-leads-management'), array('status' => 404));
        }

        // Validate email if provided
        if (isset($params['email']) && !is_email($params['email'])) {
            return new WP_Error('invalid_email', __('Invalid email address', 'wp-leads-management'), array('status' => 400));
        }

        // Update lead
        $result = WPLM()->lead->update($lead_id, $params);

        if (!$result) {
            return new WP_Error('lead_update_failed', __('Failed to update lead', 'wp-leads-management'), array('status' => 500));
        }

        // Get updated lead
        $lead = WPLM()->lead->get($lead_id);

        return new WP_REST_Response($lead);
    }

    /**
     * Delete a lead
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function delete_lead($request) {
        $lead_id = $request->get_param('id');

        // Check if lead exists
        $lead = WPLM()->lead->get($lead_id);
        if (!$lead) {
            return new WP_Error('lead_not_found', __('Lead not found', 'wp-leads-management'), array('status' => 404));
        }

        // Delete lead
        $result = WPLM()->lead->delete($lead_id);

        if (!$result) {
            return new WP_Error('lead_deletion_failed', __('Failed to delete lead', 'wp-leads-management'), array('status' => 500));
        }

        return new WP_REST_Response(null, 204);
    }

    /**
     * Get lead statuses
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_statuses($request) {
        $statuses = WPLM()->lead->get_statuses();
        return new WP_REST_Response($statuses);
    }

    /**
     * Get lead sources
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_sources($request) {
        $sources = WPLM()->lead->get_sources();
        return new WP_REST_Response($sources);
    }
}