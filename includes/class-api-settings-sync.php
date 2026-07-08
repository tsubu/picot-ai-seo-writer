<?php
/**
 * Sync model/style settings from other Picot plugins when unset.
 *
 * @package PICOT_SEO_WRITING
 */

namespace PICOT_SEO_WRITING;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Imports compatible non-secret settings from sibling plugins.
 */
class Api_Settings_Sync
{
    /**
     * Option key for a one-time admin notice after auto-import.
     */
    const NOTICE_OPTION = 'picot_seo_writing_api_sync_notice';

    /**
     * Register hooks.
     */
    public static function init()
    {
        add_action('plugins_loaded', [self::class, 'maybe_sync'], 20);
        add_action('activated_plugin', [self::class, 'handle_plugin_activated'], 10, 1);
    }

    /**
     * Re-sync when a compatible plugin is activated.
     *
     * @param string $plugin Plugin basename.
     */
    public static function handle_plugin_activated($plugin)
    {
        $compatible = [
            'picot-aio-ai-content-optimizer/picot-aio-ai-content-optimizer.php',
        ];

        if (!in_array($plugin, $compatible, true)) {
            return;
        }

        self::sync(true);
    }

    /**
     * Sync settings on admin bootstrap when needed.
     */
    public static function maybe_sync()
    {
        self::sync(false);
    }

    /**
     * Import missing model/style settings from known external sources.
     *
     * @param bool $force When true, attempt sync even if only models are missing.
     * @return array<string, string> Imported setting keys mapped to source labels.
     */
    public static function sync($force = false)
    {
        $imported = [];

        if ($force || empty(get_option('picot_seo_writing_text_model', ''))) {
            $text_model = sanitize_text_field((string) get_option('picot_aio_optimizer_model', ''));
            if ($text_model !== '' && empty(get_option('picot_seo_writing_text_model', ''))) {
                update_option('picot_seo_writing_text_model', self::normalize_model_spec($text_model));
                $imported['text_model'] = self::get_source_label('picot_aio_optimizer');
            }
        }

        if ($force || empty(get_option('picot_seo_writing_image_model', ''))) {
            $image_model = sanitize_text_field((string) get_option('picot_aio_optimizer_image_model', ''));
            if ($image_model !== '' && empty(get_option('picot_seo_writing_image_model', ''))) {
                update_option('picot_seo_writing_image_model', self::normalize_model_spec($image_model));
                $imported['image_model'] = self::get_source_label('picot_aio_optimizer');
            }
        }

        $available_models = get_option('picot_seo_writing_available_gemini_models', []);
        $aio_models = get_option('picot_aio_optimizer_available_models', []);
        if ((empty($available_models) || !is_array($available_models)) && !empty($aio_models) && is_array($aio_models)) {
            update_option('picot_seo_writing_available_gemini_models', self::normalize_model_option_map($aio_models));
            $imported['models'] = self::get_source_label('picot_aio_optimizer');
        }

        $image_style = get_option('picot_seo_writing_image_style', '');
        $aio_image_style = get_option('picot_aio_optimizer_image_style', '');
        if ($image_style === '' && is_string($aio_image_style) && $aio_image_style !== '' && $aio_image_style !== 'none') {
            update_option('picot_seo_writing_image_style', sanitize_text_field($aio_image_style));
            $imported['image_style'] = self::get_source_label('picot_aio_optimizer');
        }

        if (!empty($imported) && is_admin()) {
            update_option(self::NOTICE_OPTION, $imported, false);
        }

        return $imported;
    }

    /**
     * @param string $model_id Raw model ID from another plugin.
     * @return string
     */
    private static function normalize_model_spec($model_id)
    {
        $model_id = trim((string) $model_id);
        if ($model_id === '') {
            return '';
        }

        if (strpos($model_id, '/') !== false) {
            return $model_id;
        }

        return Ai_Client_Helper::format_model_spec('google', $model_id);
    }

    /**
     * @param array<int|string, string> $models Model map.
     * @return array<string, string>
     */
    private static function normalize_model_option_map($models)
    {
        $normalized = [];
        foreach ($models as $id => $label) {
            $value = is_numeric($id) ? (string) $label : (string) $id;
            $normalized[self::normalize_model_spec($value)] = is_numeric($id) ? $value : (string) $label;
        }

        return $normalized;
    }

    /**
     * Consume and format the admin notice payload.
     *
     * @return string Notice message or empty string.
     */
    public static function consume_admin_notice_message()
    {
        $imported = get_option(self::NOTICE_OPTION, []);
        if (empty($imported) || !is_array($imported)) {
            return '';
        }

        delete_option(self::NOTICE_OPTION);

        $source_label = '';
        foreach ($imported as $label) {
            if (is_string($label) && $label !== '') {
                $source_label = $label;
                break;
            }
        }

        if ($source_label === '') {
            return '';
        }

        return sprintf(
            /* translators: %s: Source plugin label. */
            __('Model settings were imported automatically from another AI plugin (%s).', 'picot-ai-seo-writer'),
            $source_label
        );
    }

    /**
     * @param string $source_id Source identifier.
     * @return string
     */
    private static function get_source_label($source_id)
    {
        $labels = [
            'picot_aio_optimizer' => __('Picot AIO AI Content Optimizer', 'picot-ai-seo-writer'),
        ];

        return $labels[$source_id] ?? $source_id;
    }
}
