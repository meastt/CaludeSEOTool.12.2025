<?php
/**
 * Direct Answer Optimizer (Phase 2.6)
 * Restructures content to start with direct answers for AI search optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Direct_Answer_Optimizer {

    private $ai_analyzer;
    private $site_profile;

    public function __construct() {
        $this->ai_analyzer = new SAP_AI_Analyzer();
        $this->site_profile = get_option('sap_site_profile');
    }

    /**
     * Generate direct answer for post
     */
    public function generate_direct_answer($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $content = wp_strip_all_tags($post->post_content);
        $title = $post->post_title;

        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';
        $tone = $this->site_profile['writing_style']['tone'] ?? 'professional';

        $prompt = "Create a direct answer paragraph for this article.\n\n";
        $prompt .= "CONTEXT:\n";
        $prompt .= "Site Niche: {$niche}\n";
        $prompt .= "Tone: {$tone}\n";
        $prompt .= "Article Title: {$title}\n\n";
        $prompt .= "Article Content Summary:\n";
        $prompt .= wp_trim_words($content, 200) . "\n\n";
        $prompt .= "REQUIREMENTS:\n";
        $prompt .= "1. 40-80 words exactly (this is critical for AI search)\n";
        $prompt .= "2. Start with THE ANSWER immediately (no fluff)\n";
        $prompt .= "3. Answer the implicit question in the title\n";
        $prompt .= "4. Be specific and actionable\n";
        $prompt .= "5. Match the site's tone: {$tone}\n";
        $prompt .= "6. If title has 'best', start with 'The best...'\n";
        $prompt .= "7. If title has 'how to', start with the process\n";
        $prompt .= "8. If title has 'what is', start with the definition\n\n";
        $prompt .= "BAD EXAMPLE: 'In this comprehensive guide, we'll explore...'\n";
        $prompt .= "GOOD EXAMPLE: 'The best coffee maker for home use is the Breville Precision Brewer ($299). It brews at the optimal 196-205°F temperature, has a thermal carafe, and offers both single-cup and full-pot options.'\n\n";
        $prompt .= "Return ONLY the direct answer paragraph, no explanation.";

        $response = $this->ai_analyzer->call_claude($prompt, 500);

        if (is_wp_error($response)) {
            return $response;
        }

        // Validate word count
        $word_count = str_word_count(strip_tags($response));

        if ($word_count < 30 || $word_count > 100) {
            // Try again with emphasis
            $retry_prompt = $prompt . "\n\nPREVIOUS ATTEMPT had {$word_count} words. MUST be 40-80 words. Try again.";
            $response = $this->ai_analyzer->call_claude($retry_prompt, 500);
        }

        return trim($response);
    }

    /**
     * Insert direct answer at the start of content
     */
    public function insert_direct_answer($post_id, $direct_answer) {
        $post = get_post($post_id);

        if (!$post) {
            return false;
        }

        $content = $post->post_content;

        // Create direct answer box with styling
        $answer_html = $this->format_direct_answer($direct_answer);

        // Check if direct answer already exists
        if (strpos($content, 'class="sap-direct-answer"') !== false) {
            // Replace existing
            $content = preg_replace(
                '/<div class="sap-direct-answer">.*?<\/div>/s',
                $answer_html,
                $content
            );
        } else {
            // Insert at start
            $content = $answer_html . "\n\n" . $content;
        }

        // Update post
        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $content
        ], true);

        return !is_wp_error($result);
    }

    /**
     * Format direct answer with HTML
     */
    private function format_direct_answer($answer) {
        $html = '<div class="sap-direct-answer" style="background: #f0f7ff; border-left: 4px solid #0066cc; padding: 20px; margin-bottom: 30px;">' . "\n";
        $html .= '  <p style="margin: 0; font-size: 1.1em; line-height: 1.6;">' . "\n";
        $html .= '    <strong style="color: #0066cc;">Quick Answer:</strong> ' . esc_html($answer) . "\n";
        $html .= '  </p>' . "\n";
        $html .= '</div>';

        return $html;
    }

    /**
     * Optimize existing first paragraph to be more direct
     */
    public function optimize_existing_intro($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $content = $post->post_content;

        // Extract first paragraph
        preg_match('/<p[^>]*>(.*?)<\/p>/s', $content, $matches);

        if (!isset($matches[1])) {
            return new WP_Error('no_paragraph', 'No paragraph found');
        }

        $first_para = strip_tags($matches[1]);
        $full_match = $matches[0];

        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';

        $prompt = "Rewrite this intro paragraph to be more direct for AI search:\n\n";
        $prompt .= "Current intro: {$first_para}\n\n";
        $prompt .= "Article title: {$post->post_title}\n";
        $prompt .= "Niche: {$niche}\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Start with the answer/main point immediately\n";
        $prompt .= "- Remove fluff like 'In this article' or 'Welcome'\n";
        $prompt .= "- Keep 40-80 words\n";
        $prompt .= "- Be specific and actionable\n\n";
        $prompt .= "Return ONLY the rewritten paragraph.";

        $response = $this->ai_analyzer->call_claude($prompt, 500);

        if (is_wp_error($response)) {
            return $response;
        }

        // Replace first paragraph
        $new_para = '<p>' . trim($response) . '</p>';
        $new_content = str_replace($full_match, $new_para, $content);

        // Update post
        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $new_content
        ], true);

        return !is_wp_error($result) ? $response : $result;
    }

    /**
     * Generate table of contents (helps AI understand structure)
     */
    public function generate_table_of_contents($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $content = $post->post_content;

        // Extract all H2 and H3 headings
        preg_match_all('/<h([23])[^>]*>(.*?)<\/h\1>/i', $content, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return new WP_Error('no_headings', 'No headings found');
        }

        $toc_html = '<div class="sap-table-of-contents" style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px;">' . "\n";
        $toc_html .= '  <h2 style="margin-top: 0;">Table of Contents</h2>' . "\n";
        $toc_html .= '  <ul style="list-style: none; padding-left: 0;">' . "\n";

        foreach ($matches as $index => $match) {
            $level = $match[1];
            $heading = strip_tags($match[2]);
            $anchor = sanitize_title($heading);

            // Add ID to heading in content
            $old_heading = $match[0];
            $new_heading = '<h' . $level . ' id="' . $anchor . '">' . $match[2] . '</h' . $level . '>';
            $content = str_replace($old_heading, $new_heading, $content);

            // Add to TOC
            $indent = $level == 3 ? 'margin-left: 20px;' : '';
            $toc_html .= '    <li style="' . $indent . ' margin-bottom: 8px;"><a href="#' . $anchor . '" style="text-decoration: none; color: #0066cc;">' . esc_html($heading) . '</a></li>' . "\n";
        }

        $toc_html .= '  </ul>' . "\n";
        $toc_html .= '</div>';

        // Insert TOC after first paragraph or direct answer
        if (strpos($content, 'class="sap-direct-answer"') !== false) {
            $content = preg_replace(
                '/(<\/div><!-- \.sap-direct-answer -->)/s',
                '$1' . "\n\n" . $toc_html,
                $content,
                1
            );
        } else {
            $content = preg_replace(
                '/(<\/p>)/s',
                '$1' . "\n\n" . $toc_html,
                $content,
                1
            );
        }

        // Update post
        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $content
        ], true);

        return !is_wp_error($result);
    }

    /**
     * Create summary box (great for AI understanding)
     */
    public function generate_summary_box($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $content = wp_strip_all_tags($post->post_content);

        $prompt = "Create a bullet-point summary of this article:\n\n";
        $prompt .= "Title: {$post->post_title}\n\n";
        $prompt .= "Content:\n" . wp_trim_words($content, 300) . "\n\n";
        $prompt .= "Create 3-5 key takeaways as bullet points.\n";
        $prompt .= "Each point should be 1 concise sentence.\n";
        $prompt .= "Return as JSON array of strings.";

        $response = $this->ai_analyzer->call_claude($prompt, 800);

        if (is_wp_error($response)) {
            return $response;
        }

        $summary_points = $this->ai_analyzer->extract_json($response);

        if (is_wp_error($summary_points)) {
            return $summary_points;
        }

        // Format as HTML
        $summary_html = '<div class="sap-summary-box" style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 5px;">' . "\n";
        $summary_html .= '  <h3 style="margin-top: 0; color: #856404;">Key Takeaways</h3>' . "\n";
        $summary_html .= '  <ul>' . "\n";

        foreach ($summary_points as $point) {
            $summary_html .= '    <li>' . esc_html($point) . '</li>' . "\n";
        }

        $summary_html .= '  </ul>' . "\n";
        $summary_html .= '</div>';

        return [
            'html' => $summary_html,
            'points' => $summary_points
        ];
    }
}
