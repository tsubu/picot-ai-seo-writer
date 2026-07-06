<?php
/**
 * AI generation client (WordPress AI Client).
 *
 * @package PICOT_SEO_WRITING\API
 */

namespace PICOT_SEO_WRITING\API;

use PICOT_SEO_WRITING\Ai_Client_Helper;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Tools\DTO\WebSearch;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base client for text generation through WordPress AI Client.
 */
class Gemini_Client
{
    /**
     * Generate content via WordPress AI Client.
     *
     * @param string $model    Provider/model spec (e.g. google/gemini-2.5-flash).
     * @param array  $contents Legacy Gemini-style contents array.
     * @param array  $options  Generation options.
     * @param int    $timeout  Request timeout in seconds.
     * @return array Legacy response shape with candidates.
     * @throws \Exception When AI is unavailable or generation fails.
     */
    public function generate_content($model, $contents, $options = [], $timeout = PICOT_SEO_WRITING_API_TIMEOUT)
    {
        if (!Ai_Client_Helper::is_available()) {
            throw new \Exception(
                esc_html__(
                    'WordPress AI Client is not available. Install and configure the Google Gemini connector under Settings → Connectors.',
                    'picot-ai-seo-writer'
                )
            );
        }

        [$provider, $model_id] = Ai_Client_Helper::parse_model_spec($model);
        if ($provider === '' || $model_id === '') {
            throw new \Exception(
                esc_html__(
                    'テキストモデルが設定されていません。設定画面でモデルを選択してください。',
                    'picot-ai-seo-writer'
                )
            );
        }

        $prompt = $this->contents_to_prompt($contents);
        if ($prompt === '') {
            throw new \Exception(esc_html__('プロンプトが空です。', 'picot-ai-seo-writer'));
        }

        $request_options = new RequestOptions();
        $request_options->setTimeout((float) $timeout);

        $builder = wp_ai_client_prompt($prompt)
            ->using_provider($provider)
            ->using_model_preference($provider, $model_id)
            ->using_max_tokens((int) ($options['max_tokens'] ?? 8192))
            ->using_temperature((float) ($options['temperature'] ?? 0.7))
            ->using_request_options($request_options);

        if (!empty($options['response_mime_type']) && $options['response_mime_type'] === 'application/json') {
            $builder->as_json_response();
        }

        if (!empty($options['use_search'])) {
            $builder->using_web_search(new WebSearch());
        }

        if (!$builder->is_supported_for_text_generation()) {
            throw new \Exception(
                esc_html__(
                    '選択した Gemini モデルはテキスト生成に対応していません。Google Gemini コネクターの接続設定を確認してください。',
                    'picot-ai-seo-writer'
                )
            );
        }

        $result = $builder->generate_text_result();
        return Ai_Client_Helper::result_to_legacy_response($result);
    }

    /**
     * Extract text from a legacy response array.
     *
     * @param array $response Response array.
     * @return string
     */
    protected function extract_text($response)
    {
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return (string) $response['candidates'][0]['content']['parts'][0]['text'];
        }

        return '';
    }

    /**
     * @param array $contents Legacy contents array.
     * @return string
     */
    private function contents_to_prompt($contents)
    {
        if (!is_array($contents)) {
            return '';
        }

        $parts = [];
        foreach ($contents as $content) {
            if (!isset($content['parts']) || !is_array($content['parts'])) {
                continue;
            }
            foreach ($content['parts'] as $part) {
                if (!empty($part['text'])) {
                    $parts[] = (string) $part['text'];
                }
            }
        }

        return trim(implode("\n\n", $parts));
    }
}
