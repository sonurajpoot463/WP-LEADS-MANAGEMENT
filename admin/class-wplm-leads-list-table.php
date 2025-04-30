<?php
/**
 * Leads List Table class
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WPLM_Leads_List_Table extends WP_List_Table {
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'lead',
            'plural'   => 'leads',
            'ajax'     => false
        ));
    }

    /**
     * Get columns
     */
    public function get_columns() {
        return array(
            'cb'        => '<input type="checkbox" />',
            'name'      => __('Name', 'wp-leads-management'),
            'email'     => __('Email', 'wp-leads-management'),
            'phone'     => __('Phone', 'wp-leads-management'),
            'company'   => __('Company', 'wp-leads-management'),
            'source'    => __('Source', 'wp-leads-management'),
            'status'    => __('Status', 'wp-leads-management'),
            'assigned'  => __('Assigned To', 'wp-leads-management'),
            'created'   => __('Created', 'wp-leads-management')
        );
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'name'     => array('name', false),
            'email'    => array('email', false),
            'company'  => array('company', false),
            'created'  => array('created_at', true)
        );
    }

    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'wplm_leads';
        $status_table = $wpdb->prefix . 'wplm_lead_statuses';
        $sources_table = $wpdb->prefix . 'wplm_lead_sources';

        // Process bulk actions
        $this->process_bulk_action();

        // Set pagination arguments
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Get sort parameters
        $orderby = isset($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'created_at';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'desc';

        // Map orderby values to column names
        $orderby_map = array(
            'name' => 'l.first_name',
            'email' => 'l.email',
            'company' => 'l.company',
            'created_at' => 'l.created_at'
        );

        $orderby = isset($orderby_map[$orderby]) ? $orderby_map[$orderby] : 'l.created_at';

        // Get filter parameters
        $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        $source = isset($_REQUEST['source']) ? sanitize_text_field($_REQUEST['source']) : '';
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

        // Build where clause
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

        if (!empty($search)) {
            $where[] = '(l.first_name LIKE %s OR l.last_name LIKE %s OR l.email LIKE %s OR l.phone LIKE %s OR l.company LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_args[] = $search_term;
            $where_args[] = $search_term;
            $where_args[] = $search_term;
            $where_args[] = $search_term;
            $where_args[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        // Get total items
        $total_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table l
            LEFT JOIN $status_table s ON l.status_id = s.id
            LEFT JOIN $sources_table src ON l.source_id = src.id
            WHERE $where_clause",
            $where_args
        );

        $total_items = $wpdb->get_var($total_query);

        // Get leads
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
            ORDER BY $orderby $order
            LIMIT %d, %d",
            array_merge($where_args, array($offset, $per_page))
        );

        $this->items = $wpdb->get_results($query);

        // Set pagination arguments
        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }

    /**
     * Column default
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'email':
                return '<a href="mailto:' . esc_attr($item->email) . '">' . esc_html($item->email) . '</a>';
            case 'phone':
                return esc_html($item->phone);
            case 'company':
                return esc_html($item->company);
            case 'source':
                return esc_html($item->source_name);
            case 'status':
                return sprintf(
                    '<span class="badge" style="background-color: %s;">%s</span>',
                    esc_attr($item->status_color),
                    esc_html($item->status_name)
                );
            case 'assigned':
                return esc_html($item->assigned_name ?: __('Unassigned', 'wp-leads-management'));
            case 'created':
                return date_i18n(get_option('date_format'), strtotime($item->created_at));
            default:
                return print_r($item, true);
        }
    }

    /**
     * Column name
     */
    public function column_name($item) {
        // Build row actions
        $actions = array();
        
        // View action
        if (current_user_can('manage_wplm_leads')) {
            $actions['view'] = sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=wplm-leads&action=view&id=' . $item->id),
                __('View', 'wp-leads-management')
            );
        }
        
        // Edit action
        if (current_user_can('edit_wplm_leads')) {
            $actions['edit'] = sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=wplm-leads&action=edit&id=' . $item->id),
                __('Edit', 'wp-leads-management')
            );
        }
        
        // Delete action
        if (current_user_can('delete_wplm_leads')) {
            $actions['delete'] = sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
                wp_nonce_url(admin_url('admin.php?page=wplm-leads&action=delete&id=' . $item->id), 'wplm_delete_lead'),
                __('Are you sure you want to delete this lead? This action cannot be undone.', 'wp-leads-management'),
                __('Delete', 'wp-leads-management')
            );
        }
        
        // Return the name with actions
        return sprintf(
            '<a href="%1$s"><strong>%2$s %3$s</strong></a> %4$s',
            admin_url('admin.php?page=wplm-leads&action=view&id=' . $item->id),
            esc_html($item->first_name),
            esc_html($item->last_name),
            $this->row_actions($actions)
        );
    }

    /**
     * Column checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="leads[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        $actions = array(
            'delete' => __('Delete', 'wp-leads-management')
        );
        
        return $actions;
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        // Detect when a bulk action is being triggered
        if ('delete' === $this->current_action()) {
            // Verify nonce
            if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'])) {
                wp_die(__('Security check failed', 'wp-leads-management'));
            }
            
            // Check permissions
            if (!current_user_can('delete_wplm_leads')) {
                wp_die(__('You do not have permission to perform this action', 'wp-leads-management'));
            }
            
            $lead_ids = isset($_REQUEST['leads']) ? array_map('intval', $_REQUEST['leads']) : array();
            
            if (empty($lead_ids)) {
                return;
            }
            
            foreach ($lead_ids as $lead_id) {
                WPLM()->lead->delete($lead_id);
            }
            
            wp_redirect(add_query_arg(array('deleted' => count($lead_ids)), admin_url('admin.php?page=wplm-leads')));
            exit;
        }
    }

    /**
     * Display custom no items text
     */
    public function no_items() {
        _e('No leads found.', 'wp-leads-management');
    }

    /**
     * Display extra table nav
     */
    public function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }
        
        // Get lead statuses and sources for filtering
        $statuses = WPLM()->lead->get_statuses();
        $sources = WPLM()->lead->get_sources();
        
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
        ?>
        <div class="alignleft actions">
            <select name="status" id="filter-by-status">
                <option value=""><?php _e('All Statuses', 'wp-leads-management'); ?></option>
                <?php foreach ($statuses as $s) : ?>
                    <option value="<?php echo esc_attr($s->slug); ?>" <?php selected($status, $s->slug); ?>>
                        <?php echo esc_html($s->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="source" id="filter-by-source">
                <option value=""><?php _e('All Sources', 'wp-leads-management'); ?></option>
                <?php foreach ($sources as $s) : ?>
                    <option value="<?php echo esc_attr($s->slug); ?>" <?php selected($source, $s->slug); ?>>
                        <?php echo esc_html($s->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php submit_button(__('Filter', 'wp-leads-management'), 'secondary', '', false); ?>
            
            <?php if (current_user_can('export_wplm_leads')) : ?>
                <a href="<?php echo admin_url('admin-ajax.php?action=wplm_export_leads'); ?>" class="button">
                    <?php _e('Export to CSV', 'wp-leads-management'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
}