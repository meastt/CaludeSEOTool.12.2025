<?php
/**
 * Cron Scheduler (Phase 8)
 * Manages all scheduled tasks for SEO automation
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Cron_Scheduler {

    /**
     * Schedule all cron jobs
     */
    public static function schedule_jobs() {
        // Daily SEO audit
        if (!wp_next_scheduled('sap_daily_audit')) {
            wp_schedule_event(strtotime('03:00:00'), 'daily', 'sap_daily_audit');
        }

        // Weekly site profile update
        if (!wp_next_scheduled('sap_weekly_profile_update')) {
            wp_schedule_event(strtotime('Sunday 02:00:00'), 'weekly', 'sap_weekly_profile_update');
        }

        // Hourly monitoring check
        if (!wp_next_scheduled('sap_hourly_monitoring')) {
            wp_schedule_event(time(), 'hourly', 'sap_hourly_monitoring');
        }

        // Daily cleanup of expired cache
        if (!wp_next_scheduled('sap_daily_cleanup')) {
            wp_schedule_event(strtotime('04:00:00'), 'daily', 'sap_daily_cleanup');
        }

        // Weekly AI search optimization batch
        if (!wp_next_scheduled('sap_weekly_ai_optimization')) {
            wp_schedule_event(strtotime('Saturday 01:00:00'), 'weekly', 'sap_weekly_ai_optimization');
        }

        // Register cron actions
        add_action('sap_daily_audit', [__CLASS__, 'run_daily_audit']);
        add_action('sap_weekly_profile_update', [__CLASS__, 'run_profile_update']);
        add_action('sap_hourly_monitoring', [__CLASS__, 'run_monitoring_check']);
        add_action('sap_daily_cleanup', [__CLASS__, 'run_cleanup']);
        add_action('sap_weekly_ai_optimization', [__CLASS__, 'run_ai_optimization_batch']);
    }

    /**
     * Clear all scheduled jobs
     */
    public static function clear_jobs() {
        wp_clear_scheduled_hook('sap_daily_audit');
        wp_clear_scheduled_hook('sap_weekly_profile_update');
        wp_clear_scheduled_hook('sap_hourly_monitoring');
        wp_clear_scheduled_hook('sap_daily_cleanup');
        wp_clear_scheduled_hook('sap_weekly_ai_optimization');
    }

    /**
     * Run daily SEO audit
     */
    public static function run_daily_audit() {
        $settings = new SAP_Settings();

        // Check if auto-audit is enabled
        if (!$settings->get('auto_audit_enabled', false)) {
            return;
        }

        try {
            // Create new audit
            $audit_id = SAP_Database::insert_audit([
                'audit_date' => current_time('mysql'),
                'status' => 'running',
                'total_issues' => 0,
                'critical_issues' => 0,
                'warnings' => 0
            ]);

            // Run site crawl
            $crawler = new SAP_Site_Crawler();
            $crawl_results = $crawler->crawl_site($audit_id);

            // Analyze issues
            $analyzer = new SAP_Technical_Analyzer();
            $issues = $analyzer->analyze_crawl_results($audit_id);

            // Count issues by severity
            $critical = 0;
            $warnings = 0;

            foreach ($issues as $issue) {
                if ($issue['severity'] === 'critical') {
                    $critical++;
                } elseif ($issue['severity'] === 'warning') {
                    $warnings++;
                }

                // Insert issue
                SAP_Database::insert_issue([
                    'audit_id' => $audit_id,
                    'issue_type' => $issue['type'],
                    'severity' => $issue['severity'],
                    'url' => $issue['url'] ?? null,
                    'post_id' => $issue['post_id'] ?? null,
                    'description' => $issue['description'],
                    'fix_available' => $issue['fix_available'] ? 1 : 0,
                    'auto_fixable' => $issue['auto_fixable'] ? 1 : 0,
                    'priority_score' => $issue['priority_score'] ?? 0,
                    'created_at' => current_time('mysql')
                ]);
            }

            // Update audit completion
            SAP_Database::update_audit($audit_id, [
                'status' => 'completed',
                'total_issues' => count($issues),
                'critical_issues' => $critical,
                'warnings' => $warnings,
                'completed_at' => current_time('mysql')
            ]);

            // Send notification if critical issues found
            if ($critical > 0) {
                self::send_critical_alert($audit_id, $critical);
            }

            // Auto-fix if enabled
            if ($settings->is_auto_fix_enabled()) {
                self::trigger_auto_fix($audit_id);
            }

            // Log success
            error_log("SAP: Daily audit completed successfully. Audit ID: {$audit_id}");

        } catch (Exception $e) {
            error_log("SAP: Daily audit failed - " . $e->getMessage());

            // Send error notification
            self::send_error_notification('Daily Audit Failed', $e->getMessage());
        }
    }

    /**
     * Run weekly site profile update
     */
    public static function run_profile_update() {
        try {
            $profiler = new SAP_Site_Profiler();

            // Check if profile exists and is old enough for update
            $last_updated = get_option('sap_site_profile_updated');
            $days_old = $last_updated ? (time() - strtotime($last_updated)) / DAY_IN_SECONDS : 999;

            if ($days_old >= 7) {
                $profile = $profiler->build_site_profile();

                error_log("SAP: Site profile updated successfully");

                // Send notification about profile update
                self::send_notification('Site Profile Updated', 'Your site profile has been refreshed with the latest data.');
            }

        } catch (Exception $e) {
            error_log("SAP: Profile update failed - " . $e->getMessage());
        }
    }

    /**
     * Run hourly monitoring check
     */
    public static function run_monitoring_check() {
        $monitor = new SAP_Change_Tracker();

        try {
            // Check for significant changes
            $changes = $monitor->check_for_changes();

            if (!empty($changes['critical'])) {
                // Send alert
                $alert_system = new SAP_Alert_System();
                $alert_system->create_alert([
                    'alert_type' => 'critical_change',
                    'severity' => 'critical',
                    'title' => 'Critical SEO Changes Detected',
                    'message' => count($changes['critical']) . ' critical changes detected on your site',
                    'created_at' => current_time('mysql')
                ]);
            }

        } catch (Exception $e) {
            error_log("SAP: Monitoring check failed - " . $e->getMessage());
        }
    }

    /**
     * Run daily cleanup
     */
    public static function run_cleanup() {
        try {
            // Clean expired research cache
            SAP_Database::clean_expired_cache();

            // Clean old transients
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sap_%' AND option_value < UNIX_TIMESTAMP()");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sap_%' AND option_value < UNIX_TIMESTAMP()");

            // Clean old audit data (keep last 90 days)
            $wpdb->query("DELETE FROM {$wpdb->prefix}sap_audits WHERE audit_date < DATE_SUB(NOW(), INTERVAL 90 DAY)");

            error_log("SAP: Daily cleanup completed");

        } catch (Exception $e) {
            error_log("SAP: Cleanup failed - " . $e->getMessage());
        }
    }

    /**
     * Run weekly AI optimization batch
     */
    public static function run_ai_optimization_batch() {
        $settings = new SAP_Settings();

        if (!$settings->get('auto_ai_optimization_enabled', false)) {
            return;
        }

        try {
            $ai_search_fixer = new SAP_AI_Search_Fixer();

            // Find posts that need optimization
            $posts_needing_optimization = $ai_search_fixer->find_posts_needing_optimization(60);

            // Limit to 10 posts per week to avoid overwhelming the AI API
            $posts_to_optimize = array_slice($posts_needing_optimization, 0, 10);

            $results = [];
            foreach ($posts_to_optimize as $post_data) {
                $result = $ai_search_fixer->optimize_post_for_ai_search($post_data['post_id'], [
                    'pm_review' => true
                ]);

                $results[] = $result;

                // Rate limiting
                sleep(3);
            }

            error_log("SAP: AI optimization batch completed. Optimized " . count($results) . " posts");

            // Send summary notification
            if (!empty($results)) {
                self::send_notification(
                    'Weekly AI Optimization Complete',
                    count($results) . ' posts optimized for AI search this week.'
                );
            }

        } catch (Exception $e) {
            error_log("SAP: AI optimization batch failed - " . $e->getMessage());
        }
    }

    /**
     * Trigger auto-fix for audit
     */
    private static function trigger_auto_fix($audit_id) {
        $settings = new SAP_Settings();
        $max_fixes = $settings->get_max_fixes_per_run();

        // Get auto-fixable issues
        $issues = SAP_Database::get_issues($audit_id, 'pending');
        $auto_fixable = array_filter($issues, function($issue) {
            return $issue->auto_fixable == 1;
        });

        // Limit to max fixes
        $issue_ids = array_slice(array_column($auto_fixable, 'id'), 0, $max_fixes);

        if (!empty($issue_ids)) {
            $auto_fixer = new SAP_Auto_Fixer();
            $results = $auto_fixer->execute_fixes($issue_ids);

            error_log("SAP: Auto-fix applied {$results['applied']} fixes");
        }
    }

    /**
     * Send critical alert
     */
    private static function send_critical_alert($audit_id, $critical_count) {
        $settings = new SAP_Settings();
        $email = $settings->get_notification_email();

        $subject = "[SEO Alert] {$critical_count} Critical Issues Found";
        $message = "Your SEO audit found {$critical_count} critical issues that need attention.\n\n";
        $message .= "View full report: " . admin_url("admin.php?page=seo-autopilot-audit&audit_id={$audit_id}");

        wp_mail($email, $subject, $message);
    }

    /**
     * Send general notification
     */
    private static function send_notification($subject, $message) {
        $settings = new SAP_Settings();
        $email = $settings->get_notification_email();

        wp_mail($email, "[SEO Autopilot] {$subject}", $message);
    }

    /**
     * Send error notification
     */
    private static function send_error_notification($subject, $error) {
        $settings = new SAP_Settings();
        $email = $settings->get_notification_email();

        $message = "An error occurred:\n\n{$error}\n\nPlease check your SEO Autopilot Pro settings.";

        wp_mail($email, "[SEO Autopilot Error] {$subject}", $message);
    }

    /**
     * Get next scheduled time for job
     */
    public static function get_next_run($hook) {
        $timestamp = wp_next_scheduled($hook);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    /**
     * Manually trigger a job
     */
    public static function trigger_job($job_name) {
        switch ($job_name) {
            case 'audit':
                return self::run_daily_audit();
            case 'profile':
                return self::run_profile_update();
            case 'monitoring':
                return self::run_monitoring_check();
            case 'cleanup':
                return self::run_cleanup();
            case 'ai_optimization':
                return self::run_ai_optimization_batch();
            default:
                return new WP_Error('invalid_job', 'Invalid job name');
        }
    }

    /**
     * Get cron job status
     */
    public static function get_jobs_status() {
        return [
            'daily_audit' => [
                'name' => 'Daily SEO Audit',
                'next_run' => self::get_next_run('sap_daily_audit'),
                'frequency' => 'Daily at 3:00 AM'
            ],
            'weekly_profile' => [
                'name' => 'Weekly Profile Update',
                'next_run' => self::get_next_run('sap_weekly_profile_update'),
                'frequency' => 'Weekly (Sunday 2:00 AM)'
            ],
            'hourly_monitoring' => [
                'name' => 'Hourly Monitoring',
                'next_run' => self::get_next_run('sap_hourly_monitoring'),
                'frequency' => 'Every hour'
            ],
            'daily_cleanup' => [
                'name' => 'Daily Cleanup',
                'next_run' => self::get_next_run('sap_daily_cleanup'),
                'frequency' => 'Daily at 4:00 AM'
            ],
            'weekly_ai_optimization' => [
                'name' => 'Weekly AI Optimization',
                'next_run' => self::get_next_run('sap_weekly_ai_optimization'),
                'frequency' => 'Weekly (Saturday 1:00 AM)'
            ]
        ];
    }
}
