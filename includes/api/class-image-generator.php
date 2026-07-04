<?php
/**
 * Image Generator Class - Gemini Edition
 *
 * @package PICOT_SEO_WRITING\API
 */

namespace PICOT_SEO_WRITING\API;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-gemini-client.php';

/**
 * Image Generator Class
 */
class Image_Generator extends Gemini_Client
{
    /** 画像生成モデル */
    const IMAGE_MODEL = 'imagen-3.0-generate-002';

    /**
     * プロンプトから画像を生成し、メディアライブラリに保存
     *
     * @param string $prompt  プロンプトテキスト
     * @param int    $post_id 投稿ID (0の場合は紐付けなし)
     * @param string $style   画像スタイル
     * @return array 成功時のレスポンス
     */
    public function generate_image($prompt, $post_id = 0, $style = '')
    {
        if (empty($this->api_key)) {
            throw new \Exception(esc_html__('Gemini APIキーが設定されていません。', 'picot-ai-seo-writer'));
        }

        $base64 = $this->call_image_api($prompt, $style);
        return $this->upload_to_media_library($base64, $prompt);
    }

    /**
     * 画像生成APIを呼び出し、Base64データを取得
     */
    protected function call_image_api($prompt, $style = '')
    {
        $model = get_option('picot_seo_writing_image_model', self::IMAGE_MODEL);

        // スタイル指示の追加
        if (empty($style)) {
            $style = get_option('picot_seo_writing_image_style', 'photorealistic');
        }
        $style_desc = $this->get_image_style_description($style);
        $final_prompt = $prompt . ', ' . $style_desc;

        if (strpos($model, 'imagen') !== false) {
            // Imagen 3 format
            $url = "{$this->api_base}/models/{$model}:predict?key={$this->api_key}";

            $body = [
                'instances' => [
                    ['prompt' => $final_prompt]
                ],
                'parameters' => [
                    'sampleCount' => 1,
                    'aspectRatio' => '16:9',
                    'outputOptions' => [
                        'mimeType' => 'image/png'
                    ]
                ]
            ];

            $json_body = wp_json_encode($body);
            if ($json_body === false) {
                $body['instances'][0]['prompt'] = mb_convert_encoding($final_prompt, 'UTF-8', 'UTF-8');
                $json_body = wp_json_encode($body);
            }
        } else {
            // Gemini (Nano Banana) format
            $url = "{$this->api_base}/models/{$model}:generateContent?key={$this->api_key}";

            $body = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Generate an image based on this description: ' . $final_prompt]
                        ]
                    ]
                ]
            ];

            $json_body = wp_json_encode($body);
            if ($json_body === false) {
                $body['contents'][0]['parts'][0]['text'] = mb_convert_encoding('Generate an image based on this description: ' . $final_prompt, 'UTF-8', 'UTF-8');
                $json_body = wp_json_encode($body);
            }
        }

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $json_body,
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(esc_html($response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $msg = $data['error']['message'] ?? 'Image generation API error';
            /* translators: 1: Error code, 2: Error message */
            throw new \Exception(sprintf(esc_html__('Image Gen Failed (%1$s): %2$s', 'picot-ai-seo-writer'), esc_html($code), esc_html($msg)));
        }

        if (strpos($model, 'imagen') !== false) {
            if (isset($data['predictions'][0]['bytesBase64Encoded'])) {
                return $data['predictions'][0]['bytesBase64Encoded'];
            }
        } else {
            if (isset($data['candidates'][0]['content']['parts'])) {
                foreach ($data['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['inlineData']['data'])) {
                        return $part['inlineData']['data'];
                    }
                }
            }
        }

        if (empty($base64)) {
            throw new \Exception('Image API returned empty base64 data.');
        }

        return $base64;
    }

    /**
     * base64 画像を WordPress メディアライブラリにアップロード
     *
     * @return array { attachment_id, url }
     */
    private function upload_to_media_library($base64_data, $title)
    {
        $image_data = base64_decode($base64_data);
        if (!$image_data) {
            throw new \Exception('Failed to decode base64 image data');
        }

        $upload_dir = wp_upload_dir();
        $safe_name  = substr(sanitize_title($title), 0, 40);
        $filename   = 'picot-seo-' . $safe_name . '-' . time() . '.png';
        $file_path  = $upload_dir['path'] . '/' . $filename;

        if (false === file_put_contents($file_path, $image_data)) {
            throw new \Exception('Failed to save image file to disk');
        }

        $wp_filetype = wp_check_filetype($filename, null);
        $attachment  = [
            'post_mime_type' => $wp_filetype['type'] ?: 'image/png',
            'post_title'     => sanitize_text_field($title),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path);
        if (is_wp_error($attach_id)) {
            throw new \Exception(esc_html($attach_id->get_error_message()));
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return [
            'attachment_id' => $attach_id,
            'url'           => wp_get_attachment_url($attach_id),
        ];
    }

    /**
     * 記事内の画像挿入ポイントを提案（レガシー）
     */
    public function suggest_image_points($content)
    {
        $model = get_option('picot_seo_writing_text_model', '');
        $prompt = "あなたは記事のビジュアル編集者です。以下の記事内容を分析し、画像を挿入すべき最適な箇所を5箇所提案してください。\n\n" .
                  "重要ルール:\n" .
                  "1. 'location' フィールドには、画像を挿入したい直前の文章（記事内に実際に存在する一文）を正確に書き出してください。一部でも改変しないでください。\n" .
                  "2. 見出しの直後や、段落の切り替わりなど、視覚的に効果的な場所を選んでください。\n\n" .
                  "記事内容:\n" . mb_substr(wp_strip_all_tags($content), 0, 10000) . "\n\n" .
                  "JSONフォーマットで返答:\n" .
                  "{\"featured_text\":\"\",\"featured_prompt\":\"\",\"suggestions\":[{\"location\":\"\",\"description\":\"\",\"prompt\":\"\"}]}";

        $contents = [['parts' => [['text' => $prompt]]]];
        try {
            $response = $this->generate_content($model, $contents, [
                'temperature'        => 0.3,
                'max_tokens'         => 2048,
                'response_mime_type' => 'application/json',
            ]);
            $text = $this->extract_text($response);
            if (empty($text)) {
                $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            }
            $parsed = json_decode($text, true) ?: [];
            return [
                'featured_text'   => $parsed['featured_text']   ?? '',
                'featured_prompt' => $parsed['featured_prompt'] ?? '',
                'suggestions'     => $parsed['suggestions']     ?? [],
            ];
        } catch (\Exception $e) {
            return ['featured_text' => '', 'featured_prompt' => '', 'suggestions' => []];
        }
    }

    /**
     * スタイル名から英語のプロンプト修飾語を取得
     */
    private function get_image_style_description($style)
    {
        $styles = [
            'photorealistic' => 'photorealistic, highly detailed, 8k resolution',
            'digital_art'    => 'digital art style, vibrant colors, clean lines',
            'vector'         => 'vector illustration, flat design, minimalist',
            'sketch'         => 'hand-drawn sketch, pencil drawing style',
            'watercolor'     => 'watercolor painting style, soft edges, artistic',
            'cyberpunk'      => 'cyberpunk style, neon lights, futuristic',
            'anime'          => 'anime style, Japanese animation aesthetic',
            'oil_painting'   => 'oil painting style, visible brushstrokes, classic',
        ];
        return $styles[$style] ?? $styles['photorealistic'];
    }
}
