<?php
/**
 * Auto-Fixer Engine (Phase 5)
 * Intelligently fixes SEO issues with PM Agent oversight
 * Integrates with Phase 2.5 intelligence system
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Auto_Fixer {

    private $ai_analyzer;
    private $context_generator;
    private $pm_agent;
    private $settings;

    public function __construct() {
        $this->ai_analyzer = new SAP_AI_Analyzer();
        $this->context_generator = new SAP_Context_Aware_Generator();
        $this->pm_agent = new SAP_PM_Agent();
        $this->settings = new SAP_Settings();
    }

    /**
     * Execute fixes with PM review
     * Main entry point for auto-fixing
     */
    public function execute_fixes($issue_ids) {
        $proposed_fixes = [];

        // Step 1: Generate all proposed fixes
        foreach ($issue_ids as $issue_id) {
            $issue = $this->get_issue($issue_id);

            if (!$issue) {
                continue;
            }

            $proposed_fix = $this->generate_fix($issue);

            if (!is_wp_error($proposed_fix)) {
                $proposed_fixes[] = [
                    'issue_id' => $issue_id,
                    'issue' => $issue,
                    'proposed_fix' => $proposed_fix
                ];
            }
        }

        if (empty($proposed_fixes)) {
            return [
                'applied' => 0,
                'rejected' => 0,
                'error' => 'No valid fixes to process'
            ];
        }

        // Step 2: PM Agent reviews all fixes
        $review_results = $this->pm_agent->review_all_fixes($proposed_fixes);

        // Step 3: Handle revisions
        foreach ($review_results['needs_revision'] as $revision) {
            $improved_fix = $this->revise_fix(
                $revision['fix']['proposed_fix'],
                $revision['improvements']
            );

            if (!is_wp_error($improved_fix)) {
                // Re-review
                $re_review = $this->pm_agent->review_fix(
                    $revision['fix']['issue'],
                    $improved_fix
                );

                if ($re_review['decision'] === 'approve' && $re_review['score'] >= $this->settings->get_pm_quality_threshold()) {
                    $review_results['approved'][] = [
                        'issue_id' => $revision['fix']['issue_id'],
                        'issue' => $revision['fix']['issue'],
                        'proposed_fix' => $improved_fix
                    ];
                }
            }
        }

        // Step 4: Check consistency across all approved fixes
        $consistency_check = $this->pm_agent->ensure_consistency($review_results['approved']);

        // Step 5: Apply only approved fixes
        $results = [];
        foreach ($review_results['approved'] as $approved_fix) {
            $result = $this->apply_fix(
                $approved_fix['issue_id'],
                $approved_fix['issue'],
                $approved_fix['proposed_fix']
            );
            $results[] = $result;
        }

        return [
            'applied' => count($review_results['approved']),
            'rejected' => count($review_results['rejected']),
            'revised' => count($review_results['needs_revision']),
            'consistency_score' => $consistency_check['score'] ?? 100,
            'results' => $results,
            'review_summary' => $review_results['stats']
        ];
    }

    /**
     * Generate fix for specific issue
     */
    private function generate_fix($issue) {
        // Use context-aware generator based on issue type
        switch ($issue->issue_type) {
            case 'missing_meta_description':
                return $this->context_generator->generate_meta_description($issue->post_id);

            case 'thin_content':
                return $this->context_generator->expand_thin_content($issue->post_id);

            case 'missing_alt_text':
                // Extract image URL from issue description or URL
                $image_url = $issue->url;
                return $this->context_generator->generate_alt_text($image_url, $issue->post_id);

            case 'missing_title_tag':
                return $this->context_generator->generate_title_tag($issue->post_id);

            case 'missing_schema':
                return $this->context_generator->generate_schema_markup($issue->post_id);

            case 'missing_h1':
                return $this->generate_h1($issue->post_id);

            case 'duplicate_title':
                return $this->fix_duplicate_title($issue->post_id);

            case 'broken_internal_link':
                return $this->fix_broken_link($issue);

            default:
                return new WP_Error('unknown_issue_type', 'Unknown issue type: ' . $issue->issue_type);
        }
    }

    /**
     * Apply fix to post/page
     */
    private function apply_fix($issue_id, $issue, $fix) {
        $success = false;
        $error_message = null;

        // Get current value for rollback
        $before_value = $this->get_current_value($issue);

        // Apply fix based on issue type
        switch ($issue->issue_type) {
            case 'missing_meta_description':
                $success = update_post_meta($issue->post_id, '_yoast_wpseo_metadesc', $fix);
                break;

            case 'thin_content':
                $post_data = [
                    'ID' => $issue->post_id,
                    'post_content' => $fix
                ];
                $success = wp_update_post($post_data, true);
                if (is_wp_error($success)) {
                    $error_message = $success->get_error_message();
                    $success = false;
                }
                break;

            case 'missing_alt_text':
                // Assuming issue->url contains attachment ID in format
                $attachment_id = url_to_postid($issue->url);
                if ($attachment_id) {
                    $success = update_post_meta($attachment_id, '_wp_attachment_image_alt', $fix);
                }
                break;

            case 'missing_title_tag':
                $success = update_post_meta($issue->post_id, '_yoast_wpseo_title', $fix);
                break;

            case 'missing_schema':
                $success = update_post_meta($issue->post_id, 'sap_schema_markup', $fix);
                break;

            case 'missing_h1':
                $success = $this->update_post_h1($issue->post_id, $fix);
                break;

            default:
                $error_message = 'No apply logic for issue type: ' . $issue->issue_type;
                break;
        }

        // Record fix in database
        $fix_data = [
            'issue_id' => $issue_id,
            'fix_type' => $issue->issue_type,
            'applied_at' => current_time('mysql'),
            'applied_by' => get_current_user_id(),
            'before_value' => $before_value,
            'after_value' => is_string($fix) ? $fix : json_encode($fix),
            'success' => $success ? 1 : 0,
            'error_message' => $error_message,
            'rollback_available' => !empty($before_value) ? 1 : 0
        ];

        $fix_id = SAP_Database::insert_fix($fix_data);

        // Update issue status
        if ($success) {
            SAP_Database::update_issue($issue_id, [
                'status' => 'fixed',
                'updated_at' => current_time('mysql')
            ]);
        } else {
            SAP_Database::update_issue($issue_id, [
                'status' => 'failed',
                'updated_at' => current_time('mysql')
            ]);
        }

        return [
            'issue_id' => $issue_id,
            'fix_id' => $fix_id,
            'success' => $success,
            'error_message' => $error_message
        ];
    }

    /**
     * Revise fix based on PM feedback
     */
    private function revise_fix($original_fix, $improvements) {
        if (empty($improvements)) {
            return $original_fix;
        }

        $prompt = "Improve this SEO fix based on feedback:\n\n";
        $prompt .= "ORIGINAL:\n";
        $prompt .= is_string($original_fix) ? $original_fix : json_encode($original_fix);
        $prompt .= "\n\nIMPROVEMENTS NEEDED:\n" . implode("\n", $improvements) . "\n\n";
        $prompt .= "Return the improved version ONLY, no explanation.";

        return $this->ai_analyzer->call_claude($prompt, 1000);
    }

    /**
     * Get issue from database
     */
    private function get_issue($issue_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_issues';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $issue_id
        ));
    }

    /**
     * Get current value for rollback
     */
    private function get_current_value($issue) {
        switch ($issue->issue_type) {
            case 'missing_meta_description':
                return get_post_meta($issue->post_id, '_yoast_wpseo_metadesc', true);

            case 'thin_content':
                $post = get_post($issue->post_id);
                return $post ? $post->post_content : '';

            case 'missing_alt_text':
                $attachment_id = url_to_postid($issue->url);
                return $attachment_id ? get_post_meta($attachment_id, '_wp_attachment_image_alt', true) : '';

            case 'missing_title_tag':
                return get_post_meta($issue->post_id, '_yoast_wpseo_title', true);

            default:
                return null;
        }
    }

    /**
     * Generate H1 for post
     */
    private function generate_h1($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        // Use post title as H1 by default
        return $post->post_title;
    }

    /**
     * Fix duplicate title
     */
    private function fix_duplicate_title($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        // Generate unique variation of title
        $prompt = "Create a unique SEO title variation for: '{$post->post_title}'\n\n";
        $prompt .= "Keep the same meaning but make it unique. 50-60 characters.\n";
        $prompt .= "Return ONLY the title, no explanation.";

        return $this->ai_analyzer->call_claude($prompt, 200);
    }

    /**
     * Fix broken link
     */
    private function fix_broken_link($issue) {
        // This would require more complex logic to find and replace links
        return new WP_Error('not_implemented', 'Broken link fixing not yet implemented');
    }

    /**
     * Update post H1
     */
    private function update_post_h1($post_id, $h1) {
        // This would require parsing and updating the post content
        // For now, we'll just store it as meta
        return update_post_meta($post_id, 'sap_h1_tag', $h1);
    }

    /**
     * Rollback fix
     */
    public function rollback_fix($fix_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_fixes';

        $fix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND rollback_available = 1",
            $fix_id
        ));

        if (!$fix) {
            return new WP_Error('fix_not_found', 'Fix not found or rollback not available');
        }

        // Get issue
        $issue = $this->get_issue($fix->issue_id);

        if (!$issue) {
            return new WP_Error('issue_not_found', 'Associated issue not found');
        }

        // Restore previous value
        $success = false;

        switch ($fix->fix_type) {
            case 'missing_meta_description':
                $success = update_post_meta($issue->post_id, '_yoast_wpseo_metadesc', $fix->before_value);
                break;

            case 'thin_content':
                $post_data = [
                    'ID' => $issue->post_id,
                    'post_content' => $fix->before_value
                ];
                $result = wp_update_post($post_data, true);
                $success = !is_wp_error($result);
                break;

            case 'missing_alt_text':
                $attachment_id = url_to_postid($issue->url);
                if ($attachment_id) {
                    $success = update_post_meta($attachment_id, '_wp_attachment_image_alt', $fix->before_value);
                }
                break;
        }

        if ($success) {
            // Update issue status back to pending
            SAP_Database::update_issue($fix->issue_id, [
                'status' => 'pending',
                'updated_at' => current_time('mysql')
            ]);
        }

        return $success;
    }

    /**
     * Get fix statistics
     */
    public function get_fix_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'sap_fixes';

        $stats = [
            'total_fixes' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'successful_fixes' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE success = 1"),
            'failed_fixes' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE success = 0"),
            'rollback_available' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE rollback_available = 1")
        ];

        $stats['success_rate'] = $stats['total_fixes'] > 0
            ? round(($stats['successful_fixes'] / $stats['total_fixes']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Check if auto-fix is enabled
     */
    public function is_enabled() {
        return $this->settings->is_auto_fix_enabled();
    }
}
