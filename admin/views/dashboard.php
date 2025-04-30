<?php
/**
 * Dashboard view
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Get lead data for charts
global $wpdb;
$leads_table = $wpdb->prefix . 'wplm_leads';
$status_table = $wpdb->prefix . 'wplm_lead_statuses';
$sources_table = $wpdb->prefix . 'wplm_lead_sources';

// Get lead counts by status
$status_counts = $wpdb->get_results(
    "SELECT s.name, s.color, COUNT(l.id) as count
    FROM $status_table s
    LEFT JOIN $leads_table l ON s.id = l.status_id
    GROUP BY s.id
    ORDER BY s.order_num ASC"
);

// Get lead counts by source
$source_counts = $wpdb->get_results(
    "SELECT s.name, COUNT(l.id) as count
    FROM $sources_table s
    LEFT JOIN $leads_table l ON s.id = l.source_id
    GROUP BY s.id
    ORDER BY count DESC"
);

// Get leads by date for the past 30 days
$date_counts = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT DATE(created_at) as date, COUNT(id) as count
        FROM $leads_table
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC"
    )
);

// Format data for charts
$status_labels = array();
$status_data = array();
$status_colors = array();

foreach ($status_counts as $status) {
    $status_labels[] = $status->name;
    $status_data[] = $status->count;
    $status_colors[] = $status->color;
}

$source_labels = array();
$source_data = array();

foreach ($source_counts as $source) {
    $source_labels[] = $source->name;
    $source_data[] = $source->count;
}

$date_labels = array();
$date_data = array();

// Create an array of the last 30 days
$dates = array();
for ($i = 30; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[$date] = 0;
}

// Fill in actual counts
foreach ($date_counts as $date_count) {
    $dates[$date_count->date] = $date_count->count;
}

// Format for chart
foreach ($dates as $date => $count) {
    $date_labels[] = date_i18n(get_option('date_format'), strtotime($date));
    $date_data[] = $count;
}

// Get total leads count
$total_leads = $wpdb->get_var("SELECT COUNT(id) FROM $leads_table");

// Get new leads in the last 7 days
$new_leads_7days = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(id) FROM $leads_table WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
    )
);

// Get leads created today
$leads_today = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(id) FROM $leads_table WHERE DATE(created_at) = CURDATE()"
    )
);

// Get conversion rate (leads with status "converted")
$converted_leads = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(l.id) FROM $leads_table l
        LEFT JOIN $status_table s ON l.status_id = s.id
        WHERE s.slug = %s",
        'converted'
    )
);

$conversion_rate = $total_leads > 0 ? round(($converted_leads / $total_leads) * 100, 1) : 0;

// Get recent leads
$recent_leads = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT l.*, s.name as status_name, s.color as status_color, src.name as source_name
        FROM $leads_table l
        LEFT JOIN $status_table s ON l.status_id = s.id
        LEFT JOIN $sources_table src ON l.source_id = src.id
        ORDER BY l.created_at DESC
        LIMIT 5"
    )
);
?>

<div class="wrap wplm-admin-wrap">
    <h1><?php _e('Leads Dashboard', 'wp-leads-management'); ?></h1>
    
    <div class="wplm-dashboard">
        <!-- Stats Cards -->
        <div class="wplm-stats-cards">
            <div class="row">
                <div class="col-md-3">
                    <div class="card wplm-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php _e('Total Leads', 'wp-leads-management'); ?></h5>
                            <h2 class="card-value"><?php echo number_format($total_leads); ?></h2>
                            <p class="card-text"><?php _e('All time', 'wp-leads-management'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card wplm-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php _e('New Leads', 'wp-leads-management'); ?></h5>
                            <h2 class="card-value"><?php echo number_format($new_leads_7days); ?></h2>
                            <p class="card-text"><?php _e('Last 7 days', 'wp-leads-management'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card wplm-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php _e('Today', 'wp-leads-management'); ?></h5>
                            <h2 class="card-value"><?php echo number_format($leads_today); ?></h2>
                            <p class="card-text"><?php _e('New leads today', 'wp-leads-management'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card wplm-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php _e('Conversion Rate', 'wp-leads-management'); ?></h5>
                            <h2 class="card-value"><?php echo number_format($conversion_rate, 1); ?>%</h2>
                            <p class="card-text"><?php _e('Leads converted', 'wp-leads-management'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="wplm-charts">
            <div class="row">
                <div class="col-md-8">
                    <div class="card wplm-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php _e('Leads Trend', 'wp-leads-management'); ?></h5>
                            <canvas id="leadsChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card wplm-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php _e('Leads by Status', 'wp-leads-management'); ?></h5>
                            <canvas id="statusChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card wplm-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php _e('Leads by Source', 'wp-leads-management'); ?></h5>
                            <canvas id="sourceChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card wplm-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php _e('Recent Leads', 'wp-leads-management'); ?></h5>
                            <div class="wplm-recent-leads">
                                <?php if ($recent_leads) : ?>
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th><?php _e('Name', 'wp-leads-management'); ?></th>
                                                <th><?php _e('Email', 'wp-leads-management'); ?></th>
                                                <th><?php _e('Status', 'wp-leads-management'); ?></th>
                                                <th><?php _e('Date', 'wp-leads-management'); ?></th>
                                                <th><?php _e('Actions', 'wp-leads-management'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_leads as $lead) : ?>
                                                <tr>
                                                    <td><?php echo esc_html("{$lead->first_name} {$lead->last_name}"); ?></td>
                                                    <td><?php echo esc_html($lead->email); ?></td>
                                                    <td>
                                                        <span class="badge" style="background-color: <?php echo esc_attr($lead->status_color); ?>">
                                                            <?php echo esc_html($lead->status_name); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date_i18n(get_option('date_format'), strtotime($lead->created_at)); ?></td>
                                                    <td>
                                                        <a href="<?php echo admin_url('admin.php?page=wplm-leads&action=view&id=' . $lead->id); ?>" class="button button-small">
                                                            <?php _e('View', 'wp-leads-management'); ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else : ?>
                                    <p><?php _e('No leads found.', 'wp-leads-management'); ?></p>
                                <?php endif; ?>
                                
                                <div class="text-center mt-3">
                                    <a href="<?php echo admin_url('admin.php?page=wplm-leads'); ?>" class="button button-primary">
                                        <?php _e('View All Leads', 'wp-leads-management'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Charts
    const ctx1 = document.getElementById('leadsChart').getContext('2d');
    const ctx2 = document.getElementById('statusChart').getContext('2d');
    const ctx3 = document.getElementById('sourceChart').getContext('2d');
    
    // Leads Trend Chart
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($date_labels); ?>,
            datasets: [{
                label: '<?php _e('New Leads', 'wp-leads-management'); ?>',
                data: <?php echo json_encode($date_data); ?>,
                fill: true,
                backgroundColor: 'rgba(0, 122, 255, 0.1)',
                borderColor: 'rgba(0, 122, 255, 1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Status Chart
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($status_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($status_data); ?>,
                backgroundColor: <?php echo json_encode($status_colors); ?>
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Source Chart
    new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($source_labels); ?>,
            datasets: [{
                label: '<?php _e('Leads', 'wp-leads-management'); ?>',
                data: <?php echo json_encode($source_data); ?>,
                backgroundColor: 'rgba(52, 199, 89, 0.7)',
                borderColor: 'rgba(52, 199, 89, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
});
</script>