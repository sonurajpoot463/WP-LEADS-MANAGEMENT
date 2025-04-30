<?php
/**
 * Add/Edit Lead view
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Get lead data if editing
$lead_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$lead = $lead_id ? WPLM()->lead->get($lead_id) : null;

// Get statuses and sources
$statuses = WPLM()->lead->get_statuses();
$sources = WPLM()->lead->get_sources();

// Get users for assignment
$users = get_users(array(
    'role__in' => array('administrator', 'lead_manager'),
    'orderby' => 'display_name'
));
?>

<div class="wrap wplm-admin-wrap">
    <h1><?php echo $lead ? __('Edit Lead', 'wp-leads-management') : __('Add New Lead', 'wp-leads-management'); ?></h1>
    
    <div id="wplm-messages"></div>
    
    <div class="wplm-form card">
        <div class="card-body">
            <form id="wplm-lead-form" method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">
                                <?php _e('First Name', 'wp-leads-management'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control required" id="first_name" name="first_name" 
                                value="<?php echo $lead ? esc_attr($lead->first_name) : ''; ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label">
                                <?php _e('Last Name', 'wp-leads-management'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control required" id="last_name" name="last_name" 
                                value="<?php echo $lead ? esc_attr($lead->last_name) : ''; ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <?php _e('Email', 'wp-leads-management'); ?>
                                <span class="required">*</span>
                            </label>
                            <input type="email" class="form-control required" id="email" name="email" 
                                value="<?php echo $lead ? esc_attr($lead->email) : ''; ?>" required>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">
                                <?php _e('Phone', 'wp-leads-management'); ?>
                            </label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                value="<?php echo $lead ? esc_attr($lead->phone) : ''; ?>">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="company" class="form-label">
                                <?php _e('Company', 'wp-leads-management'); ?>
                            </label>
                            <input type="text" class="form-control" id="company" name="company" 
                                value="<?php echo $lead ? esc_attr($lead->company) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="source_id" class="form-label">
                                <?php _e('Source', 'wp-leads-management'); ?>
                            </label>
                            <select class="form-control" id="source_id" name="source_id">
                                <?php foreach ($sources as $source) : ?>
                                    <option value="<?php echo esc_attr($source->id); ?>" 
                                        <?php selected($lead && $lead->source_id == $source->id); ?>>
                                        <?php echo esc_html($source->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status_id" class="form-label">
                                <?php _e('Status', 'wp-leads-management'); ?>
                            </label>
                            <select class="form-control" id="status_id" name="status_id">
                                <?php foreach ($statuses as $status) : ?>
                                    <option value="<?php echo esc_attr($status->id); ?>" 
                                        <?php selected($lead && $lead->status_id == $status->id); ?>>
                                        <?php echo esc_html($status->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="assigned_to" class="form-label">
                                <?php _e('Assigned To', 'wp-leads-management'); ?>
                            </label>
                            <select class="form-control" id="assigned_to" name="assigned_to">
                                <option value=""><?php _e('Unassigned', 'wp-leads-management'); ?></option>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>" 
                                        <?php selected($lead && $lead->assigned_to == $user->ID); ?>>
                                        <?php echo esc_html($user->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <?php if ($lead) : ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($lead->id); ?>">
                <?php endif; ?>
                
                <div class="mb-3">
                    <button type="submit" class="button button-primary">
                        <?php echo $lead ? __('Update Lead', 'wp-leads-management') : __('Add Lead', 'wp-leads-management'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=wplm-leads'); ?>" class="button">
                        <?php _e('Cancel', 'wp-leads-management'); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#wplm-lead-form').on('submit', function(e) {
        e.preventDefault();
        
        // Reset validation
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').html('');
        $('#wplm-messages').html('').hide();
        
        // Get form data
        var formData = $(this).serialize();
        formData += '&action=wplm_' + ($('input[name="id"]').length ? 'update' : 'add') + '_lead';
        formData += '&nonce=<?php echo wp_create_nonce('wplm-nonce'); ?>';
        
        // Submit form
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#wplm-messages').html(
                        '<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>'
                    ).show();
                    
                    // Redirect to leads list after a delay
                    setTimeout(function() {
                        window.location.href = '<?php echo admin_url('admin.php?page=wplm-leads'); ?>';
                    }, 1000);
                } else {
                    // Show error message
                    $('#wplm-messages').html(
                        '<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>'
                    ).show();
                    
                    // Show field-specific errors
                    if (response.data.errors) {
                        $.each(response.data.errors, function(field, error) {
                            var $input = $('#' + field);
                            $input.addClass('is-invalid');
                            $input.siblings('.invalid-feedback').html(error);
                        });
                    }
                }
            },
            error: function() {
                $('#wplm-messages').html(
                    '<div class="notice notice-error is-dismissible"><p><?php _e('An error occurred. Please try again.', 'wp-leads-management'); ?></p></div>'
                ).show();
            }
        });
    });
});
</script>