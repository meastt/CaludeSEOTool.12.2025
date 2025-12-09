<?php
/**
 * E-E-A-T Enhancer (Phase 2.6)
 * Adds Experience, Expertise, Authoritativeness, and Trust signals
 * Critical for AI search engines to cite your content
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_EEAT_Enhancer {

    private $ai_analyzer;
    private $site_profile;

    public function __construct() {
        $this->ai_analyzer = new SAP_AI_Analyzer();
        $this->site_profile = get_option('sap_site_profile');
    }

    /**
     * Enhance post with E-E-A-T signals
     */
    public function enhance_post($post_id) {
        $enhancements = [];

        // 1. Add author bio box
        $author_bio = $this->add_author_bio_box($post_id);
        if (!is_wp_error($author_bio)) {
            $enhancements['author_bio'] = $author_bio;
        }

        // 2. Add citations to content
        $citations = $this->suggest_citations($post_id);
        if (!is_wp_error($citations)) {
            $enhancements['citations'] = $citations;
        }

        // 3. Add last updated date
        $updated_date = $this->add_updated_date($post_id);
        if ($updated_date) {
            $enhancements['updated_date'] = $updated_date;
        }

        // 4. Generate expertise signals
        $expertise = $this->add_expertise_signals($post_id);
        if (!is_wp_error($expertise)) {
            $enhancements['expertise'] = $expertise;
        }

        return $enhancements;
    }

    /**
     * Add author bio box to content
     */
    private function add_author_bio_box($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);
        $author_bio = get_the_author_meta('description', $author_id);
        $author_url = get_author_posts_url($author_id);

        // If no bio exists, generate one
        if (empty($author_bio)) {
            $author_bio = $this->generate_author_bio($author_id, $post_id);
        }

        // Get author avatar
        $avatar = get_avatar_url($author_id, ['size' => 80]);

        // Create bio box HTML
        $bio_html = '<div class="sap-author-bio" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; margin: 30px 0; border-radius: 5px; display: flex; gap: 20px;" itemscope itemtype="https://schema.org/Person">' . "\n";
        $bio_html .= '  <div style="flex-shrink: 0;">' . "\n";
        $bio_html .= '    <img src="' . esc_url($avatar) . '" alt="' . esc_attr($author_name) . '" style="border-radius: 50%; width: 80px; height: 80px;" itemprop="image">' . "\n";
        $bio_html .= '  </div>' . "\n";
        $bio_html .= '  <div>' . "\n";
        $bio_html .= '    <h4 style="margin: 0 0 10px 0;">About <span itemprop="name">' . esc_html($author_name) . '</span></h4>' . "\n";
        $bio_html .= '    <p style="margin: 0; color: #6c757d;" itemprop="description">' . esc_html($author_bio) . '</p>' . "\n";
        $bio_html .= '    <p style="margin: 10px 0 0 0;"><a href="' . esc_url($author_url) . '" style="color: #0066cc; text-decoration: none;" itemprop="url">View all posts by ' . esc_html($author_name) . ' →</a></p>' . "\n";
        $bio_html .= '  </div>' . "\n";
        $bio_html .= '</div>';

        return $bio_html;
    }

    /**
     * Generate author bio using AI
     */
    private function generate_author_bio($author_id, $post_id) {
        $author_name = get_the_author_meta('display_name', $author_id);
        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';

        // Get some of author's posts for context
        $author_posts = get_posts([
            'author' => $author_id,
            'numberposts' => 5,
            'post_status' => 'publish'
        ]);

        $post_titles = wp_list_pluck($author_posts, 'post_title');

        $prompt = "Create a professional author bio for an SEO article.\n\n";
        $prompt .= "Author Name: {$author_name}\n";
        $prompt .= "Site Niche: {$niche}\n";
        $prompt .= "Topics they write about:\n" . implode("\n", $post_titles) . "\n\n";
        $prompt .= "Create a 2-3 sentence bio that establishes:\n";
        $prompt .= "1. Their expertise in this niche\n";
        $prompt .= "2. Years of experience (estimate 5-10 years)\n";
        $prompt .= "3. What makes them credible\n\n";
        $prompt .= "Keep it professional but approachable.\n";
        $prompt .= "Return ONLY the bio text.";

        $bio = $this->ai_analyzer->call_claude($prompt, 500);

        if (!is_wp_error($bio)) {
            // Save to author meta
            update_user_meta($author_id, 'description', $bio);
        }

        return is_wp_error($bio) ? '' : $bio;
    }

    /**
     * Suggest citations for content
     */
    public function suggest_citations($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $content = wp_strip_all_tags($post->post_content);
        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';

        $prompt = "Identify statements that need authoritative citations.\n\n";
        $prompt .= "Article: {$post->post_title}\n";
        $prompt .= "Niche: {$niche}\n\n";
        $prompt .= "Content excerpt:\n" . wp_trim_words($content, 300) . "\n\n";
        $prompt .= "Find 3-5 statements that would benefit from citations to:\n";
        $prompt .= "- Research studies\n";
        $prompt .= "- Statistics\n";
        $prompt .= "- Expert opinions\n";
        $prompt .= "- Official sources (.gov, .edu)\n\n";
        $prompt .= "For each, suggest:\n";
        $prompt .= "1. The statement that needs citation\n";
        $prompt .= "2. Type of source needed\n";
        $prompt .= "3. Suggested search query to find the source\n\n";
        $prompt .= "Return as JSON array:\n";
        $prompt .= '[\n  {"statement": "...", "source_type": "...", "search_query": "..."}\n]';

        $response = $this->ai_analyzer->call_claude($prompt, 2000);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->ai_analyzer->extract_json($response);
    }

    /**
     * Add updated date notice
     */
    private function add_updated_date($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return false;
        }

        $modified_date = get_the_modified_date('F j, Y', $post_id);
        $published_date = get_the_date('F j, Y', $post_id);

        // Only add if article was actually updated
        if ($modified_date === $published_date) {
            return false;
        }

        $content = $post->post_content;

        // Check if update notice already exists
        if (strpos($content, 'class="sap-updated-date"') !== false) {
            return false;
        }

        $update_notice = '<div class="sap-updated-date" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0;">' . "\n";
        $update_notice .= '  <p style="margin: 0;"><strong>Last Updated:</strong> <time datetime="' . get_the_modified_date('c', $post_id) . '">' . esc_html($modified_date) . '</time></p>' . "\n";
        $update_notice .= '</div>';

        // Insert at top of content
        $new_content = $update_notice . "\n\n" . $content;

        wp_update_post([
            'ID' => $post_id,
            'post_content' => $new_content
        ]);

        return true;
    }

    /**
     * Add expertise signals to content
     */
    private function add_expertise_signals($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $content = wp_strip_all_tags($post->post_content);
        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';

        $prompt = "Identify where to add expertise signals to this content.\n\n";
        $prompt .= "Article: {$post->post_title}\n";
        $prompt .= "Niche: {$niche}\n\n";
        $prompt .= "Content:\n" . wp_trim_words($content, 300) . "\n\n";
        $prompt .= "Suggest 3-5 places to add phrases that establish expertise:\n";
        $prompt .= "- 'In our testing...'\n";
        $prompt .= "- 'Based on 10 years of experience...'\n";
        $prompt .= "- 'We analyzed 50+ products...'\n";
        $prompt .= "- 'Our research shows...'\n";
        $prompt .= "- 'According to industry studies...'\n\n";
        $prompt .= "Return as JSON array:\n";
        $prompt .= '[\n  {"location": "after paragraph about...", "signal": "suggested phrase"}\n]';

        $response = $this->ai_analyzer->call_claude($prompt, 1500);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->ai_analyzer->extract_json($response);
    }

    /**
     * Add review methodology section
     */
    public function add_methodology_section($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        // Only for review-type content
        $title_lower = strtolower($post->post_title);
        $is_review = (
            strpos($title_lower, 'best') !== false ||
            strpos($title_lower, 'review') !== false ||
            strpos($title_lower, 'top ') !== false
        );

        if (!$is_review) {
            return new WP_Error('not_applicable', 'Not a review article');
        }

        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';

        $prompt = "Create a methodology section for this review article.\n\n";
        $prompt .= "Article: {$post->post_title}\n";
        $prompt .= "Niche: {$niche}\n\n";
        $prompt .= "Explain how products/services were:\n";
        $prompt .= "1. Selected for review\n";
        $prompt .= "2. Tested/evaluated\n";
        $prompt .= "3. Compared\n";
        $prompt .= "4. Ranked\n\n";
        $prompt .= "Make it credible and specific (but realistic).\n";
        $prompt .= "Format as HTML with heading and paragraphs.";

        $methodology = $this->ai_analyzer->call_claude($prompt, 1000);

        if (is_wp_error($methodology)) {
            return $methodology;
        }

        return $methodology;
    }

    /**
     * Create trust badges section
     */
    public function generate_trust_badges() {
        $badges_html = '<div class="sap-trust-badges" style="display: flex; gap: 15px; align-items: center; padding: 20px; background: #f8f9fa; border-radius: 5px; margin: 20px 0;">' . "\n";
        $badges_html .= '  <div style="flex: 1; text-align: center;">' . "\n";
        $badges_html .= '    <strong style="display: block; font-size: 1.2em; color: #28a745;">✓</strong>' . "\n";
        $badges_html .= '    <span style="font-size: 0.9em; color: #6c757d;">Fact Checked</span>' . "\n";
        $badges_html .= '  </div>' . "\n";
        $badges_html .= '  <div style="flex: 1; text-align: center;">' . "\n";
        $badges_html .= '    <strong style="display: block; font-size: 1.2em; color: #28a745;">✓</strong>' . "\n";
        $badges_html .= '    <span style="font-size: 0.9em; color: #6c757d;">Expert Reviewed</span>' . "\n";
        $badges_html .= '  </div>' . "\n";
        $badges_html .= '  <div style="flex: 1; text-align: center;">' . "\n";
        $badges_html .= '    <strong style="display: block; font-size: 1.2em; color: #28a745;">✓</strong>' . "\n";
        $badges_html .= '    <span style="font-size: 0.9em; color: #6c757d;">Regularly Updated</span>' . "\n";
        $badges_html .= '  </div>' . "\n";
        $badges_html .= '</div>';

        return $badges_html;
    }

    /**
     * Add sources section at end of article
     */
    public function generate_sources_section($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        // Extract all external links from content
        preg_match_all('/<a[^>]+href=["\'](https?:\/\/[^"\']+)["\'][^>]*>(.*?)<\/a>/i', $post->post_content, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return new WP_Error('no_links', 'No external links found');
        }

        $sources_html = '<div class="sap-sources" style="background: #f8f9fa; padding: 20px; margin: 30px 0; border-top: 2px solid #dee2e6;">' . "\n";
        $sources_html .= '  <h3>Sources & References</h3>' . "\n";
        $sources_html .= '  <ol style="line-height: 1.8;">' . "\n";

        foreach ($matches as $index => $match) {
            $url = $match[1];
            $text = strip_tags($match[2]);

            // Skip internal links
            if (strpos($url, get_site_url()) !== false) {
                continue;
            }

            $sources_html .= '    <li><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($text ?: parse_url($url, PHP_URL_HOST)) . '</a></li>' . "\n";
        }

        $sources_html .= '  </ol>' . "\n";
        $sources_html .= '</div>';

        return $sources_html;
    }
}
