<?php
/**
 * Content Analyzer
 * Analyzes content quality and SEO optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Content_Analyzer {

    /**
     * Analyze post content
     */
    public function analyze_post($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $content = wp_strip_all_tags($post->post_content);
        $word_count = str_word_count($content);

        return [
            'word_count' => $word_count,
            'is_thin' => $word_count < 300,
            'readability_score' => $this->calculate_readability($content),
            'keyword_density' => $this->analyze_keyword_density($content),
            'has_images' => preg_match('/<img/', $post->post_content) > 0,
            'has_lists' => preg_match('/<(ul|ol)/', $post->post_content) > 0,
            'has_headings' => preg_match('/<h[2-6]/', $post->post_content) > 0
        ];
    }

    /**
     * Calculate basic readability score
     */
    private function calculate_readability($text) {
        $sentences = preg_split('/[.!?]+/', $text);
        $words = str_word_count($text);
        $sentence_count = count(array_filter($sentences));

        if ($sentence_count === 0) {
            return 0;
        }

        $avg_words_per_sentence = $words / $sentence_count;

        // Simple scoring
        if ($avg_words_per_sentence <= 15) {
            return 90; // Easy to read
        } elseif ($avg_words_per_sentence <= 20) {
            return 70; // Average
        } else {
            return 50; // Hard to read
        }
    }

    /**
     * Analyze keyword density
     */
    private function analyze_keyword_density($text) {
        $words = str_word_count(strtolower($text), 1);
        $total_words = count($words);

        if ($total_words === 0) {
            return [];
        }

        $word_freq = array_count_values($words);
        arsort($word_freq);

        $density = [];
        $i = 0;

        foreach ($word_freq as $word => $count) {
            if ($i >= 10 || strlen($word) < 4) {
                continue;
            }

            $density[$word] = round(($count / $total_words) * 100, 2);
            $i++;
        }

        return $density;
    }
}
