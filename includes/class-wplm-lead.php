<?php
/**
 * Lead class
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class WPLM_Lead {
    /**
     * Lead database table
     *
     * @var string
     */
    private $table;

    /**
     * Lead meta database table
     *
     * @var string
     */
    private $meta_table;

    /**
     * Lead notes database table
     *
     * @var string
     */
    private $notes_table;

    /**
     * Lead statuses database table
     *
     * @var string
     */
    private $status_table;

    /**
     * Lead sources database table
     *
     * @var string
     */
    private $sources_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        
        $this->table = $wpdb->prefix . 'wplm_leads';
        $this->meta_table = $wpdb->prefix . 'wplm_lead_meta';
        $this->notes_table = $wpdb->prefix . 'wplm_lead_notes';
        $this->status_table = $wpdb->prefix . 'wplm_lead_statuses';
        $this->sources_table = $wpdb->prefix . 'wplm_lead_sources';
        
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_wplm_get_lead', array($this, 'ajax_get_lead'));
        add_action('wp_ajax_wplm_update_lead', array($this, 'ajax_update_lead'));
        add_action('wp_ajax_wplm_delete_lead', array($this, 'ajax_delete_lead'));
        add_action('wp_ajax_wplm_add_note', array($this, 'ajax_add_note'));
    }

    /**
     * Get a lead by ID
     *
     * @param int $id Lead ID
     * @return object|null Lead object or null if not found
     */
    public function get($id) {
        global $wpdb;

        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, 
                    s.name as status_name, 
                    s.color as status_color,
                    src.name as source_name,
                    usr.display_name as assigned_name
            FROM {$this->table} l
            LEFT JOIN {$this->status_table} s ON l.status_id = s.id
            LEFT JOIN {$this->sources_table} src ON l.source_id = src.id
            LEFT JOIN {$wpdb->users} usr ON l.assigned_to = usr.ID
            WHERE l.id = %d",
            $id
        ));

        if (!$lead) {
            return null;
        }

        // Get lead meta
        $lead->meta = $this->get_meta($id);

        return $lead;
    }

    /**
     * Create a new lead
     *
     * @param array $data Lead data
     * @return int|false Lead ID or false on failure
     */
    public function create($data) {
        global $wpdb;

        $defaults = array(
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'company' => '',
            'source_id' => $this->get_default_source_id(),
            'status_id' => $this->get_default_status_id(),
            'assigned_to' => null
        );

        $data = wp_parse_args($data, $defaults);

        // Extract meta fields
        $meta = array();
        foreach ($data as $key => $value) {
            if (!in_array($key, array_keys($defaults))) {
                $meta[$key] = $value;
                unset($data[$key]);
            }
        }

        // Insert lead
        $result = $wpdb->insert($this->table, $data);

        if (!$result) {
            return false;
        }

        $lead_id = $wpdb->insert_id;

        // Insert meta
        foreach ($meta as $key => $value) {
            $this->update_meta($lead_id, $key, $value);
        }

        // Trigger action
        do_action('wplm_lead_created', $lead_id, $data);

        return $lead_id;
    }

    /**
     * Update a lead
     *
     * @param int $id Lead ID
     * @param array $data Lead data
     * @return bool True on success, false on failure
     */
    public function update($id, $data) {
        global $wpdb;

        $lead_fields = array(
            'first_name',
            'last_name',
            'email',
            'phone',
            'company',
            'source_id',
            'status_id',
            'assigned_to'
        );

        // Extract lead data and meta data
        $lead_data = array();
        $meta_data = array();

        foreach ($data as $key => $value) {
            if (in_array($key, $lead_fields)) {
                $lead_data[$key] = $value;
            } else {
                $meta_data[$key] = $value;
            }
        }

        // Update lead
        $updated = false;
        if (!empty($lead_data)) {
            $updated = $wpdb->update($this->table, $lead_data, array('id' => $id));
        }

        // Update meta
        foreach ($meta_data as $key => $value) {
            $this->update_meta($id, $key, $value);
        }

        // Trigger action
        do_action('wplm_lead_updated', $id, $data);

        return ($updated !== false || !empty($meta_data));
    }

    /**
     * Delete a lead
     *
     * @param int $id Lead ID
     * @return bool True on success, false on failure
     */
    public function delete($id) {
        global $wpdb;

        // Delete lead meta
        $wpdb->delete($this->meta_table, array('lead_id' => $id));

        // Delete lead notes
        $wpdb->delete($this->notes_table, array('lead_id' => $id));

        // Delete lead
        $result = $wpdb->delete($this->table, array('id' => $id));

        // Trigger action
        do_action('wplm_lead_deleted', $id);

        return $result !== false;
    }

    /**
     * Get lead meta
     *
     * @param int $lead_id Lead ID
     * @param string $key Optional. Meta key
     * @return mixed
     */
    public function get_meta($lead_id, $key = '') {
        global $wpdb;

        if (!empty($key)) {
            $meta_value = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$this->meta_table} WHERE lead_id = %d AND meta_key = %s",
                $lead_id, $key
            ));

            return maybe_unserialize($meta_value);
        }

        $meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$this->meta_table} WHERE lead_id = %d",
            $lead_id
        ), OBJECT_K);

        $return = array();
        if ($meta) {
            foreach ($meta as $key => $object) {
                $return[$key] = maybe_unserialize($object->meta_value);
            }
        }

        return $return;
    }

    /**
     * Update lead meta
     *
     * @param int $lead_id Lead ID
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool True on success, false on failure
     */
    public function update_meta($lead_id, $key, $value) {
        global $wpdb;

        $value = maybe_serialize($value);

        $meta_id = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM {$this->meta_table} WHERE lead_id = %d AND meta_key = %s",
            $lead_id, $key
        ));

        if ($meta_id) {
            $result = $wpdb->update(
                $this->meta_table,
                array('meta_value' => $value),
                array('meta_id' => $meta_id)
            );
        } else {
            $result = $wpdb->insert(
                $this->meta_table,
                array(
                    'lead_id' => $lead_id,
                    'meta_key' => $key,
                    'meta_value' => $value
                )
            );
        }

        return $result !== false;
    }

    /**
     * Delete lead meta
     *
     * @param int $lead_id Lead ID
     * @param string $key Meta key
     * @return bool True on success, false on failure
     */
    public function delete_meta($lead_id, $key) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->meta_table,
            array(
                'lead_id' => $lead_id,
                'meta_key' => $key
            )
        );

        return $result !== false;
    }

    /**
     * Get lead statuses
     *
     * @return array
     */
    public function get_statuses() {
        global $wpdb;

        $statuses = $wpdb->get_results(
            "SELECT * FROM {$this->status_table} ORDER BY order_num ASC"
        );

        return $statuses;
    }

    /**
     * Get lead sources
     *
     * @return array
     */
    public function get_sources() {
        global $wpdb;

        $sources = $wpdb->get_results(
            "SELECT * FROM {$this->sources_table} ORDER BY name ASC"
        );

        return $sources;
    }

    /**
     * Get default status ID
     *
     * @return int
     */
    public function get_default_status_id() {
        global $wpdb;
        
        $settings = get_option('wplm_settings');
        $default_status = isset($settings['lead_default_status']) ? $settings['lead_default_status'] : 'new';
        
        $status_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->status_table} WHERE slug = %s",
            $default_status
        ));
        
        return $status_id;
    }

    /**
     * Get default source ID
     *
     * @return int
     */
    public function get_default_source_id() {
        global $wpdb;
        
        $settings = get_option('wplm_settings');
        $default_source = isset($settings['lead_default_source']) ? $settings['lead_default_source'] : 'website';
        
        $source_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->sources_table} WHERE slug = %s",
            $default_source
        ));
        
        return $source_id;
    }

    /**
     * Add a note to a lead
     *
     * @param int $lead_id Lead ID
     * @param int $user_id User ID
     * @param string $note Note content
     * @return int|false Note ID or false on failure
     */
    public function add_note($lead_id, $user_id, $note) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->notes_table,
            array(
                'lead_id' => $lead_id,
                'user_id' => $user_id,
                'note' => $note
            )
        );

        if (!$result) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get lead notes
     *
     * @param int $lead_id Lead ID
     * @return array
     */
    public function get_notes($lead_id) {
        global $wpdb;

        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as user_name
            FROM {$this->notes_table} n
            LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
            WHERE n.lead_id = %d
            ORDER BY n.created_at DESC",
            $lead_id
        ));

        return $notes;
    }

    /**
     * AJAX: Get lead
     */
    public function ajax_get_lead() {
        // Check nonce
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'wplm-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-leads-management')));
        }

        // Check permissions
        if (!current_user_can('manage_wplm_leads')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-leads-management')));
        }

        $lead_id = isset($_REQUEST['lead_id']) ? intval($_REQUEST['lead_id']) : 0;

        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Invalid lead ID', 'wp-leads-management')));
        }

        $lead = $this->get($lead_id);

        if (!$lead) {
            wp_send_json_error(array('message' => __('Lead not found', 'wp-leads-management')));
        }

        // Get notes
        $lead->notes = $this->get_notes($lead_id);

        wp_send_json_success(array('lead' => $lead));
    }

    /**
     * AJAX: Update lead
     */
    public function ajax_update_lead() {
        // Check nonce
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'wplm-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-leads-management')));
        }

        // Check permissions
        if (!current_user_can('edit_wplm_leads')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-leads-management')));
        }

        $lead_id = isset($_REQUEST['lead_id']) ? intval($_REQUEST['lead_id']) : 0;
        $data = isset($_REQUEST['data']) ? $_REQUEST['data'] : array();

        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Invalid lead ID', 'wp-leads-management')));
        }

        $result = $this->update($lead_id, $data);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to update lead', 'wp-leads-management')));
        }

        wp_send_json_success(array('message' => __('Lead updated successfully', 'wp-leads-management')));
    }

    /**
     * AJAX: Delete lead
     */
    public function ajax_delete_lead() {
        // Check nonce
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'wplm-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-leads-management')));
        }

        // Check permissions
        if (!current_user_can('delete_wplm_leads')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-leads-management')));
        }

        $lead_id = isset($_REQUEST['lead_id']) ? intval($_REQUEST['lead_id']) : 0;

        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Invalid lead ID', 'wp-leads-management')));
        }

        $result = $this->delete($lead_id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to delete lead', 'wp-leads-management')));
        }

        wp_send_json_success(array('message' => __('Lead deleted successfully', 'wp-leads-management')));
    }

    /**
     * AJAX: Add note
     */
    public function ajax_add_note() {
        // Check nonce
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'wplm-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-leads-management')));
        }

        // Check permissions
        if (!current_user_can('edit_wplm_leads')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'wp-leads-management')));
        }

        $lead_id = isset($_REQUEST['lead_id']) ? intval($_REQUEST['lead_id']) : 0;
        $note = isset($_REQUEST['note']) ? sanitize_textarea_field($_REQUEST['note']) : '';

        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Invalid lead ID', 'wp-leads-management')));
        }

        if (empty($note)) {
            wp_send_json_error(array('message' => __('Note cannot be empty', 'wp-leads-management')));
        }

        $user_id = get_current_user_id();
        $note_id = $this->add_note($lead_id, $user_id, $note);

        if (!$note_id) {
            wp_send_json_error(array('message' => __('Failed to add note', 'wp-leads-management')));
        }

        // Get the note with user info
        global $wpdb;
        $note_data = $wpdb->get_row($wpdb->prepare(
            "SELECT n.*, u.display_name as user_name
            FROM {$this->notes_table} n
            LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
            WHERE n.id = %d",
            $note_id
        ));

        wp_send_json_success(array(
            'message' => __('Note added successfully', 'wp-leads-management'),
            'note' => $note_data
        ));
    }
}