<?php
/**
 * Priority Scorer
 * Calculates priority scores for issues
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Priority_Scorer {

    /**
     * Calculate priority score for issue
     */
    public function calculate_score($issue) {
        $score = 0;

        // Severity weight
        $severity_weights = [
            'critical' => 50,
            'warning' => 30,
            'info' => 10
        ];

        $score += $severity_weights[$issue['severity']] ?? 0;

        // Auto-fixable bonus
        if ($issue['auto_fixable']) {
            $score += 20;
        }

        // Issue type specific scoring
        $type_weights = [
            'missing_meta_description' => 15,
            'missing_title' => 25,
            'thin_content' => 10,
            'missing_alt_text' => 10,
            'missing_h1' => 15,
            'broken_link' => 20
        ];

        $score += $type_weights[$issue['type']] ?? 5;

        return min(100, $score);
    }
}
