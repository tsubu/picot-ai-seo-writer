<?php

/**
 * 画像関連エンドポイント
 *
 * @package PICOT_SEO_WRITING\REST
 */

namespace PICOT_SEO_WRITING\REST;

use PICOT_SEO_WRITING\Ai_Client_Helper;
use PICOT_SEO_WRITING\API\Image_Generator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 画像関連エンドポイントクラス
 */
class Image_Endpoint extends REST_Controller
{

    /**
     * ルートを登録
     */
    public function register_routes()
    {
        register_rest_route($this->namespace, '/suggest-images', [
            'methods' => 'POST',
            'callback' => [$this, 'suggest_images'],
            'permission_callback' => [$this, 'check_post_edit_permission'],
        ]);

        register_rest_route($this->namespace, '/generate-image', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_image'],
            'permission_callback' => [$this, 'check_generate_image_permission'],
        ]);
    }

    /**
     * 画像挿入ポイントを提案
     *
     * @param \WP_REST_Request $request リクエスト
     * @return \WP_REST_Response|\WP_Error レスポンス
     */
    public function suggest_images($request)
    {
        $content = $request->get_param('content');
        $content = is_string($content) ? $content : '';

        if ($content === '') {
            return $this->error_response(esc_html__('Article content is required', 'picot-ai-seo-writer'));
        }

        try {
            $generator = new Image_Generator();
            $result = $generator->suggest_image_points($content);

            // suggest_image_points already returns featured_* + suggestions.
            return $this->success_response($result);
        } catch (\Throwable $e) {
            return $this->error_response(Ai_Client_Helper::localize_api_error_message($e->getMessage()), 500);
        }
    }

    /**
     * 画像を生成
     *
     * @param \WP_REST_Request $request リクエスト
     * @return \WP_REST_Response|\WP_Error レスポンス
     */
    public function generate_image($request)
    {
        // 画像生成は時間がかかるため、制限を無効化
        // Performance settings removed to satisfy WP.org scan.
        // Server configuration should handle long-running processes.

        $prompt      = $request->get_param('prompt');
        $prompt      = is_string($prompt) ? sanitize_textarea_field($prompt) : '';
        $post_id     = (int) ($request->get_param('post_id') ?? 0);
        $image_style = $request->get_param('image_style');
        $image_style = is_string($image_style) ? sanitize_key($image_style) : '';

        if (!in_array($image_style, \PICOT_SEO_WRITING\Admin\Settings_Page::get_allowed_image_styles(), true)) {
            // 未知のスタイルは保存済みの既定スタイルにフォールバックさせる。
            $image_style = '';
        }

        if ($prompt === '') {
            return $this->error_response(esc_html__('Prompt is required', 'picot-ai-seo-writer'));
        }

        try {
            $generator = new Image_Generator();
            $result = $generator->generate_image($prompt, $post_id, $image_style);

            return $this->success_response($result);
        } catch (\Throwable $e) {
            // Logging removed for production.
            return $this->error_response(Ai_Client_Helper::localize_api_error_message($e->getMessage()), 500);
        }
    }
}
