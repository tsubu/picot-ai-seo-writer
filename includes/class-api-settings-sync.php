<?php
/**
 * Sync Gemini settings from other Picot / WordPress AI plugins when unset.
 *
 * @package PICOT_SEO_WRITING
 */

namespace PICOT_SEO_WRITING;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Imports API keys and related model settings from compatible plugins.
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
            'ai/ai.php',
            'ai-provider-for-google/plugin.php',
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
     * Import missing settings from known external sources.
     *
     * @param bool $force When true, attempt sync even if only models are missing.
     * @return array<string, string> Imported setting keys mapped to source labels.
     */
    public static function sync($force = false)
    {
        $imported = [];

        if (empty(get_option('picot_seo_writing_gemini_api_key', ''))) {
            foreach (self::get_api_key_sources() as $source) {
                $key = call_user_func($source['getter']);
                if (!self::is_valid_gemini_api_key($key)) {
                    continue;
                }

                update_option('picot_seo_writing_gemini_api_key', sanitize_text_field($key));
                $imported['api_key'] = $source['label'];
                break;
            }
        }

        if ($force || empty(get_option('picot_seo_writing_text_model', ''))) {
            $text_model = sanitize_text_field((string) get_option('picot_aio_optimizer_model', ''));
            if ($text_model !== '' && empty(get_option('picot_seo_writing_text_model', ''))) {
                update_option('picot_seo_writing_text_model', $text_model);
                $imported['text_model'] = self::get_source_label('picot_aio_optimizer');
            }
        }

        if ($force || empty(get_option('picot_seo_writing_image_model', ''))) {
            $image_model = sanitize_text_field((string) get_option('picot_aio_optimizer_image_model', ''));
            if ($image_model !== '' && empty(get_option('picot_seo_writing_image_model', ''))) {
                update_option('picot_seo_writing_image_model', $image_model);
                $imported['image_model'] = self::get_source_label('picot_aio_optimizer');
            }
        }

        $available_models = get_option('picot_seo_writing_available_gemini_models', []);
        $aio_models = get_option('picot_aio_optimizer_available_models', []);
        if ((empty($available_models) || !is_array($available_models)) && !empty($aio_models) && is_array($aio_models)) {
            update_option('picot_seo_writing_available_gemini_models', $aio_models);
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
            /* translators: %s: Source plugin or environment label. */
            __('他の AI プラグイン（%s）の設定から Gemini API キー等を自動で引き継ぎました。', 'picot-ai-seo-writer'),
            $source_label
        );
    }

    /**
     * Detect whether a compatible external API key source exists.
     *
     * @return bool
     */
    public static function has_external_api_key_source()
    {
        foreach (self::get_api_key_sources() as $source) {
            if (self::is_valid_gemini_api_key(call_user_func($source['getter']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{id: string, label: string, getter: callable(): string}>
     */
    private static function get_api_key_sources()
    {
        return [
            [
                'id' => 'picot_aio_optimizer',
                'label' => self::get_source_label('picot_aio_optimizer'),
                'getter' => [self::class, 'get_picot_aio_api_key'],
            ],
            [
                'id' => 'wordpress_ai_google',
                'label' => self::get_source_label('wordpress_ai_google'),
                'getter' => [self::class, 'get_wordpress_ai_google_api_key'],
            ],
            [
                'id' => 'legacy',
                'label' => self::get_source_label('legacy'),
                'getter' => [self::class, 'get_legacy_api_key'],
            ],
            [
                'id' => 'env_gemini',
                'label' => self::get_source_label('env_gemini'),
                'getter' => [self::class, 'get_env_gemini_api_key'],
            ],
            [
                'id' => 'env_google',
                'label' => self::get_source_label('env_google'),
                'getter' => [self::class, 'get_env_google_api_key'],
            ],
        ];
    }

    /**
     * @param string $source_id Source identifier.
     * @return string
     */
    private static function get_source_label($source_id)
    {
        $labels = [
            'picot_aio_optimizer' => __('Picot AIO AI Content Optimizer', 'picot-ai-seo-writer'),
            'wordpress_ai_google' => __('WordPress AI（Google コネクター）', 'picot-ai-seo-writer'),
            'legacy' => __('Picot AI SEO Writer（旧設定）', 'picot-ai-seo-writer'),
            'env_gemini' => __('環境変数 GEMINI_API_KEY', 'picot-ai-seo-writer'),
            'env_google' => __('環境変数 GOOGLE_API_KEY', 'picot-ai-seo-writer'),
        ];

        return $labels[$source_id] ?? $source_id;
    }

    /**
     * @return string
     */
    public static function get_picot_aio_api_key()
    {
        return (string) get_option('picot_aio_optimizer_api_key', '');
    }

    /**
     * @return string
     */
    public static function get_wordpress_ai_google_api_key()
    {
        if (function_exists('WordPress\AI\get_connector_api_key_source')) {
            $source = \WordPress\AI\get_connector_api_key_source(
                'connectors_ai_google_api_key',
                'GOOGLE_API_KEY',
                'GOOGLE_API_KEY'
            );

            if ($source === 'env') {
                $env_value = getenv('GOOGLE_API_KEY');
                return is_string($env_value) ? $env_value : '';
            }

            if ($source === 'constant' && defined('GOOGLE_API_KEY')) {
                $const_value = constant('GOOGLE_API_KEY');
                return is_string($const_value) ? $const_value : '';
            }
        }

        return (string) get_option('connectors_ai_google_api_key', '');
    }

    /**
     * @return string
     */
    public static function get_legacy_api_key()
    {
        return (string) get_option('picot_seo_writing_api_key', '');
    }

    /**
     * @return string
     */
    public static function get_env_gemini_api_key()
    {
        $value = getenv('GEMINI_API_KEY');
        return is_string($value) ? $value : '';
    }

    /**
     * @return string
     */
    public static function get_env_google_api_key()
    {
        $value = getenv('GOOGLE_API_KEY');
        return is_string($value) ? $value : '';
    }

    /**
     * @param mixed $key Candidate API key.
     * @return bool
     */
    public static function is_valid_gemini_api_key($key)
    {
        $key = trim((string) $key);
        if ($key === '' || strlen($key) < 20 || strlen($key) > 200) {
            return false;
        }

        return (bool) preg_match('/^AIza[0-9A-Za-z_-]+$/', $key);
    }
}
