<?php
/**
 * Settings Page
 */

if (!defined('ABSPATH')) exit;

class SAP_Settings_Page {
    public function render() {
        // Handle form submission
        if (isset($_POST['sap_settings_submit'])) {
            check_admin_referer('sap_settings');
            
            $settings = new SAP_Settings();
            $settings->update($_POST['sap_settings']);
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        include SAP_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
