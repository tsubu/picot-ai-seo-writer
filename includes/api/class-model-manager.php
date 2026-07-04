<?php
/**
 * Model Manager Class - Gemini Edition
 *
 * @package PICOT_SEO_WRITING\API
 */

namespace PICOT_SEO_WRITING\API;

if (!defined('ABSPATH')) {
    exit;
}

// 親クラスをGemini版に変更
require_once __DIR__ . '/class-gemini-client.php';

/**
 * Model Manager Class
 */
class Model_Manager extends Gemini_Client
{
    /**
     * List Text Models
     */
    public function list_models()
    {
        if (empty($this->api_key)) {
            return [];
        }

        try {
            $url = "{$this->api_base}/models?key={$this->api_key}";
            $response = wp_remote_get($url);

            if (is_wp_error($response)) {
                return [];
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $models = [];

            if (isset($body['models'])) {
                foreach ($body['models'] as $model) {
                    $id = str_replace('models/', '', $model['name']);
                    // Geminiモデルのみ抽出
                    if (strpos($id, 'gemini') !== false) {
                        $models[] = [
                            'id' => $id,
                            'name' => $model['displayName'] ?? $id
                        ];
                    }
                }
            }

            return $models;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * List Image Models (Gemini does not support DALL-E style image generation directly in AI Studio)
     */
    public function list_image_models()
    {
        // 互換性のために空の配列を返す、あるいは固定値を返す
        return [];
    }
}
