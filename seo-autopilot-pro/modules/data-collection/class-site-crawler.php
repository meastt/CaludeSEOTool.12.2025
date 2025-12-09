<?php
/**
 * Site Crawler
 * Crawls site pages to collect SEO data
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Site_Crawler {

    private $crawled_urls = [];
    private $max_pages = 100;

    public function __construct() {
        $this->max_pages = apply_filters('sap_crawler_max_pages', 100);
    }

    /**
     * Crawl entire site
     */
    public function crawl_site($audit_id) {
        $urls = $this->get_urls_to_crawl();
        $results = [];

        foreach (array_slice($urls, 0, $this->max_pages) as $url) {
            $crawl_data = $this->crawl_url($url);

            if (!is_wp_error($crawl_data)) {
                // Save to database
                $this->save_crawl_data($audit_id, $url, $crawl_data);
                $results[] = $crawl_data;
            }

            // Rate limiting
            usleep(500000); // 0.5 seconds
        }

        return $results;
    }

    /**
     * Crawl single URL
     */
    public function crawl_url($url) {
        $start_time = microtime(true);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 5,
            'user-agent' => 'SEO Autopilot Pro Crawler/1.0'
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $load_time = microtime(true) - $start_time;
        $status_code = wp_remote_retrieve_response_code($response);
        $html = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        // Parse HTML
        $parsed = $this->parse_html($html);

        return [
            'url' => $url,
            'status_code' => $status_code,
            'load_time' => $load_time,
            'page_size' => strlen($html),
            'title' => $parsed['title'],
            'meta_description' => $parsed['meta_description'],
            'h1_tags' => $parsed['h1_tags'],
            'word_count' => $parsed['word_count'],
            'has_noindex' => $parsed['has_noindex'],
            'has_nofollow' => $parsed['has_nofollow'],
            'canonical_url' => $parsed['canonical_url'],
            'schema_types' => $parsed['schema_types'],
            'internal_links' => $parsed['internal_links'],
            'external_links' => $parsed['external_links'],
            'images_count' => $parsed['images_count'],
            'images_without_alt' => $parsed['images_without_alt'],
            'open_graph' => $parsed['open_graph'],
            'twitter_card' => $parsed['twitter_card']
        ];
    }

    /**
     * Parse HTML for SEO elements
     */
    private function parse_html($html) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $data = [
            'title' => '',
            'meta_description' => '',
            'h1_tags' => [],
            'word_count' => 0,
            'has_noindex' => false,
            'has_nofollow' => false,
            'canonical_url' => '',
            'schema_types' => [],
            'internal_links' => 0,
            'external_links' => 0,
            'images_count' => 0,
            'images_without_alt' => 0,
            'open_graph' => [],
            'twitter_card' => []
        ];

        // Title
        $title_nodes = $xpath->query('//title');
        if ($title_nodes->length > 0) {
            $data['title'] = $title_nodes->item(0)->textContent;
        }

        // Meta description
        $meta_desc = $xpath->query('//meta[@name="description"]');
        if ($meta_desc->length > 0) {
            $data['meta_description'] = $meta_desc->item(0)->getAttribute('content');
        }

        // H1 tags
        $h1_nodes = $xpath->query('//h1');
        foreach ($h1_nodes as $h1) {
            $data['h1_tags'][] = $h1->textContent;
        }

        // Robots meta
        $robots = $xpath->query('//meta[@name="robots"]');
        if ($robots->length > 0) {
            $robots_content = strtolower($robots->item(0)->getAttribute('content'));
            $data['has_noindex'] = strpos($robots_content, 'noindex') !== false;
            $data['has_nofollow'] = strpos($robots_content, 'nofollow') !== false;
        }

        // Canonical
        $canonical = $xpath->query('//link[@rel="canonical"]');
        if ($canonical->length > 0) {
            $data['canonical_url'] = $canonical->item(0)->getAttribute('href');
        }

        // Schema.org
        $scripts = $xpath->query('//script[@type="application/ld+json"]');
        foreach ($scripts as $script) {
            $json = json_decode($script->textContent, true);
            if (isset($json['@type'])) {
                $data['schema_types'][] = $json['@type'];
            }
        }

        // Links
        $links = $xpath->query('//a[@href]');
        $site_url = get_site_url();
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (strpos($href, $site_url) !== false || strpos($href, '/') === 0) {
                $data['internal_links']++;
            } else if (strpos($href, 'http') === 0) {
                $data['external_links']++;
            }
        }

        // Images
        $images = $xpath->query('//img');
        $data['images_count'] = $images->length;
        foreach ($images as $img) {
            if (!$img->hasAttribute('alt') || empty($img->getAttribute('alt'))) {
                $data['images_without_alt']++;
            }
        }

        // Open Graph
        $og_tags = $xpath->query('//meta[starts-with(@property, "og:")]');
        foreach ($og_tags as $tag) {
            $property = $tag->getAttribute('property');
            $data['open_graph'][$property] = $tag->getAttribute('content');
        }

        // Twitter Card
        $twitter_tags = $xpath->query('//meta[starts-with(@name, "twitter:")]');
        foreach ($twitter_tags as $tag) {
            $name = $tag->getAttribute('name');
            $data['twitter_card'][$name] = $tag->getAttribute('content');
        }

        // Word count (body text only)
        $body_nodes = $xpath->query('//body');
        if ($body_nodes->length > 0) {
            $text = $body_nodes->item(0)->textContent;
            $data['word_count'] = str_word_count(strip_tags($text));
        }

        return $data;
    }

    /**
     * Get URLs to crawl
     */
    private function get_urls_to_crawl() {
        $urls = [];

        // Get all published posts and pages
        $posts = get_posts([
            'numberposts' => $this->max_pages,
            'post_type' => ['post', 'page'],
            'post_status' => 'publish'
        ]);

        foreach ($posts as $post) {
            $urls[] = get_permalink($post->ID);
        }

        // Add homepage
        array_unshift($urls, get_site_url());

        return array_unique($urls);
    }

    /**
     * Save crawl data to database
     */
    private function save_crawl_data($audit_id, $url, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_crawl_data';

        $wpdb->insert($table, [
            'audit_id' => $audit_id,
            'url' => $url,
            'status_code' => $data['status_code'],
            'title' => $data['title'],
            'meta_description' => $data['meta_description'],
            'h1_tags' => json_encode($data['h1_tags']),
            'word_count' => $data['word_count'],
            'has_noindex' => $data['has_noindex'] ? 1 : 0,
            'has_nofollow' => $data['has_nofollow'] ? 1 : 0,
            'canonical_url' => $data['canonical_url'],
            'schema_types' => json_encode($data['schema_types']),
            'internal_links' => $data['internal_links'],
            'external_links' => $data['external_links'],
            'images_count' => $data['images_count'],
            'images_without_alt' => $data['images_without_alt'],
            'load_time' => $data['load_time'],
            'page_size' => $data['page_size'],
            'crawled_at' => current_time('mysql')
        ]);
    }

    /**
     * Get crawl results for audit
     */
    public function get_crawl_results($audit_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_crawl_data';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE audit_id = %d",
            $audit_id
        ));
    }
}
