<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table = $wpdb->prefix . 'sap_audits';
$audits = $wpdb->get_results("SELECT * FROM $table ORDER BY audit_date DESC LIMIT 10");
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <p>
        <button class="button button-primary" onclick="runAudit()">Run New Audit</button>
    </p>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Status</th>
                <th>Total Issues</th>
                <th>Critical</th>
                <th>Warnings</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($audits): foreach ($audits as $audit): ?>
            <tr>
                <td><?php echo esc_html($audit->audit_date); ?></td>
                <td><span class="sap-badge sap-badge-<?php echo esc_attr($audit->status); ?>"><?php echo esc_html(ucfirst($audit->status)); ?></span></td>
                <td><?php echo number_format($audit->total_issues); ?></td>
                <td><?php echo number_format($audit->critical_issues); ?></td>
                <td><?php echo number_format($audit->warnings); ?></td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=seo-autopilot-fixes&audit_id=' . $audit->id); ?>" class="button">View Issues</a>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6">No audits yet. Run your first audit!</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function runAudit() {
    if (!confirm('Run a new SEO audit?')) return;
    location.href = '<?php echo admin_url('admin.php?page=seo-autopilot'); ?>';
}
</script>
