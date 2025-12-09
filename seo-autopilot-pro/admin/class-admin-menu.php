<?php
/**
 * Admin Menu (Phase 6)
 * Creates admin menu structure
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Admin_Menu {

    public function init() {
        add_action('admin_menu', [$this, 'add_menu_pages']);
    }

    /**
     * Add admin menu pages
     */
    public function add_menu_pages() {
        // Main menu
        add_menu_page(
            'SEO Autopilot Pro',
            'SEO Autopilot',
            'manage_options',
            'seo-autopilot',
            [new SAP_Dashboard_Page(), 'render'],
            'dashicons-search',
            30
        );

        // Dashboard (default)
        add_submenu_page(
            'seo-autopilot',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'seo-autopilot',
            [new SAP_Dashboard_Page(), 'render']
        );

        // Audits
        add_submenu_page(
            'seo-autopilot',
            'SEO Audits',
            'Audits',
            'manage_options',
            'seo-autopilot-audits',
            [new SAP_Audit_Page(), 'render']
        );

        // Fixes
        add_submenu_page(
            'seo-autopilot',
            'SEO Fixes',
            'Fixes',
            'manage_options',
            'seo-autopilot-fixes',
            [new SAP_Fixes_Page(), 'render']
        );

        // Settings
        add_submenu_page(
            'seo-autopilot',
            'Settings',
            'Settings',
            'manage_options',
            'seo-autopilot-settings',
            [new SAP_Settings_Page(), 'render']
        );
    }
}
