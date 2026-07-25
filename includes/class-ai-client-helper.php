<?php
/**
 * WordPress AI Client helpers.
 *
 * @package PICOT_SEO_WRITING
 */

namespace PICOT_SEO_WRITING;

use WordPress\AiClient\AiClient;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared helpers for the WordPress 7.0+ AI Client API.
 */
class Ai_Client_Helper
{
    /**
     * Official WordPress AI plugin main file (wordpress.org slug: ai).
     */
    public const AI_PLUGIN_BASENAME = 'ai/ai.php';

    /**
     * Google Gemini provider slug in the WordPress AI Client registry.
     */
    public const GOOGLE_PROVIDER_ID = 'google';

    /**
     * Admin URL for site-level AI provider configuration.
     *
     * @return string
     */
    public static function get_settings_url()
    {
        return admin_url('options-connectors.php');
    }

    /**
     * Whether the official WordPress AI plugin is installed on disk.
     *
     * @return bool
     */
    public static function is_ai_plugin_installed()
    {
        return file_exists(WP_PLUGIN_DIR . '/' . self::AI_PLUGIN_BASENAME);
    }

    /**
     * Whether the official WordPress AI plugin is active.
     *
     * Required for Connector Approvals used by third-party AI plugins.
     *
     * @return bool
     */
    public static function is_ai_plugin_active()
    {
        if (defined('WPAI_VERSION') || defined('WPAI_PLUGIN_FILE')) {
            return true;
        }

        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active(self::AI_PLUGIN_BASENAME);
    }

    /**
     * @return string
     */
    public static function get_ai_plugin_install_url()
    {
        return self_admin_url('plugin-install.php?s=AI&tab=search&type=term');
    }

    /**
     * @return string
     */
    public static function get_ai_plugin_activate_url()
    {
        return wp_nonce_url(
            self_admin_url('plugins.php?action=activate&plugin=' . rawurlencode(self::AI_PLUGIN_BASENAME)),
            'activate-plugin_' . self::AI_PLUGIN_BASENAME
        );
    }

    /**
     * @return string
     */
    public static function ai_plugin_required_message()
    {
        if (self::is_ai_plugin_installed()) {
            return __(
                'After connecting AI providers under Settings → Connectors, activate the official WordPress AI plugin. This plugin also supports the AI plugin\'s experimental Connector Approvals feature.',
                'picot-ai-seo-writer'
            );
        }

        return __(
            'After connecting AI providers under Settings → Connectors, install and activate the official WordPress AI plugin. This plugin also supports the AI plugin\'s experimental Connector Approvals feature.',
            'picot-ai-seo-writer'
        );
    }

    /**
     * @return string
     */
    public static function get_ai_plugin_action_label()
    {
        if (self::is_ai_plugin_installed()) {
            return __('Activate WordPress AI plugin', 'picot-ai-seo-writer');
        }

        return __('Install WordPress AI plugin', 'picot-ai-seo-writer');
    }

    /**
     * @return string
     */
    public static function get_ai_plugin_action_url()
    {
        if (self::is_ai_plugin_installed()) {
            return self::get_ai_plugin_activate_url();
        }

        return self::get_ai_plugin_install_url();
    }

    /**
     * @param string $class Notice CSS classes.
     * @return void
     */
    public static function print_ai_plugin_requirement_notice($class = 'notice notice-warning')
    {
        if (self::is_ai_plugin_active() || !current_user_can('activate_plugins')) {
            return;
        }

        $class = is_string($class) && $class !== '' ? $class : 'notice notice-warning';
        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <p><?php echo esc_html(self::ai_plugin_required_message()); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(self::get_ai_plugin_action_url()); ?>">
                    <?php echo esc_html(self::get_ai_plugin_action_label()); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Sanitize Gemini API plan option (free|paid).
     *
     * @param mixed $value Raw option value.
     * @return string
     */
    public static function sanitize_api_plan($value)
    {
        $value = sanitize_text_field((string) $value);
        return in_array($value, ['free', 'paid'], true) ? $value : 'paid';
    }

    /**
     * Current Gemini API plan selection.
     *
     * @return string free|paid
     */
    public static function get_api_plan()
    {
        return self::sanitize_api_plan(get_option('picot_seo_writing_api_plan', 'paid'));
    }

    /**
     * Whether the site is configured for a paid Gemini API plan.
     *
     * @return bool
     */
    public static function is_paid_api_plan()
    {
        return self::get_api_plan() === 'paid';
    }

    /**
     * Free-tier prompt instruction to keep output within token limits.
     *
     * @return string Empty on paid plan; instruction text on free plan.
     */
    public static function free_tier_output_instruction()
    {
        if (self::is_paid_api_plan()) {
            return '';
        }

        return "\n\n" . __('IMPORTANT: On the free tier, return a concise, simplified response, keep it within the token limit, and always finish the output.', 'picot-ai-seo-writer');
    }

    /**
     * Whether core AI Client functions are available.
     *
     * @return bool
     */
    public static function is_available()
    {
        return function_exists('wp_ai_client_prompt') && function_exists('wp_supports_ai') && wp_supports_ai();
    }

    /**
     * Whether AI Client and the official AI plugin are ready for connector use.
     *
     * @return bool
     */
    public static function is_ready()
    {
        return self::is_available() && self::is_ai_plugin_active();
    }

    /**
     * @return string
     */
    public static function unavailable_message()
    {
        return __(
            'WordPress AI Client is not available. Install and configure the Google Gemini connector under Settings → Connectors.',
            'picot-ai-seo-writer'
        );
    }

    /**
     * @return string
     */
    public static function readiness_error_message()
    {
        if (!self::is_available()) {
            return self::unavailable_message();
        }

        if (!self::is_ai_plugin_active()) {
            return self::ai_plugin_required_message();
        }

        return self::unavailable_message();
    }

    /**
     * Whether the Google Gemini connector is configured.
     *
     * @return bool
     */
    public static function is_google_configured()
    {
        if (!self::is_available() || !class_exists(AiClient::class)) {
            return false;
        }

        return AiClient::isConfigured(self::GOOGLE_PROVIDER_ID);
    }

    /**
     * @param string|null $prompt Optional prompt text.
     * @return \WP_AI_Client_Prompt_Builder|null
     */
    public static function create_prompt_builder($prompt = null)
    {
        if (!self::is_available()) {
            return null;
        }

        return wp_ai_client_prompt($prompt);
    }

    /**
     * @param string|null $prompt Optional prompt text.
     * @return \WP_AI_Client_Prompt_Builder|null
     */
    public static function create_google_prompt_builder($prompt = null)
    {
        $builder = self::create_prompt_builder($prompt);
        if (!$builder) {
            return null;
        }

        return $builder->using_provider(self::GOOGLE_PROVIDER_ID);
    }

    /**
     * @return bool
     */
    public static function supports_text_generation()
    {
        if (!self::is_google_configured()) {
            return false;
        }

        $builder = self::create_google_prompt_builder(null);
        return $builder && $builder->is_supported_for_text_generation();
    }

    /**
     * @return bool
     */
    public static function supports_image_generation()
    {
        if (!self::is_google_configured()) {
            return false;
        }

        $builder = self::create_google_prompt_builder(null);
        return $builder && $builder->is_supported_for_image_generation();
    }

    /**
     * Split a stored model value into provider and model ID.
     *
     * @param string $model Stored value such as "google/gemini-2.5-flash".
     * @return array{0: string, 1: string}
     */
    public static function parse_model_spec($model)
    {
        $model = trim((string) $model);
        if ($model === '') {
            return ['', ''];
        }

        if (strpos($model, '/') !== false) {
            $parts = explode('/', $model, 2);
            return [sanitize_key($parts[0]), self::sanitize_model_id($parts[1])];
        }

        return [self::GOOGLE_PROVIDER_ID, self::sanitize_model_id($model)];
    }

    /**
     * Restrict a model ID to characters used by provider model names.
     *
     * @param string $model_id Raw model ID.
     * @return string
     */
    private static function sanitize_model_id($model_id)
    {
        $model_id = trim((string) $model_id);

        return (string) preg_replace('/[^A-Za-z0-9._:\-]/', '', $model_id);
    }

    /**
     * @param string $provider Provider slug.
     * @param string $model_id Model ID.
     * @return string
     */
    public static function format_model_spec($provider, $model_id)
    {
        return sanitize_key($provider) . '/' . $model_id;
    }

    /**
     * Pick the recommended model label from a stored id => label map.
     * Prefers the first model whose id/label contains "-lite" (list order).
     * Used for both text and image model lists.
     *
     * @param array<string|int, string> $models Model id => display label.
     * @return string Empty when $models is empty.
     */
    public static function get_recommended_model_label(array $models)
    {
        if (empty($models)) {
            return '';
        }

        foreach ($models as $id => $label) {
            $haystack = is_numeric($id)
                ? (string) $label
                : (string) $id . ' ' . (string) $label;
            if (stripos($haystack, '-lite') !== false) {
                return (string) $label;
            }
        }

        $first = reset($models);
        return is_string($first) ? $first : (string) $first;
    }

    /**
     * @param array<int, array{0: string, 1: string}> $preferences
     * @return \WP_AI_Client_Prompt_Builder|null
     */
    public static function apply_model_preference($builder, array $preferences)
    {
        if (!$builder || empty($preferences)) {
            return $builder;
        }

        return $builder->using_model_preference(...$preferences);
    }

    /**
     * Map raw Gemini / AI Client English errors to a locale-aware user message.
     * Already-localized or plugin-authored messages are returned as-is.
     * Technical detail is appended only when WP_DEBUG is on.
     *
     * @param string $raw Raw error message from WP_Error, Exception, or API.
     * @return string
     */
    public static function localize_api_error_message($raw)
    {
        // 例外メッセージは生成時にエスケープされているため、二重エスケープを避けるためここで戻す。
        $raw = html_entity_decode((string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $raw = trim(wp_strip_all_tags($raw));
        if ($raw === '') {
            return __(
                'The AI request failed. Check the Google Gemini connector settings and the selected model.',
                'picot-ai-seo-writer'
            );
        }

        // Already localized (e.g. Japanese) — keep as-is.
        if (preg_match('/[\x{3040}-\x{30ff}\x{3400}-\x{9fff}]/u', $raw)) {
            return $raw;
        }

        $lower = strtolower($raw);
        $matched = true;

        if (
            strpos($lower, 'quota') !== false
            || strpos($lower, 'rate limit') !== false
            || strpos($lower, 'rate_limit') !== false
            || strpos($lower, 'resource_exhausted') !== false
            || strpos($lower, 'too many requests') !== false
            || preg_match('/\b429\b/', $lower)
        ) {
            $message = __(
                'The Gemini API quota or rate limit was reached. Wait a moment and try again, or check your plan in Google AI Studio.',
                'picot-ai-seo-writer'
            );
        } elseif (
            strpos($lower, 'billing') !== false
            || (strpos($lower, 'paid') !== false && (strpos($lower, 'plan') !== false || strpos($lower, 'tier') !== false))
            || (
                strpos($lower, 'imagen') !== false
                && (strpos($lower, 'not') !== false || strpos($lower, 'denied') !== false)
            )
        ) {
            $message = __(
                'This feature requires a paid Gemini API plan with billing enabled. Check your Google AI plan or switch the model.',
                'picot-ai-seo-writer'
            );
        } elseif (
            strpos($lower, 'api key') !== false
            || strpos($lower, 'apikey') !== false
            || strpos($lower, 'permission_denied') !== false
            || strpos($lower, 'permission denied') !== false
            || strpos($lower, 'unauthenticated') !== false
            || strpos($lower, 'unauthorized') !== false
            || strpos($lower, 'invalid_api_key') !== false
            || preg_match('/\b401\b/', $lower)
            || (preg_match('/\b403\b/', $lower) && strpos($lower, 'model') === false)
        ) {
            $message = __(
                'Google Gemini authentication failed. Check the API key under Settings → Connectors.',
                'picot-ai-seo-writer'
            );
        } elseif (
            strpos($lower, 'not found') !== false
            || strpos($lower, 'not supported') !== false
            || strpos($lower, 'not available') !== false
            || strpos($lower, 'unsupported') !== false
            || strpos($lower, 'unknown model') !== false
            || strpos($lower, 'invalid model') !== false
            || strpos($lower, 'no longer available') !== false
            || (strpos($lower, 'invalid_argument') !== false && strpos($lower, 'model') !== false)
            || (
                strpos($lower, 'model') !== false
                && (strpos($lower, 'does not exist') !== false || preg_match('/\bis not\b/', $lower))
            )
        ) {
            $message = __(
                'The selected model is not available with this API key. Choose another model (for example a -lite model) or create a new API key in Google AI Studio.',
                'picot-ai-seo-writer'
            );
        } elseif (
            preg_match('/\b(error|exception|failed|denied|invalid|status|code|googleapis|rpc)\b/i', $raw)
            || strpos($raw, '{') !== false
            || strlen($raw) > 180
        ) {
            $message = __(
                'The AI request failed. Check the Google Gemini connector settings and the selected model.',
                'picot-ai-seo-writer'
            );
        } else {
            // Short plugin-authored English message — keep as-is.
            $matched = false;
            $message = $raw;
        }

        if ($matched && defined('WP_DEBUG') && WP_DEBUG) {
            $message .= ' (' . $raw . ')';
        }

        return $message;
    }

    /**
     * @param mixed $result
     * @return array
     */
    public static function result_to_legacy_response($result)
    {
        if (is_wp_error($result)) {
            throw new \Exception(esc_html(self::localize_api_error_message($result->get_error_message())));
        }

        $text = method_exists($result, 'toText') ? $result->toText() : '';
        $additional = method_exists($result, 'getAdditionalData') ? $result->getAdditionalData() : [];
        $candidates = [];

        if (!empty($additional['candidates']) && is_array($additional['candidates'])) {
            $candidates = $additional['candidates'];
        } else {
            $candidates[] = [
                'content' => [
                    'parts' => [
                        ['text' => $text],
                    ],
                ],
            ];
        }

        return [
            'candidates' => $candidates,
        ];
    }
}
