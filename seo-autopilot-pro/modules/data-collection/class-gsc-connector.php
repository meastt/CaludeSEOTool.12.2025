<?php
/**
 * Google Search Console Connector
 * Handles all GSC API interactions
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_GSC_Connector {

    private $api_manager;
    private $site_url;

    public function __construct() {
        $this->api_manager = new SAP_API_Manager();
        $this->site_url = get_site_url();
    }

    /**
     * Get indexation status for site
     */
    public function get_indexation_status() {
        $access_token = $this->api_manager->get_credential('gsc', 'oauth_token');

        if (!$access_token) {
            return new WP_Error('no_token', 'GSC OAuth token not configured');
        }

        // Get sitemap URLs first
        $sitemap_urls = $this->get_sitemap_urls();

        if (is_wp_error($sitemap_urls)) {
            return $sitemap_urls;
        }

        $indexed = 0;
        $not_indexed = 0;
        $errors = [];

        // Check first 10 URLs for demo
        foreach (array_slice($sitemap_urls, 0, 10) as $url) {
            $status = $this->inspect_url($url);

            if (is_wp_error($status)) {
                $errors[] = $url;
                continue;
            }

            if ($status['indexed']) {
                $indexed++;
            } else {
                $not_indexed++;
            }
        }

        return [
            'indexed' => $indexed,
            'not_indexed' => $not_indexed,
            'total_checked' => $indexed + $not_indexed,
            'errors' => $errors
        ];
    }

    /**
     * Inspect specific URL
     */
    public function inspect_url($url) {
        $access_token = $this->api_manager->get_credential('gsc', 'oauth_token');

        if (!$access_token) {
            return new WP_Error('no_token', 'GSC OAuth token not configured');
        }

        $response = wp_remote_post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'inspectionUrl' => $url,
                'siteUrl' => $this->site_url
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error('api_error', 'GSC API returned error: ' . $code);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'indexed' => $body['inspectionResult']['indexStatusResult']['verdict'] === 'PASS',
            'coverage_state' => $body['inspectionResult']['indexStatusResult']['coverageState'] ?? 'unknown',
            'last_crawl' => $body['inspectionResult']['indexStatusResult']['lastCrawlTime'] ?? null
        ];
    }

    /**
     * Get search analytics data
     */
    public function get_search_analytics($start_date, $end_date, $dimensions = ['query']) {
        $access_token = $this->api_manager->get_credential('gsc', 'oauth_token');

        if (!$access_token) {
            return new WP_Error('no_token', 'GSC OAuth token not configured');
        }

        $response = wp_remote_post(
            'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($this->site_url) . '/searchAnalytics/query',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'startDate' => $start_date,
                    'endDate' => $end_date,
                    'dimensions' => $dimensions,
                    'rowLimit' => 1000
                ]),
                'timeout' => 30
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error('api_error', 'GSC API returned error: ' . $code);
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Get crawl errors
     */
    public function get_crawl_errors() {
        $access_token = $this->api_manager->get_credential('gsc', 'oauth_token');

        if (!$access_token) {
            return new WP_Error('no_token', 'GSC OAuth token not configured');
        }

        // Note: Crawl Errors API was deprecated, using Search Analytics for errors
        return $this->get_search_analytics(
            date('Y-m-d', strtotime('-30 days')),
            date('Y-m-d'),
            ['page']
        );
    }

    /**
     * Submit URL for indexing
     */
    public function submit_url_for_indexing($url) {
        $access_token = $this->api_manager->get_credential('gsc', 'oauth_token');

        if (!$access_token) {
            return new WP_Error('no_token', 'GSC OAuth token not configured');
        }

        $response = wp_remote_post('https://indexing.googleapis.com/v3/urlNotifications:publish', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'url' => $url,
                'type' => 'URL_UPDATED'
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error('api_error', 'Indexing API returned error: ' . $code);
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Get sitemap URLs
     */
    private function get_sitemap_urls() {
        // Check for common sitemap locations
        $sitemap_urls = [
            $this->site_url . '/sitemap.xml',
            $this->site_url . '/sitemap_index.xml',
            $this->site_url . '/wp-sitemap.xml'
        ];

        foreach ($sitemap_urls as $sitemap_url) {
            $response = wp_remote_get($sitemap_url);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $xml = wp_remote_retrieve_body($response);
                return $this->parse_sitemap($xml);
            }
        }

        // Fallback: get URLs from WordPress
        return $this->get_urls_from_wordpress();
    }

    /**
     * Parse sitemap XML
     */
    private function parse_sitemap($xml) {
        $urls = [];

        try {
            $sitemap = simplexml_load_string($xml);

            if ($sitemap === false) {
                return $this->get_urls_from_wordpress();
            }

            // Register namespace
            $namespaces = $sitemap->getNamespaces(true);

            foreach ($sitemap->url as $url_node) {
                $urls[] = (string) $url_node->loc;
            }

            // If it's a sitemap index, parse child sitemaps
            if (empty($urls) && isset($sitemap->sitemap)) {
                foreach ($sitemap->sitemap as $sitemap_node) {
                    $sitemap_url = (string) $sitemap_node->loc;
                    $child_response = wp_remote_get($sitemap_url);

                    if (!is_wp_error($child_response)) {
                        $child_urls = $this->parse_sitemap(wp_remote_retrieve_body($child_response));
                        $urls = array_merge($urls, $child_urls);
                    }
                }
            }
        } catch (Exception $e) {
            return $this->get_urls_from_wordpress();
        }

        return !empty($urls) ? $urls : $this->get_urls_from_wordpress();
    }

    /**
     * Get URLs directly from WordPress
     */
    private function get_urls_from_wordpress() {
        $urls = [];

        // Get all published posts and pages
        $posts = get_posts([
            'numberposts' => 100,
            'post_type' => ['post', 'page'],
            'post_status' => 'publish'
        ]);

        foreach ($posts as $post) {
            $urls[] = get_permalink($post->ID);
        }

        return $urls;
    }

    /**
     * Get top queries for site
     */
    public function get_top_queries($limit = 100) {
        $data = $this->get_search_analytics(
            date('Y-m-d', strtotime('-30 days')),
            date('Y-m-d'),
            ['query']
        );

        if (is_wp_error($data) || !isset($data['rows'])) {
            return [];
        }

        $queries = [];
        foreach (array_slice($data['rows'], 0, $limit) as $row) {
            $queries[] = [
                'query' => $row['keys'][0],
                'clicks' => $row['clicks'],
                'impressions' => $row['impressions'],
                'ctr' => $row['ctr'],
                'position' => $row['position']
            ];
        }

        return $queries;
    }

    /**
     * Check if GSC is configured
     */
    public function is_configured() {
        return $this->api_manager->is_service_configured('gsc');
    }
}
