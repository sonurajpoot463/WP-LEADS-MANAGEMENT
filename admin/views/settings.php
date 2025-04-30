<?php
/**
 * Settings view
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$settings = get_option('wplm_settings', array());

// Get lead statuses and sources
$statuses = WPLM()->lead->get_statuses();
$sources = WPLM()->lead->get_sources();
?>

<div class="wrap wplm-admin-wrap">
    <h1><?php _e('Settings', 'wp-leads-management'); ?></h1>
    
    <?php if (isset($message)) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="wplm-settings card">
        <div class="card-body">
            <form method="post" action="">
                <?php wp_nonce_field('wplm_settings'); ?>
                
                <h2><?php _e('Form Settings', 'wp-leads-management'); ?></h2>
                
                <div class="mb-3">
                    <label for="form_title" class="form-label">
                        <?php _e('Form Title', 'wp-leads-management'); ?>
                    </label>
                    <input type="text" class="form-control" id="form_title" name="form_title" 
                        value="<?php echo esc_attr($settings['form_title'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="submit_button_text" class="form-label">
                        <?php _e('Submit Button Text', 'wp-leads-management'); ?>
                    </label>
                    <input type="text" class="form-control" id="submit_button_text" name="submit_button_text" 
                        value="<?php echo esc_attr($settings['submit_button_text'] ?? ''); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="success_message" class="form-label">
                        <?php _e('Success Message', 'wp-leads-management'); ?>
                    </label>
                    <textarea class="form-control" id="success_message" name="success_message" rows="3"><?php 
                        echo esc_textarea($settings['success_message'] ?? ''); 
                    ?></textarea>
                </div>
                
                <h2><?php _e('Email Notifications', 'wp-leads-management'); ?></h2>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="email_notification" name="email_notification" 
                            <?php checked(isset($settings['email_notification']) && $settings['email_notification']); ?>>
                        <label class="form-check-label" for="email_notification">
                            <?php _e('Enable email notifications for new leads', 'wp-leads-management'); ?>
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notification_email" class="form-label">
                        <?php _e('Notification Email', 'wp-leads-management'); ?>
                    </label>
                    <input type="email" class="form-control" id="notification_email" name="notification_email" 
                        value="<?php echo esc_attr($settings['notification_email'] ?? get_option('admin_email')); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="notification_subject" class="form-label">
                        <?php _e('Notification Subject', 'wp-leads-management'); ?>
                    </label>
                    <input type="text" class="form-control" id="notification_subject" name="notification_subject" 
                        value="<?php echo esc_attr($settings['notification_subject'] ?? ''); ?>">
                </div>
                
                <h2><?php _e('Form Fields', 'wp-leads-management'); ?></h2>
                
                <div class="mb-3">
                    <label class="form-label"><?php _e('Required Fields', 'wp-leads-management'); ?></label>
                    <?php
                    $required_fields = $settings['required_fields'] ?? array('first_name', 'last_name', 'email');
                    $available_fields = array(
                        'first_name' => __('First Name', 'wp-leads-management'),
                        'last_name' => __('Last Name', 'wp-leads-management'),
                        'email' => __('Email', 'wp-leads-management'),
                        'phone' => __('Phone', 'wp-leads-management'),
                        'company' => __('Company', 'wp-leads-management')
                    );
                    foreach ($available_fields as $field => $label) :
                    ?>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="required_<?php echo esc_attr($field); ?>" 
                                name="required_fields[]" value="<?php echo esc_attr($field); ?>" 
                                <?php checked(in_array($field, $required_fields)); ?>>
                            <label class="form-check-label" for="required_<?php echo esc_attr($field); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <h2><?php _e('Default Values', 'wp-leads-management'); ?></h2>
                
                <div class="mb-3">
                    <label for="lead_default_status" class="form-label">
                        <?php _e('Default Lead Status', 'wp-leads-management'); ?>
                    </label>
                    <select class="form-control" id="lead_default_status" name="lead_default_status">
                        <?php foreach ($statuses as $status) : ?>
                            <option value="<?php echo esc_attr($status->slug); ?>" 
                                <?php selected($settings['lead_default_status'] ?? 'new', $status->slug); ?>>
                                <?php echo esc_html($status->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="lead_default_source" class="form-label">
                        <?php _e('Default Lead Source', 'wp-leads-management'); ?>
                    </label>
                    <select class="form-control" id="lead_default_source" name="lead_default_source">
                        <?php foreach ($sources as $source) : ?>
                            <option value="<?php echo esc_attr($source->slug); ?>" 
                                <?php selected($settings['lead_default_source'] ?? 'website', $source->slug); ?>>
                                <?php echo esc_html($source->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <button type="submit" name="wplm_save_settings" class="button button-primary">
                        <?php _e('Save Settings', 'wp-leads-management'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>