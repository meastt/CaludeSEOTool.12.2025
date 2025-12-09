<?php
/**
 * AI Search Fixer (Phase 2.6)
 * Coordinates all AI search optimization fixes
 * Integrates with Auto-Fixer and PM Agent for quality control
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_AI_Search_Fixer {

    private $optimizer;
    private $faq_generator;
    private $direct_answer_optimizer;
    private $eeat_enhancer;
    private $pm_agent;
    private $settings;

    public function __construct() {
        $this->optimizer = new SAP_AI_Search_Optimizer();
        $this->faq_generator = new SAP_FAQ_Generator();
        $this->direct_answer_optimizer = new SAP_Direct_Answer_Optimizer();
        $this->eeat_enhancer = new SAP_EEAT_Enhancer();
        $this->pm_agent = new SAP_PM_Agent();
        $this->settings = new SAP_Settings();
    }

    /**
     * Optimize post for AI search (full workflow)
     */
    public function optimize_post_for_ai_search($post_id, $options = []) {
        $defaults = [
            'add_direct_answer' => true,
            'generate_faq' => true,
            'add_toc' => true,
            'enhance_eeat' => true,
            'generate_schema' => true,
            'pm_review' => true
        ];

        $options = wp_parse_args($options, $defaults);

        $results = [
            'post_id' => $post_id,
            'optimizations' => [],
            'pm_reviews' => [],
            'errors' => [],
            'before_score' => 0,
            'after_score' => 0
        ];

        // Step 1: Analyze current state
        $before_analysis = $this->optimizer->analyze_ai_search_readiness($post_id);

        if (is_wp_error($before_analysis)) {
            $results['errors'][] = $before_analysis->get_error_message();
            return $results;
        }

        $results['before_score'] = $before_analysis['overall'];
        $results['before_analysis'] = $before_analysis;

        // Step 2: Apply optimizations based on recommendations

        // Direct Answer
        if ($options['add_direct_answer'] && $before_analysis['direct_answer']['score'] < 70) {
            $direct_answer = $this->direct_answer_optimizer->generate_direct_answer($post_id);

            if (!is_wp_error($direct_answer)) {
                // PM Review
                if ($options['pm_review']) {
                    $review = $this->pm_agent->review_fix(
                        (object)['issue_type' => 'missing_direct_answer', 'post_id' => $post_id],
                        $direct_answer
                    );

                    $results['pm_reviews']['direct_answer'] = $review;

                    if ($review['decision'] === 'approve' && $review['score'] >= 70) {
                        $this->direct_answer_optimizer->insert_direct_answer($post_id, $direct_answer);
                        $results['optimizations']['direct_answer'] = 'added';
                    } else {
                        $results['optimizations']['direct_answer'] = 'rejected';
                    }
                } else {
                    $this->direct_answer_optimizer->insert_direct_answer($post_id, $direct_answer);
                    $results['optimizations']['direct_answer'] = 'added';
                }
            } else {
                $results['errors'][] = 'Direct answer generation failed: ' . $direct_answer->get_error_message();
            }
        }

        // FAQ Section
        if ($options['generate_faq'] && $before_analysis['faq_section']['score'] < 60) {
            $faq = $this->faq_generator->generate_faq($post_id, 10);

            if (!is_wp_error($faq)) {
                // PM Review FAQ quality
                if ($options['pm_review']) {
                    $review = $this->pm_agent->review_fix(
                        (object)['issue_type' => 'missing_faq', 'post_id' => $post_id],
                        $faq['faq_html']
                    );

                    $results['pm_reviews']['faq'] = $review;

                    if ($review['decision'] === 'approve' && $review['score'] >= 70) {
                        $this->faq_generator->insert_faq_into_post($post_id, $faq['faq_html'], 'before_conclusion');
                        $this->faq_generator->save_faq_schema($post_id, $faq['faq_schema']);
                        $results['optimizations']['faq'] = 'added';
                        $results['faq_count'] = $faq['question_count'];
                    } else {
                        $results['optimizations']['faq'] = 'rejected';
                    }
                } else {
                    $this->faq_generator->insert_faq_into_post($post_id, $faq['faq_html'], 'before_conclusion');
                    $this->faq_generator->save_faq_schema($post_id, $faq['faq_schema']);
                    $results['optimizations']['faq'] = 'added';
                    $results['faq_count'] = $faq['question_count'];
                }
            } else {
                $results['errors'][] = 'FAQ generation failed: ' . $faq->get_error_message();
            }
        }

        // Table of Contents
        if ($options['add_toc'] && $before_analysis['content_depth']['h2_count'] >= 4) {
            $toc_added = $this->direct_answer_optimizer->generate_table_of_contents($post_id);

            if (!is_wp_error($toc_added)) {
                $results['optimizations']['toc'] = 'added';
            }
        }

        // E-E-A-T Enhancements
        if ($options['enhance_eeat'] && $before_analysis['eeat_signals']['score'] < 60) {
            $eeat_enhancements = $this->eeat_enhancer->enhance_post($post_id);

            if (!is_wp_error($eeat_enhancements)) {
                $results['optimizations']['eeat'] = $eeat_enhancements;
            } else {
                $results['errors'][] = 'E-E-A-T enhancement failed';
            }
        }

        // Step 3: Re-analyze to get new score
        $after_analysis = $this->optimizer->analyze_ai_search_readiness($post_id);

        if (!is_wp_error($after_analysis)) {
            $results['after_score'] = $after_analysis['overall'];
            $results['after_analysis'] = $after_analysis;
            $results['improvement'] = $after_analysis['overall'] - $before_analysis['overall'];
        }

        // Step 4: Log optimization
        $this->log_optimization($post_id, $results);

        return $results;
    }

    /**
     * Bulk optimize posts
     */
    public function bulk_optimize($post_ids, $options = []) {
        $results = [];

        foreach ($post_ids as $post_id) {
            $results[$post_id] = $this->optimize_post_for_ai_search($post_id, $options);

            // Rate limiting to avoid overwhelming AI API
            sleep(2);
        }

        return $results;
    }

    /**
     * Get optimization recommendations for post
     */
    public function get_recommendations($post_id) {
        $analysis = $this->optimizer->analyze_ai_search_readiness($post_id);

        if (is_wp_error($analysis)) {
            return $analysis;
        }

        return $analysis['recommendations'];
    }

    /**
     * Quick fix for most critical issues
     */
    public function quick_fix($post_id) {
        $analysis = $this->optimizer->analyze_ai_search_readiness($post_id);

        if (is_wp_error($analysis)) {
            return $analysis;
        }

        $fixes_applied = [];

        // Only fix highest priority items
        foreach ($analysis['recommendations'] as $rec) {
            if ($rec['priority'] !== 'high') {
                continue;
            }

            switch ($rec['action']) {
                case 'add_direct_answer':
                    $direct_answer = $this->direct_answer_optimizer->generate_direct_answer($post_id);
                    if (!is_wp_error($direct_answer)) {
                        $this->direct_answer_optimizer->insert_direct_answer($post_id, $direct_answer);
                        $fixes_applied[] = 'direct_answer';
                    }
                    break;

                case 'generate_faq':
                    $faq = $this->faq_generator->generate_faq($post_id, 5);
                    if (!is_wp_error($faq)) {
                        $this->faq_generator->insert_faq_into_post($post_id, $faq['faq_html']);
                        $fixes_applied[] = 'faq';
                    }
                    break;

                case 'add_schema':
                    // Schema generation would go here
                    break;
            }
        }

        return [
            'post_id' => $post_id,
            'fixes_applied' => $fixes_applied,
            'count' => count($fixes_applied)
        ];
    }

    /**
     * Log optimization activity
     */
    private function log_optimization($post_id, $results) {
        $log_entry = [
            'post_id' => $post_id,
            'timestamp' => current_time('mysql'),
            'before_score' => $results['before_score'],
            'after_score' => $results['after_score'],
            'improvement' => $results['improvement'] ?? 0,
            'optimizations' => $results['optimizations'],
            'errors' => $results['errors']
        ];

        // Store in transient or custom table
        $logs = get_transient('sap_ai_search_optimization_logs') ?: [];
        $logs[] = $log_entry;

        // Keep last 100 logs
        $logs = array_slice($logs, -100);

        set_transient('sap_ai_search_optimization_logs', $logs, WEEK_IN_SECONDS);

        // Also save to post meta
        update_post_meta($post_id, 'sap_ai_search_score', $results['after_score']);
        update_post_meta($post_id, 'sap_ai_search_optimized', current_time('mysql'));
    }

    /**
     * Get optimization statistics
     */
    public function get_optimization_stats() {
        global $wpdb;

        $stats = [
            'total_posts' => 0,
            'optimized_posts' => 0,
            'average_score' => 0,
            'score_distribution' => [
                'excellent' => 0,  // 80+
                'good' => 0,       // 60-79
                'needs_work' => 0, // 40-59
                'poor' => 0        // <40
            ]
        ];

        // Get all posts with AI search scores
        $posts_with_scores = $wpdb->get_results("
            SELECT meta_value as score
            FROM {$wpdb->postmeta}
            WHERE meta_key = 'sap_ai_search_score'
        ");

        $stats['total_posts'] = wp_count_posts('post')->publish;
        $stats['optimized_posts'] = count($posts_with_scores);

        if (!empty($posts_with_scores)) {
            $total_score = 0;

            foreach ($posts_with_scores as $row) {
                $score = (int) $row->score;
                $total_score += $score;

                if ($score >= 80) {
                    $stats['score_distribution']['excellent']++;
                } elseif ($score >= 60) {
                    $stats['score_distribution']['good']++;
                } elseif ($score >= 40) {
                    $stats['score_distribution']['needs_work']++;
                } else {
                    $stats['score_distribution']['poor']++;
                }
            }

            $stats['average_score'] = round($total_score / count($posts_with_scores));
        }

        return $stats;
    }

    /**
     * Find posts that need AI search optimization
     */
    public function find_posts_needing_optimization($threshold = 60) {
        $all_posts = get_posts([
            'numberposts' => -1,
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);

        $needs_optimization = [];

        foreach ($all_posts as $post) {
            $current_score = get_post_meta($post->ID, 'sap_ai_search_score', true);

            // If never analyzed or score below threshold
            if (!$current_score || $current_score < $threshold) {
                $needs_optimization[] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'current_score' => $current_score ?: 0,
                    'last_optimized' => get_post_meta($post->ID, 'sap_ai_search_optimized', true)
                ];
            }
        }

        return $needs_optimization;
    }
}
