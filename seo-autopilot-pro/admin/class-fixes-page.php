<?php
/**
 * Fixes Page
 */

if (!defined('ABSPATH')) exit;

class SAP_Fixes_Page {
    public function render() {
        include SAP_PLUGIN_DIR . 'admin/views/fixes.php';
    }
}
