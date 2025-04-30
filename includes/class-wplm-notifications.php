<?php
/**
 * Notifications class
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class WPLM_Notifications {
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
        add_action('wplm_lead_created', array($this, 'new_lead_notification'), 10, 2);
        add_action('wplm_lead_updated', array($this, 'lead_updated_notification'), 10, 2);
    }

    /**
     * Send notification for new lead
     *
     * @param int $lead_id Lead ID
     * @param array $lead_data Lead data
     */
    public function new_lead_notification($lead_id, $lead_data) {
        $settings = get_option('wplm_settings');
        
        // Skip if notifications are disabled
        if (!isset($settings['email_notification']) || !$settings['email_notification']) {
            return;
        }
        
        $to = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
        $subject = isset($settings['notification_subject']) ? $settings['notification_subject'] : __('New Lead Submission', 'wp-leads-management');
        
        // Build email content
        $message = $this->build_lead_email($lead_id, $lead_data, 'new');
        
        // Send email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send notification for lead update
     *
     * @param int $lead_id Lead ID
     * @param array $lead_data Lead data
     */
    public function lead_updated_notification($lead_id, $lead_data) {
        // Get assigned user if changed
        if (isset($lead_data['assigned_to']) && $lead_data['assigned_to']) {
            $user = get_user_by('id', $lead_data['assigned_to']);
            
            if ($user) {
                $to = $user->user_email;
                $subject = __('Lead Assigned to You', 'wp-leads-management');
                
                // Build email content
                $message = $this->build_lead_email($lead_id, $lead_data, 'assigned');
                
                // Send email
                $headers = array('Content-Type: text/html; charset=UTF-8');
                wp_mail($to, $subject, $message, $headers);
            }
        }
        
        // Get status changes
        if (isset($lead_data['status_id'])) {
            global $wpdb;
            $status_table = $wpdb->prefix . 'wplm_lead_statuses';
            
            $status = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM $status_table WHERE id = %d",
                $lead_data['status_id']
            ));
            
            if ($status && $status->name === 'Converted') {
                $settings = get_option('wplm_settings');
                $to = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
                $subject = __('Lead Converted', 'wp-leads-management');
                
                // Build email content
                $message = $this->build_lead_email($lead_id, $lead_data, 'converted');
                
                // Send email
                $headers = array('Content-Type: text/html; charset=UTF-8');
                wp_mail($to, $subject, $message, $headers);
            }
        }
    }

    /**
     * Build lead email content
     *
     * @param int $lead_id Lead ID
     * @param array $lead_data Lead data
     * @param string $type Email type: 'new', 'assigned', 'converted'
     * @return string
     */
    private function build_lead_email($lead_id, $lead_data, $type = 'new') {
        // Get lead data if not complete
        if (!isset($lead_data['first_name']) || !isset($lead_data['email'])) {
            $lead = WPLM()->lead->get($lead_id);
            $lead_data = (array) $lead;
        }
        
        // Get status and source names
        global $wpdb;
        $status_name = '';
        $source_name = '';
        
        if (isset($lead_data['status_id'])) {
            $status_table = $wpdb->prefix . 'wplm_lead_statuses';
            $status = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM $status_table WHERE id = %d",
                $lead_data['status_id']
            ));
            
            if ($status) {
                $status_name = $status->name;
            }
        }
        
        if (isset($lead_data['source_id'])) {
            $sources_table = $wpdb->prefix . 'wplm_lead_sources';
            $source = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM $sources_table WHERE id = %d",
                $lead_data['source_id']
            ));
            
            if ($source) {
                $source_name = $source->name;
            }
        }
        
        // Get assigned user name
        $assigned_name = '';
        if (isset($lead_data['assigned_to']) && $lead_data['assigned_to']) {
            $user = get_user_by('id', $lead_data['assigned_to']);
            if ($user) {
                $assigned_name = $user->display_name;
            }
        }
        
        // Build email content based on type
        ob_start();
        
        // Email header
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <title><?php echo get_bloginfo('name'); ?></title>
            <style type="text/css">
                body {
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    color: #333333;
                    margin: 0;
                    padding: 20px;
                    background-color: #f5f5f5;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 5px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                    padding: 20px;
                }
                h1 {
                    color: #007AFF;
                    font-size: 24px;
                    margin-top: 0;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #eeeeee;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                table td {
                    padding: 10px;
                    border-bottom: 1px solid #eeeeee;
                }
                table th {
                    text-align: left;
                    padding: 10px;
                    border-bottom: 2px solid #eeeeee;
                    color: #666666;
                }
                .footer {
                    margin-top: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #999999;
                }
                .button {
                    display: inline-block;
                    background-color: #007AFF;
                    color: #ffffff;
                    text-decoration: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <?php if ($type === 'new') : ?>
                    <h1><?php _e('New Lead Submission', 'wp-leads-management'); ?></h1>
                    <p><?php _e('A new lead has been submitted on your website.', 'wp-leads-management'); ?></p>
                <?php elseif ($type === 'assigned') : ?>
                    <h1><?php _e('Lead Assigned to You', 'wp-leads-management'); ?></h1>
                    <p><?php _e('A lead has been assigned to you for follow-up.', 'wp-leads-management'); ?></p>
                <?php elseif ($type === 'converted') : ?>
                    <h1><?php _e('Lead Converted', 'wp-leads-management'); ?></h1>
                    <p><?php _e('A lead has been marked as converted.', 'wp-leads-management'); ?></p>
                <?php endif; ?>
                
                <h2><?php _e('Lead Details', 'wp-leads-management'); ?></h2>
                <table>
                    <tr>
                        <th><?php _e('Name', 'wp-leads-management'); ?></th>
                        <td><?php echo isset($lead_data['first_name']) ? esc_html($lead_data['first_name'] . ' ' . $lead_data['last_name']) : ''; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Email', 'wp-leads-management'); ?></th>
                        <td><?php echo isset($lead_data['email']) ? esc_html($lead_data['email']) : ''; ?></td>
                    </tr>
                    <?php if (!empty($lead_data['phone'])) : ?>
                    <tr>
                        <th><?php _e('Phone', 'wp-leads-management'); ?></th>
                        <td><?php echo esc_html($lead_data['phone']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($lead_data['company'])) : ?>
                    <tr>
                        <th><?php _e('Company', 'wp-leads-management'); ?></th>
                        <td><?php echo esc_html($lead_data['company']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($status_name)) : ?>
                    <tr>
                        <th><?php _e('Status', 'wp-leads-management'); ?></th>
                        <td><?php echo esc_html($status_name); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($source_name)) : ?>
                    <tr>
                        <th><?php _e('Source', 'wp-leads-management'); ?></th>
                        <td><?php echo esc_html($source_name); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($assigned_name)) : ?>
                    <tr>
                        <th><?php _e('Assigned To', 'wp-leads-management'); ?></th>
                        <td><?php echo esc_html($assigned_name); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (isset($lead_data['created_at'])) : ?>
                    <tr>
                        <th><?php _e('Created', 'wp-leads-management'); ?></th>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($lead_data['created_at'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="<?php echo admin_url('admin.php?page=wplm-leads&action=view&id=' . $lead_id); ?>" class="button">
                        <?php _e('View Lead', 'wp-leads-management'); ?>
                    </a>
                </div>
                
                <div class="footer">
                    <p>
                        <?php printf(
                            __('This email was sent from %s', 'wp-leads-management'),
                            '<a href="' . get_bloginfo('url') . '">' . get_bloginfo('name') . '</a>'
                        ); ?>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
}