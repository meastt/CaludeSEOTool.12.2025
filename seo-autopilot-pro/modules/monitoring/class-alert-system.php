<?php
/**
 * Alert System (Phase 8)
 * Manages SEO alerts and notifications
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Alert_System {

    /**
     * Create new alert
     */
    public function create_alert($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';

        $wpdb->insert($table, $data);

        $alert_id = $wpdb->insert_id;

        // Send notification if critical
        if ($data['severity'] === 'critical') {
            $this->send_critical_notification($data);
        }

        return $alert_id;
    }

    /**
     * Get unread alerts
     */
    public function get_unread_alerts() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';

        return $wpdb->get_results(
            "SELECT * FROM $table WHERE is_read = 0 ORDER BY created_at DESC LIMIT 50"
        );
    }

    /**
     * Get all alerts
     */
    public function get_alerts($limit = 100, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }

    /**
     * Mark alert as read
     */
    public function mark_as_read($alert_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';

        return $wpdb->update(
            $table,
            ['is_read' => 1],
            ['id' => $alert_id]
        );
    }

    /**
     * Mark all alerts as read
     */
    public function mark_all_as_read() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';

        return $wpdb->query("UPDATE $table SET is_read = 1 WHERE is_read = 0");
    }

    /**
     * Delete alert
     */
    public function delete_alert($alert_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';

        return $wpdb->delete($table, ['id' => $alert_id]);
    }

    /**
     * Get alert count by severity
     */
    public function get_alert_counts() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';

        $counts = $wpdb->get_results("
            SELECT severity, COUNT(*) as count
            FROM $table
            WHERE is_read = 0
            GROUP BY severity
        ", OBJECT_K);

        return [
            'critical' => isset($counts['critical']) ? $counts['critical']->count : 0,
            'warning' => isset($counts['warning']) ? $counts['warning']->count : 0,
            'info' => isset($counts['info']) ? $counts['info']->count : 0
        ];
    }

    /**
     * Send critical notification
     */
    private function send_critical_notification($alert) {
        $settings = new SAP_Settings();
        $email = $settings->get_notification_email();

        $subject = "[CRITICAL] " . $alert['title'];
        $message = $alert['message'] . "\n\n";

        if (!empty($alert['url'])) {
            $message .= "Affected URL: " . $alert['url'] . "\n\n";
        }

        $message .= "View in dashboard: " . admin_url('admin.php?page=seo-autopilot-alerts');

        wp_mail($email, $subject, $message);
    }

    /**
     * Clean old alerts (keep last 30 days)
     */
    public function clean_old_alerts() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';

        return $wpdb->query("
            DELETE FROM $table
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND is_read = 1
        ");
    }

    /**
     * Get recent critical alerts
     */
    public function get_recent_critical($days = 7) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table
            WHERE severity = 'critical'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC
        ", $days));
    }

    /**
     * Check for duplicate alerts (prevent spam)
     */
    private function is_duplicate_alert($alert_type, $url = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table
            WHERE alert_type = %s
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $alert_type
        );

        if ($url) {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $table
                WHERE alert_type = %s
                AND url = %s
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                $alert_type,
                $url
            );
        }

        return $wpdb->get_var($query) > 0;
    }

    /**
     * Create alert with duplicate check
     */
    public function create_alert_safe($data) {
        if ($this->is_duplicate_alert($data['alert_type'], $data['url'] ?? null)) {
            return false; // Don't create duplicate
        }

        return $this->create_alert($data);
    }
}
