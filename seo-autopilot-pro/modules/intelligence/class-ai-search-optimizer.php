<?php
/**
 * AI Search Optimizer (Phase 2.6)
 * Optimizes content for AI search engines like ChatGPT, Perplexity, Google AI Overviews
 * This is the future of SEO - getting cited by AI, not just ranked by Google
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_AI_Search_Optimizer {

    private $ai_analyzer;
    private $site_profile;
    private $settings;

    public function __construct() {
        $this->ai_analyzer = new SAP_AI_Analyzer();
        $this->site_profile = get_option('sap_site_profile');
        $this->settings = new SAP_Settings();
    }

    /**
     * Analyze content for AI search readiness
     * Scores content on how well it's optimized for AI search engines
     */
    public function analyze_ai_search_readiness($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }

        $content = $post->post_content;
        $title = $post->post_title;

        $score = [
            'overall' => 0,
            'direct_answer' => $this->check_direct_answer($content),
            'faq_section' => $this->check_faq_section($content),
            'structured_data' => $this->check_structured_data($post_id),
            'eeat_signals' => $this->check_eeat_signals($post_id, $content),
            'content_depth' => $this->check_content_depth($content),
            'citations' => $this->check_citations($content),
            'conversational_tone' => $this->check_conversational_tone($content),
            'entity_optimization' => $this->check_entity_optimization($content),
            'recommendations' => []
        ];

        // Calculate overall score (0-100)
        $weights = [
            'direct_answer' => 20,
            'faq_section' => 15,
            'structured_data' => 15,
            'eeat_signals' => 15,
            'content_depth' => 15,
            'citations' => 10,
            'conversational_tone' => 5,
            'entity_optimization' => 5
        ];

        $total = 0;
        foreach ($weights as $key => $weight) {
            $total += ($score[$key]['score'] / 100) * $weight;
        }

        $score['overall'] = round($total);

        // Generate recommendations
        $score['recommendations'] = $this->generate_recommendations($score);

        return $score;
    }

    /**
     * Check if content starts with direct answer
     */
    private function check_direct_answer($content) {
        // Remove HTML tags
        $text = wp_strip_all_tags($content);

        // Get first paragraph (first 300 characters or until double newline)
        $first_para = substr($text, 0, strpos($text, "\n\n") ?: 300);

        $score = 0;
        $issues = [];

        // Check if answer appears early
        if (strlen($first_para) < 50) {
            $issues[] = 'First paragraph too short (less than 50 characters)';
        } else {
            $score += 30;
        }

        // Check for answer indicators
        $answer_patterns = [
            '/^(The (answer|best|top|most)|Here\'s what|In short|Simply put|To summarize)/i',
            '/is \w+\./',  // Definitive statements
            '/are \w+\./'
        ];

        foreach ($answer_patterns as $pattern) {
            if (preg_match($pattern, $first_para)) {
                $score += 20;
                break;
            }
        }

        // Penalize if starts with fluff
        $fluff_patterns = [
            '/^(In this article|Welcome to|Today we|Have you ever wondered)/i'
        ];

        foreach ($fluff_patterns as $pattern) {
            if (preg_match($pattern, $first_para)) {
                $score -= 30;
                $issues[] = 'Content starts with fluff instead of direct answer';
                break;
            }
        }

        // Check first paragraph length (sweet spot 40-80 words for AI)
        $word_count = str_word_count($first_para);
        if ($word_count >= 40 && $word_count <= 80) {
            $score += 50;
        } elseif ($word_count > 80) {
            $issues[] = 'First paragraph too long (over 80 words) - AI prefers concise answers';
        }

        return [
            'score' => max(0, min(100, $score)),
            'has_direct_answer' => $score >= 50,
            'first_paragraph_words' => $word_count,
            'issues' => $issues
        ];
    }

    /**
     * Check for FAQ section
     */
    private function check_faq_section($content) {
        $score = 0;
        $faq_count = 0;
        $issues = [];

        // Check for FAQ heading
        $has_faq_heading = preg_match('/<h[2-3][^>]*>.*?(FAQ|Frequently Asked Questions|Common Questions|Q&A).*?<\/h[2-3]>/i', $content);

        if ($has_faq_heading) {
            $score += 40;
        } else {
            $issues[] = 'No FAQ section heading found';
        }

        // Count question-answer pairs
        $qa_patterns = [
            '/<h[3-4][^>]*>\s*Q:|<strong>\s*Q:/',  // Q: format
            '/<h[3-4][^>]*>\s*\?/',  // Heading ending with ?
            '/What is|How to|Why does|When should|Where can|Who is/i'  // Question words
        ];

        foreach ($qa_patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            $faq_count += count($matches[0]);
        }

        if ($faq_count >= 5) {
            $score += 60;
        } elseif ($faq_count >= 3) {
            $score += 30;
            $issues[] = 'Only ' . $faq_count . ' questions found - aim for 5+';
        } else {
            $issues[] = 'Insufficient FAQ content (found ' . $faq_count . ' questions)';
        }

        return [
            'score' => min(100, $score),
            'has_faq' => $has_faq_heading,
            'faq_count' => $faq_count,
            'issues' => $issues
        ];
    }

    /**
     * Check structured data (schema markup)
     */
    private function check_structured_data($post_id) {
        $score = 0;
        $schemas_found = [];
        $issues = [];

        // Check for FAQ schema
        $faq_schema = get_post_meta($post_id, 'sap_faq_schema', true);
        if ($faq_schema) {
            $score += 35;
            $schemas_found[] = 'FAQ';
        } else {
            $issues[] = 'No FAQ schema markup';
        }

        // Check for Article schema
        $article_schema = get_post_meta($post_id, 'sap_schema_markup', true);
        if ($article_schema) {
            $score += 35;
            $schemas_found[] = 'Article';
        } else {
            $issues[] = 'No Article schema markup';
        }

        // Check for HowTo schema (if applicable)
        if (stripos(get_the_title($post_id), 'how to') !== false) {
            $howto_schema = get_post_meta($post_id, 'sap_howto_schema', true);
            if ($howto_schema) {
                $score += 30;
                $schemas_found[] = 'HowTo';
            } else {
                $issues[] = 'Title suggests HowTo but no HowTo schema found';
            }
        }

        return [
            'score' => min(100, $score),
            'schemas_found' => $schemas_found,
            'issues' => $issues
        ];
    }

    /**
     * Check E-E-A-T signals
     */
    private function check_eeat_signals($post_id, $content) {
        $score = 0;
        $signals_found = [];
        $issues = [];

        // Check for author bio
        $author_id = get_post_field('post_author', $post_id);
        $author_description = get_the_author_meta('description', $author_id);

        if (!empty($author_description) && strlen($author_description) > 100) {
            $score += 20;
            $signals_found[] = 'Author bio';
        } else {
            $issues[] = 'No comprehensive author bio (needs 100+ characters)';
        }

        // Check for published/updated dates
        if (get_the_modified_date('U', $post_id) > get_the_date('U', $post_id)) {
            $score += 10;
            $signals_found[] = 'Updated date shown';
        }

        // Check for external citations
        $external_links = preg_match_all('/<a[^>]+href=["\'](https?:\/\/[^"\']+)["\'][^>]*>/i', $content, $matches);
        if ($external_links >= 3) {
            $score += 25;
            $signals_found[] = "$external_links external citations";
        } else {
            $issues[] = 'Few external citations (found ' . $external_links . ', need 3+)';
        }

        // Check for credentials/expertise mentions
        $expertise_keywords = ['certified', 'expert', 'professional', 'years of experience', 'research', 'study', 'tested', 'analyzed'];
        $expertise_count = 0;

        foreach ($expertise_keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $expertise_count++;
            }
        }

        if ($expertise_count >= 2) {
            $score += 25;
            $signals_found[] = 'Expertise signals present';
        } else {
            $issues[] = 'Lack of expertise/credential signals';
        }

        // Check for data/statistics
        if (preg_match('/\d+%|\d+\s*(percent|users|people|studies)/i', $content)) {
            $score += 20;
            $signals_found[] = 'Statistics/data included';
        } else {
            $issues[] = 'No statistics or data points';
        }

        return [
            'score' => min(100, $score),
            'signals_found' => $signals_found,
            'issues' => $issues
        ];
    }

    /**
     * Check content depth and comprehensiveness
     */
    private function check_content_depth($content) {
        $text = wp_strip_all_tags($content);
        $word_count = str_word_count($text);

        $score = 0;
        $issues = [];

        // Word count scoring (AI prefers comprehensive content)
        if ($word_count >= 1500) {
            $score += 40;
        } elseif ($word_count >= 800) {
            $score += 25;
        } else {
            $issues[] = 'Content too short for comprehensive coverage (current: ' . $word_count . ' words)';
        }

        // Check for subheadings (H2/H3)
        $h2_count = preg_match_all('/<h2[^>]*>/i', $content);
        $h3_count = preg_match_all('/<h3[^>]*>/i', $content);

        if ($h2_count >= 4) {
            $score += 30;
        } elseif ($h2_count >= 2) {
            $score += 15;
        } else {
            $issues[] = 'Insufficient content structure (need 4+ H2 headings)';
        }

        // Check for lists (AI loves structured info)
        $list_count = preg_match_all('/<(ul|ol)[^>]*>/i', $content);
        if ($list_count >= 2) {
            $score += 15;
        } else {
            $issues[] = 'Add more lists for scannable content';
        }

        // Check for tables (great for comparisons)
        $table_count = preg_match_all('/<table[^>]*>/i', $content);
        if ($table_count >= 1) {
            $score += 15;
        }

        return [
            'score' => min(100, $score),
            'word_count' => $word_count,
            'h2_count' => $h2_count,
            'h3_count' => $h3_count,
            'list_count' => $list_count,
            'table_count' => $table_count,
            'issues' => $issues
        ];
    }

    /**
     * Check citations quality
     */
    private function check_citations($content) {
        $score = 0;
        $issues = [];

        // Extract all external links
        preg_match_all('/<a[^>]+href=["\'](https?:\/\/([^"\'\/]+)[^"\']*)["\'][^>]*>/i', $content, $matches);
        $links = $matches[1] ?? [];
        $domains = $matches[2] ?? [];

        $authoritative_domains = [
            'wikipedia.org', 'edu', 'gov', '.gov', 'nih.gov', 'cdc.gov',
            'nature.com', 'sciencedirect.com', 'pubmed', 'scholar.google'
        ];

        $authoritative_count = 0;
        foreach ($domains as $domain) {
            foreach ($authoritative_domains as $auth_domain) {
                if (stripos($domain, $auth_domain) !== false) {
                    $authoritative_count++;
                    break;
                }
            }
        }

        if ($authoritative_count >= 3) {
            $score += 60;
        } elseif ($authoritative_count >= 1) {
            $score += 30;
            $issues[] = 'Add more authoritative sources (found ' . $authoritative_count . ', need 3+)';
        } else {
            $issues[] = 'No authoritative citations (.edu, .gov, research sites)';
        }

        // Check for dates in citations
        if (preg_match('/20(1[5-9]|2[0-5])/', $content)) {
            $score += 40;
        } else {
            $issues[] = 'Citations should include dates for credibility';
        }

        return [
            'score' => min(100, $score),
            'citation_count' => count($links),
            'authoritative_count' => $authoritative_count,
            'issues' => $issues
        ];
    }

    /**
     * Check conversational tone
     */
    private function check_conversational_tone($content) {
        $text = wp_strip_all_tags($content);
        $score = 50; // Start neutral
        $issues = [];

        // Check for question usage (conversational)
        $question_count = substr_count($text, '?');
        $sentence_count = max(1, preg_match_all('/[.!?]/', $text));
        $question_ratio = $question_count / $sentence_count;

        if ($question_ratio >= 0.05) {
            $score += 30;
        } else {
            $issues[] = 'Use more questions to match conversational search queries';
        }

        // Check for "you" usage (addressing reader)
        $you_count = preg_match_all('/\byou\b/i', $text);
        if ($you_count >= 5) {
            $score += 20;
        }

        // Penalize overly formal/academic language
        $formal_indicators = ['furthermore', 'henceforth', 'therefore', 'thus', 'wherein'];
        foreach ($formal_indicators as $word) {
            if (stripos($text, $word) !== false) {
                $score -= 10;
            }
        }

        return [
            'score' => max(0, min(100, $score)),
            'question_count' => $question_count,
            'issues' => $issues
        ];
    }

    /**
     * Check entity optimization
     */
    private function check_entity_optimization($content) {
        $score = 50; // Start neutral
        $issues = [];

        // Check for proper noun usage
        preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b/', wp_strip_all_tags($content), $matches);
        $proper_nouns = count($matches[0] ?? []);

        if ($proper_nouns >= 10) {
            $score += 50;
        } elseif ($proper_nouns >= 5) {
            $score += 25;
        } else {
            $issues[] = 'Use more specific entities (names, brands, places)';
        }

        return [
            'score' => min(100, $score),
            'entity_mentions' => $proper_nouns,
            'issues' => $issues
        ];
    }

    /**
     * Generate actionable recommendations
     */
    private function generate_recommendations($score) {
        $recommendations = [];

        // Direct answer
        if ($score['direct_answer']['score'] < 50) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'direct_answer',
                'title' => 'Add Direct Answer at Start',
                'description' => 'Start your content with a clear, concise answer (40-80 words) before expanding.',
                'action' => 'add_direct_answer'
            ];
        }

        // FAQ section
        if ($score['faq_section']['score'] < 60) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'faq',
                'title' => 'Add Comprehensive FAQ Section',
                'description' => 'Include at least 5-10 common questions with direct answers.',
                'action' => 'generate_faq'
            ];
        }

        // Schema markup
        if ($score['structured_data']['score'] < 70) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'schema',
                'title' => 'Implement Schema Markup',
                'description' => 'Add FAQ schema, Article schema, and HowTo schema where applicable.',
                'action' => 'add_schema'
            ];
        }

        // E-E-A-T signals
        if ($score['eeat_signals']['score'] < 60) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'eeat',
                'title' => 'Strengthen E-E-A-T Signals',
                'description' => 'Add author credentials, cite authoritative sources, include statistics with dates.',
                'action' => 'enhance_eeat'
            ];
        }

        // Content depth
        if ($score['content_depth']['score'] < 60) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'depth',
                'title' => 'Expand Content Depth',
                'description' => 'Add more comprehensive coverage with examples, lists, and comparisons.',
                'action' => 'expand_content'
            ];
        }

        // Citations
        if ($score['citations']['score'] < 50) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'citations',
                'title' => 'Add Authoritative Citations',
                'description' => 'Link to .edu, .gov, and research sources to build credibility.',
                'action' => 'add_citations'
            ];
        }

        return $recommendations;
    }

    /**
     * Get AI search score for multiple posts
     */
    public function bulk_analyze($post_ids) {
        $results = [];

        foreach ($post_ids as $post_id) {
            $results[$post_id] = $this->analyze_ai_search_readiness($post_id);
        }

        return $results;
    }
}
