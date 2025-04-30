<?php
/**
 * Form Handler class
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class WPLM_Form_Handler {
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
        add_action('wp_ajax_wplm_submit_lead', array($this, 'ajax_submit_lead'));
        add_action('wp_ajax_nopriv_wplm_submit_lead', array($this, 'ajax_submit_lead'));
    }

    /**
     * AJAX: Submit lead form
     */
    public function ajax_submit_lead() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wplm-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-leads-management')));
        }

        // Get settings
        $settings = get_option('wplm_settings');
        $required_fields = isset($settings['required_fields']) ? $settings['required_fields'] : array('first_name', 'last_name', 'email');

        // Validate required fields
        $errors = array();
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[$field] = __('This field is required', 'wp-leads-management');
            }
        }

        // Validate email
        if (!empty($_POST['email']) && !is_email($_POST['email'])) {
            $errors['email'] = __('Please enter a valid email address', 'wp-leads-management');
        }

        // Return errors if any
        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => __('Please fix the errors below and try again', 'wp-leads-management'),
                'errors' => $errors
            ));
        }

        // Prepare lead data
        $lead_data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '',
            'company' => isset($_POST['company']) ? sanitize_text_field($_POST['company']) : ''
        );

        // Add source if provided
        if (isset($_POST['source']) && !empty($_POST['source'])) {
            global $wpdb;
            $sources_table = $wpdb->prefix . 'wplm_lead_sources';
            
            $source_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $sources_table WHERE slug = %s",
                sanitize_text_field($_POST['source'])
            ));
            
            if ($source_id) {
                $lead_data['source_id'] = $source_id;
            }
        }

        // Add custom fields as meta
        foreach ($_POST as $key => $value) {
            if (!in_array($key, array('first_name', 'last_name', 'email', 'phone', 'company', 'source', 'nonce', 'action'))) {
                $lead_data[$key] = sanitize_text_field($value);
            }
        }

        // Add form URL and referrer as meta
        $lead_data['form_url'] = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $lead_data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $lead_data['ip_address'] = $this->get_client_ip();

        // Create lead
        $lead = WPLM()->lead;
        $lead_id = $lead->create($lead_data);

        if (!$lead_id) {
            wp_send_json_error(array('message' => __('Failed to submit form. Please try again later.', 'wp-leads-management')));
        }

        // Send notification email if enabled
        if (isset($settings['email_notification']) && $settings['email_notification']) {
            $this->send_notification_email($lead_id, $lead_data);
        }

        // Return success
        $success_message = isset($settings['success_message']) ? $settings['success_message'] : __('Thank you for contacting us! We will get back to you shortly.', 'wp-leads-management');
        
        wp_send_json_success(array(
            'message' => $success_message,
            'lead_id' => $lead_id
        ));
    }

    /**
     * Send notification email
     *
     * @param int $lead_id Lead ID
     * @param array $lead_data Lead data
     */
    private function send_notification_email($lead_id, $lead_data) {
        $settings = get_option('wplm_settings');
        
        $to = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
        $subject = isset($settings['notification_subject']) ? $settings['notification_subject'] : __('New Lead Submission', 'wp-leads-management');
        
        // Build email content
        $message = sprintf(__('A new lead has been submitted on your website. Here are the details:', 'wp-leads-management')) . "\n\n";
        
        $message .= sprintf(__('Name: %s %s', 'wp-leads-management'), $lead_data['first_name'], $lead_data['last_name']) . "\n";
        $message .= sprintf(__('Email: %s', 'wp-leads-management'), $lead_data['email']) . "\n";
        
        if (!empty($lead_data['phone'])) {
            $message .= sprintf(__('Phone: %s', 'wp-leads-management'), $lead_data['phone']) . "\n";
        }
        
        if (!empty($lead_data['company'])) {
            $message .= sprintf(__('Company: %s', 'wp-leads-management'), $lead_data['company']) . "\n";
        }
        
        // Add custom fields
        $message .= "\n" . __('Additional Information:', 'wp-leads-management') . "\n";
        
        foreach ($lead_data as $key => $value) {
            if (!in_array($key, array('first_name', 'last_name', 'email', 'phone', 'company', 'source_id'))) {
                if (!is_array($value) && !is_object($value)) {
                    $message .= ucfirst(str_replace('_', ' ', $key)) . ': ' . $value . "\n";
                }
            }
        }
        
        $message .= "\n\n";
        $message .= sprintf(__('View Lead: %s', 'wp-leads-management'), admin_url('admin.php?page=wplm-leads&action=view&id=' . $lead_id)) . "\n";
        
        // Send email
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
                
                if (strpos($ip, ',') !== false) {
                    $ip_array = explode(',', $ip);
                    foreach ($ip_array as $ip_single) {
                        $ip_single = trim($ip_single);
                        if (filter_var($ip_single, FILTER_VALIDATE_IP)) {
                            return $ip_single;
                        }
                    }
                }
            }
        }

        return '127.0.0.1';
    }
}