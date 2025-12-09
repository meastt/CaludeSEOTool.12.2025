<?php
/**
 * Security class
 * Handles security, nonce verification, and capability checks
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Security {

    /**
     * Verify nonce for AJAX requests
     */
    public static function verify_ajax_nonce() {
        if (!isset($_POST['nonce']) && !isset($_GET['nonce'])) {
            wp_send_json_error(['message' => 'Nonce not provided'], 403);
            exit;
        }

        $nonce = $_POST['nonce'] ?? $_GET['nonce'];

        if (!wp_verify_nonce($nonce, 'sap_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
            exit;
        }

        return true;
    }

    /**
     * Verify REST API nonce
     */
    public static function verify_rest_nonce($request) {
        $nonce = $request->get_header('X-WP-Nonce');

        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('invalid_nonce', 'Invalid nonce', ['status' => 403]);
        }

        return true;
    }

    /**
     * Check if user has required capability
     */
    public static function check_capability($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
            exit;
        }

        return true;
    }

    /**
     * Check capability for REST API
     */
    public static function check_rest_capability($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            return new WP_Error('insufficient_permissions', 'Insufficient permissions', ['status' => 403]);
        }

        return true;
    }

    /**
     * Sanitize input data
     */
    public static function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($data);
            case 'url':
                return esc_url_raw($data);
            case 'int':
                return intval($data);
            case 'float':
                return floatval($data);
            case 'bool':
                return (bool) $data;
            case 'textarea':
                return sanitize_textarea_field($data);
            case 'array':
                return is_array($data) ? array_map(['self', 'sanitize_input'], $data) : [];
            case 'html':
                return wp_kses_post($data);
            default:
                return sanitize_text_field($data);
        }
    }

    /**
     * Sanitize array of data
     */
    public static function sanitize_array($data, $schema = []) {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $type = $schema[$key] ?? 'text';
            $sanitized[$key] = self::sanitize_input($value, $type);
        }

        return $sanitized;
    }

    /**
     * Rate limiting check
     */
    public static function check_rate_limit($action, $limit = 10, $period = 60) {
        $user_id = get_current_user_id();
        $transient_key = "sap_rate_limit_{$action}_{$user_id}";

        $count = get_transient($transient_key);

        if ($count === false) {
            set_transient($transient_key, 1, $period);
            return true;
        }

        if ($count >= $limit) {
            return false;
        }

        set_transient($transient_key, $count + 1, $period);
        return true;
    }

    /**
     * Validate API key format
     */
    public static function validate_api_key($key, $service) {
        if (empty($key)) {
            return false;
        }

        switch ($service) {
            case 'claude':
                // Claude API keys start with 'sk-ant-'
                return strpos($key, 'sk-ant-') === 0;
            case 'gemini':
                // Gemini API keys are 39 characters
                return strlen($key) === 39;
            case 'pagespeed':
                // Google API keys are typically 39 characters
                return strlen($key) === 39;
            default:
                return strlen($key) > 10;
        }
    }

    /**
     * Log security event
     */
    public static function log_security_event($event_type, $details = []) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'event_type' => $event_type,
            'details' => $details
        ];

        // Store in transient (or custom table for production)
        $logs = get_transient('sap_security_logs') ?: [];
        $logs[] = $log_entry;

        // Keep only last 100 entries
        $logs = array_slice($logs, -100);

        set_transient('sap_security_logs', $logs, DAY_IN_SECONDS);
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }

        return 'UNKNOWN';
    }

    /**
     * Validate JSON data
     */
    public static function validate_json($json_string) {
        if (empty($json_string)) {
            return false;
        }

        json_decode($json_string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Prevent XSS in output
     */
    public static function escape_output($data) {
        if (is_array($data)) {
            return array_map(['self', 'escape_output'], $data);
        }

        return esc_html($data);
    }
}
