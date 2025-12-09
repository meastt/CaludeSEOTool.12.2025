<?php
/**
 * API Manager class
 * Handles API credentials and secure storage
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_API_Manager {

    private $credentials = [];
    private $encryption_key;

    public function __construct() {
        $this->encryption_key = $this->get_encryption_key();
        $this->load_credentials();
    }

    /**
     * Load credentials from database
     */
    private function load_credentials() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_credentials';

        $results = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1");

        foreach ($results as $row) {
            $this->credentials[$row->service_name] = [
                'api_key' => $this->decrypt($row->api_key),
                'api_secret' => $this->decrypt($row->api_secret),
                'oauth_token' => $this->decrypt($row->oauth_token),
                'oauth_refresh' => $this->decrypt($row->oauth_refresh),
                'additional_data' => maybe_unserialize($row->additional_data),
                'last_verified' => $row->last_verified
            ];
        }
    }

    /**
     * Get encryption key for credentials
     */
    private function get_encryption_key() {
        $key = get_option('sap_encryption_key');

        if (!$key) {
            // Generate new encryption key
            $key = bin2hex(random_bytes(32));
            update_option('sap_encryption_key', $key, false);
        }

        return $key;
    }

    /**
     * Encrypt sensitive data
     */
    private function encrypt($data) {
        if (empty($data)) {
            return '';
        }

        // Use OpenSSL for encryption
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $this->encryption_key, 0, $iv);

        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypt sensitive data
     */
    private function decrypt($data) {
        if (empty($data)) {
            return '';
        }

        $parts = explode('::', base64_decode($data), 2);

        if (count($parts) !== 2) {
            return '';
        }

        list($encrypted_data, $iv) = $parts;
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $this->encryption_key, 0, $iv);
    }

    /**
     * Get credential value
     */
    public function get_credential($service, $key) {
        return $this->credentials[$service][$key] ?? null;
    }

    /**
     * Save API credentials
     */
    public function save_credentials($service, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_credentials';

        $encrypted_data = [
            'service_name' => $service,
            'api_key' => $this->encrypt($data['api_key'] ?? ''),
            'api_secret' => $this->encrypt($data['api_secret'] ?? ''),
            'oauth_token' => $this->encrypt($data['oauth_token'] ?? ''),
            'oauth_refresh' => $this->encrypt($data['oauth_refresh'] ?? ''),
            'additional_data' => maybe_serialize($data['additional_data'] ?? []),
            'last_verified' => current_time('mysql'),
            'is_active' => 1
        ];

        // Check if credentials already exist
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE service_name = %s",
            $service
        ));

        if ($exists) {
            $wpdb->update(
                $table,
                $encrypted_data,
                ['service_name' => $service]
            );
        } else {
            $wpdb->insert($table, $encrypted_data);
        }

        // Reload credentials
        $this->load_credentials();

        return true;
    }

    /**
     * Verify API credentials
     */
    public function verify_credentials($service) {
        switch ($service) {
            case 'claude':
                return $this->verify_claude();
            case 'gemini':
                return $this->verify_gemini();
            case 'gsc':
                return $this->verify_gsc();
            case 'pagespeed':
                return $this->verify_pagespeed();
            default:
                return false;
        }
    }

    /**
     * Verify Claude API
     */
    private function verify_claude() {
        $api_key = $this->get_credential('claude', 'api_key');

        if (!$api_key) {
            return false;
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 10,
                'messages' => [
                    ['role' => 'user', 'content' => 'test']
                ]
            ])
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Verify Gemini API
     */
    private function verify_gemini() {
        $api_key = $this->get_credential('gemini', 'api_key');

        if (!$api_key) {
            return false;
        }

        $response = wp_remote_get(
            'https://generativelanguage.googleapis.com/v1/models?key=' . $api_key
        );

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Verify Google Search Console
     */
    private function verify_gsc() {
        $oauth_token = $this->get_credential('gsc', 'oauth_token');

        if (!$oauth_token) {
            return false;
        }

        // Test API call
        $response = wp_remote_get('https://www.googleapis.com/webmasters/v3/sites', [
            'headers' => [
                'Authorization' => 'Bearer ' . $oauth_token
            ]
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Verify PageSpeed API
     */
    private function verify_pagespeed() {
        $api_key = $this->get_credential('pagespeed', 'api_key');

        if (!$api_key) {
            return false;
        }

        $response = wp_remote_get(
            'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=' . urlencode(get_site_url()) . '&key=' . $api_key
        );

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get all configured services
     */
    public function get_configured_services() {
        return array_keys($this->credentials);
    }

    /**
     * Check if service is configured
     */
    public function is_service_configured($service) {
        return isset($this->credentials[$service]) && !empty($this->credentials[$service]['api_key']);
    }
}
