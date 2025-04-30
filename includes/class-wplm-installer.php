<?php
/**
 * Installer class
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class WPLM_Installer {
    /**
     * Install the plugin
     */
    public static function install() {
        self::create_tables();
        self::create_options();
        self::create_roles();
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $leads_table = $wpdb->prefix . 'wplm_leads';
        $lead_meta_table = $wpdb->prefix . 'wplm_lead_meta';
        $lead_notes_table = $wpdb->prefix . 'wplm_lead_notes';
        $lead_status_table = $wpdb->prefix . 'wplm_lead_statuses';
        $lead_sources_table = $wpdb->prefix . 'wplm_lead_sources';

        // Leads table
        $sql = "CREATE TABLE IF NOT EXISTS $leads_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            company varchar(100) DEFAULT NULL,
            source_id bigint(20) unsigned DEFAULT NULL,
            status_id bigint(20) unsigned DEFAULT NULL,
            assigned_to bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY status_id (status_id),
            KEY source_id (source_id),
            KEY assigned_to (assigned_to)
        ) $charset_collate;";

        // Lead meta table
        $sql .= "CREATE TABLE IF NOT EXISTS $lead_meta_table (
            meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY lead_id (lead_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";

        // Lead notes table
        $sql .= "CREATE TABLE IF NOT EXISTS $lead_notes_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            note text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY lead_id (lead_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Lead status table
        $sql .= "CREATE TABLE IF NOT EXISTS $lead_status_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text,
            color varchar(20) DEFAULT '#007AFF',
            order_num int(11) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        // Lead sources table
        $sql .= "CREATE TABLE IF NOT EXISTS $lead_sources_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Insert default statuses if they don't exist
        $default_statuses = array(
            array(
                'name' => 'New',
                'slug' => 'new',
                'color' => '#007AFF',
                'order_num' => 1
            ),
            array(
                'name' => 'Contacted',
                'slug' => 'contacted',
                'color' => '#FF9500',
                'order_num' => 2
            ),
            array(
                'name' => 'Qualified',
                'slug' => 'qualified',
                'color' => '#5856D6',
                'order_num' => 3
            ),
            array(
                'name' => 'Proposal',
                'slug' => 'proposal',
                'color' => '#FF2D55',
                'order_num' => 4
            ),
            array(
                'name' => 'Converted',
                'slug' => 'converted',
                'color' => '#34C759',
                'order_num' => 5
            ),
            array(
                'name' => 'Lost',
                'slug' => 'lost',
                'color' => '#8E8E93',
                'order_num' => 6
            )
        );

        foreach ($default_statuses as $status) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $lead_status_table WHERE slug = %s",
                $status['slug']
            ));

            if (!$existing) {
                $wpdb->insert($lead_status_table, $status);
            }
        }

        // Insert default sources if they don't exist
        $default_sources = array(
            array(
                'name' => 'Website',
                'slug' => 'website'
            ),
            array(
                'name' => 'Referral',
                'slug' => 'referral'
            ),
            array(
                'name' => 'Social Media',
                'slug' => 'social-media'
            ),
            array(
                'name' => 'Email Campaign',
                'slug' => 'email-campaign'
            ),
            array(
                'name' => 'Phone Inquiry',
                'slug' => 'phone-inquiry'
            ),
            array(
                'name' => 'Other',
                'slug' => 'other'
            )
        );

        foreach ($default_sources as $source) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $lead_sources_table WHERE slug = %s",
                $source['slug']
            ));

            if (!$existing) {
                $wpdb->insert($lead_sources_table, $source);
            }
        }
    }

    /**
     * Create default options
     */
    private static function create_options() {
        // Plugin version
        add_option('wplm_version', WPLM_VERSION);

        // Default settings
        $default_settings = array(
            'form_title' => 'Contact Us',
            'submit_button_text' => 'Submit',
            'success_message' => 'Thank you for contacting us! We will get back to you shortly.',
            'email_notification' => true,
            'notification_email' => get_option('admin_email'),
            'notification_subject' => 'New Lead Submission',
            'required_fields' => array('first_name', 'last_name', 'email'),
            'lead_default_status' => 'new',
            'lead_default_source' => 'website'
        );

        add_option('wplm_settings', $default_settings);
    }

    /**
     * Create user roles and capabilities
     */
    private static function create_roles() {
        // Add capabilities to administrator
        $admin = get_role('administrator');
        
        $capabilities = array(
            'view_wplm_dashboard' => true,
            'manage_wplm_leads' => true,
            'add_wplm_leads' => true,
            'edit_wplm_leads' => true,
            'delete_wplm_leads' => true,
            'export_wplm_leads' => true,
            'manage_wplm_settings' => true
        );

        foreach ($capabilities as $cap => $grant) {
            $admin->add_cap($cap, $grant);
        }

        // Create Lead Manager role
        add_role(
            'lead_manager',
            __('Lead Manager', 'wp-leads-management'),
            array(
                'read' => true,
                'view_wplm_dashboard' => true,
                'manage_wplm_leads' => true,
                'add_wplm_leads' => true,
                'edit_wplm_leads' => true,
                'export_wplm_leads' => true
            )
        );
    }
}