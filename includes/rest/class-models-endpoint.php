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
            if (!\PICOT_SEO_WRITING\Ai_Client_Helper::supports_text_generation()) {
                \PICOT_SEO_WRITING\Logger::error('WordPress AI Client is not configured for text generation');
                return $this->error_response(
                    esc_html__('Google Gemini connector is not configured. Connect Gemini under Settings → Connectors.', 'picot-ai-seo-writer'),
                    400
                );
            }

            $manager = new Model_Manager();
            \PICOT_SEO_WRITING\Logger::debug('Model_Manager instantiated');

            $text_models = $manager->list_models();
            \PICOT_SEO_WRITING\Logger::debug('Text models retrieved', ['count' => count($text_models)]);

            $text_models_map = [];
            foreach ($text_models as $model) {
                $text_models_map[$model['id']] = $model['name'];
            }

            if (!empty($text_models_map)) {
                update_option('picot_seo_writing_available_gemini_models', $text_models_map);
                \PICOT_SEO_WRITING\Logger::info('Saved picot_seo_writing_available_gemini_models');
            }

            return $this->success_response([
                'text_models' => $text_models,
            ]);
        } catch (\Exception $e) {
            \PICOT_SEO_WRITING\Logger::error('Exception in get_models', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->error_response($e->getMessage(), 500);
        }
    }
}
