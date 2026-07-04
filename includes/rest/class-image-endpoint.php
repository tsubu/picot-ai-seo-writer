<?php

/**
 * 画像関連エンドポイント
 *
 * @package PICOT_SEO_WRITING\REST
 */

namespace PICOT_SEO_WRITING\REST;

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
            'permission_callback' => [$this, 'check_edit_permission'],
        ]);

        register_rest_route($this->namespace, '/generate-image', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_image'],
            'permission_callback' => [$this, 'check_upload_permission'],
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

        if (empty($content)) {
            return $this->error_response(esc_html__('記事内容は必須です', 'picot-ai-seo-writer'));
        }

        try {
            $generator = new Image_Generator();
            $suggestions = $generator->suggest_image_points($content);

            return $this->success_response(['suggestions' => $suggestions]);
        } catch (\Exception $e) {
            return $this->error_response($e->getMessage(), 500);
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
        $post_id     = $request->get_param('post_id') ?? 0;
        $image_style = $request->get_param('image_style');

        if (empty($prompt)) {
            return $this->error_response(esc_html__('プロンプトは必須です', 'picot-ai-seo-writer'));
        }

        try {
            $generator = new Image_Generator();
            $result = $generator->generate_image($prompt, $post_id, $image_style);

            return $this->success_response($result);
        } catch (\Exception $e) {
            // Logging removed for production.
            return $this->error_response($e->getMessage(), 500);
        }
    }
}
