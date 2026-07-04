<?php

/**
 * モデル一覧エンドポイント
 *
 * @package PICOT_SEO_WRITING\REST
 */

namespace PICOT_SEO_WRITING\REST;

use PICOT_SEO_WRITING\API\Model_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * モデル一覧エンドポイントクラス
 */
class Models_Endpoint extends REST_Controller
{

    /**
     * ルートを登録
     */
    public function register_routes()
    {
        register_rest_route($this->namespace, '/models', [
            'methods' => 'POST',
            'callback' => [$this, 'get_models'],
            'permission_callback' => [$this, 'check_manage_permission'],
        ]);
    }

    /**
     * モデル一覧を取得
     *
     * @param \WP_REST_Request $request リクエスト
     * @return \WP_REST_Response|\WP_Error レスポンス
     */
    public function get_models($request)
    {
        \PICOT_SEO_WRITING\Logger::info('get_models called');

        try {
            // APIキーの存在確認
            $api_key = get_option('picot_seo_writing_gemini_api_key', '');
            \PICOT_SEO_WRITING\Logger::debug('API Key check', [
                'exists' => !empty($api_key),
                'length' => strlen($api_key)
            ]);

            if (empty($api_key)) {
                \PICOT_SEO_WRITING\Logger::error('API key is empty');
                return $this->error_response(
                    esc_html__('Gemini APIキーが設定されていません。設定画面からAPIキーを入力してください。', 'picot-ai-seo-writer'),
                    400
                );
            }

            $manager = new Model_Manager();
            \PICOT_SEO_WRITING\Logger::debug('Model_Manager instantiated');

            $text_models = $manager->list_models();
            \PICOT_SEO_WRITING\Logger::debug('Text models retrieved', ['count' => count($text_models)]);

            // モデルIDのみの配列を作成
            $text_model_ids = array_map(function ($m) {
                return $m['id'];
            }, $text_models);

            // オプションを更新
            if (!empty($text_model_ids)) {
                update_option('picot_seo_writing_available_gemini_models', $text_model_ids);
                \PICOT_SEO_WRITING\Logger::info('Saved picot_seo_writing_available_gemini_models');
            }

            return $this->success_response([
                'text_models' => $text_models,
            ]);
        } catch (\Exception $e) {
            \PICOT_SEO_WRITING\Logger::error('Exception in get_models', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->error_response($e->getMessage(), 500);
        }
    }
}
