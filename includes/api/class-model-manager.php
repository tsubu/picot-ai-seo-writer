<?php
/**
 * Model Manager Class — WordPress AI Client
 *
 * @package PICOT_SEO_WRITING\API
 */

namespace PICOT_SEO_WRITING\API;

use PICOT_SEO_WRITING\Ai_Client_Helper;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Models\DTO\ModelRequirements;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-gemini-client.php';

/**
 * Model Manager Class
 */
class Model_Manager extends Gemini_Client
{
    private const GOOGLE_PROVIDER_ID = Ai_Client_Helper::GOOGLE_PROVIDER_ID;

    /**
     * List text-generation models from the Google Gemini connector.
     *
     * @return array<int, array{id: string, name: string, provider: string}>
     */
    public function list_models()
    {
        return $this->list_models_for_capability(CapabilityEnum::textGeneration(), false);
    }

    /**
     * List image-generation models from the Google Gemini connector.
     *
     * @return array<int, array{id: string, name: string, provider: string}>
     */
    public function list_image_models()
    {
        return $this->list_models_for_capability(CapabilityEnum::imageGeneration(), true);
    }

    /**
     * @param \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum $capability Capability enum.
     * @param bool                                                       $image_only Limit to image-like model IDs.
     * @return array<int, array{id: string, name: string, provider: string}>
     * @throws \Exception When model discovery fails.
     */
    private function list_models_for_capability($capability, $image_only)
    {
        if (!Ai_Client_Helper::is_available() || !class_exists(AiClient::class)) {
            return [];
        }

        if (!AiClient::isConfigured(self::GOOGLE_PROVIDER_ID)) {
            return [];
        }

        $registry = AiClient::defaultRegistry();
        $requirements = new ModelRequirements([$capability], []);

        try {
            $model_metadata_list = $registry->findProviderModelsMetadataForSupport(
                self::GOOGLE_PROVIDER_ID,
                $requirements
            );
        } catch (\Throwable $e) {
            \PICOT_SEO_WRITING\Logger::error('Failed to list Google Gemini models', [
                'message' => $e->getMessage(),
            ]);
            throw new \Exception(
                esc_html__(
                    'Gemini モデル一覧の取得に失敗しました。Google Gemini コネクターの API キーを確認してください。',
                    'picot-ai-seo-writer'
                )
            );
        }

        $models = [];

        foreach ($model_metadata_list as $model_meta) {
            $model_id = $model_meta->getId();
            $name = $model_meta->getName();
            $check_str = $model_id . ' ' . $name;
            $is_image = (bool) preg_match('/(imagen|banana|nano-|image-preview|gpt-image)/i', $check_str);

            if ($image_only && !$is_image) {
                continue;
            }
            if (!$image_only && $is_image) {
                continue;
            }

            $spec = Ai_Client_Helper::format_model_spec(self::GOOGLE_PROVIDER_ID, $model_id);
            $models[] = [
                'id' => $spec,
                'name' => $name . ' (' . self::GOOGLE_PROVIDER_ID . '/' . $model_id . ')',
                'provider' => self::GOOGLE_PROVIDER_ID,
            ];
        }

        return $models;
    }
}
