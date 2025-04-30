<?php
/**
 * Shortcodes class
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class WPLM_Shortcodes {
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_shortcodes();
    }

    /**
     * Initialize shortcodes
     */
    private function init_shortcodes() {
        add_shortcode('wplm_form', array($this, 'render_form'));
    }

    /**
     * Render lead form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_form($atts) {
        $atts = shortcode_atts(array(
            'title' => '',
            'description' => '',
            'source' => 'website',
            'fields' => 'first_name,last_name,email,phone,company',
            'submit_text' => '',
            'redirect' => '',
            'success_message' => '',
            'class' => '',
            'id' => ''
        ), $atts, 'wplm_form');

        // Get settings
        $settings = get_option('wplm_settings');
        $required_fields = isset($settings['required_fields']) ? $settings['required_fields'] : array('first_name', 'last_name', 'email');

        // Get form title
        $form_title = !empty($atts['title']) ? $atts['title'] : (isset($settings['form_title']) ? $settings['form_title'] : __('Contact Us', 'wp-leads-management'));
        
        // Get submit button text
        $submit_text = !empty($atts['submit_text']) ? $atts['submit_text'] : (isset($settings['submit_button_text']) ? $settings['submit_button_text'] : __('Submit', 'wp-leads-management'));
        
        // Get success message
        $success_message = !empty($atts['success_message']) ? $atts['success_message'] : (isset($settings['success_message']) ? $settings['success_message'] : __('Thank you for contacting us! We will get back to you shortly.', 'wp-leads-management'));
        
        // Get fields to display
        $display_fields = explode(',', $atts['fields']);
        $display_fields = array_map('trim', $display_fields);

        // Start output buffering
        ob_start();

        // Form wrapper
        $form_id = !empty($atts['id']) ? $atts['id'] : 'wplm-form-' . mt_rand(1000, 9999);
        $form_class = !empty($atts['class']) ? 'wplm-form ' . $atts['class'] : 'wplm-form';
        ?>
        <div id="<?php echo esc_attr($form_id); ?>-wrapper" class="<?php echo esc_attr($form_class); ?>-wrapper">
            <?php if (!empty($form_title)) : ?>
                <h3 class="wplm-form-title"><?php echo esc_html($form_title); ?></h3>
            <?php endif; ?>

            <?php if (!empty($atts['description'])) : ?>
                <div class="wplm-form-description"><?php echo wp_kses_post($atts['description']); ?></div>
            <?php endif; ?>

            <div class="wplm-form-message" style="display: none;"></div>

            <form id="<?php echo esc_attr($form_id); ?>" class="<?php echo esc_attr($form_class); ?>" method="post">
                <div class="wplm-form-fields">
                    <?php if (in_array('first_name', $display_fields)) : ?>
                        <div class="wplm-form-field form-group">
                            <label for="<?php echo esc_attr($form_id); ?>-first-name">
                                <?php esc_html_e('First Name', 'wp-leads-management'); ?>
                                <?php if (in_array('first_name', $required_fields)) : ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" id="<?php echo esc_attr($form_id); ?>-first-name" name="first_name" class="form-control <?php echo in_array('first_name', $required_fields) ? 'required' : ''; ?>" 
                                <?php echo in_array('first_name', $required_fields) ? 'required' : ''; ?>>
                            <div class="invalid-feedback"></div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('last_name', $display_fields)) : ?>
                        <div class="wplm-form-field form-group">
                            <label for="<?php echo esc_attr($form_id); ?>-last-name">
                                <?php esc_html_e('Last Name', 'wp-leads-management'); ?>
                                <?php if (in_array('last_name', $required_fields)) : ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" id="<?php echo esc_attr($form_id); ?>-last-name" name="last_name" class="form-control <?php echo in_array('last_name', $required_fields) ? 'required' : ''; ?>" 
                                <?php echo in_array('last_name', $required_fields) ? 'required' : ''; ?>>
                            <div class="invalid-feedback"></div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('email', $display_fields)) : ?>
                        <div class="wplm-form-field form-group">
                            <label for="<?php echo esc_attr($form_id); ?>-email">
                                <?php esc_html_e('Email', 'wp-leads-management'); ?>
                                <?php if (in_array('email', $required_fields)) : ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="email" id="<?php echo esc_attr($form_id); ?>-email" name="email" class="form-control <?php echo in_array('email', $required_fields) ? 'required' : ''; ?>" 
                                <?php echo in_array('email', $required_fields) ? 'required' : ''; ?>>
                            <div class="invalid-feedback"></div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('phone', $display_fields)) : ?>
                        <div class="wplm-form-field form-group">
                            <label for="<?php echo esc_attr($form_id); ?>-phone">
                                <?php esc_html_e('Phone', 'wp-leads-management'); ?>
                                <?php if (in_array('phone', $required_fields)) : ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="tel" id="<?php echo esc_attr($form_id); ?>-phone" name="phone" class="form-control <?php echo in_array('phone', $required_fields) ? 'required' : ''; ?>" 
                                <?php echo in_array('phone', $required_fields) ? 'required' : ''; ?>>
                            <div class="invalid-feedback"></div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('company', $display_fields)) : ?>
                        <div class="wplm-form-field form-group">
                            <label for="<?php echo esc_attr($form_id); ?>-company">
                                <?php esc_html_e('Company', 'wp-leads-management'); ?>
                                <?php if (in_array('company', $required_fields)) : ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" id="<?php echo esc_attr($form_id); ?>-company" name="company" class="form-control <?php echo in_array('company', $required_fields) ? 'required' : ''; ?>" 
                                <?php echo in_array('company', $required_fields) ? 'required' : ''; ?>>
                            <div class="invalid-feedback"></div>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('message', $display_fields)) : ?>
                        <div class="wplm-form-field form-group">
                            <label for="<?php echo esc_attr($form_id); ?>-message">
                                <?php esc_html_e('Message', 'wp-leads-management'); ?>
                                <?php if (in_array('message', $required_fields)) : ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            <textarea id="<?php echo esc_attr($form_id); ?>-message" name="message" class="form-control <?php echo in_array('message', $required_fields) ? 'required' : ''; ?>" rows="4" 
                                <?php echo in_array('message', $required_fields) ? 'required' : ''; ?>></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="source" value="<?php echo esc_attr($atts['source']); ?>">
                    <input type="hidden" name="action" value="wplm_submit_lead">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('wplm-nonce'); ?>">
                    <?php if (!empty($atts['redirect'])) : ?>
                        <input type="hidden" name="redirect" value="<?php echo esc_url($atts['redirect']); ?>">
                    <?php endif; ?>

                    <div class="wplm-form-field form-group">
                        <button type="submit" class="wplm-submit-button btn btn-primary">
                            <span class="wplm-submit-text"><?php echo esc_html($submit_text); ?></span>
                            <span class="wplm-loading" style="display: none;">
                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                <?php esc_html_e('Submitting...', 'wp-leads-management'); ?>
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <script>
        (function($) {
            $(document).ready(function() {
                var $form = $('#<?php echo esc_attr($form_id); ?>');
                var $formWrapper = $('#<?php echo esc_attr($form_id); ?>-wrapper');
                var $message = $formWrapper.find('.wplm-form-message');
                var $submitBtn = $form.find('.wplm-submit-button');
                var $submitText = $submitBtn.find('.wplm-submit-text');
                var $loading = $submitBtn.find('.wplm-loading');

                $form.on('submit', function(e) {
                    e.preventDefault();
                    
                    // Reset form validation
                    $form.find('.is-invalid').removeClass('is-invalid');
                    $form.find('.invalid-feedback').html('');
                    $message.html('').hide();
                    
                    // Show loading state
                    $submitText.hide();
                    $loading.show();
                    $submitBtn.prop('disabled', true);
                    
                    // Collect form data
                    var formData = $(this).serialize();
                    
                    // Submit via AJAX
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                // Show success message
                                $message.html('<div class="alert alert-success">' + response.data.message + '</div>').fadeIn();
                                
                                // Reset form
                                $form[0].reset();
                                
                                // Redirect if specified
                                var redirectUrl = $form.find('input[name="redirect"]').val();
                                if (redirectUrl) {
                                    setTimeout(function() {
                                        window.location.href = redirectUrl;
                                    }, 1000);
                                }
                            } else {
                                // Show general error message if provided
                                if (response.data.message) {
                                    $message.html('<div class="alert alert-danger">' + response.data.message + '</div>').fadeIn();
                                }
                                
                                // Show field-specific errors
                                if (response.data.errors) {
                                    $.each(response.data.errors, function(field, error) {
                                        var $input = $form.find('[name="' + field + '"]');
                                        $input.addClass('is-invalid');
                                        $input.siblings('.invalid-feedback').html(error);
                                    });
                                }
                            }
                        },
                        error: function() {
                            $message.html('<div class="alert alert-danger"><?php echo esc_js(__('An error occurred. Please try again later.', 'wp-leads-management')); ?></div>').fadeIn();
                        },
                        complete: function() {
                            // Reset button state
                            $loading.hide();
                            $submitText.show();
                            $submitBtn.prop('disabled', false);
                        }
                    });
                });
            });
        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }
}