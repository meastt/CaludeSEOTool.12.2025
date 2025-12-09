<?php
/**
 * REST API Endpoints (Phase 7)
 * Provides REST API for admin dashboard and external integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_REST_Endpoints {

    private $namespace = 'sap/v1';

    /**
     * Register all REST API routes
     */
    public function register_routes() {
        // Dashboard routes
        register_rest_route($this->namespace, '/dashboard/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard_stats'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Audit routes
        register_rest_route($this->namespace, '/audits', [
            'methods' => 'GET',
            'callback' => [$this, 'get_audits'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/audits/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_audit'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/audits/run', [
            'methods' => 'POST',
            'callback' => [$this, 'run_audit'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Issue routes
        register_rest_route($this->namespace, '/issues', [
            'methods' => 'GET',
            'callback' => [$this, 'get_issues'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/issues/(?P<id>\d+)/fix', [
            'methods' => 'POST',
            'callback' => [$this, 'fix_issue'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // AI Search Optimization routes
        register_rest_route($this->namespace, '/ai-search/analyze/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'analyze_ai_search'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/ai-search/optimize/(?P<post_id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'optimize_for_ai_search'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/ai-search/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ai_search_stats'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Site Profile routes
        register_rest_route($this->namespace, '/profile', [
            'methods' => 'GET',
            'callback' => [$this, 'get_site_profile'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/profile/rebuild', [
            'methods' => 'POST',
            'callback' => [$this, 'rebuild_site_profile'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Settings routes
        register_rest_route($this->namespace, '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Alerts routes
        register_rest_route($this->namespace, '/alerts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_alerts'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/alerts/(?P<id>\d+)/read', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_alert_read'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Cron jobs routes
        register_rest_route($this->namespace, '/cron/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cron_status'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/cron/trigger/(?P<job>[a-z_]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'trigger_cron_job'],
            'permission_callback' => [$this, 'check_permission']
        ]);
    }

    /**
     * Permission check
     */
    public function check_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats($request) {
        $stats = [
            'total_posts' => wp_count_posts('post')->publish,
            'total_pages' => wp_count_posts('page')->publish,
            'total_issues' => $this->get_total_issues(),
            'critical_issues' => $this->get_critical_issues(),
            'fixes_applied' => $this->get_fixes_applied(),
            'ai_search_score' => $this->get_average_ai_search_score(),
            'last_audit' => $this->get_last_audit_info(),
            'unread_alerts' => $this->get_unread_alerts_count()
        ];

        return rest_ensure_response($stats);
    }

    /**
     * Get audits list
     */
    public function get_audits($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_audits';

        $limit = $request->get_param('limit') ?: 20;
        $offset = $request->get_param('offset') ?: 0;

        $audits = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY audit_date DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        return rest_ensure_response($audits);
    }

    /**
     * Get single audit
     */
    public function get_audit($request) {
        $audit_id = $request->get_param('id');
        $audit = SAP_Database::get_audit($audit_id);

        if (!$audit) {
            return new WP_Error('not_found', 'Audit not found', ['status' => 404]);
        }

        $issues = SAP_Database::get_issues($audit_id);

        return rest_ensure_response([
            'audit' => $audit,
            'issues' => $issues
        ]);
    }

    /**
     * Run new audit
     */
    public function run_audit($request) {
        // Trigger audit in background
        wp_schedule_single_event(time(), 'sap_daily_audit');

        return rest_ensure_response([
            'success' => true,
            'message' => 'Audit scheduled'
        ]);
    }

    /**
     * Get issues
     */
    public function get_issues($request) {
        $audit_id = $request->get_param('audit_id');
        $status = $request->get_param('status');

        if ($audit_id) {
            $issues = SAP_Database::get_issues($audit_id, $status);
        } else {
            // Get latest audit issues
            global $wpdb;
            $table_audits = $wpdb->prefix . 'sap_audits';
            $latest_audit = $wpdb->get_row("SELECT * FROM $table_audits ORDER BY audit_date DESC LIMIT 1");

            if ($latest_audit) {
                $issues = SAP_Database::get_issues($latest_audit->id, $status);
            } else {
                $issues = [];
            }
        }

        return rest_ensure_response($issues);
    }

    /**
     * Fix single issue
     */
    public function fix_issue($request) {
        $issue_id = $request->get_param('id');

        $auto_fixer = new SAP_Auto_Fixer();
        $result = $auto_fixer->execute_fixes([$issue_id]);

        return rest_ensure_response($result);
    }

    /**
     * Analyze post for AI search
     */
    public function analyze_ai_search($request) {
        $post_id = $request->get_param('post_id');

        $optimizer = new SAP_AI_Search_Optimizer();
        $analysis = $optimizer->analyze_ai_search_readiness($post_id);

        if (is_wp_error($analysis)) {
            return $analysis;
        }

        return rest_ensure_response($analysis);
    }

    /**
     * Optimize post for AI search
     */
    public function optimize_for_ai_search($request) {
        $post_id = $request->get_param('post_id');
        $options = $request->get_json_params();

        $ai_search_fixer = new SAP_AI_Search_Fixer();
        $result = $ai_search_fixer->optimize_post_for_ai_search($post_id, $options);

        return rest_ensure_response($result);
    }

    /**
     * Get AI search optimization stats
     */
    public function get_ai_search_stats($request) {
        $ai_search_fixer = new SAP_AI_Search_Fixer();
        $stats = $ai_search_fixer->get_optimization_stats();

        return rest_ensure_response($stats);
    }

    /**
     * Get site profile
     */
    public function get_site_profile($request) {
        $profile = get_option('sap_site_profile');
        $last_updated = get_option('sap_site_profile_updated');

        return rest_ensure_response([
            'profile' => $profile,
            'last_updated' => $last_updated
        ]);
    }

    /**
     * Rebuild site profile
     */
    public function rebuild_site_profile($request) {
        $profiler = new SAP_Site_Profiler();
        $profile = $profiler->build_site_profile();

        return rest_ensure_response([
            'success' => true,
            'profile' => $profile
        ]);
    }

    /**
     * Get settings
     */
    public function get_settings($request) {
        $settings = new SAP_Settings();

        return rest_ensure_response($settings->get_all());
    }

    /**
     * Update settings
     */
    public function update_settings($request) {
        $data = $request->get_json_params();

        $settings = new SAP_Settings();
        $settings->update($data);

        return rest_ensure_response([
            'success' => true,
            'settings' => $settings->get_all()
        ]);
    }

    /**
     * Get alerts
     */
    public function get_alerts($request) {
        $alert_system = new SAP_Alert_System();

        $unread_only = $request->get_param('unread');

        if ($unread_only) {
            $alerts = $alert_system->get_unread_alerts();
        } else {
            $alerts = $alert_system->get_alerts();
        }

        return rest_ensure_response($alerts);
    }

    /**
     * Mark alert as read
     */
    public function mark_alert_read($request) {
        $alert_id = $request->get_param('id');

        $alert_system = new SAP_Alert_System();
        $alert_system->mark_as_read($alert_id);

        return rest_ensure_response(['success' => true]);
    }

    /**
     * Get cron job status
     */
    public function get_cron_status($request) {
        $status = SAP_Cron_Scheduler::get_jobs_status();

        return rest_ensure_response($status);
    }

    /**
     * Trigger cron job manually
     */
    public function trigger_cron_job($request) {
        $job = $request->get_param('job');

        $result = SAP_Cron_Scheduler::trigger_job($job);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'message' => "Job '{$job}' triggered successfully"
        ]);
    }

    // Helper methods

    private function get_total_issues() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_issues';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
    }

    private function get_critical_issues() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_issues';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE severity = 'critical' AND status = 'pending'");
    }

    private function get_fixes_applied() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_fixes';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE success = 1");
    }

    private function get_average_ai_search_score() {
        global $wpdb;
        $avg = $wpdb->get_var("
            SELECT AVG(CAST(meta_value AS UNSIGNED))
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'sap_ai_search_score'
        ");

        return $avg ? round($avg) : 0;
    }

    private function get_last_audit_info() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_audits';

        $audit = $wpdb->get_row("SELECT * FROM $table ORDER BY audit_date DESC LIMIT 1");

        if (!$audit) {
            return null;
        }

        return [
            'id' => $audit->id,
            'date' => $audit->audit_date,
            'status' => $audit->status,
            'total_issues' => $audit->total_issues,
            'critical_issues' => $audit->critical_issues
        ];
    }

    private function get_unread_alerts_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_alerts';
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_read = 0");
    }
}
