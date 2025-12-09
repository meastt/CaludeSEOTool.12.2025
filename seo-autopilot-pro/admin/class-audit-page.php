<?php
/**
 * Audit Page
 */

if (!defined('ABSPATH')) exit;

class SAP_Audit_Page {
    public function render() {
        include SAP_PLUGIN_DIR . 'admin/views/audit.php';
    }
}
