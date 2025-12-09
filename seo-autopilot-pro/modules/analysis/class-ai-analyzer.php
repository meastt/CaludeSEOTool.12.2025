<?php
/**
 * AI Analyzer class
 * Handles all AI API calls to Claude and Gemini
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAP_AI_Analyzer {

    private $api_manager;
    private $settings;

    public function __construct() {
        $this->api_manager = new SAP_API_Manager();
        $this->settings = new SAP_Settings();
    }

    /**
     * Call Claude API
     */
    public function call_claude($prompt, $max_tokens = 1000, $model = 'claude-3-5-sonnet-20241022') {
        $api_key = $this->api_manager->get_credential('claude', 'api_key');

        if (!$api_key) {
            return new WP_Error('no_api_key', 'Claude API key not configured');
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => $model,
                'max_tokens' => $max_tokens,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['content'][0]['text'])) {
            return $body['content'][0]['text'];
        }

        return new WP_Error('api_error', $body['error']['message'] ?? 'Unknown error');
    }

    /**
     * Call Claude with web search capability
     * Note: This requires extended prompts feature or manual implementation
     */
    public function call_claude_with_search($prompt, $max_tokens = 3000) {
        // For now, we'll use Claude to generate search queries and then fetch results
        $search_query_prompt = "Based on this request, generate 3 Google search queries that would help answer it:\n\n{$prompt}\n\nReturn only the queries, one per line.";

        $queries = $this->call_claude($search_query_prompt, 500);

        if (is_wp_error($queries)) {
            return $queries;
        }

        // Execute searches and compile results
        $search_results = [];
        $query_lines = explode("\n", trim($queries));

        foreach (array_slice($query_lines, 0, 3) as $query) {
            $query = trim($query, '- ');
            if (empty($query)) continue;

            // Use WordPress HTTP API to search (simplified version)
            $results = $this->perform_web_search($query);
            if ($results) {
                $search_results[] = $results;
            }
        }

        // Now call Claude with the original prompt + search results
        $enhanced_prompt = $prompt . "\n\nWEB SEARCH RESULTS:\n" . implode("\n\n", $search_results);

        return $this->call_claude($enhanced_prompt, $max_tokens);
    }

    /**
     * Perform web search (simplified version)
     * In production, integrate with Google Custom Search API or similar
     */
    private function perform_web_search($query) {
        // This is a placeholder - in production, use Google Custom Search API
        // For now, return a note that search would happen here
        return "Search results for: {$query}\n[In production, this would fetch real search results]";
    }

    /**
     * Call Gemini API
     */
    public function call_gemini($prompt, $max_tokens = 2000) {
        $api_key = $this->api_manager->get_credential('gemini', 'api_key');

        if (!$api_key) {
            return new WP_Error('no_api_key', 'Gemini API key not configured');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$api_key}";

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => $max_tokens
                ]
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return $body['candidates'][0]['content']['parts'][0]['text'];
        }

        return new WP_Error('api_error', $body['error']['message'] ?? 'Unknown error');
    }

    /**
     * Describe image using Gemini Vision
     */
    public function describe_image_gemini($image_url) {
        $api_key = $this->api_manager->get_credential('gemini', 'api_key');

        if (!$api_key) {
            return new WP_Error('no_api_key', 'Gemini API key not configured');
        }

        // Download image and convert to base64
        $image_data = wp_remote_get($image_url);

        if (is_wp_error($image_data)) {
            return $image_data;
        }

        $image_content = wp_remote_retrieve_body($image_data);
        $image_base64 = base64_encode($image_content);

        // Detect mime type
        $mime_type = wp_remote_retrieve_header($image_data, 'content-type');

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-vision:generateContent?key={$api_key}";

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Describe this image in detail for SEO alt text purposes. Focus on what is shown, any text visible, and the context.'],
                            [
                                'inline_data' => [
                                    'mime_type' => $mime_type,
                                    'data' => $image_base64
                                ]
                            ]
                        ]
                    ]
                ]
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return $body['candidates'][0]['content']['parts'][0]['text'];
        }

        return new WP_Error('api_error', $body['error']['message'] ?? 'Unknown error');
    }

    /**
     * Extract JSON from AI response
     * Handles cases where AI returns JSON within markdown code blocks
     */
    public function extract_json($response) {
        if (is_wp_error($response)) {
            return $response;
        }

        // Try to extract JSON from code blocks
        if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
            $json = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
            $json = $matches[1];
        } else {
            $json = $response;
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'Failed to decode JSON: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Check if AI services are configured
     */
    public function is_configured() {
        return $this->api_manager->is_service_configured('claude');
    }

    /**
     * Get usage statistics
     */
    public function get_usage_stats() {
        return get_option('sap_ai_usage_stats', [
            'claude_calls' => 0,
            'gemini_calls' => 0,
            'total_tokens' => 0,
            'last_reset' => current_time('mysql')
        ]);
    }

    /**
     * Increment usage counter
     */
    private function increment_usage($service, $tokens = 0) {
        $stats = $this->get_usage_stats();

        if ($service === 'claude') {
            $stats['claude_calls']++;
        } elseif ($service === 'gemini') {
            $stats['gemini_calls']++;
        }

        $stats['total_tokens'] += $tokens;

        update_option('sap_ai_usage_stats', $stats);
    }
}
