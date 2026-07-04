<?php
/**
 * Gemini API Client
 *
 * @package PICOT_SEO_WRITING\API
 */

namespace PICOT_SEO_WRITING\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gemini API Client Class
 */
class Gemini_Client
{
    /**
     * API Key
     *
     * @var string
     */
    protected $api_key;

    /**
     * API Base URL
     *
     * @var string
     */
    protected $api_base = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_key = get_option('picot_seo_writing_gemini_api_key', '');
    }

    /**
     * Generate Content via Gemini API
     *
     * @param string $model Model ID
     * @param array $contents Contents array
     * @param array $options Additional options (tools, safetySettings, etc.)
     * @param int $timeout Timeout in seconds
     * @return array Response data
     * @throws \Exception API error
     */
    public function generate_content($model, $contents, $options = [], $timeout = PICOT_SEO_WRITING_API_TIMEOUT)
    {
        if (empty($this->api_key)) {
            throw new \Exception(esc_html__('Gemini APIキーが設定されていません。', 'picot-ai-seo-writer'));
        }

        $model = trim((string) $model);
        if ($model === '') {
            throw new \Exception(esc_html__('テキストモデルが設定されていません。設定画面でモデルを選択してください。', 'picot-ai-seo-writer'));
        }

        $gen_config = [
            'temperature'     => $options['temperature'] ?? 0.7,
            'maxOutputTokens' => $options['max_tokens']  ?? 8192,
        ];
        if (!empty($options['response_mime_type'])) {
            $gen_config['responseMimeType'] = $options['response_mime_type'];
        }

        // デフォルトオプションの設定
        $body = [
            'contents'         => $contents,
            'generationConfig' => $gen_config,
        ];

        // Google検索ツール (Grounding) の追加
        if (isset($options['use_search']) && $options['use_search']) {
            $body['tools'] = [
                ['google_search' => new \stdClass()]
            ];
        }

        $clean_model = str_replace('models/', '', $model);
        $url = "{$this->api_base}/models/{$clean_model}:generateContent?key={$this->api_key}";

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(esc_html($response->get_error_message()));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_msg = $result['error']['message'] ?? __('Gemini APIエラーが発生しました。', 'picot-ai-seo-writer');
            if (class_exists('PICOT_SEO_WRITING\Logger')) {
                \PICOT_SEO_WRITING\Logger::error('Gemini API error', [
                    'status' => $status_code,
                    'model'  => $clean_model,
                    'message' => $error_msg,
                ]);
            }
            throw new \Exception(esc_html($error_msg));
        }

        return $result;
    }

    /**
     * Extract text from Gemini response
     *
     * @param array $response
     * @return string
     */
    protected function extract_text($response)
    {
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return $response['candidates'][0]['content']['parts'][0]['text'];
        }
        return '';
    }

    /**
     * Log Error
     */
    protected function log_error($message, $context = [])
    {
        if (class_exists('PICOT_SEO_WRITING\Logger')) {
            // Loggerがあれば利用
        } else {
            // Log message removed for production.
        }
    }
}
