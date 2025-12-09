<?php
/**
 * PageSpeed Insights Connector
 * Handles PageSpeed API interactions
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_PageSpeed_Connector {

    private $api_manager;
    private $api_key;

    public function __construct() {
        $this->api_manager = new SAP_API_Manager();
        $this->api_key = $this->api_manager->get_credential('pagespeed', 'api_key');
    }

    /**
     * Run PageSpeed analysis for URL
     */
    public function analyze_url($url, $strategy = 'mobile') {
        if (!$this->api_key) {
            return new WP_Error('no_api_key', 'PageSpeed API key not configured');
        }

        $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
        $api_url = add_query_arg([
            'url' => urlencode($url),
            'key' => $this->api_key,
            'strategy' => $strategy,
            'category' => 'performance'
        ], $api_url);

        $response = wp_remote_get($api_url, [
            'timeout' => 60 // PageSpeed can take a while
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error('api_error', 'PageSpeed API returned error: ' . $code);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $this->parse_pagespeed_results($body);
    }

    /**
     * Parse PageSpeed results into usable format
     */
    private function parse_pagespeed_results($raw_data) {
        $lighthouse = $raw_data['lighthouseResult'] ?? [];
        $audits = $lighthouse['audits'] ?? [];

        return [
            'performance_score' => round(($lighthouse['categories']['performance']['score'] ?? 0) * 100),
            'metrics' => [
                'fcp' => $audits['first-contentful-paint']['numericValue'] ?? null,
                'lcp' => $audits['largest-contentful-paint']['numericValue'] ?? null,
                'cls' => $audits['cumulative-layout-shift']['numericValue'] ?? null,
                'fid' => $audits['max-potential-fid']['numericValue'] ?? null,
                'ttfb' => $audits['server-response-time']['numericValue'] ?? null,
                'si' => $audits['speed-index']['numericValue'] ?? null,
                'tbt' => $audits['total-blocking-time']['numericValue'] ?? null,
                'tti' => $audits['interactive']['numericValue'] ?? null
            ],
            'opportunities' => $this->extract_opportunities($audits),
            'diagnostics' => $this->extract_diagnostics($audits),
            'passed_audits' => $this->extract_passed_audits($audits)
        ];
    }

    /**
     * Extract optimization opportunities
     */
    private function extract_opportunities($audits) {
        $opportunity_audits = [
            'render-blocking-resources',
            'unminified-css',
            'unminified-javascript',
            'unused-css-rules',
            'unused-javascript',
            'modern-image-formats',
            'offscreen-images',
            'uses-text-compression',
            'uses-optimized-images',
            'uses-responsive-images',
            'efficient-animated-content'
        ];

        $opportunities = [];

        foreach ($opportunity_audits as $audit_id) {
            if (isset($audits[$audit_id]) && $audits[$audit_id]['score'] < 1) {
                $audit = $audits[$audit_id];
                $opportunities[] = [
                    'id' => $audit_id,
                    'title' => $audit['title'],
                    'description' => $audit['description'],
                    'score' => $audit['score'],
                    'savings_ms' => $audit['numericValue'] ?? null,
                    'details' => $audit['details'] ?? []
                ];
            }
        }

        return $opportunities;
    }

    /**
     * Extract diagnostics
     */
    private function extract_diagnostics($audits) {
        $diagnostic_audits = [
            'mainthread-work-breakdown',
            'bootup-time',
            'uses-long-cache-ttl',
            'total-byte-weight',
            'dom-size',
            'critical-request-chains',
            'user-timings',
            'redirects',
            'uses-rel-preconnect'
        ];

        $diagnostics = [];

        foreach ($diagnostic_audits as $audit_id) {
            if (isset($audits[$audit_id])) {
                $audit = $audits[$audit_id];
                $diagnostics[] = [
                    'id' => $audit_id,
                    'title' => $audit['title'],
                    'description' => $audit['description'],
                    'score' => $audit['score'] ?? null,
                    'value' => $audit['displayValue'] ?? null,
                    'details' => $audit['details'] ?? []
                ];
            }
        }

        return $diagnostics;
    }

    /**
     * Extract passed audits
     */
    private function extract_passed_audits($audits) {
        $passed = [];

        foreach ($audits as $audit_id => $audit) {
            if (isset($audit['score']) && $audit['score'] === 1) {
                $passed[] = [
                    'id' => $audit_id,
                    'title' => $audit['title']
                ];
            }
        }

        return $passed;
    }

    /**
     * Analyze multiple URLs
     */
    public function analyze_multiple_urls($urls, $strategy = 'mobile') {
        $results = [];

        foreach ($urls as $url) {
            $result = $this->analyze_url($url, $strategy);

            if (!is_wp_error($result)) {
                $results[$url] = $result;
            }

            // Rate limiting - wait 2 seconds between requests
            sleep(2);
        }

        return $results;
    }

    /**
     * Get Core Web Vitals status
     */
    public function get_core_web_vitals($url) {
        $mobile = $this->analyze_url($url, 'mobile');
        $desktop = $this->analyze_url($url, 'desktop');

        if (is_wp_error($mobile)) {
            return $mobile;
        }

        return [
            'mobile' => [
                'lcp' => $mobile['metrics']['lcp'] ?? null,
                'fid' => $mobile['metrics']['fid'] ?? null,
                'cls' => $mobile['metrics']['cls'] ?? null,
                'score' => $mobile['performance_score']
            ],
            'desktop' => is_wp_error($desktop) ? null : [
                'lcp' => $desktop['metrics']['lcp'] ?? null,
                'fid' => $desktop['metrics']['fid'] ?? null,
                'cls' => $desktop['metrics']['cls'] ?? null,
                'score' => $desktop['performance_score']
            ],
            'cwv_status' => $this->assess_core_web_vitals($mobile['metrics'])
        ];
    }

    /**
     * Assess Core Web Vitals status
     */
    private function assess_core_web_vitals($metrics) {
        $lcp = $metrics['lcp'] ?? 0;
        $fid = $metrics['fid'] ?? 0;
        $cls = $metrics['cls'] ?? 0;

        $status = [
            'lcp' => 'good',
            'fid' => 'good',
            'cls' => 'good',
            'overall' => 'good'
        ];

        // LCP thresholds (milliseconds)
        if ($lcp > 4000) {
            $status['lcp'] = 'poor';
        } elseif ($lcp > 2500) {
            $status['lcp'] = 'needs-improvement';
        }

        // FID thresholds (milliseconds)
        if ($fid > 300) {
            $status['fid'] = 'poor';
        } elseif ($fid > 100) {
            $status['fid'] = 'needs-improvement';
        }

        // CLS thresholds
        if ($cls > 0.25) {
            $status['cls'] = 'poor';
        } elseif ($cls > 0.1) {
            $status['cls'] = 'needs-improvement';
        }

        // Overall status
        if ($status['lcp'] === 'poor' || $status['fid'] === 'poor' || $status['cls'] === 'poor') {
            $status['overall'] = 'poor';
        } elseif ($status['lcp'] === 'needs-improvement' || $status['fid'] === 'needs-improvement' || $status['cls'] === 'needs-improvement') {
            $status['overall'] = 'needs-improvement';
        }

        return $status;
    }

    /**
     * Get performance recommendations
     */
    public function get_recommendations($url) {
        $analysis = $this->analyze_url($url);

        if (is_wp_error($analysis)) {
            return $analysis;
        }

        $recommendations = [];

        // High-priority opportunities
        foreach ($analysis['opportunities'] as $opportunity) {
            if ($opportunity['score'] < 0.5) {
                $recommendations[] = [
                    'priority' => 'high',
                    'type' => 'opportunity',
                    'title' => $opportunity['title'],
                    'description' => $opportunity['description'],
                    'potential_savings' => $opportunity['savings_ms'] . 'ms'
                ];
            }
        }

        // Important diagnostics
        foreach ($analysis['diagnostics'] as $diagnostic) {
            if (isset($diagnostic['score']) && $diagnostic['score'] < 0.5) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'type' => 'diagnostic',
                    'title' => $diagnostic['title'],
                    'description' => $diagnostic['description'],
                    'value' => $diagnostic['value']
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Check if PageSpeed API is configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        return $this->analyze_url(get_site_url(), 'mobile');
    }
}
