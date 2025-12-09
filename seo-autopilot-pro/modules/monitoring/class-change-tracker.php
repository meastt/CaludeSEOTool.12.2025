<?php
/**
 * Change Tracker (Phase 8)
 * Monitors critical SEO changes and alerts when issues occur
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Change_Tracker {

    /**
     * Check for SEO-impacting changes
     */
    public function check_for_changes() {
        $changes = [
            'critical' => [],
            'warnings' => [],
            'info' => []
        ];

        // Check for robots.txt changes
        $robots_change = $this->check_robots_txt();
        if ($robots_change) {
            $changes['critical'][] = $robots_change;
        }

        // Check for sitemap availability
        $sitemap_change = $this->check_sitemap();
        if ($sitemap_change) {
            $changes['critical'][] = $sitemap_change;
        }

        // Check for meta robots changes
        $meta_robots_changes = $this->check_meta_robots();
        if (!empty($meta_robots_changes)) {
            $changes['critical'] = array_merge($changes['critical'], $meta_robots_changes);
        }

        // Check for broken links
        $broken_links = $this->check_broken_links();
        if (!empty($broken_links)) {
            $changes['warnings'] = array_merge($changes['warnings'], $broken_links);
        }

        // Check for schema markup removal
        $schema_changes = $this->check_schema_changes();
        if (!empty($schema_changes)) {
            $changes['warnings'] = array_merge($changes['warnings'], $schema_changes);
        }

        return $changes;
    }

    /**
     * Check robots.txt for changes
     */
    private function check_robots_txt() {
        $robots_url = get_site_url() . '/robots.txt';
        $response = wp_remote_get($robots_url);

        if (is_wp_error($response)) {
            return null;
        }

        $current_content = wp_remote_retrieve_body($response);
        $stored_content = get_option('sap_robots_txt_hash');
        $current_hash = md5($current_content);

        if ($stored_content && $stored_content !== $current_hash) {
            // Robots.txt changed - check if it's blocking important content
            if (stripos($current_content, 'Disallow: /') !== false) {
                update_option('sap_robots_txt_hash', $current_hash);

                return [
                    'type' => 'robots_txt_blocking',
                    'message' => 'robots.txt is now blocking site content',
                    'severity' => 'critical'
                ];
            }
        }

        // Store current hash if not stored
        if (!$stored_content) {
            update_option('sap_robots_txt_hash', $current_hash);
        }

        return null;
    }

    /**
     * Check sitemap availability
     */
    private function check_sitemap() {
        $sitemap_urls = [
            get_site_url() . '/sitemap.xml',
            get_site_url() . '/sitemap_index.xml',
            get_site_url() . '/wp-sitemap.xml'
        ];

        $sitemap_found = false;

        foreach ($sitemap_urls as $url) {
            $response = wp_remote_get($url);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $sitemap_found = true;
                break;
            }
        }

        $previous_status = get_option('sap_sitemap_status', true);

        if (!$sitemap_found && $previous_status) {
            update_option('sap_sitemap_status', false);

            return [
                'type' => 'sitemap_missing',
                'message' => 'Sitemap is no longer accessible',
                'severity' => 'critical'
            ];
        }

        if ($sitemap_found && !$previous_status) {
            update_option('sap_sitemap_status', true);
        }

        return null;
    }

    /**
     * Check for meta robots noindex additions
     */
    private function check_meta_robots() {
        $changes = [];

        // Get recently modified posts
        $posts = get_posts([
            'numberposts' => 50,
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC'
        ]);

        foreach ($posts as $post) {
            // Check if post has noindex
            $robots_meta = get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true);

            if ($robots_meta === '1') {
                // Check if this is a new noindex
                $was_tracked = get_post_meta($post->ID, 'sap_tracked_noindex', true);

                if (!$was_tracked) {
                    $changes[] = [
                        'type' => 'noindex_added',
                        'message' => "Post '{$post->post_title}' now has noindex meta tag",
                        'post_id' => $post->ID,
                        'severity' => 'critical'
                    ];

                    update_post_meta($post->ID, 'sap_tracked_noindex', '1');
                }
            } else {
                // Remove tracking if noindex removed
                delete_post_meta($post->ID, 'sap_tracked_noindex');
            }
        }

        return $changes;
    }

    /**
     * Check for broken internal links
     */
    private function check_broken_links() {
        $broken = [];

        // Get recent posts to check
        $posts = get_posts([
            'numberposts' => 20,
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);

        foreach ($posts as $post) {
            // Extract internal links
            preg_match_all('/<a[^>]+href=["\'](' . preg_quote(get_site_url(), '/') . '[^"\']+)["\']/i', $post->post_content, $matches);

            foreach ($matches[1] as $url) {
                // Check if URL exists
                $response = wp_remote_head($url, ['timeout' => 5]);

                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) === 404) {
                    $broken[] = [
                        'type' => 'broken_link',
                        'message' => "Broken link found: {$url}",
                        'post_id' => $post->ID,
                        'url' => $url,
                        'severity' => 'warning'
                    ];
                }
            }
        }

        return array_slice($broken, 0, 10); // Limit to 10
    }

    /**
     * Check for schema markup changes
     */
    private function check_schema_changes() {
        $changes = [];

        // Check recent posts for schema removal
        $posts = get_posts([
            'numberposts' => 20,
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC'
        ]);

        foreach ($posts as $post) {
            $has_schema = get_post_meta($post->ID, 'sap_schema_markup', true);
            $had_schema = get_post_meta($post->ID, 'sap_had_schema', true);

            if (!$has_schema && $had_schema) {
                $changes[] = [
                    'type' => 'schema_removed',
                    'message' => "Schema markup removed from '{$post->post_title}'",
                    'post_id' => $post->ID,
                    'severity' => 'warning'
                ];
            }

            // Track current state
            if ($has_schema) {
                update_post_meta($post->ID, 'sap_had_schema', '1');
            }
        }

        return $changes;
    }

    /**
     * Track post update
     */
    public function track_post_update($post_id, $post_after, $post_before) {
        // Check for significant SEO changes
        $changes = [];

        // Title changed
        if ($post_before->post_title !== $post_after->post_title) {
            $changes[] = [
                'type' => 'title_change',
                'before' => $post_before->post_title,
                'after' => $post_after->post_title
            ];
        }

        // URL/slug changed
        if ($post_before->post_name !== $post_after->post_name) {
            $changes[] = [
                'type' => 'slug_change',
                'before' => $post_before->post_name,
                'after' => $post_after->post_name
            ];
        }

        // Content significantly changed
        $before_word_count = str_word_count(wp_strip_all_tags($post_before->post_content));
        $after_word_count = str_word_count(wp_strip_all_tags($post_after->post_content));
        $word_count_change = abs($after_word_count - $before_word_count);

        if ($word_count_change > 100) {
            $changes[] = [
                'type' => 'content_change',
                'word_count_change' => $word_count_change
            ];
        }

        if (!empty($changes)) {
            // Log changes
            $log = get_post_meta($post_id, 'sap_change_log', true) ?: [];
            $log[] = [
                'timestamp' => current_time('mysql'),
                'changes' => $changes
            ];

            // Keep last 10 changes
            $log = array_slice($log, -10);

            update_post_meta($post_id, 'sap_change_log', $log);
        }
    }

    /**
     * Get change history for post
     */
    public function get_post_change_history($post_id) {
        return get_post_meta($post_id, 'sap_change_log', true) ?: [];
    }

    /**
     * Monitor homepage changes
     */
    public function check_homepage_changes() {
        $homepage_id = get_option('page_on_front');

        if (!$homepage_id) {
            return null;
        }

        $homepage = get_post($homepage_id);
        $current_hash = md5($homepage->post_content . $homepage->post_title);
        $stored_hash = get_option('sap_homepage_hash');

        if ($stored_hash && $stored_hash !== $current_hash) {
            update_option('sap_homepage_hash', $current_hash);

            return [
                'type' => 'homepage_change',
                'message' => 'Homepage content has been modified',
                'severity' => 'warning'
            ];
        }

        if (!$stored_hash) {
            update_option('sap_homepage_hash', $current_hash);
        }

        return null;
    }
}
