<?php
/**
 * Image Generator Class — WordPress AI Client
 *
 * @package PICOT_SEO_WRITING\API
 */

namespace PICOT_SEO_WRITING\API;

use PICOT_SEO_WRITING\Ai_Client_Helper;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-gemini-client.php';

/**
 * Image Generator Class
 */
class Image_Generator extends Gemini_Client
{
    /**
     * Default image model when unset.
     */
    const DEFAULT_IMAGE_MODEL = 'google/gemini-2.5-flash-image';

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
        if (!Ai_Client_Helper::is_available()) {
            throw new \Exception(
                esc_html__(
                    'WordPress AI Client is not available. Install and configure the Google Gemini connector under Settings → Connectors.',
                    'picot-ai-seo-writer'
                )
            );
        }

        $base64 = $this->call_image_api($prompt, $style);
        return $this->upload_to_media_library($base64, $prompt);
    }

    /**
     * Generate image bytes through WordPress AI Client.
     *
     * @param string $prompt Prompt text.
     * @param string $style  Image style slug.
     * @return string Base64-encoded image data.
     */
    protected function call_image_api($prompt, $style = '')
    {
        $model = get_option('picot_seo_writing_image_model', self::DEFAULT_IMAGE_MODEL);
        [$provider, $model_id] = Ai_Client_Helper::parse_model_spec($model);

        if ($provider === '' || $model_id === '') {
            throw new \Exception(
                esc_html__('Image model is not set. Choose one on the settings screen.', 'picot-ai-seo-writer')
            );
        }

        if ($style === '') {
            $style = get_option('picot_seo_writing_image_style', 'photorealistic');
        }

        $final_prompt = $prompt . ', ' . $this->get_image_style_description($style);

        $request_options = new RequestOptions();
        $request_options->setTimeout((float) PICOT_SEO_WRITING_IMAGE_API_TIMEOUT);

        $builder = wp_ai_client_prompt($final_prompt)
            ->using_provider($provider)
            ->using_model_preference($provider, $model_id)
            ->using_request_options($request_options)
            ->as_output_file_type(FileTypeEnum::inline());

        if (!$builder->is_supported_for_image_generation()) {
            throw new \Exception(
                esc_html__('The selected Gemini model does not support image generation. Check the Google Gemini connector settings.', 'picot-ai-seo-writer')
            );
        }

        $result = $builder->generate_image_result();
        if (is_wp_error($result)) {
            throw new \Exception(esc_html($result->get_error_message()));
        }

        try {
            $image_file = $result->toImageFile();
            $base64 = sanitize_text_field(trim($image_file->getBase64Data() ?? ''));
            if ($base64 === '') {
                throw new \Exception(esc_html__('No image data was returned.', 'picot-ai-seo-writer'));
            }
            return $base64;
        } catch (\Throwable $e) {
            throw new \Exception(esc_html($e->getMessage()));
        }
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
            'vector'         => 'flat vector illustration, clean shapes',
            'sketch'         => 'pencil sketch style, hand-drawn look',
            'watercolor'     => 'watercolor painting style, soft edges',
            'cyberpunk'      => 'cyberpunk aesthetic, neon lights',
            'anime'          => 'anime style illustration',
            'oil_painting'   => 'oil painting style, textured brush strokes',
        ];

        return $styles[$style] ?? $styles['photorealistic'];
    }
}
