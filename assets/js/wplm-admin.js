/**
 * Admin scripts for WP Leads Management
 */
(function($) {
    'use strict';

    // Initialize lead detail view
    function initLeadView() {
        // Add note form submission
        $('#wplm-add-note-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const $textarea = $form.find('textarea[name="note"]');
            const note = $textarea.val().trim();
            
            if (!note) {
                return;
            }
            
            // Disable form
            $submitBtn.prop('disabled', true).html(wplm_admin_params.i18n.loading);
            
            // Submit note
            $.ajax({
                url: wplm_admin_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wplm_add_note',
                    lead_id: $form.data('lead-id'),
                    note: note,
                    nonce: wplm_admin_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Add note to list
                        const note = response.data.note;
                        const noteHtml = `
                            <div class="wplm-note">
                                <div class="wplm-note-header">
                                    <span class="wplm-note-user">${note.user_name}</span>
                                    <span class="wplm-note-date">${note.created_at}</span>
                                </div>
                                <div class="wplm-note-content">${note.note}</div>
                            </div>
                        `;
                        
                        $('.wplm-notes-list').prepend(noteHtml);
                        
                        // Clear form
                        $textarea.val('');
                        
                        // Show message
                        const $message = $('#wplm-messages');
                        $message.html(`<div class="notice notice-success is-dismissible"><p>${response.data.message}</p></div>`).show();
                        setTimeout(function() {
                            $message.fadeOut();
                        }, 3000);
                    } else {
                        alert(response.data.message || wplm_admin_params.i18n.add_note_error);
                    }
                },
                error: function() {
                    alert(wplm_admin_params.i18n.add_note_error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html('Add Note');
                }
            });
        });
        
        // Lead status update
        $('#lead-status').on('change', function() {
            const leadId = $(this).data('lead-id');
            const statusId = $(this).val();
            
            updateLead(leadId, { status_id: statusId });
        });
        
        // Lead assigned to update
        $('#lead-assigned-to').on('change', function() {
            const leadId = $(this).data('lead-id');
            const assignedTo = $(this).val();
            
            updateLead(leadId, { assigned_to: assignedTo });
        });
    }
    
    // Update lead via AJAX
    function updateLead(leadId, data) {
        $.ajax({
            url: wplm_admin_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wplm_update_lead',
                lead_id: leadId,
                data: data,
                nonce: wplm_admin_params.nonce
            },
            success: function(response) {
                const $message = $('#wplm-messages');
                
                if (response.success) {
                    $message.html(`<div class="notice notice-success is-dismissible"><p>${response.data.message}</p></div>`).show();
                } else {
                    $message.html(`<div class="notice notice-error is-dismissible"><p>${response.data.message || wplm_admin_params.i18n.update_error}</p></div>`).show();
                }
                
                setTimeout(function() {
                    $message.fadeOut();
                }, 3000);
            },
            error: function() {
                const $message = $('#wplm-messages');
                $message.html(`<div class="notice notice-error is-dismissible"><p>${wplm_admin_params.i18n.update_error}</p></div>`).show();
                
                setTimeout(function() {
                    $message.fadeOut();
                }, 3000);
            }
        });
    }
    
    // Initialize DataTables for leads list
    function initLeadsTable() {
        if ($('#wplm-leads-table').length) {
            $('#wplm-leads-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: wplm_admin_params.ajax_url,
                    type: 'POST',
                    data: function(d) {
                        d.action = 'wplm_get_leads';
                        d.nonce = wplm_admin_params.nonce;
                        
                        // Add filter parameters
                        const status = $('#filter-status').val();
                        const source = $('#filter-source').val();
                        
                        if (status) {
                            d.status = status;
                        }
                        
                        if (source) {
                            d.source = source;
                        }
                    }
                },
                columns: [
                    { data: 0 }, // ID
                    { data: 1 }, // Name
                    { data: 2 }, // Email
                    { data: 3 }, // Phone
                    { data: 4 }, // Company
                    { data: 5 }, // Source
                    { data: 6 }, // Status
                    { data: 7 }, // Assigned To
                    { data: 8 }, // Created At
                    { data: 9, orderable: false } // Actions
                ],
                order: [[0, 'desc']],
                language: {
                    search: 'Search:',
                    lengthMenu: 'Show _MENU_ leads per page',
                    info: 'Showing _START_ to _END_ of _TOTAL_ leads',
                    infoEmpty: 'No leads found',
                    infoFiltered: '(filtered from _MAX_ total leads)',
                    emptyTable: 'No leads found'
                }
            });
            
            // Apply filters
            $('#wplm-filters').on('change', 'select', function() {
                $('#wplm-leads-table').DataTable().ajax.reload();
            });
            
            // Export button
            $('#wplm-export').on('click', function(e) {
                e.preventDefault();
                
                const status = $('#filter-status').val();
                const source = $('#filter-source').val();
                const startDate = $('#filter-start-date').val();
                const endDate = $('#filter-end-date').val();
                
                let url = $(this).attr('href');
                url += '&nonce=' + wplm_admin_params.nonce;
                
                if (status) {
                    url += '&status=' + status;
                }
                
                if (source) {
                    url += '&source=' + source;
                }
                
                if (startDate) {
                    url += '&start_date=' + startDate;
                }
                
                if (endDate) {
                    url += '&end_date=' + endDate;
                }
                
                window.location.href = url;
            });
        }
    }
    
    // Initialize add/edit lead form
    function initLeadForm() {
        if ($('#wplm-lead-form').length) {
            // Form validation
            $('#wplm-lead-form').on('submit', function() {
                let isValid = true;
                
                // Validate required fields
                $(this).find('.required').each(function() {
                    if (!$(this).val().trim()) {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });
                
                // Validate email
                const $email = $(this).find('[name="email"]');
                if ($email.val().trim()) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test($email.val().trim())) {
                        $email.addClass('is-invalid');
                        isValid = false;
                    } else {
                        $email.removeClass('is-invalid');
                    }
                }
                
                return isValid;
            });
            
            // Remove invalid class on input
            $('#wplm-lead-form').on('input', '.is-invalid', function() {
                $(this).removeClass('is-invalid');
            });
        }
    }
    
    // Initialize date pickers
    function initDatePickers() {
        if ($.fn.datepicker) {
            $('.datepicker').datepicker({
                dateFormat: 'yy-mm-dd'
            });
        }
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        initLeadView();
        initLeadsTable();
        initLeadForm();
        initDatePickers();
    });
    
})(jQuery);