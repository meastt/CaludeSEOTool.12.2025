<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_issues = $wpdb->prefix . 'sap_issues';

// Get latest audit issues
$table_audits = $wpdb->prefix . 'sap_audits';
$latest_audit = $wpdb->get_row("SELECT * FROM $table_audits ORDER BY audit_date DESC LIMIT 1");

$issues = [];
if ($latest_audit) {
    $issues = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_issues WHERE audit_id = %d AND status = 'pending' ORDER BY priority_score DESC LIMIT 50",
        $latest_audit->id
    ));
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if ($latest_audit): ?>
        <p><strong>Showing issues from audit:</strong> <?php echo esc_html($latest_audit->audit_date); ?></p>
    <?php endif; ?>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Severity</th>
                <th>Type</th>
                <th>Description</th>
                <th>Priority</th>
                <th>Auto-Fix</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($issues): foreach ($issues as $issue): ?>
            <tr>
                <td>
                    <span class="sap-severity sap-severity-<?php echo esc_attr($issue->severity); ?>">
                        <?php echo esc_html(ucfirst($issue->severity)); ?>
                    </span>
                </td>
                <td><?php echo esc_html($issue->issue_type); ?></td>
                <td><?php echo esc_html($issue->description); ?></td>
                <td><?php echo number_format($issue->priority_score); ?></td>
                <td><?php echo $issue->auto_fixable ? '✓ Yes' : '✗ No'; ?></td>
                <td>
                    <?php if ($issue->auto_fixable): ?>
                        <button class="button button-primary" onclick="fixIssue(<?php echo $issue->id; ?>)">Auto-Fix</button>
                    <?php else: ?>
                        <span style="color: #999;">Manual fix required</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6">No pending issues! 🎉</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.sap-severity { padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
.sap-severity-critical { background: #f8d7da; color: #721c24; }
.sap-severity-warning { background: #fff3cd; color: #856404; }
.sap-severity-info { background: #d1ecf1; color: #0c5460; }
</style>

<script>
function fixIssue(issueId) {
    if (!confirm('Apply auto-fix for this issue?')) return;
    
    jQuery.post(ajaxurl, {
        action: 'sap_fix_issue',
        issue_id: issueId,
        nonce: '<?php echo wp_create_nonce('sap_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            alert('Fix applied successfully!');
            location.reload();
        } else {
            alert('Error: ' + response.data.message);
        }
    });
}
</script>
