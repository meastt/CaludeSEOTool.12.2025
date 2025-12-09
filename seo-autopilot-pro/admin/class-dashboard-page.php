<?php
/**
 * Dashboard Page (Phase 6)
 * Main admin dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Dashboard_Page {

    public function render() {
        // Get statistics
        $stats = $this->get_statistics();

        include SAP_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    private function get_statistics() {
        global $wpdb;

        // Get latest audit
        $table_audits = $wpdb->prefix . 'sap_audits';
        $latest_audit = $wpdb->get_row("SELECT * FROM $table_audits ORDER BY audit_date DESC LIMIT 1");

        // Get issue counts
        $table_issues = $wpdb->prefix . 'sap_issues';
        $total_issues = $wpdb->get_var("SELECT COUNT(*) FROM $table_issues WHERE status = 'pending'");
        $critical_issues = $wpdb->get_var("SELECT COUNT(*) FROM $table_issues WHERE severity = 'critical' AND status = 'pending'");

        // Get fix stats
        $table_fixes = $wpdb->prefix . 'sap_fixes';
        $fixes_applied = $wpdb->get_var("SELECT COUNT(*) FROM $table_fixes WHERE success = 1");

        // Get AI search stats
        $ai_search_fixer = new SAP_AI_Search_Fixer();
        $ai_stats = $ai_search_fixer->get_optimization_stats();

        // Get alert counts
        $alert_system = new SAP_Alert_System();
        $alert_counts = $alert_system->get_alert_counts();

        // Get site profile status
        $has_profile = get_option('sap_site_profile') !== false;
        $profile_updated = get_option('sap_site_profile_updated');

        // Get cron status
        $cron_status = SAP_Cron_Scheduler::get_jobs_status();

        return [
            'latest_audit' => $latest_audit,
            'total_issues' => $total_issues,
            'critical_issues' => $critical_issues,
            'fixes_applied' => $fixes_applied,
            'ai_stats' => $ai_stats,
            'alert_counts' => $alert_counts,
            'has_profile' => $has_profile,
            'profile_updated' => $profile_updated,
            'cron_status' => $cron_status
        ];
    }
}
