<?php
/**
 * Settings management class
 * Handles plugin settings and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Settings {

    private $settings;
    private $default_settings = [
        'pm_quality_threshold' => 80,
        'enable_content_research' => true,
        'auto_fix_enabled' => false,
        'notification_email' => '',
        'audit_frequency' => 'daily',
        'max_fixes_per_run' => 50,
        'thin_content_threshold' => 300,
        'target_word_count' => 800,
        'enable_competitor_analysis' => true,
        'profile_update_frequency' => 'monthly'
    ];

    public function __construct() {
        $this->load_settings();
    }

    /**
     * Load settings from database
     */
    private function load_settings() {
        $saved_settings = get_option('sap_settings', []);
        $this->settings = wp_parse_args($saved_settings, $this->default_settings);
    }

    /**
     * Get setting value
     */
    public function get($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set setting value
     */
    public function set($key, $value) {
        $this->settings[$key] = $value;
        return update_option('sap_settings', $this->settings);
    }

    /**
     * Get all settings
     */
    public function get_all() {
        return $this->settings;
    }

    /**
     * Update multiple settings at once
     */
    public function update($settings) {
        $this->settings = wp_parse_args($settings, $this->settings);
        return update_option('sap_settings', $this->settings);
    }

    /**
     * Reset to default settings
     */
    public function reset() {
        $this->settings = $this->default_settings;
        return update_option('sap_settings', $this->settings);
    }

    /**
     * Get PM quality threshold
     */
    public function get_pm_quality_threshold() {
        return (int) $this->get('pm_quality_threshold', 80);
    }

    /**
     * Check if content research is enabled
     */
    public function is_content_research_enabled() {
        return (bool) $this->get('enable_content_research', true);
    }

    /**
     * Check if auto-fix is enabled
     */
    public function is_auto_fix_enabled() {
        return (bool) $this->get('auto_fix_enabled', false);
    }

    /**
     * Get notification email
     */
    public function get_notification_email() {
        $email = $this->get('notification_email');
        return !empty($email) ? $email : get_option('admin_email');
    }

    /**
     * Get audit frequency
     */
    public function get_audit_frequency() {
        return $this->get('audit_frequency', 'daily');
    }

    /**
     * Get max fixes per run
     */
    public function get_max_fixes_per_run() {
        return (int) $this->get('max_fixes_per_run', 50);
    }

    /**
     * Get thin content threshold
     */
    public function get_thin_content_threshold() {
        return (int) $this->get('thin_content_threshold', 300);
    }

    /**
     * Get target word count for content expansion
     */
    public function get_target_word_count() {
        return (int) $this->get('target_word_count', 800);
    }

    /**
     * Check if competitor analysis is enabled
     */
    public function is_competitor_analysis_enabled() {
        return (bool) $this->get('enable_competitor_analysis', true);
    }
}
