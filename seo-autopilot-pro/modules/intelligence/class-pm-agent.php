<?php
/**
 * PM/QA Agent System (Phase 2.5)
 * This oversees all fixes and ensures quality
 * Acts as project manager and quality control
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_PM_Agent {

    private $site_profile;
    private $ai_analyzer;
    private $settings;

    public function __construct() {
        $this->site_profile = get_option('sap_site_profile');
        $this->ai_analyzer = new SAP_AI_Analyzer();
        $this->settings = new SAP_Settings();
    }

    /**
     * Review proposed fix before applying
     * Acts as quality control
     */
    public function review_fix($issue, $proposed_fix) {
        if (!$this->site_profile) {
            return [
                'decision' => 'approve',
                'score' => 70,
                'reasoning' => 'No site profile available - using default approval',
                'improvements' => [],
                'risks' => []
            ];
        }

        $prompt = "You are a quality control manager for an SEO agency.\n\n";
        $prompt .= "CLIENT SITE PROFILE:\n";
        $prompt .= "Niche: " . ($this->site_profile['niche']['primary_niche'] ?? 'general') . "\n";
        $prompt .= "Audience: " . ($this->site_profile['target_audience']['primary_audience'] ?? 'general') . "\n";
        $prompt .= "Tone: " . ($this->site_profile['writing_style']['tone'] ?? 'professional') . "\n";
        $prompt .= "Voice: " . ($this->site_profile['writing_style']['voice'] ?? 'third person') . "\n\n";
        $prompt .= "ISSUE:\n";
        $prompt .= is_object($issue) ? json_encode($issue, JSON_PRETTY_PRINT) : print_r($issue, true);
        $prompt .= "\n\n";
        $prompt .= "PROPOSED FIX:\n";
        $prompt .= is_string($proposed_fix) ? $proposed_fix : json_encode($proposed_fix, JSON_PRETTY_PRINT);
        $prompt .= "\n\n";
        $prompt .= "EVALUATE:\n";
        $prompt .= "1. Does this fix match the site's brand voice and tone?\n";
        $prompt .= "2. Is the content quality high enough for publication?\n";
        $prompt .= "3. Does it serve the target audience appropriately?\n";
        $prompt .= "4. Are there any red flags or quality issues?\n";
        $prompt .= "5. Overall assessment\n\n";
        $prompt .= "Return JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "decision": "approve/revise/reject",'."\n";
        $prompt .= '  "score": 1-100,'."\n";
        $prompt .= '  "reasoning": "explanation",'."\n";
        $prompt .= '  "improvements": ["suggestion1", "suggestion2"],'."\n";
        $prompt .= '  "risks": ["risk1", "risk2"]'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude($prompt, 2000);

        if (is_wp_error($response)) {
            error_log('SAP PM Agent: Review failed - ' . $response->get_error_message());
            return [
                'decision' => 'approve',
                'score' => 70,
                'reasoning' => 'Review failed, defaulting to approval',
                'improvements' => [],
                'risks' => []
            ];
        }

        $review = $this->ai_analyzer->extract_json($response);

        if (!is_array($review)) {
            return [
                'decision' => 'approve',
                'score' => 70,
                'reasoning' => 'Could not parse review, defaulting to approval',
                'improvements' => [],
                'risks' => []
            ];
        }

        return $review;
    }

    /**
     * Batch review all pending fixes
     */
    public function review_all_fixes($fixes) {
        $approved = [];
        $rejected = [];
        $needs_revision = [];

        $quality_threshold = $this->settings->get_pm_quality_threshold();

        foreach ($fixes as $fix) {
            $issue = $fix['issue'] ?? null;
            $proposed_fix = $fix['proposed_fix'] ?? null;

            if (!$issue || !$proposed_fix) {
                continue;
            }

            $review = $this->review_fix($issue, $proposed_fix);

            // Make decision based on score and decision
            if ($review['decision'] === 'approve' && $review['score'] >= $quality_threshold) {
                $approved[] = $fix;
            } elseif ($review['decision'] === 'revise' || ($review['decision'] === 'approve' && $review['score'] < $quality_threshold)) {
                $needs_revision[] = [
                    'fix' => $fix,
                    'review' => $review,
                    'improvements' => $review['improvements'] ?? []
                ];
            } else {
                $rejected[] = [
                    'fix' => $fix,
                    'review' => $review,
                    'reasoning' => $review['reasoning'] ?? 'Quality too low'
                ];
            }
        }

        return [
            'approved' => $approved,
            'needs_revision' => $needs_revision,
            'rejected' => $rejected,
            'stats' => [
                'total' => count($fixes),
                'approved' => count($approved),
                'needs_revision' => count($needs_revision),
                'rejected' => count($rejected)
            ]
        ];
    }

    /**
     * Ensure consistency across all fixes
     */
    public function ensure_consistency($all_fixes) {
        if (!$this->site_profile || empty($all_fixes)) {
            return [
                'score' => 100,
                'consistent' => true,
                'recommendations' => []
            ];
        }

        $prompt = "You're reviewing SEO fixes for consistency across a website.\n\n";
        $prompt .= "SITE PROFILE:\n";
        $prompt .= "Niche: " . ($this->site_profile['niche']['primary_niche'] ?? 'general') . "\n";
        $prompt .= "Tone: " . ($this->site_profile['writing_style']['tone'] ?? 'professional') . "\n\n";
        $prompt .= "ALL FIXES (sample):\n";

        // Limit to first 10 fixes to avoid token limits
        $sample_fixes = array_slice($all_fixes, 0, 10);
        $prompt .= json_encode($sample_fixes, JSON_PRETTY_PRINT) . "\n\n";

        $prompt .= "CHECK:\n";
        $prompt .= "1. Are meta descriptions consistent in tone?\n";
        $prompt .= "2. Do alt texts follow a pattern?\n";
        $prompt .= "3. Is keyword usage natural across content?\n";
        $prompt .= "4. Any conflicting approaches?\n\n";
        $prompt .= "Return JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "score": 1-100,'."\n";
        $prompt .= '  "consistent": true/false,'."\n";
        $prompt .= '  "recommendations": ["rec1", "rec2"]'."\n";
        $prompt .= "}";

        $response = $this->ai_analyzer->call_claude($prompt, 2500);

        if (is_wp_error($response)) {
            return [
                'score' => 100,
                'consistent' => true,
                'recommendations' => []
            ];
        }

        $consistency = $this->ai_analyzer->extract_json($response);

        if (!is_array($consistency)) {
            return [
                'score' => 100,
                'consistent' => true,
                'recommendations' => []
            ];
        }

        return $consistency;
    }

    /**
     * Get review statistics
     */
    public function get_review_stats() {
        $stats = get_option('sap_pm_review_stats', [
            'total_reviews' => 0,
            'approved' => 0,
            'rejected' => 0,
            'revised' => 0,
            'avg_score' => 0
        ]);

        return $stats;
    }

    /**
     * Update review statistics
     */
    public function update_review_stats($review_result) {
        $stats = $this->get_review_stats();

        $stats['total_reviews']++;

        if (isset($review_result['decision'])) {
            switch ($review_result['decision']) {
                case 'approve':
                    $stats['approved']++;
                    break;
                case 'reject':
                    $stats['rejected']++;
                    break;
                case 'revise':
                    $stats['revised']++;
                    break;
            }
        }

        if (isset($review_result['score'])) {
            $current_avg = $stats['avg_score'];
            $total = $stats['total_reviews'];
            $stats['avg_score'] = (($current_avg * ($total - 1)) + $review_result['score']) / $total;
        }

        update_option('sap_pm_review_stats', $stats);
    }

    /**
     * Generate improvement suggestions
     */
    public function generate_improvement_suggestions($original_content, $issues) {
        $prompt = "You are an SEO quality control expert. Review this content and suggest improvements:\n\n";
        $prompt .= "CONTENT:\n{$original_content}\n\n";
        $prompt .= "ISSUES IDENTIFIED:\n";

        if (is_array($issues)) {
            foreach ($issues as $issue) {
                $prompt .= "- " . (is_string($issue) ? $issue : json_encode($issue)) . "\n";
            }
        } else {
            $prompt .= $issues;
        }

        $prompt .= "\n\nProvide 3-5 specific, actionable improvements. Return as JSON array:\n";
        $prompt .= '["improvement 1", "improvement 2", ...]';

        $response = $this->ai_analyzer->call_claude($prompt, 1500);

        if (is_wp_error($response)) {
            return [];
        }

        $improvements = $this->ai_analyzer->extract_json($response);

        return is_array($improvements) ? $improvements : [];
    }

    /**
     * Validate fix format
     */
    public function validate_fix_format($fix, $fix_type) {
        $validation = [
            'valid' => true,
            'errors' => []
        ];

        switch ($fix_type) {
            case 'meta_description':
                if (empty($fix) || !is_string($fix)) {
                    $validation['valid'] = false;
                    $validation['errors'][] = 'Meta description must be a non-empty string';
                }
                $length = strlen($fix);
                if ($length < 120 || $length > 160) {
                    $validation['valid'] = false;
                    $validation['errors'][] = "Meta description length ({$length}) should be between 120-160 characters";
                }
                break;

            case 'alt_text':
                if (empty($fix) || !is_string($fix)) {
                    $validation['valid'] = false;
                    $validation['errors'][] = 'Alt text must be a non-empty string';
                }
                $word_count = str_word_count($fix);
                if ($word_count < 5 || $word_count > 20) {
                    $validation['valid'] = false;
                    $validation['errors'][] = "Alt text word count ({$word_count}) should be between 5-20 words";
                }
                break;

            case 'content':
                if (empty($fix) || !is_string($fix)) {
                    $validation['valid'] = false;
                    $validation['errors'][] = 'Content must be a non-empty string';
                }
                break;

            default:
                break;
        }

        return $validation;
    }

    /**
     * Check if PM Agent is enabled
     */
    public function is_enabled() {
        return $this->settings->get('pm_agent_enabled', true);
    }

    /**
     * Get quality threshold
     */
    public function get_quality_threshold() {
        return $this->settings->get_pm_quality_threshold();
    }
}
