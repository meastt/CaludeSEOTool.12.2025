<?php
/**
 * Core plugin class
 * Handles initialization and coordination of all plugin components
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Plugin_Core {

    private static $instance = null;

    private $settings;
    private $api_manager;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->load_dependencies();
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize settings
        $this->settings = new SAP_Settings();
        $this->api_manager = new SAP_API_Manager();

        // Load admin components
        if (is_admin()) {
            $this->load_admin();
        }

        // Load REST API
        $this->load_rest_api();

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Load all required dependencies
     */
    private function load_dependencies() {
        // Load module classes
        $modules_path = SAP_PLUGIN_DIR . 'modules/';

        // Data collection modules
        require_once $modules_path . 'data-collection/class-gsc-connector.php';
        require_once $modules_path . 'data-collection/class-pagespeed-connector.php';
        require_once $modules_path . 'data-collection/class-site-crawler.php';
        require_once $modules_path . 'data-collection/class-backlink-connector.php';
        require_once $modules_path . 'data-collection/class-log-analyzer.php';

        // Analysis modules
        require_once $modules_path . 'analysis/class-technical-analyzer.php';
        require_once $modules_path . 'analysis/class-ai-analyzer.php';
        require_once $modules_path . 'analysis/class-content-analyzer.php';
        require_once $modules_path . 'analysis/class-priority-scorer.php';

        // Intelligence modules (Phase 2.5)
        require_once $modules_path . 'intelligence/class-site-profiler.php';
        require_once $modules_path . 'intelligence/class-context-aware-generator.php';
        require_once $modules_path . 'intelligence/class-pm-agent.php';
        require_once $modules_path . 'intelligence/class-competitor-intelligence.php';

        // AI Search Optimization modules (Phase 2.6)
        require_once $modules_path . 'intelligence/class-ai-search-optimizer.php';
        require_once $modules_path . 'intelligence/class-faq-generator.php';
        require_once $modules_path . 'intelligence/class-direct-answer-optimizer.php';
        require_once $modules_path . 'intelligence/class-eeat-enhancer.php';

        // Fix modules
        require_once $modules_path . 'fixes/class-auto-fixer.php';
        require_once $modules_path . 'fixes/class-ai-search-fixer.php';
        require_once $modules_path . 'fixes/class-content-fixer.php';
        require_once $modules_path . 'fixes/class-technical-fixer.php';
        require_once $modules_path . 'fixes/class-image-optimizer.php';
        require_once $modules_path . 'fixes/class-schema-generator.php';

        // Monitoring modules
        require_once $modules_path . 'monitoring/class-cron-scheduler.php';
        require_once $modules_path . 'monitoring/class-change-tracker.php';
        require_once $modules_path . 'monitoring/class-alert-system.php';

        // Admin classes
        if (is_admin()) {
            require_once SAP_PLUGIN_DIR . 'admin/class-admin-menu.php';
            require_once SAP_PLUGIN_DIR . 'admin/class-dashboard-page.php';
            require_once SAP_PLUGIN_DIR . 'admin/class-audit-page.php';
            require_once SAP_PLUGIN_DIR . 'admin/class-fixes-page.php';
            require_once SAP_PLUGIN_DIR . 'admin/class-settings-page.php';
        }

        // API classes
        require_once SAP_PLUGIN_DIR . 'api/class-rest-endpoints.php';
    }

    /**
     * Load admin interface
     */
    private function load_admin() {
        $admin_menu = new SAP_Admin_Menu();
        $admin_menu->init();
    }

    /**
     * Load REST API endpoints
     */
    private function load_rest_api() {
        $rest_endpoints = new SAP_REST_Endpoints();
        add_action('rest_api_init', [$rest_endpoints, 'register_routes']);
    }

    /**
     * Register plugin hooks
     */
    private function register_hooks() {
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . SAP_PLUGIN_BASENAME, [$this, 'add_settings_link']);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'seo-autopilot') === false) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'sap-admin-dashboard',
            SAP_PLUGIN_URL . 'assets/css/admin-dashboard.css',
            [],
            SAP_VERSION
        );

        wp_enqueue_style(
            'sap-audit-report',
            SAP_PLUGIN_URL . 'assets/css/audit-report.css',
            [],
            SAP_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'sap-admin-dashboard',
            SAP_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            ['jquery', 'wp-element'],
            SAP_VERSION,
            true
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script('sap-admin-dashboard', 'sapData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('sap/v1'),
            'nonce' => wp_create_nonce('sap_nonce'),
            'siteUrl' => get_site_url()
        ]);
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=seo-autopilot-settings">' . __('Settings', 'seo-autopilot-pro') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
