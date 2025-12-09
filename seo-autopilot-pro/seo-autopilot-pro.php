<?php
/**
 * Plugin Name: SEO Autopilot Pro
 * Plugin URI: https://yoursite.com/seo-autopilot-pro
 * Description: Automated technical SEO auditing and fixing for WordPress sites with AI intelligence
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: seo-autopilot-pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SAP_VERSION', '1.0.0');
define('SAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Require core classes
require_once SAP_PLUGIN_DIR . 'includes/class-plugin-core.php';
require_once SAP_PLUGIN_DIR . 'includes/class-database.php';
require_once SAP_PLUGIN_DIR . 'includes/class-api-manager.php';
require_once SAP_PLUGIN_DIR . 'includes/class-settings.php';
require_once SAP_PLUGIN_DIR . 'includes/class-security.php';

// Initialize plugin
function sap_init() {
    $plugin = SAP_Plugin_Core::get_instance();
    $plugin->init();
}
add_action('plugins_loaded', 'sap_init');

// Activation hook
register_activation_hook(__FILE__, 'sap_activate');
function sap_activate() {
    SAP_Database::create_tables();

    // Schedule cron jobs
    if (class_exists('SAP_Cron_Scheduler')) {
        SAP_Cron_Scheduler::schedule_jobs();
    }

    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'sap_deactivate');
function sap_deactivate() {
    // Clear scheduled cron jobs
    if (class_exists('SAP_Cron_Scheduler')) {
        SAP_Cron_Scheduler::clear_jobs();
    }

    flush_rewrite_rules();
}
