<?php
/**
 * Competitor Intelligence Module (Phase 2.5)
 * Analyzes competitor content to provide strategic recommendations
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Competitor_Intelligence {

    private $ai_analyzer;
    private $site_profile;
    private $settings;

    public function __construct() {
        $this->ai_analyzer = new SAP_AI_Analyzer();
        $this->site_profile = get_option('sap_site_profile');
        $this->settings = new SAP_Settings();
    }

    /**
     * Analyze competitor for specific topic
     */
    public function analyze_competitor_content($topic, $competitor_url) {
        // Check cache first
        $cache_key = md5($topic . $competitor_url);
        $cached = $this->get_cached_analysis($cache_key);

        if ($cached) {
            return $cached;
        }

        $prompt = "Analyze this competitor article:\n\n";
        $prompt .= "URL: {$competitor_url}\n";
        $prompt .= "Topic: {$topic}\n\n";
        $prompt .= "Based on your knowledge, analyze what this type of content typically covers. Return JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "word_count": estimated_number,'."\n";
        $prompt .= '  "subtopics_covered": ["topic1", "topic2"],'."\n";
        $prompt .= '  "content_depth": "shallow/moderate/deep",'."\n";
        $prompt .= '  "unique_angles": ["angle1", "angle2"],'."\n";
        $prompt .= '  "what_they_miss": ["gap1", "gap2"],'."\n";
        $prompt .= '  "estimated_quality_score": 1-100'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude_with_search($prompt, 3000);

        if (is_wp_error($response)) {
            return $response;
        }

        $analysis = $this->ai_analyzer->extract_json($response);

        if (is_array($analysis)) {
            $this->cache_analysis($cache_key, $analysis);
        }

        return $analysis;
    }

    /**
     * Get content recommendations based on competitors
     */
    public function get_content_recommendations($post_id) {
        if (!$this->site_profile || !$this->settings->is_competitor_analysis_enabled()) {
            return null;
        }

        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $competitors = $this->site_profile['competitors']['competitors'] ?? [];

        if (empty($competitors)) {
            return $this->get_generic_recommendations($post_id);
        }

        // Analyze top 3 competitors for this topic
        $competitor_analysis = [];
        foreach (array_slice($competitors, 0, 3) as $competitor) {
            $domain = $competitor['domain'] ?? null;

            if ($domain) {
                $analysis = $this->analyze_competitor_content(
                    $post->post_title,
                    'https://' . $domain
                );

                if (!is_wp_error($analysis) && is_array($analysis)) {
                    $competitor_analysis[] = $analysis;
                }
            }
        }

        if (empty($competitor_analysis)) {
            return $this->get_generic_recommendations($post_id);
        }

        // Generate recommendations based on competitor analysis
        $current_word_count = str_word_count(wp_strip_all_tags($post->post_content));

        $prompt = "Based on competitor analysis, recommend improvements for our article:\n\n";
        $prompt .= "OUR ARTICLE:\n";
        $prompt .= "Title: {$post->post_title}\n";
        $prompt .= "Current Word Count: {$current_word_count}\n";
        $prompt .= "Category: " . (get_the_category($post_id)[0]->name ?? 'General') . "\n\n";
        $prompt .= "COMPETITOR ANALYSIS:\n";
        $prompt .= json_encode($competitor_analysis, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "Provide strategic recommendations in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "target_word_count": number,'."\n";
        $prompt .= '  "topics_to_add": ["topic1", "topic2"],'."\n";
        $prompt .= '  "unique_angles": ["angle1", "angle2"],'."\n";
        $prompt .= '  "content_structure": "suggested structure",'."\n";
        $prompt .= '  "competitive_advantage": "how to stand out",'."\n";
        $prompt .= '  "priority": "high/medium/low"'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude($prompt, 2500);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->ai_analyzer->extract_json($response);
    }

    /**
     * Get generic recommendations without competitor analysis
     */
    private function get_generic_recommendations($post_id) {
        $post = get_post($post_id);
        $current_word_count = str_word_count(wp_strip_all_tags($post->post_content));

        $prompt = "Provide SEO recommendations for this article:\n\n";
        $prompt .= "Title: {$post->post_title}\n";
        $prompt .= "Current Word Count: {$current_word_count}\n";
        $prompt .= "Excerpt: " . wp_trim_words(wp_strip_all_tags($post->post_content), 100) . "\n\n";
        $prompt .= "Suggest improvements in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "target_word_count": number,'."\n";
        $prompt .= '  "topics_to_add": ["topic1", "topic2"],'."\n";
        $prompt .= '  "content_structure": "suggested structure",'."\n";
        $prompt .= '  "priority": "high/medium/low"'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude($prompt, 1500);

        if (is_wp_error($response)) {
            return null;
        }

        return $this->ai_analyzer->extract_json($response);
    }

    /**
     * Identify content gaps across entire site
     */
    public function identify_content_gaps() {
        if (!$this->site_profile) {
            return new WP_Error('no_profile', 'Site profile not built yet');
        }

        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';
        $key_topics = $this->site_profile['niche']['key_topics'] ?? [];
        $competitors = $this->site_profile['competitors']['competitors'] ?? [];

        // Get current site topics
        $existing_posts = get_posts([
            'numberposts' => 100,
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);

        $existing_titles = wp_list_pluck($existing_posts, 'post_title');

        $prompt = "Identify content gaps and opportunities:\n\n";
        $prompt .= "SITE INFO:\n";
        $prompt .= "Niche: {$niche}\n";
        $prompt .= "Key Topics: " . implode(", ", $key_topics) . "\n";
        $prompt .= "Competitors: " . implode(", ", array_column($competitors, 'domain')) . "\n\n";
        $prompt .= "EXISTING CONTENT:\n";
        $prompt .= implode("\n", array_slice($existing_titles, 0, 50)) . "\n\n";
        $prompt .= "Identify content gaps and new opportunities. Return JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "missing_topics": ["topic1", "topic2"],'."\n";
        $prompt .= '  "trending_topics": ["trend1", "trend2"],'."\n";
        $prompt .= '  "seasonal_opportunities": ["season1", "season2"],'."\n";
        $prompt .= '  "comparison_articles": ["vs article 1", "vs article 2"],'."\n";
        $prompt .= '  "how_to_guides": ["guide1", "guide2"],'."\n";
        $prompt .= '  "priority_order": ["highest priority topic first"]'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude_with_search($prompt, 4000);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->ai_analyzer->extract_json($response);
    }

    /**
     * Benchmark against competitors
     */
    public function benchmark_site() {
        if (!$this->site_profile) {
            return new WP_Error('no_profile', 'Site profile not built yet');
        }

        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';
        $content_volume = $this->site_profile['content_volume'] ?? ['total' => 0];
        $competitors = $this->site_profile['competitors']['competitors'] ?? [];

        $prompt = "Benchmark this site against competitors:\n\n";
        $prompt .= "OUR SITE:\n";
        $prompt .= "Domain: " . get_site_url() . "\n";
        $prompt .= "Niche: {$niche}\n";
        $prompt .= "Content Volume: {$content_volume['total']} posts\n\n";
        $prompt .= "COMPETITORS:\n";
        $prompt .= json_encode($competitors, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "Provide benchmark analysis in JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "our_strengths": ["strength1", "strength2"],'."\n";
        $prompt .= '  "our_weaknesses": ["weakness1", "weakness2"],'."\n";
        $prompt .= '  "opportunities": ["opp1", "opp2"],'."\n";
        $prompt .= '  "threats": ["threat1", "threat2"],'."\n";
        $prompt .= '  "overall_position": "leader/challenger/follower",'."\n";
        $prompt .= '  "action_items": ["action1", "action2"]'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude($prompt, 3000);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->ai_analyzer->extract_json($response);
    }

    /**
     * Get cached competitor analysis
     */
    private function get_cached_analysis($cache_key) {
        $cache = get_transient('sap_competitor_' . $cache_key);
        return $cache !== false ? $cache : null;
    }

    /**
     * Cache competitor analysis
     */
    private function cache_analysis($cache_key, $analysis) {
        // Cache for 7 days
        set_transient('sap_competitor_' . $cache_key, $analysis, WEEK_IN_SECONDS);
    }

    /**
     * Clear competitor cache
     */
    public function clear_cache() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_sap_competitor_%'
            OR option_name LIKE '_transient_timeout_sap_competitor_%'"
        );
    }

    /**
     * Get competitor analysis report
     */
    public function get_analysis_report() {
        if (!$this->site_profile) {
            return null;
        }

        $report = [
            'content_gaps' => $this->identify_content_gaps(),
            'benchmark' => $this->benchmark_site(),
            'generated_at' => current_time('mysql')
        ];

        // Cache the report
        update_option('sap_competitor_report', $report);
        update_option('sap_competitor_report_date', current_time('mysql'));

        return $report;
    }

    /**
     * Check if competitor analysis is enabled
     */
    public function is_enabled() {
        return $this->settings->is_competitor_analysis_enabled();
    }
}
