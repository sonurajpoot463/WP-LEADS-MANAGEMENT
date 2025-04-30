<?php
/**
 * Leads list view
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Get statuses and sources for filtering
$statuses = WPLM()->lead->get_statuses();
$sources = WPLM()->lead->get_sources();
?>

<div class="wrap wplm-admin-wrap">
    <h1 class="wp-heading-inline"><?php _e('Leads', 'wp-leads-management'); ?></h1>
    
    <a href="<?php echo admin_url('admin.php?page=wplm-add-lead'); ?>" class="page-title-action">
        <?php _e('Add New', 'wp-leads-management'); ?>
    </a>
    
    <?php if (isset($_GET['deleted'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                $count = intval($_GET['deleted']);
                printf(
                    _n(
                        '%s lead deleted successfully.',
                        '%s leads deleted successfully.',
                        $count,
                        'wp-leads-management'
                    ),
                    number_format_i18n($count)
                );
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="wplm-filters card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <select id="filter-status" class="form-control">
                        <option value=""><?php _e('All Statuses', 'wp-leads-management'); ?></option>
                        <?php foreach ($statuses as $status) : ?>
                            <option value="<?php echo esc_attr($status->slug); ?>">
                                <?php echo esc_html($status->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filter-source" class="form-control">
                        <option value=""><?php _e('All Sources', 'wp-leads-management'); ?></option>
                        <?php foreach ($sources as $source) : ?>
                            <option value="<?php echo esc_attr($source->slug); ?>">
                                <?php echo esc_html($source->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="filter-date-start" class="form-control datepicker" placeholder="<?php _e('Start Date', 'wp-leads-management'); ?>">
                </div>
                <div class="col-md-3">
                    <input type="text" id="filter-date-end" class="form-control datepicker" placeholder="<?php _e('End Date', 'wp-leads-management'); ?>">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="button" id="apply-filters" class="button button-primary">
                        <?php _e('Apply Filters', 'wp-leads-management'); ?>
                    </button>
                    <button type="button" id="reset-filters" class="button">
                        <?php _e('Reset', 'wp-leads-management'); ?>
                    </button>
                    <?php if (current_user_can('export_wplm_leads')) : ?>
                        <a href="<?php echo admin_url('admin-ajax.php?action=wplm_export_leads'); ?>" id="export-leads" class="button button-secondary float-end">
                            <?php _e('Export to CSV', 'wp-leads-management'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="wplm-leads-table">
        <table id="wplm-leads-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th><?php _e('ID', 'wp-leads-management'); ?></th>
                    <th><?php _e('Name', 'wp-leads-management'); ?></th>
                    <th><?php _e('Email', 'wp-leads-management'); ?></th>
                    <th><?php _e('Phone', 'wp-leads-management'); ?></th>
                    <th><?php _e('Company', 'wp-leads-management'); ?></th>
                    <th><?php _e('Source', 'wp-leads-management'); ?></th>
                    <th><?php _e('Status', 'wp-leads-management'); ?></th>
                    <th><?php _e('Assigned To', 'wp-leads-management'); ?></th>
                    <th><?php _e('Created', 'wp-leads-management'); ?></th>
                    <th><?php _e('Actions', 'wp-leads-management'); ?></th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize DataTable
    var table = $('#wplm-leads-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: ajaxurl,
            type: 'POST',
            data: function(d) {
                d.action = 'wplm_get_leads';
                d.nonce = '<?php echo wp_create_nonce('wplm-nonce'); ?>';
                d.status = $('#filter-status').val();
                d.source = $('#filter-source').val();
                d.start_date = $('#filter-date-start').val();
                d.end_date = $('#filter-date-end').val();
            }
        },
        columns: [
            { data: 0 },
            { data: 1 },
            { data: 2 },
            { data: 3 },
            { data: 4 },
            { data: 5 },
            { data: 6 },
            { data: 7 },
            { data: 8 },
            { data: 9, orderable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            search: '<?php _e('Search:', 'wp-leads-management'); ?>',
            lengthMenu: '<?php _e('Show _MENU_ entries', 'wp-leads-management'); ?>',
            info: '<?php _e('Showing _START_ to _END_ of _TOTAL_ entries', 'wp-leads-management'); ?>',
            infoEmpty: '<?php _e('Showing 0 to 0 of 0 entries', 'wp-leads-management'); ?>',
            infoFiltered: '<?php _e('(filtered from _MAX_ total entries)', 'wp-leads-management'); ?>',
            emptyTable: '<?php _e('No leads found.', 'wp-leads-management'); ?>',
            zeroRecords: '<?php _e('No matching leads found.', 'wp-leads-management'); ?>',
            paginate: {
                first: '<?php _e('First', 'wp-leads-management'); ?>',
                previous: '<?php _e('Previous', 'wp-leads-management'); ?>',
                next: '<?php _e('Next', 'wp-leads-management'); ?>',
                last: '<?php _e('Last', 'wp-leads-management'); ?>'
            }
        }
    });
    
    // Apply filters
    $('#apply-filters').on('click', function() {
        table.ajax.reload();
        
        // Update export URL
        var exportUrl = '<?php echo admin_url('admin-ajax.php?action=wplm_export_leads'); ?>';
        exportUrl += '&status=' + $('#filter-status').val();
        exportUrl += '&source=' + $('#filter-source').val();
        exportUrl += '&start_date=' + $('#filter-date-start').val();
        exportUrl += '&end_date=' + $('#filter-date-end').val();
        exportUrl += '&nonce=<?php echo wp_create_nonce('wplm-nonce'); ?>';
        
        $('#export-leads').attr('href', exportUrl);
    });
    
    // Reset filters
    $('#reset-filters').on('click', function() {
        $('#filter-status, #filter-source').val('');
        $('#filter-date-start, #filter-date-end').val('');
        table.ajax.reload();
        
        // Reset export URL
        $('#export-leads').attr('href', '<?php echo admin_url('admin-ajax.php?action=wplm_export_leads'); ?>');
    });
    
    // Initialize datepickers
    $('.datepicker').datepicker({
        dateFormat: 'yy-mm-dd',
        maxDate: 0
    });
});
</script>