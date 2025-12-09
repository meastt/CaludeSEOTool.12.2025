<?php
/**
 * Site Profiler Engine (Phase 2.5)
 * Builds comprehensive site profile to understand niche, audience, and brand voice
 * This runs FIRST before any fixes
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Site_Profiler {

    private $ai_analyzer;
    private $profile_data;

    public function __construct() {
        $this->ai_analyzer = new SAP_AI_Analyzer();
    }

    /**
     * Build comprehensive site profile
     * This happens once during setup, then updates monthly
     */
    public function build_site_profile() {
        $profile = [
            'niche' => $this->detect_niche(),
            'target_audience' => $this->analyze_target_audience(),
            'content_strategy' => $this->analyze_content_strategy(),
            'top_performing_content' => $this->get_top_performers(),
            'writing_style' => $this->extract_writing_style(),
            'keyword_universe' => $this->build_keyword_universe(),
            'competitors' => $this->identify_competitors(),
            'monetization_model' => $this->detect_monetization_model(),
            'site_age' => $this->get_site_age(),
            'content_volume' => $this->get_content_volume()
        ];

        // Save to database
        update_option('sap_site_profile', $profile);
        update_option('sap_site_profile_updated', current_time('mysql'));

        return $profile;
    }

    /**
     * Detect site niche using AI + content analysis
     */
    private function detect_niche() {
        // Get sample posts
        $posts = get_posts([
            'numberposts' => 50,
            'post_type' => 'post',
            'orderby' => 'rand',
            'post_status' => 'publish'
        ]);

        if (empty($posts)) {
            return [
                'primary_niche' => 'general',
                'sub_niches' => [],
                'content_pillars' => [],
                'audience_description' => 'General audience',
                'content_approach' => 'informational',
                'expertise_level' => 'beginner',
                'tone' => 'professional',
                'key_topics' => [],
                'content_gaps' => []
            ];
        }

        $titles = [];
        $categories = [];
        $content_samples = [];

        foreach ($posts as $post) {
            $titles[] = $post->post_title;
            $post_categories = wp_get_post_categories($post->ID, ['fields' => 'names']);
            $categories = array_merge($categories, $post_categories);
            $content_samples[] = wp_trim_words(wp_strip_all_tags($post->post_content), 100);
        }

        // Use Claude to deeply analyze niche
        $prompt = "Analyze this website and provide a comprehensive niche profile:\n\n";
        $prompt .= "Site URL: " . get_site_url() . "\n";
        $prompt .= "Site Name: " . get_bloginfo('name') . "\n";
        $prompt .= "Description: " . get_bloginfo('description') . "\n\n";
        $prompt .= "Sample Post Titles:\n" . implode("\n", array_slice($titles, 0, 20)) . "\n\n";
        $prompt .= "Categories: " . implode(", ", array_unique($categories)) . "\n\n";
        $prompt .= "Content Samples:\n" . implode("\n\n---\n\n", array_slice($content_samples, 0, 5)) . "\n\n";
        $prompt .= "Return detailed JSON with:\n";
        $prompt .= "{\n";
        $prompt .= '  "primary_niche": "specific niche",'."\n";
        $prompt .= '  "sub_niches": ["sub1", "sub2"],'."\n";
        $prompt .= '  "content_pillars": ["pillar1", "pillar2"],'."\n";
        $prompt .= '  "audience_description": "who this site serves",'."\n";
        $prompt .= '  "content_approach": "informational/commercial/review-based/etc",'."\n";
        $prompt .= '  "expertise_level": "beginner/intermediate/expert",'."\n";
        $prompt .= '  "tone": "professional/casual/technical/friendly",'."\n";
        $prompt .= '  "key_topics": ["topic1", "topic2"],'."\n";
        $prompt .= '  "content_gaps": ["potential topics not covered"]'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude($prompt, 3000);

        if (is_wp_error($response)) {
            error_log('SAP Site Profiler: Niche detection failed - ' . $response->get_error_message());
            return $this->get_default_niche();
        }

        $niche_data = $this->ai_analyzer->extract_json($response);

        return is_array($niche_data) ? $niche_data : $this->get_default_niche();
    }

    /**
     * Analyze target audience using GSC data + AI
     */
    private function analyze_target_audience() {
        // Check if GSC is configured
        if (!class_exists('SAP_GSC_Connector')) {
            return $this->get_default_audience();
        }

        $gsc = new SAP_GSC_Connector();

        // Try to get search queries (this will fail gracefully if not configured)
        $queries = [];

        // For now, analyze based on site content
        $posts = get_posts([
            'numberposts' => 20,
            'post_type' => 'post',
            'orderby' => 'comment_count',
            'order' => 'DESC'
        ]);

        $post_titles = wp_list_pluck($posts, 'post_title');

        $prompt = "Based on these popular post titles, describe the target audience:\n\n";
        $prompt .= implode("\n", $post_titles) . "\n\n";
        $prompt .= "Return JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "primary_audience": "description",'."\n";
        $prompt .= '  "pain_points": ["pain1", "pain2"],'."\n";
        $prompt .= '  "search_intent": "informational/commercial/navigational",'."\n";
        $prompt .= '  "expertise_level_seeking": "beginner/intermediate/expert",'."\n";
        $prompt .= '  "demographics": "best guess based on content"'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude($prompt, 2000);

        if (is_wp_error($response)) {
            return $this->get_default_audience();
        }

        $audience_data = $this->ai_analyzer->extract_json($response);

        return is_array($audience_data) ? $audience_data : $this->get_default_audience();
    }

    /**
     * Extract writing style from top-performing content
     */
    private function extract_writing_style() {
        $top_posts = $this->get_top_performers();

        if (empty($top_posts)) {
            return $this->get_default_writing_style();
        }

        $content_samples = [];
        foreach ($top_posts as $post_id) {
            $post = get_post($post_id);
            if ($post) {
                $content_samples[] = wp_trim_words(wp_strip_all_tags($post->post_content), 200);
            }
        }

        if (empty($content_samples)) {
            return $this->get_default_writing_style();
        }

        $prompt = "Analyze these top-performing articles and extract the writing style guide:\n\n";
        $prompt .= implode("\n\n---\n\n", $content_samples) . "\n\n";
        $prompt .= "Return JSON style guide:\n";
        $prompt .= "{\n";
        $prompt .= '  "tone": "description",'."\n";
        $prompt .= '  "voice": "first person/third person/etc",'."\n";
        $prompt .= '  "sentence_structure": "short/long/varied",'."\n";
        $prompt .= '  "paragraph_length": "average",'."\n";
        $prompt .= '  "use_of_examples": "heavy/moderate/light",'."\n";
        $prompt .= '  "technical_depth": "description",'."\n";
        $prompt .= '  "call_to_actions": "present/absent/style",'."\n";
        $prompt .= '  "common_phrases": ["phrase1", "phrase2"],'."\n";
        $prompt .= '  "formatting_patterns": "uses lists/bold/headers/etc"'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude($prompt, 2500);

        if (is_wp_error($response)) {
            return $this->get_default_writing_style();
        }

        $style_data = $this->ai_analyzer->extract_json($response);

        return is_array($style_data) ? $style_data : $this->get_default_writing_style();
    }

    /**
     * Build keyword universe for the niche
     */
    private function build_keyword_universe() {
        $niche = $this->detect_niche();

        $prompt = "For a website in the '{$niche['primary_niche']}' niche, generate a comprehensive keyword universe of 100+ keywords across:\n";
        $prompt .= "- Head terms (high volume)\n";
        $prompt .= "- Long-tail keywords\n";
        $prompt .= "- Question-based keywords\n";
        $prompt .= "- Commercial intent keywords\n";
        $prompt .= "- Informational keywords\n\n";
        $prompt .= "Return as JSON array grouped by category.";

        $response = $this->ai_analyzer->call_gemini($prompt, 3000);

        if (is_wp_error($response)) {
            return ['keywords' => []];
        }

        $keyword_data = $this->ai_analyzer->extract_json($response);

        return is_array($keyword_data) ? $keyword_data : ['keywords' => []];
    }

    /**
     * Identify top competitors
     */
    private function identify_competitors() {
        $niche = $this->detect_niche();

        $prompt = "I need to identify the top 10 competitors for a site in this niche:\n\n";
        $prompt .= "Site: " . get_site_url() . "\n";
        $prompt .= "Niche: {$niche['primary_niche']}\n";
        $prompt .= "Content Topics: " . implode(", ", $niche['key_topics']) . "\n\n";
        $prompt .= "Based on your knowledge, identify direct competitors. Return JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "competitors": ['."\n";
        $prompt .= '    {"domain": "example.com", "strength": "stronger/similar/weaker", "focus": "what they do well"}'."\n";
        $prompt .= '  ]'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude($prompt, 3000);

        if (is_wp_error($response)) {
            return ['competitors' => []];
        }

        $competitor_data = $this->ai_analyzer->extract_json($response);

        return is_array($competitor_data) ? $competitor_data : ['competitors' => []];
    }

    /**
     * Get top performing posts
     */
    private function get_top_performers() {
        $posts = get_posts([
            'numberposts' => 10,
            'orderby' => 'comment_count',
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);

        return wp_list_pluck($posts, 'ID');
    }

    /**
     * Detect monetization model
     */
    private function detect_monetization_model() {
        $model = [
            'display_ads' => false,
            'affiliate' => false,
            'products' => false,
            'services' => false
        ];

        // Check for WooCommerce
        if (class_exists('WooCommerce')) {
            $model['products'] = true;
        }

        // Check for common affiliate patterns
        $sample_posts = get_posts([
            'numberposts' => 10,
            'post_type' => 'post'
        ]);

        foreach ($sample_posts as $post) {
            $content = $post->post_content;

            // Check for affiliate links
            if (preg_match('/(amazon\.com|amzn\.to|shareasale|clickbank|cj\.com)/i', $content)) {
                $model['affiliate'] = true;
            }

            // Check for ad code
            if (preg_match('/(googlesyndication|adsense|doubleclick)/i', $content)) {
                $model['display_ads'] = true;
            }
        }

        return $model;
    }

    /**
     * Analyze content strategy
     */
    private function analyze_content_strategy() {
        $posts = get_posts([
            'numberposts' => 50,
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);

        $total_posts = wp_count_posts('post')->publish;
        $avg_word_count = 0;
        $post_count = 0;

        foreach ($posts as $post) {
            $word_count = str_word_count(wp_strip_all_tags($post->post_content));
            $avg_word_count += $word_count;
            $post_count++;
        }

        $avg_word_count = $post_count > 0 ? round($avg_word_count / $post_count) : 0;

        return [
            'total_posts' => $total_posts,
            'avg_word_count' => $avg_word_count,
            'publishing_frequency' => $this->calculate_publishing_frequency(),
            'content_types' => $this->identify_content_types()
        ];
    }

    /**
     * Calculate publishing frequency
     */
    private function calculate_publishing_frequency() {
        $recent_posts = get_posts([
            'numberposts' => 10,
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        if (count($recent_posts) < 2) {
            return 'sporadic';
        }

        $dates = wp_list_pluck($recent_posts, 'post_date');
        $first_date = strtotime($dates[count($dates) - 1]);
        $last_date = strtotime($dates[0]);

        $days_diff = ($last_date - $first_date) / DAY_IN_SECONDS;
        $posts_per_week = ($days_diff > 0) ? (count($recent_posts) / $days_diff) * 7 : 0;

        if ($posts_per_week >= 5) return 'daily';
        if ($posts_per_week >= 2) return 'multiple_per_week';
        if ($posts_per_week >= 0.5) return 'weekly';
        return 'sporadic';
    }

    /**
     * Identify content types
     */
    private function identify_content_types() {
        $types = [];

        $post_types = get_post_types(['public' => true], 'objects');

        foreach ($post_types as $post_type) {
            if ($post_type->name === 'attachment') continue;

            $count = wp_count_posts($post_type->name)->publish;
            if ($count > 0) {
                $types[] = $post_type->label;
            }
        }

        return $types;
    }

    /**
     * Get site age
     */
    private function get_site_age() {
        $first_post = get_posts([
            'numberposts' => 1,
            'post_type' => 'post',
            'orderby' => 'date',
            'order' => 'ASC'
        ]);

        if (empty($first_post)) {
            return ['years' => 0, 'months' => 0];
        }

        $first_date = strtotime($first_post[0]->post_date);
        $now = current_time('timestamp');
        $diff = $now - $first_date;

        $years = floor($diff / (365 * DAY_IN_SECONDS));
        $months = floor(($diff % (365 * DAY_IN_SECONDS)) / (30 * DAY_IN_SECONDS));

        return [
            'years' => $years,
            'months' => $months,
            'first_post_date' => $first_post[0]->post_date
        ];
    }

    /**
     * Get content volume
     */
    private function get_content_volume() {
        $post_count = wp_count_posts('post')->publish;
        $page_count = wp_count_posts('page')->publish;

        return [
            'posts' => $post_count,
            'pages' => $page_count,
            'total' => $post_count + $page_count
        ];
    }

    /**
     * Get current site profile
     */
    public function get_profile() {
        return get_option('sap_site_profile', null);
    }

    /**
     * Check if profile exists
     */
    public function has_profile() {
        return get_option('sap_site_profile') !== false;
    }

    /**
     * Default niche data
     */
    private function get_default_niche() {
        return [
            'primary_niche' => 'general',
            'sub_niches' => [],
            'content_pillars' => [],
            'audience_description' => 'General audience',
            'content_approach' => 'informational',
            'expertise_level' => 'beginner',
            'tone' => 'professional',
            'key_topics' => [],
            'content_gaps' => []
        ];
    }

    /**
     * Default audience data
     */
    private function get_default_audience() {
        return [
            'primary_audience' => 'General readers',
            'pain_points' => [],
            'search_intent' => 'informational',
            'expertise_level_seeking' => 'beginner',
            'demographics' => 'Mixed'
        ];
    }

    /**
     * Default writing style
     */
    private function get_default_writing_style() {
        return [
            'tone' => 'professional',
            'voice' => 'third person',
            'sentence_structure' => 'varied',
            'paragraph_length' => 'medium',
            'use_of_examples' => 'moderate',
            'technical_depth' => 'accessible',
            'call_to_actions' => 'present',
            'common_phrases' => [],
            'formatting_patterns' => 'uses headers and lists'
        ];
    }
}
