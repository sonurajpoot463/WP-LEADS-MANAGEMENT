/**
 * Frontend scripts for WP Leads Management
 */
(function($) {
    'use strict';

    // Form validation
    function validateInput($input) {
        let isValid = true;
        const $feedback = $input.siblings('.invalid-feedback');
        
        // Required validation
        if ($input.hasClass('required') && !$input.val().trim()) {
            isValid = false;
            $input.addClass('is-invalid');
            $feedback.html(wplm_params.i18n.required_field);
            return false;
        }
        
        // Email validation
        if ($input.attr('type') === 'email' && $input.val().trim()) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test($input.val().trim())) {
                isValid = false;
                $input.addClass('is-invalid');
                $feedback.html(wplm_params.i18n.invalid_email);
                return false;
            }
        }
        
        // Phone validation (basic)
        if ($input.attr('type') === 'tel' && $input.val().trim()) {
            const phoneRegex = /^[0-9()\-+\s]{7,20}$/;
            if (!phoneRegex.test($input.val().trim())) {
                isValid = false;
                $input.addClass('is-invalid');
                $feedback.html(wplm_params.i18n.invalid_phone);
                return false;
            }
        }
        
        // If valid, remove invalid class
        if (isValid) {
            $input.removeClass('is-invalid');
        }
        
        return isValid;
    }
    
    // Initialize forms
    function initForms() {
        $('.wplm-form').each(function() {
            const $form = $(this);
            
            // Input validation on blur
            $form.find('input, textarea').on('blur', function() {
                validateInput($(this));
            });
            
            // Form submission
            $form.on('submit', function(e) {
                e.preventDefault();
                
                // Validate all fields
                let isValid = true;
                $form.find('input, textarea').each(function() {
                    if (!validateInput($(this))) {
                        isValid = false;
                    }
                });
                
                if (!isValid) {
                    return false;
                }
                
                // Show loading state
                const $submitBtn = $form.find('.wplm-submit-button');
                const $submitText = $submitBtn.find('.wplm-submit-text');
                const $loading = $submitBtn.find('.wplm-loading');
                
                $submitText.hide();
                $loading.show();
                $submitBtn.prop('disabled', true);
                
                // Submit form via AJAX
                $.ajax({
                    url: wplm_params.ajax_url,
                    type: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        const $message = $form.closest('.wplm-form-wrapper').find('.wplm-form-message');
                        
                        if (response.success) {
                            // Show success message
                            $message.html('<div class="alert alert-success">' + response.data.message + '</div>').fadeIn();
                            
                            // Reset form
                            $form[0].reset();
                            
                            // Redirect if specified
                            if ($form.find('input[name="redirect"]').length) {
                                const redirectUrl = $form.find('input[name="redirect"]').val();
                                if (redirectUrl) {
                                    setTimeout(function() {
                                        window.location.href = redirectUrl;
                                    }, 1500);
                                }
                            }
                            
                            // Scroll to message
                            $('html, body').animate({
                                scrollTop: $message.offset().top - 100
                            }, 500);
                        } else {
                            // Show error message
                            if (response.data.message) {
                                $message.html('<div class="alert alert-danger">' + response.data.message + '</div>').fadeIn();
                            }
                            
                            // Show field-specific errors
                            if (response.data.errors) {
                                $.each(response.data.errors, function(field, error) {
                                    const $input = $form.find('[name="' + field + '"]');
                                    $input.addClass('is-invalid');
                                    $input.siblings('.invalid-feedback').html(error);
                                });
                            }
                        }
                    },
                    error: function() {
                        const $message = $form.closest('.wplm-form-wrapper').find('.wplm-form-message');
                        $message.html('<div class="alert alert-danger">' + wplm_params.i18n.submit_error + '</div>').fadeIn();
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
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        initForms();
    });
    
})(jQuery);