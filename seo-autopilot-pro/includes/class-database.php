<?php
/**
 * Database management class
 * Handles creation and management of custom database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Database {

    /**
     * Create all custom database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table 1: Audit History
        $table_audits = $wpdb->prefix . 'sap_audits';
        $sql_audits = "CREATE TABLE $table_audits (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            audit_date datetime NOT NULL,
            status varchar(20) NOT NULL,
            total_issues int(11) NOT NULL,
            critical_issues int(11) NOT NULL,
            warnings int(11) NOT NULL,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY audit_date (audit_date)
        ) $charset_collate;";

        // Table 2: Issues Found
        $table_issues = $wpdb->prefix . 'sap_issues';
        $sql_issues = "CREATE TABLE $table_issues (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            audit_id bigint(20) NOT NULL,
            issue_type varchar(50) NOT NULL,
            severity enum('critical','warning','info') NOT NULL,
            url varchar(500),
            post_id bigint(20) DEFAULT NULL,
            description text NOT NULL,
            fix_available tinyint(1) DEFAULT 0,
            auto_fixable tinyint(1) DEFAULT 0,
            status enum('pending','fixed','ignored','failed') DEFAULT 'pending',
            priority_score int(11) DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY audit_id (audit_id),
            KEY status (status),
            KEY issue_type (issue_type)
        ) $charset_collate;";

        // Table 3: Fixes Applied
        $table_fixes = $wpdb->prefix . 'sap_fixes';
        $sql_fixes = "CREATE TABLE $table_fixes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            issue_id bigint(20) NOT NULL,
            fix_type varchar(50) NOT NULL,
            applied_at datetime NOT NULL,
            applied_by bigint(20) NOT NULL,
            before_value longtext,
            after_value longtext,
            success tinyint(1) NOT NULL,
            error_message text,
            rollback_available tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY issue_id (issue_id),
            KEY applied_at (applied_at)
        ) $charset_collate;";

        // Table 4: API Credentials
        $table_credentials = $wpdb->prefix . 'sap_credentials';
        $sql_credentials = "CREATE TABLE $table_credentials (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            service_name varchar(50) NOT NULL UNIQUE,
            api_key text,
            api_secret text,
            oauth_token text,
            oauth_refresh text,
            additional_data longtext,
            last_verified datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY service_name (service_name)
        ) $charset_collate;";

        // Table 5: Crawl Data
        $table_crawl = $wpdb->prefix . 'sap_crawl_data';
        $sql_crawl = "CREATE TABLE $table_crawl (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            audit_id bigint(20) NOT NULL,
            url varchar(500) NOT NULL,
            status_code int(11),
            title varchar(500),
            meta_description text,
            h1_tags text,
            word_count int(11),
            has_noindex tinyint(1) DEFAULT 0,
            has_nofollow tinyint(1) DEFAULT 0,
            canonical_url varchar(500),
            schema_types text,
            internal_links int(11) DEFAULT 0,
            external_links int(11) DEFAULT 0,
            images_count int(11) DEFAULT 0,
            images_without_alt int(11) DEFAULT 0,
            load_time float,
            page_size int(11),
            crawled_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY audit_id (audit_id),
            KEY url (url(191))
        ) $charset_collate;";

        // Table 6: Performance Metrics
        $table_performance = $wpdb->prefix . 'sap_performance';
        $sql_performance = "CREATE TABLE $table_performance (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            audit_id bigint(20) NOT NULL,
            url varchar(500) NOT NULL,
            device enum('mobile','desktop') NOT NULL,
            lcp float,
            fid float,
            cls float,
            fcp float,
            ttfb float,
            performance_score int(11),
            checked_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY audit_id (audit_id),
            KEY url (url(191))
        ) $charset_collate;";

        // Table 7: Monitoring Alerts
        $table_alerts = $wpdb->prefix . 'sap_alerts';
        $sql_alerts = "CREATE TABLE $table_alerts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            alert_type varchar(50) NOT NULL,
            severity enum('critical','warning','info') NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            url varchar(500),
            is_read tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY alert_type (alert_type),
            KEY is_read (is_read)
        ) $charset_collate;";

        // Table 8: Research Cache (Phase 2.5)
        $table_research = $wpdb->prefix . 'sap_research_cache';
        $sql_research = "CREATE TABLE $table_research (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            topic varchar(255) NOT NULL,
            research_data longtext,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY topic (topic),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_audits);
        dbDelta($sql_issues);
        dbDelta($sql_fixes);
        dbDelta($sql_credentials);
        dbDelta($sql_crawl);
        dbDelta($sql_performance);
        dbDelta($sql_alerts);
        dbDelta($sql_research);

        // Set database version
        update_option('sap_db_version', SAP_VERSION);
    }

    /**
     * Get audit by ID
     */
    public static function get_audit($audit_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_audits';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $audit_id
        ));
    }

    /**
     * Get all issues for an audit
     */
    public static function get_issues($audit_id, $status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_issues';

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE audit_id = %d AND status = %s ORDER BY priority_score DESC",
                $audit_id,
                $status
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE audit_id = %d ORDER BY priority_score DESC",
            $audit_id
        ));
    }

    /**
     * Insert new audit
     */
    public static function insert_audit($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_audits';

        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    /**
     * Insert new issue
     */
    public static function insert_issue($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_issues';

        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    /**
     * Update issue
     */
    public static function update_issue($issue_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_issues';

        return $wpdb->update(
            $table,
            $data,
            ['id' => $issue_id]
        );
    }

    /**
     * Insert fix record
     */
    public static function insert_fix($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_fixes';

        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    /**
     * Get cached research data
     */
    public static function get_cached_research($topic) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_research_cache';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE topic = %s AND expires_at > NOW()",
            $topic
        ));

        return $result ? $result->research_data : null;
    }

    /**
     * Cache research data
     */
    public static function cache_research($topic, $data, $expiry_hours = 168) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_research_cache';

        $wpdb->insert($table, [
            'topic' => $topic,
            'research_data' => $data,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"))
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Clean expired research cache
     */
    public static function clean_expired_cache() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_research_cache';

        return $wpdb->query("DELETE FROM $table WHERE expires_at < NOW()");
    }
}
