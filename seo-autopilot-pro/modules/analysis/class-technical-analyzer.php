<?php
/**
 * Technical Analyzer
 * Analyzes crawl results for technical SEO issues
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_Technical_Analyzer {

    /**
     * Analyze crawl results for issues
     */
    public function analyze_crawl_results($audit_id) {
        $crawler = new SAP_Site_Crawler();
        $crawl_data = $crawler->get_crawl_results($audit_id);

        $issues = [];

        foreach ($crawl_data as $page) {
            // Missing meta description
            if (empty($page->meta_description)) {
                $issues[] = [
                    'type' => 'missing_meta_description',
                    'severity' => 'warning',
                    'url' => $page->url,
                    'post_id' => url_to_postid($page->url),
                    'description' => 'Page is missing meta description',
                    'fix_available' => true,
                    'auto_fixable' => true,
                    'priority_score' => 70
                ];
            }

            // Missing title
            if (empty($page->title)) {
                $issues[] = [
                    'type' => 'missing_title',
                    'severity' => 'critical',
                    'url' => $page->url,
                    'post_id' => url_to_postid($page->url),
                    'description' => 'Page is missing title tag',
                    'fix_available' => true,
                    'auto_fixable' => true,
                    'priority_score' => 95
                ];
            }

            // Thin content
            if ($page->word_count < 300) {
                $issues[] = [
                    'type' => 'thin_content',
                    'severity' => 'warning',
                    'url' => $page->url,
                    'post_id' => url_to_postid($page->url),
                    'description' => "Content is thin ({$page->word_count} words)",
                    'fix_available' => true,
                    'auto_fixable' => true,
                    'priority_score' => 60
                ];
            }

            // Missing alt text on images
            if ($page->images_without_alt > 0) {
                $issues[] = [
                    'type' => 'missing_alt_text',
                    'severity' => 'warning',
                    'url' => $page->url,
                    'post_id' => url_to_postid($page->url),
                    'description' => "{$page->images_without_alt} images missing alt text",
                    'fix_available' => true,
                    'auto_fixable' => true,
                    'priority_score' => 50
                ];
            }

            // No H1 tag
            $h1_tags = json_decode($page->h1_tags, true);
            if (empty($h1_tags)) {
                $issues[] = [
                    'type' => 'missing_h1',
                    'severity' => 'warning',
                    'url' => $page->url,
                    'post_id' => url_to_postid($page->url),
                    'description' => 'Page has no H1 tag',
                    'fix_available' => true,
                    'auto_fixable' => true,
                    'priority_score' => 65
                ];
            }

            // Multiple H1 tags
            if (count($h1_tags) > 1) {
                $issues[] = [
                    'type' => 'multiple_h1',
                    'severity' => 'info',
                    'url' => $page->url,
                    'post_id' => url_to_postid($page->url),
                    'description' => 'Page has multiple H1 tags',
                    'fix_available' => false,
                    'auto_fixable' => false,
                    'priority_score' => 30
                ];
            }

            // Slow load time
            if ($page->load_time > 3.0) {
                $issues[] = [
                    'type' => 'slow_load_time',
                    'severity' => 'warning',
                    'url' => $page->url,
                    'post_id' => url_to_postid($page->url),
                    'description' => sprintf('Slow load time: %.2f seconds', $page->load_time),
                    'fix_available' => false,
                    'auto_fixable' => false,
                    'priority_score' => 55
                ];
            }
        }

        return $issues;
    }
}
