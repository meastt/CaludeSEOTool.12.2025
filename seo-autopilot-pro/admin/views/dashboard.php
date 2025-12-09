<?php
/**
 * Dashboard View
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="sap-dashboard">
        <!-- Quick Stats -->
        <div class="sap-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">

            <!-- Total Issues -->
            <div class="sap-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #f39c12; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;">Total Issues</h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #f39c12;"><?php echo number_format($stats['total_issues']); ?></p>
            </div>

            <!-- Critical Issues -->
            <div class="sap-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #e74c3c; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;">Critical Issues</h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #e74c3c;"><?php echo number_format($stats['critical_issues']); ?></p>
            </div>

            <!-- Fixes Applied -->
            <div class="sap-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #27ae60; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;">Fixes Applied</h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #27ae60;"><?php echo number_format($stats['fixes_applied']); ?></p>
            </div>

            <!-- AI Search Score -->
            <div class="sap-stat-card" style="background: #fff; padding: 20px; border-left: 4px solid #3498db; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #666;">Avg AI Search Score</h3>
                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #3498db;"><?php echo $stats['ai_stats']['average_score']; ?>/100</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
            <!-- Latest Audit -->
            <div class="sap-card" style="background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2 style="margin: 0 0 15px 0;">Latest Audit</h2>

                <?php if ($stats['latest_audit']): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <tr>
                            <th>Date:</th>
                            <td><?php echo esc_html($stats['latest_audit']->audit_date); ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><span class="sap-badge sap-badge-<?php echo esc_attr($stats['latest_audit']->status); ?>"><?php echo esc_html(ucfirst($stats['latest_audit']->status)); ?></span></td>
                        </tr>
                        <tr>
                            <th>Total Issues:</th>
                            <td><?php echo number_format($stats['latest_audit']->total_issues); ?></td>
                        </tr>
                        <tr>
                            <th>Critical:</th>
                            <td><?php echo number_format($stats['latest_audit']->critical_issues); ?></td>
                        </tr>
                        <tr>
                            <th>Warnings:</th>
                            <td><?php echo number_format($stats['latest_audit']->warnings); ?></td>
                        </tr>
                    </table>

                    <p style="margin-top: 15px;">
                        <a href="<?php echo admin_url('admin.php?page=seo-autopilot-audits&audit_id=' . $stats['latest_audit']->id); ?>" class="button button-primary">
                            View Full Report
                        </a>
                    </p>
                <?php else: ?>
                    <p>No audits run yet.</p>
                    <p>
                        <button class="button button-primary" onclick="runAudit()">Run First Audit</button>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Alerts & Quick Actions -->
            <div class="sap-sidebar">
                <!-- Alerts -->
                <div class="sap-card" style="background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                    <h3 style="margin: 0 0 15px 0;">Alerts</h3>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <strong style="color: #e74c3c;"><?php echo number_format($stats['alert_counts']['critical']); ?></strong> Critical
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                            <strong style="color: #f39c12;"><?php echo number_format($stats['alert_counts']['warning']); ?></strong> Warnings
                        </li>
                        <li style="padding: 10px 0;">
                            <strong style="color: #3498db;"><?php echo number_format($stats['alert_counts']['info']); ?></strong> Info
                        </li>
                    </ul>
                </div>

                <!-- Site Profile -->
                <div class="sap-card" style="background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                    <h3 style="margin: 0 0 15px 0;">Site Profile</h3>

                    <?php if ($stats['has_profile']): ?>
                        <p style="color: #27ae60; margin: 0 0 10px 0;">✓ Profile active</p>
                        <p style="font-size: 12px; color: #666; margin: 0 0 10px 0;">
                            Last updated:<br><?php echo $stats['profile_updated'] ? esc_html(date('M j, Y', strtotime($stats['profile_updated']))) : 'Never'; ?>
                        </p>
                        <button class="button button-secondary" onclick="rebuildProfile()">Rebuild Profile</button>
                    <?php else: ?>
                        <p style="color: #e74c3c; margin: 0 0 10px 0;">⚠ No profile</p>
                        <p style="font-size: 12px; color: #666; margin: 0 0 10px 0;">
                            Build a site profile for AI-powered optimizations
                        </p>
                        <button class="button button-primary" onclick="buildProfile()">Build Profile</button>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="sap-card" style="background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 15px 0;">Quick Actions</h3>
                    <p><button class="button button-primary button-large" style="width: 100%; margin-bottom: 10px;" onclick="runAudit()">▶ Run SEO Audit</button></p>
                    <p><button class="button button-secondary" style="width: 100%; margin-bottom: 10px;" onclick="location.href='<?php echo admin_url('admin.php?page=seo-autopilot-fixes'); ?>'">🔧 View Fixes</button></p>
                    <p><button class="button button-secondary" style="width: 100%;" onclick="location.href='<?php echo admin_url('admin.php?page=seo-autopilot-settings'); ?>'">⚙ Settings</button></p>
                </div>
            </div>
        </div>

        <!-- AI Search Optimization Stats -->
        <div class="sap-card" style="background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
            <h2 style="margin: 0 0 15px 0;">🤖 AI Search Optimization</h2>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px;">
                <div>
                    <p style="margin: 0; font-size: 12px; color: #666;">Total Posts</p>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold;"><?php echo number_format($stats['ai_stats']['total_posts']); ?></p>
                </div>
                <div>
                    <p style="margin: 0; font-size: 12px; color: #666;">Optimized</p>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #27ae60;"><?php echo number_format($stats['ai_stats']['optimized_posts']); ?></p>
                </div>
                <div>
                    <p style="margin: 0; font-size: 12px; color: #666;">Avg Score</p>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #3498db;"><?php echo $stats['ai_stats']['average_score']; ?>/100</p>
                </div>
                <div>
                    <p style="margin: 0; font-size: 12px; color: #666;">Excellent (80+)</p>
                    <p style="margin: 5px 0 0 0; font-size: 24px; font-weight: bold; color: #f39c12;"><?php echo number_format($stats['ai_stats']['score_distribution']['excellent']); ?></p>
                </div>
            </div>

            <p style="margin: 0;">
                <em>AI Search Optimization makes your content citable by ChatGPT, Perplexity, and Google AI Overviews.</em>
            </p>
        </div>
    </div>
</div>

<script>
function runAudit() {
    if (!confirm('Run a full SEO audit? This may take a few minutes.')) return;

    jQuery.post(ajaxurl, {
        action: 'sap_run_audit',
        nonce: '<?php echo wp_create_nonce('sap_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            alert('Audit started! Check back in a few minutes.');
            location.reload();
        } else {
            alert('Error: ' + response.data.message);
        }
    });
}

function buildProfile() {
    if (!confirm('Build site profile? This will analyze your site to understand your niche and brand voice.')) return;

    jQuery.post(ajaxurl, {
        action: 'sap_build_profile',
        nonce: '<?php echo wp_create_nonce('sap_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            alert('Profile built successfully!');
            location.reload();
        } else {
            alert('Error: ' + response.data.message);
        }
    });
}

function rebuildProfile() {
    if (!confirm('Rebuild site profile? This will refresh your niche analysis.')) return;
    buildProfile();
}
</script>

<style>
.sap-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}
.sap-badge-completed { background: #d4edda; color: #155724; }
.sap-badge-running { background: #fff3cd; color: #856404; }
.sap-badge-failed { background: #f8d7da; color: #721c24; }
</style>
