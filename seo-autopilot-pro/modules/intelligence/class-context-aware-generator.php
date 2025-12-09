<?php
/**
 * Context-Aware Content Generator (Phase 2.5)
 * Every AI call now gets rich context about the site
 * This is what makes content match the brand voice
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Context_Aware_Generator {

    private $site_profile;
    private $ai_analyzer;
    private $settings;

    public function __construct() {
        $this->site_profile = get_option('sap_site_profile');
        $this->ai_analyzer = new SAP_AI_Analyzer();
        $this->settings = new SAP_Settings();
    }

    /**
     * Generate meta description with full context
     */
    public function generate_meta_description($post_id) {
        if (!$this->site_profile) {
            return new WP_Error('no_profile', 'Site profile not built yet');
        }

        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        // Build rich context
        $context = $this->build_content_context($post_id);

        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';
        $audience = $this->site_profile['target_audience']['primary_audience'] ?? 'general readers';
        $tone = $this->site_profile['writing_style']['tone'] ?? 'professional';
        $approach = $this->site_profile['niche']['content_approach'] ?? 'informational';

        $prompt = "You are an SEO expert for a {$niche} website.\n\n";
        $prompt .= "SITE CONTEXT:\n";
        $prompt .= "- Audience: {$audience}\n";
        $prompt .= "- Tone: {$tone}\n";
        $prompt .= "- Content Approach: {$approach}\n\n";
        $prompt .= "ARTICLE CONTEXT:\n";
        $prompt .= "- Title: {$post->post_title}\n";
        $prompt .= "- Category: {$context['category']}\n";
        $prompt .= "- Related Topics: " . implode(", ", array_slice($context['related_topics'], 0, 3)) . "\n";
        $prompt .= "- Primary Keywords: " . implode(", ", array_slice($context['keywords'], 0, 3)) . "\n";
        $prompt .= "- Content Summary: {$context['summary']}\n\n";
        $prompt .= "Write a compelling 150-160 character meta description that:\n";
        $prompt .= "1. Matches our site's tone and audience\n";
        $prompt .= "2. Includes primary keyword naturally\n";
        $prompt .= "3. Addresses user search intent\n";
        $prompt .= "4. Encourages clicks\n\n";
        $prompt .= "Return ONLY the meta description, no explanation.";

        return $this->ai_analyzer->call_claude($prompt, 300);
    }

    /**
     * Generate alt text with image research
     */
    public function generate_alt_text($image_url, $post_id) {
        if (!$this->site_profile) {
            return new WP_Error('no_profile', 'Site profile not built yet');
        }

        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $context = $this->build_content_context($post_id);
        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';

        // Use Gemini Vision for image description
        $image_description = $this->ai_analyzer->describe_image_gemini($image_url);

        if (is_wp_error($image_description)) {
            // Fallback to simpler alt text
            $image_description = 'Image for ' . $post->post_title;
        }

        $prompt = "You're writing alt text for a {$niche} website.\n\n";
        $prompt .= "ARTICLE: {$post->post_title}\n";
        $prompt .= "TOPIC: {$context['category']}\n";
        $prompt .= "IMAGE DESCRIPTION: {$image_description}\n\n";
        $prompt .= "Write 10-15 word alt text that:\n";
        $prompt .= "1. Describes the image specifically\n";
        $prompt .= "2. Relates to the article topic\n";
        $prompt .= "3. Includes relevant keywords naturally\n";
        $prompt .= "4. Helps visually impaired users\n\n";
        $prompt .= "Return ONLY the alt text, no explanation.";

        return $this->ai_analyzer->call_claude($prompt, 200);
    }

    /**
     * Expand thin content with research
     */
    public function expand_thin_content($post_id, $target_word_count = null) {
        if (!$this->site_profile) {
            return new WP_Error('no_profile', 'Site profile not built yet');
        }

        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        if ($target_word_count === null) {
            $target_word_count = $this->settings->get_target_word_count();
        }

        $context = $this->build_content_context($post_id);

        // FIRST: Research the topic if enabled
        $research = null;
        if ($this->settings->is_content_research_enabled()) {
            $research = $this->research_topic($post->post_title, $context);
        }

        $prompt = "You are a content writer for {$this->site_profile['niche']['primary_niche']}.\n\n";
        $prompt .= "BRAND VOICE:\n";
        $prompt .= json_encode($this->site_profile['writing_style'], JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "TARGET AUDIENCE:\n";
        $prompt .= json_encode($this->site_profile['target_audience'], JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "CURRENT ARTICLE:\n";
        $prompt .= "Title: {$post->post_title}\n";
        $prompt .= "Current word count: {$context['word_count']}\n";
        $prompt .= "Current content:\n" . wp_trim_words($post->post_content, 500) . "\n\n";

        if ($research && !is_wp_error($research)) {
            $prompt .= "RESEARCH:\n";
            $prompt .= "I've researched this topic. Here's what's trending and what competitors cover:\n";
            $prompt .= $research . "\n\n";
        }

        $prompt .= "TASK:\n";
        $prompt .= "Expand this article to approximately {$target_word_count} words by adding:\n";
        $prompt .= "1. Missing information from research\n";
        $prompt .= "2. Practical examples relevant to our audience\n";
        $prompt .= "3. Data/statistics where relevant\n";
        $prompt .= "4. FAQ section if helpful\n";
        $prompt .= "5. Actionable takeaways\n\n";
        $prompt .= "CRITICAL: Match our existing writing style exactly. Don't sound like AI.\n";
        $prompt .= "Return the expanded content in HTML format, ready to replace the current content.";

        return $this->ai_analyzer->call_claude($prompt, 4000);
    }

    /**
     * Generate title tag
     */
    public function generate_title_tag($post_id) {
        if (!$this->site_profile) {
            return new WP_Error('no_profile', 'Site profile not built yet');
        }

        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $context = $this->build_content_context($post_id);
        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';

        $prompt = "Create an SEO-optimized title tag for this article.\n\n";
        $prompt .= "CONTEXT:\n";
        $prompt .= "- Niche: {$niche}\n";
        $prompt .= "- Current Title: {$post->post_title}\n";
        $prompt .= "- Category: {$context['category']}\n";
        $prompt .= "- Keywords: " . implode(", ", array_slice($context['keywords'], 0, 3)) . "\n\n";
        $prompt .= "Create a title tag that:\n";
        $prompt .= "1. Is 50-60 characters\n";
        $prompt .= "2. Includes primary keyword\n";
        $prompt .= "3. Is compelling for clicks\n";
        $prompt .= "4. Accurately represents the content\n\n";
        $prompt .= "Return ONLY the title tag, no explanation.";

        return $this->ai_analyzer->call_claude($prompt, 200);
    }

    /**
     * Generate schema markup
     */
    public function generate_schema_markup($post_id, $schema_type = 'Article') {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $context = $this->build_content_context($post_id);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $schema_type,
            'headline' => $post->post_title,
            'description' => $context['summary'],
            'datePublished' => get_the_date('c', $post_id),
            'dateModified' => get_the_modified_date('c', $post_id),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author)
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url()
                ]
            ]
        ];

        // Add image if available
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
            if ($image_url) {
                $schema['image'] = $image_url;
            }
        }

        return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Research topic before writing
     */
    private function research_topic($title, $context) {
        // Check cache first
        $cached = SAP_Database::get_cached_research($title);
        if ($cached) {
            return $cached;
        }

        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';

        $prompt = "Research this topic: '{$title}'\n\n";
        $prompt .= "Context: This is for a {$context['category']} article on a {$niche} site.\n\n";
        $prompt .= "Find:\n";
        $prompt .= "1. Top 5 ranking articles for this topic (what they cover)\n";
        $prompt .= "2. Common subtopics/questions\n";
        $prompt .= "3. Recent statistics/data\n";
        $prompt .= "4. Trending angles\n";
        $prompt .= "5. Content gaps (what they miss)\n\n";
        $prompt .= "Return detailed research brief.";

        $research = $this->ai_analyzer->call_claude_with_search($prompt, 3000);

        if (!is_wp_error($research)) {
            // Cache for 1 week
            SAP_Database::cache_research($title, $research, 168);
        }

        return $research;
    }

    /**
     * Build rich context for any piece of content
     */
    private function build_content_context($post_id) {
        $post = get_post($post_id);

        // Get category
        $categories = get_the_category($post_id);
        $category = $categories ? $categories[0]->name : 'General';

        // Get related posts
        $related = [];
        if (!empty($categories)) {
            $related = get_posts([
                'category' => $categories[0]->term_id,
                'numberposts' => 5,
                'exclude' => [$post_id],
                'post_status' => 'publish'
            ]);
        }
        $related_topics = wp_list_pluck($related, 'post_title');

        // Extract keywords (simple version)
        $keywords = $this->extract_keywords_simple($post->post_content, $post->post_title);

        // Summarize content
        $summary = wp_trim_words(wp_strip_all_tags($post->post_content), 50);

        // Count internal links
        $internal_links_count = substr_count($post->post_content, get_site_url());

        return [
            'keywords' => $keywords,
            'category' => $category,
            'related_topics' => $related_topics,
            'summary' => $summary,
            'word_count' => str_word_count(wp_strip_all_tags($post->post_content)),
            'internal_links_count' => $internal_links_count,
            'post_type' => $post->post_type,
            'post_date' => $post->post_date
        ];
    }

    /**
     * Simple keyword extraction
     */
    private function extract_keywords_simple($content, $title) {
        // Extract words from title
        $title_words = explode(' ', strtolower($title));

        // Remove common stop words
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'what', 'which', 'who', 'when', 'where', 'why', 'how'];

        $keywords = array_diff($title_words, $stop_words);

        // Get first 5 unique keywords
        return array_slice(array_unique(array_filter($keywords)), 0, 5);
    }

    /**
     * Check if site profile exists
     */
    public function has_profile() {
        return !empty($this->site_profile);
    }

    /**
     * Reload site profile
     */
    public function reload_profile() {
        $this->site_profile = get_option('sap_site_profile');
    }
}
