<?php
/**
 * Admin class
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include WP_List_Table if not defined
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WPLM_Admin {
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
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_wplm_get_leads', array($this, 'ajax_get_leads'));
        add_action('wp_ajax_wplm_export_leads', array($this, 'ajax_export_leads'));
    }

    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        // Main menu
        add_menu_page(
            __('Leads Management', 'wp-leads-management'),
            __('Leads', 'wp-leads-management'),
            'manage_wplm_leads',
            'wplm-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-chart-line',
            25
        );

        // Dashboard submenu
        add_submenu_page(
            'wplm-dashboard',
            __('Dashboard', 'wp-leads-management'),
            __('Dashboard', 'wp-leads-management'),
            'view_wplm_dashboard',
            'wplm-dashboard',
            array($this, 'render_dashboard_page')
        );

        // Leads submenu
        add_submenu_page(
            'wplm-dashboard',
            __('Leads', 'wp-leads-management'),
            __('All Leads', 'wp-leads-management'),
            'manage_wplm_leads',
            'wplm-leads',
            array($this, 'render_leads_page')
        );

        // Add Lead submenu
        add_submenu_page(
            'wplm-dashboard',
            __('Add New Lead', 'wp-leads-management'),
            __('Add New', 'wp-leads-management'),
            'add_wplm_leads',
            'wplm-add-lead',
            array($this, 'render_add_lead_page')
        );

        // Settings submenu
        add_submenu_page(
            'wplm-dashboard',
            __('Settings', 'wp-leads-management'),
            __('Settings', 'wp-leads-management'),
            'manage_wplm_settings',
            'wplm-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'wplm-') === false) {
            return;
        }

        // Bootstrap
        wp_enqueue_style('bootstrap', WPLM_PLUGIN_URL . 'assets/css/bootstrap.min.css', array(), '5.3.0');
        wp_enqueue_script('bootstrap', WPLM_PLUGIN_URL . 'assets/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);

        // Chart.js for dashboard
        if ($hook === 'toplevel_page_wplm-dashboard') {
            wp_enqueue_script('chartjs', WPLM_PLUGIN_URL . 'assets/js/chart.min.js', array(), '3.9.1', true);
        }
        
        // Admin styles
        wp_enqueue_style('wplm-admin-styles', WPLM_PLUGIN_URL . 'assets/css/wplm-admin.css', array(), WPLM_VERSION);
        
        // Admin scripts
        wp_enqueue_script('wplm-admin-scripts', WPLM_PLUGIN_URL . 'assets/js/wplm-admin.js', array('jquery'), WPLM_VERSION, true);
        
        // Localize script
        wp_localize_script('wplm-admin-scripts', 'wplm_admin_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wplm-nonce'),
            'i18n' => array(
                'confirm_delete' => __('Are you sure you want to delete this lead? This action cannot be undone.', 'wp-leads-management'),
                'delete_success' => __('Lead deleted successfully', 'wp-leads-management'),
                'delete_error' => __('Error deleting lead', 'wp-leads-management'),
                'update_success' => __('Lead updated successfully', 'wp-leads-management'),
                'update_error' => __('Error updating lead', 'wp-leads-management'),
                'add_note_error' => __('Error adding note', 'wp-leads-management'),
                'loading' => __('Loading...', 'wp-leads-management')
            )
        ));
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        include WPLM_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render leads page
     */
    public function render_leads_page() {
        // Check if viewing, editing or deleting a lead
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $lead_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'view':
                if ($lead_id) {
                    include WPLM_PLUGIN_DIR . 'admin/views/view-lead.php';
                    return;
                }
                break;
                
            case 'edit':
                if ($lead_id) {
                    include WPLM_PLUGIN_DIR . 'admin/views/edit-lead.php';
                    return;
                }
                break;
                
            case 'delete':
                if ($lead_id && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'wplm_delete_lead')) {
                    WPLM()->lead->delete($lead_id);
                    wp_redirect(admin_url('admin.php?page=wplm-leads&deleted=1'));
                    exit;
                }
                break;
        }

        // Load the leads list table
        require_once WPLM_PLUGIN_DIR . 'admin/class-wplm-leads-list-table.php';
        $leads_table = new WPLM_Leads_List_Table();
        $leads_table->prepare_items();

        include WPLM_PLUGIN_DIR . 'admin/views/leads.php';
    }

    /**
     * Render add lead page
     */
    public function render_add_lead_page() {
        include WPLM_PLUGIN_DIR . 'admin/views/add-lead.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Process form submission
        if (isset($_POST['wplm_save_settings']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'wplm_settings')) {
            $settings = array(
                'form_title' => sanitize_text_field($_POST['form_title']),
                'submit_button_text' => sanitize_text_field($_POST['submit_button_text']),
                'success_message' => sanitize_textarea_field($_POST['success_message']),
                'email_notification' => isset($_POST['email_notification']),
                'notification_email' => sanitize_email($_POST['notification_email']),
                'notification_subject' => sanitize_text_field($_POST['notification_subject']),
                'required_fields' => isset($_POST['required_fields']) ? array_map('sanitize_text_field', $_POST['required_fields']) : array(),
                'lead_default_status' => sanitize_text_field($_POST['lead_default_status']),
                'lead_default_source' => sanitize_text_field($_POST['lead_default_source'])
            );

            update_option('wplm_settings', $settings);
            $message = __('Settings saved successfully', 'wp-leads-management');
        }

        // Get current settings
        $settings = get_option('wplm_settings');
        
        include WPLM_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * AJAX: Get leads for the data table
     */
    public function ajax_get_leads() {
        // Check nonce
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'wplm-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-leads-management')));
        }

        // Check permissions
        if (!current_user_can('manage_wplm_leads')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-leads-management')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wplm_leads';
        $status_table = $wpdb->prefix . 'wplm_lead_statuses';
        $sources_table = $wpdb->prefix . 'wplm_lead_sources';

        // Parameters
        $start = isset($_REQUEST['start']) ? intval($_REQUEST['start']) : 0;
        $length = isset($_REQUEST['length']) ? intval($_REQUEST['length']) : 10;
        $search = isset($_REQUEST['search']['value']) ? sanitize_text_field($_REQUEST['search']['value']) : '';
        $order_column = isset($_REQUEST['order'][0]['column']) ? intval($_REQUEST['order'][0]['column']) : 0;
        $order_dir = isset($_REQUEST['order'][0]['dir']) ? sanitize_text_field($_REQUEST['order'][0]['dir']) : 'desc';

        // Filter by status or source if provided
        $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        $source = isset($_REQUEST['source']) ? sanitize_text_field($_REQUEST['source']) : '';

        // Build query
        $columns = array(
            'l.id',
            'CONCAT(l.first_name, " ", l.last_name)',
            'l.email',
            'l.phone',
            'l.company',
            'src.name',
            's.name',
            'u.display_name',
            'l.created_at'
        );

        $order_column = $columns[$order_column];
        
        // Where clauses
        $where = array('1=1');
        $where_args = array();
        
        if (!empty($search)) {
            $where[] = '(l.first_name LIKE %s OR l.last_name LIKE %s OR l.email LIKE %s OR l.phone LIKE %s OR l.company LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_args[] = $search_term;
            $where_args[] = $search_term;
            $where_args[] = $search_term;
            $where_args[] = $search_term;
            $where_args[] = $search_term;
        }
        
        if (!empty($status)) {
            $where[] = 's.slug = %s';
            $where_args[] = $status;
        }
        
        if (!empty($source)) {
            $where[] = 'src.slug = %s';
            $where_args[] = $source;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Count total records
        $total_query = $wpdb->prepare(
            "SELECT COUNT(l.id) 
            FROM $table l 
            LEFT JOIN $status_table s ON l.status_id = s.id
            LEFT JOIN $sources_table src ON l.source_id = src.id
            LEFT JOIN {$wpdb->users} u ON l.assigned_to = u.ID
            WHERE $where_clause",
            $where_args
        );
        
        $total = $wpdb->get_var($total_query);

        // Get filtered data
        $query = $wpdb->prepare(
            "SELECT l.*, 
                    s.name as status_name, 
                    s.color as status_color,
                    src.name as source_name,
                    u.display_name as assigned_name
            FROM $table l
            LEFT JOIN $status_table s ON l.status_id = s.id
            LEFT JOIN $sources_table src ON l.source_id = src.id
            LEFT JOIN {$wpdb->users} u ON l.assigned_to = u.ID
            WHERE $where_clause
            ORDER BY $order_column $order_dir
            LIMIT %d, %d",
            array_merge($where_args, array($start, $length))
        );
        
        $data = $wpdb->get_results($query);

        // Format data for DataTables
        $formatted_data = array();
        
        foreach ($data as $lead) {
            $status_badge = sprintf(
                '<span class="badge" style="background-color: %s;">%s</span>',
                esc_attr($lead->status_color),
                esc_html($lead->status_name)
            );
            
            $row = array(
                $lead->id,
                esc_html("{$lead->first_name} {$lead->last_name}"),
                '<a href="mailto:' . esc_attr($lead->email) . '">' . esc_html($lead->email) . '</a>',
                esc_html($lead->phone),
                esc_html($lead->company),
                esc_html($lead->source_name),
                $status_badge,
                esc_html($lead->assigned_name ?: __('Unassigned', 'wp-leads-management')),
                date_i18n(get_option('date_format'), strtotime($lead->created_at)),
                $this->get_row_actions($lead->id)
            );
            
            $formatted_data[] = $row;
        }

        wp_send_json(array(
            'draw' => isset($_REQUEST['draw']) ? intval($_REQUEST['draw']) : 1,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $formatted_data
        ));
    }

    /**
     * Get row actions for leads table
     *
     * @param int $lead_id Lead ID
     * @return string HTML for row actions
     */
    private function get_row_actions($lead_id) {
        $actions = array();
        
        // View action
        if (current_user_can('manage_wplm_leads')) {
            $actions[] = sprintf(
                '<a href="%s" class="button button-small">%s</a>',
                admin_url("admin.php?page=wplm-leads&action=view&id={$lead_id}"),
                __('View', 'wp-leads-management')
            );
        }
        
        // Edit action
        if (current_user_can('edit_wplm_leads')) {
            $actions[] = sprintf(
                '<a href="%s" class="button button-small">%s</a>',
                admin_url("admin.php?page=wplm-leads&action=edit&id={$lead_id}"),
                __('Edit', 'wp-leads-management')
            );
        }
        
        // Delete action
        if (current_user_can('delete_wplm_leads')) {
            $delete_url = wp_nonce_url(
                admin_url("admin.php?page=wplm-leads&action=delete&id={$lead_id}"),
                'wplm_delete_lead'
            );
            
            $actions[] = sprintf(
                '<a href="%s" class="button button-small button-link-delete" onclick="return confirm(\'%s\')">%s</a>',
                $delete_url,
                __('Are you sure you want to delete this lead? This action cannot be undone.', 'wp-leads-management'),
                __('Delete', 'wp-leads-management')
            );
        }
        
        return implode(' ', $actions);
    }

    /**
     * AJAX: Export leads to CSV
     */
    public function ajax_export_leads() {
        // Check nonce
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'wplm-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-leads-management')));
        }

        // Check permissions
        if (!current_user_can('export_wplm_leads')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-leads-management')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wplm_leads';
        $status_table = $wpdb->prefix . 'wplm_lead_statuses';
        $sources_table = $wpdb->prefix . 'wplm_lead_sources';

        // Parameters for filtering
        $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        $source = isset($_REQUEST['source']) ? sanitize_text_field($_REQUEST['source']) : '';
        $start_date = isset($_REQUEST['start_date']) ? sanitize_text_field($_REQUEST['start_date']) : '';
        $end_date = isset($_REQUEST['end_date']) ? sanitize_text_field($_REQUEST['end_date']) : '';

        // Where clauses
        $where = array('1=1');
        $where_args = array();
        
        if (!empty($status)) {
            $where[] = 's.slug = %s';
            $where_args[] = $status;
        }
        
        if (!empty($source)) {
            $where[] = 'src.slug = %s';
            $where_args[] = $source;
        }
        
        if (!empty($start_date)) {
            $where[] = 'l.created_at >= %s';
            $where_args[] = $start_date . ' 00:00:00';
        }
        
        if (!empty($end_date)) {
            $where[] = 'l.created_at <= %s';
            $where_args[] = $end_date . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where);

        // Get data
        $query = $wpdb->prepare(
            "SELECT l.*, 
                    s.name as status_name,
                    src.name as source_name,
                    u.display_name as assigned_name
            FROM $table l
            LEFT JOIN $status_table s ON l.status_id = s.id
            LEFT JOIN $sources_table src ON l.source_id = src.id
            LEFT JOIN {$wpdb->users} u ON l.assigned_to = u.ID
            WHERE $where_clause
            ORDER BY l.created_at DESC",
            $where_args
        );
        
        $leads = $wpdb->get_results($query);

        // Prepare CSV data
        $csv_data = array();
        
        // Headers
        $headers = array(
            __('ID', 'wp-leads-management'),
            __('First Name', 'wp-leads-management'),
            __('Last Name', 'wp-leads-management'),
            __('Email', 'wp-leads-management'),
            __('Phone', 'wp-leads-management'),
            __('Company', 'wp-leads-management'),
            __('Source', 'wp-leads-management'),
            __('Status', 'wp-leads-management'),
            __('Assigned To', 'wp-leads-management'),
            __('Created', 'wp-leads-management')
        );
        
        $csv_data[] = $headers;
        
        // Lead data
        foreach ($leads as $lead) {
            $row = array(
                $lead->id,
                $lead->first_name,
                $lead->last_name,
                $lead->email,
                $lead->phone,
                $lead->company,
                $lead->source_name,
                $lead->status_name,
                $lead->assigned_name ?: __('Unassigned', 'wp-leads-management'),
                $lead->created_at
            );
            
            $csv_data[] = $row;
        }

        // Generate CSV content
        $csv_content = '';
        foreach ($csv_data as $row) {
            $escaped_row = array_map(array($this, 'escape_csv'), $row);
            $csv_content .= implode(',', $escaped_row) . "\n";
        }

        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=leads-export-' . date('Y-m-d') . '.csv');
        
        // Output CSV content
        echo $csv_content;
        exit;
    }

    /**
     * Escape a value for CSV
     *
     * @param string $value Value to escape
     * @return string Escaped value
     */
    private function escape_csv($value) {
        // If value contains comma, newline or double quote, enclose in double quotes
        if (preg_match('/[,"\r\n]/', $value)) {
            // Escape double quotes by doubling them
            $value = str_replace('"', '""', $value);
            // Enclose in double quotes
            $value = '"' . $value . '"';
        }
        
        return $value;
    }
}