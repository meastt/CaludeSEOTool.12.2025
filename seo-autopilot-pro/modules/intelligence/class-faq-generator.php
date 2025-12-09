<?php
/**
 * FAQ Generator (Phase 2.6)
 * Automatically generates comprehensive FAQ sections for AI search optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_FAQ_Generator {

    private $ai_analyzer;
    private $site_profile;

    public function __construct() {
        $this->ai_analyzer = new SAP_AI_Analyzer();
        $this->site_profile = get_option('sap_site_profile');
    }

    /**
     * Generate FAQ section from post content
     */
    public function generate_faq($post_id, $question_count = 10) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        // Build context
        $context = $this->build_faq_context($post);

        // Generate questions and answers
        $prompt = $this->build_faq_prompt($post, $context, $question_count);

        $response = $this->ai_analyzer->call_claude($prompt, 3000);

        if (is_wp_error($response)) {
            return $response;
        }

        // Parse JSON response
        $faq_data = $this->ai_analyzer->extract_json($response);

        if (is_wp_error($faq_data)) {
            // Try to extract as plain text if JSON fails
            return $this->parse_plain_text_faq($response);
        }

        // Format FAQ for WordPress
        $faq_html = $this->format_faq_html($faq_data);

        // Generate schema markup
        $faq_schema = $this->generate_faq_schema($faq_data);

        return [
            'faq_html' => $faq_html,
            'faq_schema' => $faq_schema,
            'faq_data' => $faq_data,
            'question_count' => count($faq_data)
        ];
    }

    /**
     * Build context for FAQ generation
     */
    private function build_faq_context($post) {
        $content = wp_strip_all_tags($post->post_content);

        // Extract key topics
        $categories = get_the_category($post->ID);
        $category = $categories ? $categories[0]->name : 'General';

        // Get related posts for context
        $related_posts = get_posts([
            'category' => $categories[0]->term_id ?? 0,
            'numberposts' => 5,
            'exclude' => [$post->ID],
            'post_status' => 'publish'
        ]);

        $related_topics = wp_list_pluck($related_posts, 'post_title');

        return [
            'title' => $post->post_title,
            'category' => $category,
            'content_summary' => wp_trim_words($content, 150),
            'related_topics' => $related_topics,
            'word_count' => str_word_count($content)
        ];
    }

    /**
     * Build prompt for FAQ generation
     */
    private function build_faq_prompt($post, $context, $question_count) {
        $niche = $this->site_profile['niche']['primary_niche'] ?? 'general';
        $audience = $this->site_profile['target_audience']['primary_audience'] ?? 'general readers';

        $prompt = "You are an SEO expert creating an FAQ section for AI search optimization.\n\n";
        $prompt .= "CONTEXT:\n";
        $prompt .= "Site Niche: {$niche}\n";
        $prompt .= "Target Audience: {$audience}\n";
        $prompt .= "Article Title: {$context['title']}\n";
        $prompt .= "Category: {$context['category']}\n";
        $prompt .= "Related Topics: " . implode(", ", array_slice($context['related_topics'], 0, 5)) . "\n\n";
        $prompt .= "ARTICLE SUMMARY:\n";
        $prompt .= "{$context['content_summary']}\n\n";
        $prompt .= "TASK:\n";
        $prompt .= "Generate {$question_count} questions that people would ask AI assistants about this topic.\n\n";
        $prompt .= "REQUIREMENTS:\n";
        $prompt .= "1. Questions should be natural, conversational (how people talk to AI)\n";
        $prompt .= "2. Mix of question types:\n";
        $prompt .= "   - What is... (definitions)\n";
        $prompt .= "   - How to... (procedures)\n";
        $prompt .= "   - Why... (explanations)\n";
        $prompt .= "   - When... (timing/conditions)\n";
        $prompt .= "   - What are the best... (recommendations)\n";
        $prompt .= "3. Answers should be:\n";
        $prompt .= "   - Direct and concise (2-4 sentences)\n";
        $prompt .= "   - Start with the answer, then expand\n";
        $prompt .= "   - Match our brand tone\n";
        $prompt .= "   - Include specific details when relevant\n\n";
        $prompt .= "Return JSON array:\n";
        $prompt .= "[\n";
        $prompt .= '  {"question": "question text?", "answer": "direct answer..."},'."\n";
        $prompt .= '  ...'."\n";
        $prompt .= "]\n\n";
        $prompt .= "Make questions progressively more specific from basic to advanced.";

        return $prompt;
    }

    /**
     * Format FAQ as HTML
     */
    private function format_faq_html($faq_data) {
        $html = '<div class="sap-faq-section">' . "\n";
        $html .= '<h2>Frequently Asked Questions</h2>' . "\n\n";

        foreach ($faq_data as $index => $qa) {
            $question = esc_html($qa['question']);
            $answer = wpautop($qa['answer']); // Convert to paragraphs

            $html .= '<div class="faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">' . "\n";
            $html .= '  <h3 itemprop="name">' . $question . '</h3>' . "\n";
            $html .= '  <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">' . "\n";
            $html .= '    <div itemprop="text">' . "\n";
            $html .= '      ' . $answer . "\n";
            $html .= '    </div>' . "\n";
            $html .= '  </div>' . "\n";
            $html .= '</div>' . "\n\n";
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate FAQ schema markup (JSON-LD)
     */
    private function generate_faq_schema($faq_data) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => []
        ];

        foreach ($faq_data as $qa) {
            $schema['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $qa['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($qa['answer'])
                ]
            ];
        }

        return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Parse plain text FAQ if JSON parsing fails
     */
    private function parse_plain_text_faq($text) {
        $faq_data = [];

        // Try to extract Q&A pairs
        $lines = explode("\n", $text);
        $current_q = null;
        $current_a = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Check if line is a question
            if (preg_match('/^Q:|^\d+\.|\?$/', $line)) {
                // Save previous Q&A if exists
                if ($current_q && !empty($current_a)) {
                    $faq_data[] = [
                        'question' => $current_q,
                        'answer' => implode(' ', $current_a)
                    ];
                }

                $current_q = preg_replace('/^(Q:|\d+\.)\s*/', '', $line);
                $current_a = [];
            } elseif (preg_match('/^A:/', $line)) {
                $current_a[] = preg_replace('/^A:\s*/', '', $line);
            } else {
                $current_a[] = $line;
            }
        }

        // Add last Q&A
        if ($current_q && !empty($current_a)) {
            $faq_data[] = [
                'question' => $current_q,
                'answer' => implode(' ', $current_a)
            ];
        }

        return $faq_data;
    }

    /**
     * Insert FAQ into post content
     */
    public function insert_faq_into_post($post_id, $faq_html, $position = 'end') {
        $post = get_post($post_id);

        if (!$post) {
            return false;
        }

        $content = $post->post_content;

        switch ($position) {
            case 'start':
                $new_content = $faq_html . "\n\n" . $content;
                break;

            case 'end':
            default:
                $new_content = $content . "\n\n" . $faq_html;
                break;

            case 'before_conclusion':
                // Try to find conclusion heading
                if (preg_match('/<h[2-3][^>]*>(Conclusion|Summary|Final Thoughts|Wrap Up)/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    $position_offset = $matches[0][1];
                    $new_content = substr($content, 0, $position_offset) . $faq_html . "\n\n" . substr($content, $position_offset);
                } else {
                    $new_content = $content . "\n\n" . $faq_html;
                }
                break;
        }

        // Update post
        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $new_content
        ], true);

        return !is_wp_error($result);
    }

    /**
     * Save FAQ schema to post meta
     */
    public function save_faq_schema($post_id, $faq_schema) {
        return update_post_meta($post_id, 'sap_faq_schema', $faq_schema);
    }

    /**
     * Generate targeted FAQ based on keywords
     */
    public function generate_keyword_faq($post_id, $target_keywords) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $prompt = "Generate FAQ questions specifically about these keywords:\n\n";
        $prompt .= "Keywords: " . implode(", ", $target_keywords) . "\n\n";
        $prompt .= "Article: {$post->post_title}\n\n";
        $prompt .= "Create 5-7 questions people would ask AI about these specific keywords.\n";
        $prompt .= "Return as JSON array of {question, answer} objects.";

        $response = $this->ai_analyzer->call_claude($prompt, 2000);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->ai_analyzer->extract_json($response);
    }

    /**
     * Enhance existing FAQ with better answers
     */
    public function enhance_existing_faq($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        // Extract existing FAQ from content
        $existing_faq = $this->extract_existing_faq($post->post_content);

        if (empty($existing_faq)) {
            return new WP_Error('no_faq', 'No existing FAQ found in content');
        }

        $prompt = "Enhance these FAQ answers for AI search optimization:\n\n";
        $prompt .= json_encode($existing_faq, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "Improve answers to be:\n";
        $prompt .= "- More direct (answer first, then expand)\n";
        $prompt .= "- More comprehensive\n";
        $prompt .= "- Include specific details\n";
        $prompt .= "- Optimized for AI citations\n\n";
        $prompt .= "Return enhanced FAQ as JSON array.";

        $response = $this->ai_analyzer->call_claude($prompt, 3000);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->ai_analyzer->extract_json($response);
    }

    /**
     * Extract existing FAQ from content
     */
    private function extract_existing_faq($content) {
        $faq = [];

        // Look for Q&A patterns
        preg_match_all('/<h[3-4][^>]*>(.*?\?)<\/h[3-4]>\s*<p>(.*?)<\/p>/is', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $faq[] = [
                'question' => strip_tags($match[1]),
                'answer' => strip_tags($match[2])
            ];
        }

        return $faq;
    }
}
