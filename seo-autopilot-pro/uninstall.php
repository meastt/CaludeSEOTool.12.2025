<?php
/**
 * Uninstall handler for SEO Autopilot Pro
 * Removes all plugin data when uninstalled
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all plugin options
delete_option('sap_settings');
delete_option('sap_site_profile');
delete_option('sap_site_profile_updated');
delete_option('sap_api_credentials');
delete_option('sap_pm_quality_threshold');
delete_option('sap_enable_content_research');

// Drop all custom tables
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sap_audits");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sap_issues");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sap_fixes");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sap_credentials");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sap_crawl_data");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sap_performance");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sap_alerts");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sap_research_cache");

// Clear all scheduled cron jobs
wp_clear_scheduled_hook('sap_daily_audit');
wp_clear_scheduled_hook('sap_weekly_profile_update');
wp_clear_scheduled_hook('sap_hourly_monitoring');
