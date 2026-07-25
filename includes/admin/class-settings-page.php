<?php
/**
 * Settings Page Class - Gemini Edition
 *
 * @package PicotSEOWriting
 */

namespace PICOT_SEO_WRITING\Admin;

use PICOT_SEO_WRITING\Ai_Client_Helper;
use PICOT_SEO_WRITING\Api_Settings_Sync;
use PICOT_SEO_WRITING\API\Model_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Page Class
 */
class Settings_Page
{
    /**
     * Page slug
     */
    const PAGE_SLUG = 'picot-ai-seo-writer';

    /**
     * Option group
     */
    const OPTION_GROUP = 'picot_seo_writing_options';

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_picot_seo_writing_fetch_gemini_models', [$this, 'ajax_fetch_gemini_models']);
        add_action('wp_ajax_picot_seo_writing_test_connection', [$this, 'ajax_test_connection']);
    }

    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook)
    {
        if ('settings_page_' . self::PAGE_SLUG !== $hook) {
            return;
        }

        wp_enqueue_style(
            'picot-ai-seo-writer-admin-common',
           \PICOT_SEO_WRITING_PLUGIN_URL . 'assets/css/admin-common.css',
            [],
            self::asset_version('assets/css/admin-common.css')
        );

        wp_enqueue_style(
            'picot-ai-seo-writer-settings',
           \PICOT_SEO_WRITING_PLUGIN_URL . 'assets/css/settings-page.css',
            ['picot-ai-seo-writer-admin-common'],
            self::asset_version('assets/css/settings-page.css')
        );

        wp_enqueue_script(
            'picot-ai-seo-writer-admin-settings',
           \PICOT_SEO_WRITING_PLUGIN_URL . 'assets/js/settings-page.js',
            ['jquery'],
            self::asset_version('assets/js/settings-page.js'),
            true
        );

        wp_localize_script('picot-ai-seo-writer-admin-settings', 'picot_seo_writing_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => get_rest_url(null, 'picot-ai-seo-writer/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajax_nonce' => wp_create_nonce('picot_seo_writing_admin_nonce'),
            'model_descriptions' => get_option('picot_seo_writing_gemini_model_descriptions', []),
            'isPaidApiPlan' => Ai_Client_Helper::is_paid_api_plan(),
            'strings' => Admin::get_localized_strings(),
        ]);

        if (!self::is_wizard_view()) {
            return;
        }

        wp_enqueue_style(
            'picot-ai-seo-writer-wizard',
           \PICOT_SEO_WRITING_PLUGIN_URL . 'assets/css/wizard.css',
            ['picot-ai-seo-writer-settings'],
            self::asset_version('assets/css/wizard.css')
        );

        wp_enqueue_script(
            'picot-ai-seo-writer-wizard',
           \PICOT_SEO_WRITING_PLUGIN_URL . 'assets/js/wizard.js',
            ['jquery'],
            self::asset_version('assets/js/wizard.js'),
            true
        );

        wp_localize_script('picot-ai-seo-writer-wizard', 'picot_seo_writing_wizard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('picot_seo_writing_admin_nonce'),
            'model_descriptions' => get_option('picot_seo_writing_gemini_model_descriptions', []),
            'isPaidApiPlan' => Ai_Client_Helper::is_paid_api_plan(),
            'strings' => Admin::get_localized_strings(),
        ]);
    }

    /**
     * File-time based asset version with a plugin version fallback.
     *
     * @param string $relative_path Path relative to the plugin directory.
     * @return string
     */
    private static function asset_version($relative_path)
    {
        $path = \PICOT_SEO_WRITING_PLUGIN_DIR . $relative_path;

        return file_exists($path) ? (string) filemtime($path) : \PICOT_SEO_WRITING_VERSION;
    }

    /**
     * Whether the wizard view is requested.
     *
     * Must stay in sync with render() so assets match the rendered screen.
     *
     * @return bool
     */
    private static function is_wizard_view()
    {
        $view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        return is_string($view) && $view === 'wizard';
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_api_plan', [
            'sanitize_callback' => [Ai_Client_Helper::class, 'sanitize_api_plan'],
            'default'           => 'paid',
        ]);
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_text_model', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_image_model', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_writing_style', [
            'sanitize_callback' => [$this, 'sanitize_writing_style'],
            'default'           => \PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE,
        ]);
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_image_style', [
            'sanitize_callback' => [$this, 'sanitize_image_style'],
            'default'           => 'photorealistic',
        ]);
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_writing_style_detail', ['sanitize_callback' => 'sanitize_textarea_field']);
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_common_prompt', ['sanitize_callback' => 'sanitize_textarea_field']);
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_image_common_prompt', ['sanitize_callback' => 'sanitize_textarea_field']);
    }

    /**
     * Allowed writing style keys.
     *
     * @return string[]
     */
    public static function get_allowed_writing_styles()
    {
        return ['casual', 'professional', 'friendly', 'technical', 'humorous', 'persuasive', 'informative', 'detailed_role'];
    }

    /**
     * Allowed image style keys.
     *
     * @return string[]
     */
    public static function get_allowed_image_styles()
    {
        return ['photorealistic', 'digital_art', 'vector', 'sketch', 'watercolor', 'cyberpunk', 'anime', 'oil_painting'];
    }

    /**
     * Sanitize the writing style option.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    public function sanitize_writing_style($value)
    {
        $value = sanitize_key(is_string($value) ? $value : '');

        return in_array($value, self::get_allowed_writing_styles(), true)
            ? $value
            : \PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE;
    }

    /**
     * Sanitize the image style option.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    public function sanitize_image_style($value)
    {
        $value = sanitize_key(is_string($value) ? $value : '');

        return in_array($value, self::get_allowed_image_styles(), true) ? $value : 'photorealistic';
    }

    /**
     * Output hidden inputs so partial forms do not wipe other options in the group.
     *
     * @param array $rendered Option names already rendered by the form.
     */
    private function render_preserved_settings_fields(array $rendered)
    {
        foreach (self::registered_option_names() as $option) {
            if (in_array($option, $rendered, true)) {
                continue;
            }

            $value = get_option($option, '');
            if (is_array($value) || is_object($value)) {
                continue;
            }

            printf(
                '<input type="hidden" name="%1$s" value="%2$s" />',
                esc_attr($option),
                esc_attr((string) $value)
            );
        }
    }

    /**
     * Option names registered under this plugin's settings group.
     *
     * @return string[]
     */
    private static function registered_option_names()
    {
        return [
            'picot_seo_writing_api_plan',
            'picot_seo_writing_text_model',
            'picot_seo_writing_image_model',
            'picot_seo_writing_writing_style',
            'picot_seo_writing_image_style',
            'picot_seo_writing_writing_style_detail',
            'picot_seo_writing_common_prompt',
            'picot_seo_writing_image_common_prompt',
        ];
    }

    /**
     * Render Gemini API plan selector markup.
     *
     * @param string $select_id HTML id for the select element.
     */
    private function render_api_plan_field($select_id = 'picot_seo_writing_api_plan')
    {
        $current = Ai_Client_Helper::get_api_plan();
        $notice_id = $select_id . '_free_notice';
        ?>
        <select id="<?php echo esc_attr($select_id); ?>" name="picot_seo_writing_api_plan" class="picot-api-plan-select" data-free-notice="<?php echo esc_attr($notice_id); ?>">
            <option value="paid" <?php selected($current, 'paid'); ?>><?php esc_html_e('Paid Gemini API (billing enabled)', 'picot-ai-seo-writer'); ?></option>
            <option value="free" <?php selected($current, 'free'); ?>><?php esc_html_e('Free Gemini API (free tier)', 'picot-ai-seo-writer'); ?></option>
        </select>
        <p class="description">
            <?php esc_html_e('Choose the plan that matches your Google AI API key. On the free tier, Google Search grounding and image generation are disabled in this plugin.', 'picot-ai-seo-writer'); ?>
        </p>
        <p
            id="<?php echo esc_attr($notice_id); ?>"
            class="description picot-api-plan-free-notice"
            style="<?php echo $current === 'free' ? '' : 'display:none;'; ?>"
        >
            <?php esc_html_e('With free API usage, service may become unavailable due to Gemini token limit changes or similar policy updates.', 'picot-ai-seo-writer'); ?>
        </p>
        <script>
        (function () {
            var select = document.getElementById(<?php echo wp_json_encode($select_id); ?>);
            if (!select) {
                return;
            }
            var noticeId = select.getAttribute('data-free-notice');
            var notice = noticeId ? document.getElementById(noticeId) : null;
            if (!notice) {
                return;
            }
            var sync = function () {
                notice.style.display = select.value === 'free' ? '' : 'none';
            };
            select.addEventListener('change', sync);
            sync();
        })();
        </script>
        <?php
    }

    /**
     * Render the settings page
     */
    public function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // キーの移行 (古いキーがあれば新しい項目にコピー) — Api_Settings_Sync でも処理
        Api_Settings_Sync::sync(true);

        $sync_notice = Api_Settings_Sync::consume_admin_notice_message();
        if ($sync_notice !== '') {
            add_settings_error('picot_seo_writing_messages', 'api_sync', $sync_notice, 'updated');
        }

        $view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $view = is_string($view) && $view !== '' ? $view : 'standard';

        if ($view === 'wizard') {
            $this->render_wizard();
            return;
        }

        settings_errors('picot_seo_writing_messages');

        $ai_configured = Ai_Client_Helper::supports_text_generation();
        $ai_settings_url = Ai_Client_Helper::get_settings_url();
        $ai_plugin_active = Ai_Client_Helper::is_ai_plugin_active();
?>
        <div class="wrap picot-settings-page">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <!-- ── WordPress AI 連携カード ── -->
                <div class="picot-settings-card">
                    <div class="picot-card-header">
                        <div class="picot-card-icon icon-gemini">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </div>
                        <div>
                            <p class="picot-card-title"><?php esc_html_e('Google Gemini integration', 'picot-ai-seo-writer'); ?></p>
                            <p class="picot-card-desc"><?php esc_html_e('The Google Gemini connector (Settings → Connectors) must be connected', 'picot-ai-seo-writer'); ?></p>
                        </div>
                    </div>
                    <div class="picot-card-body">
                        <table class="form-table" role="presentation"><tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Connection status', 'picot-ai-seo-writer'); ?></th>
                            <td>
                                <?php if ($ai_configured) : ?>
                                    <p style="margin: 0 0 10px; color: #155724;"><?php esc_html_e('Google Gemini connector is connected and text generation is available.', 'picot-ai-seo-writer'); ?></p>
                                <?php else : ?>
                                    <p style="margin: 0 0 10px; color: #856404;"><?php esc_html_e('Google Gemini connector is not configured or does not support text generation.', 'picot-ai-seo-writer'); ?></p>
                                <?php endif; ?>
                                <a href="<?php echo esc_url($ai_settings_url); ?>" class="button">
                                    <?php esc_html_e('Open AI connector settings', 'picot-ai-seo-writer'); ?>
                                </a>
                                <button type="button" class="button picot-test-connection-btn" data-provider="ai">
                                    <?php esc_html_e('Connection test', 'picot-ai-seo-writer'); ?>
                                </button>
                                <span class="picot-connection-test-result" id="picot-test-result-ai"></span>
                                <p class="description"><?php esc_html_e('This plugin uses the Google Gemini connector. Manage API keys under Settings → Connectors. Requests are sent through the WordPress AI Client.', 'picot-ai-seo-writer'); ?></p>
                                <p class="description">
                                    <?php
                                    printf(
                                        wp_kses(
                                            /* translators: 1: Google AI Studio URL, 2: Gemini API pricing URL */
                                            __('To check free-tier quotas, rate limits, and whether a model is free or paid, open <a href="%1$s" target="_blank" rel="noopener noreferrer">Google AI Studio</a> or the <a href="%2$s" target="_blank" rel="noopener noreferrer">Gemini API pricing</a> page.', 'picot-ai-seo-writer'),
                                            array(
                                                'a' => array(
                                                    'href'   => true,
                                                    'target' => true,
                                                    'rel'    => true,
                                                ),
                                            )
                                        ),
                                        esc_url('https://aistudio.google.com/'),
                                        esc_url('https://ai.google.dev/gemini-api/docs/pricing')
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('WordPress AI plugin', 'picot-ai-seo-writer'); ?></th>
                            <td>
                                <?php if ($ai_plugin_active) : ?>
                                    <p style="margin: 0 0 10px; color: #155724;"><?php esc_html_e('The official WordPress AI plugin is active.', 'picot-ai-seo-writer'); ?></p>
                                <?php else : ?>
                                    <p style="margin: 0 0 10px; color: #856404;"><?php echo esc_html(Ai_Client_Helper::ai_plugin_required_message()); ?></p>
                                    <p style="margin: 0 0 10px;">
                                        <a class="button button-primary" href="<?php echo esc_url(Ai_Client_Helper::get_ai_plugin_action_url()); ?>">
                                            <?php echo esc_html(Ai_Client_Helper::get_ai_plugin_action_label()); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                                <p class="description"><?php esc_html_e('Do this after connecting providers under Settings → Connectors. Also supports the AI plugin\'s experimental Connector Approvals feature.', 'picot-ai-seo-writer'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_api_plan"><?php esc_html_e('Gemini API plan', 'picot-ai-seo-writer'); ?></label></th>
                            <td>
                                <?php $this->render_api_plan_field(); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_text_model"><?php esc_html_e('Text model', 'picot-ai-seo-writer'); ?></label></th>
                            <td>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <select id="picot_seo_writing_text_model" name="picot_seo_writing_text_model">
                                        <?php
                                        $current_model = get_option('picot_seo_writing_text_model', '');
                                        $models = get_option('picot_seo_writing_available_gemini_models', []);
                                        if (!empty($models) && is_array($models)) {
                                            foreach ($models as $id => $label) {
                                                // もし古い形式（単なる配列）なら $id は数値、$label がモデル名
                                                $val = is_numeric($id) ? $label : $id;
                                                printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($current_model, $val, false), esc_html($label));
                                            }
                                        } else {
                                            echo '<option value="">' . esc_html__('No models available. Refresh the model list first.', 'picot-ai-seo-writer') . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button type="button" id="fetch-gemini-models" class="button"><?php esc_html_e('Refresh model list', 'picot-ai-seo-writer'); ?></button>
                                </div>
                                <div id="picot_seo_writing_text_model_description" class="picot-model-description" style="margin-top: 8px; font-size: 12px; color: #666; font-style: italic;"></div>
                                <p class="description picot-recommended-text-model">
                                    <?php 
                                    if (!empty($models) && is_array($models)) {
                                        $recommended_label = Ai_Client_Helper::get_recommended_model_label($models);
                                        /* translators: %s: Recommended model name */
                                        printf(esc_html__('Recommended model: %s', 'picot-ai-seo-writer'), '<strong>' . esc_html($recommended_label) . '</strong>');
                                    } else {
                                        esc_html_e('Click "Refresh model list" to fetch available models.', 'picot-ai-seo-writer');
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_image_model"><?php esc_html_e('Image model', 'picot-ai-seo-writer'); ?></label></th>
                            <td>
                                <select id="picot_seo_writing_image_model" name="picot_seo_writing_image_model">
                                    <?php
                                    $current_img_model = get_option('picot_seo_writing_image_model', '');
                                    $image_models = get_option('picot_seo_writing_available_image_models', []);
                                    
                                    if (!empty($image_models) && is_array($image_models)) {
                                        foreach ($image_models as $id => $label) {
                                            $val = is_numeric($id) ? $label : $id;
                                            printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($current_img_model, $val, false), esc_html($label));
                                        }
                                    } else {
                                        // 最小限のフォールバック
                                        echo '<option value="imagen-3">Imagen 3</option>';
                                    }
                                    ?>
                                </select>
                                <div id="picot_seo_writing_image_model_description" class="picot-model-description" style="margin-top: 8px; font-size: 12px; color: #666; font-style: italic;"></div>
                                <p class="description picot-recommended-image-model">
                                    <?php
                                    if (!empty($image_models) && is_array($image_models)) {
                                        $recommended_image_label = Ai_Client_Helper::get_recommended_model_label($image_models);
                                        /* translators: %s: Recommended image model name */
                                        printf(esc_html__('Recommended image model: %s', 'picot-ai-seo-writer'), '<strong>' . esc_html($recommended_image_label) . '</strong>');
                                    } else {
                                        esc_html_e('Choose the model used for image generation.', 'picot-ai-seo-writer');
                                    }
                                    ?>
                                </p>
                                <p class="description"><?php esc_html_e('Image generation models (such as Imagen) require a paid Google AI API plan with billing enabled. Image generation may not work with API keys that only include the free tier.', 'picot-ai-seo-writer'); ?></p>
                                <?php if (!Ai_Client_Helper::is_paid_api_plan()) : ?>
                                    <p class="description" style="color: #856404;">
                                        <?php esc_html_e('Free Gemini API plan is selected. Image generation is disabled until you switch to the paid plan setting.', 'picot-ai-seo-writer'); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>
                </div>

                <!-- ── 執筆設定カード ── -->
                <div class="picot-settings-card">
                    <div class="picot-card-header">
                        <div class="picot-card-icon icon-settings">
                            <span class="dashicons dashicons-admin-settings"></span>
                        </div>
                        <div>
                            <p class="picot-card-title"><?php esc_html_e('Writing style settings', 'picot-ai-seo-writer'); ?></p>
                            <p class="picot-card-desc"><?php esc_html_e('Configure the tone of generated content', 'picot-ai-seo-writer'); ?></p>
                        </div>
                    </div>
                    <div class="picot-card-body">
                        <table class="form-table" role="presentation"><tbody>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_writing_style"><?php esc_html_e('Writing style', 'picot-ai-seo-writer'); ?></label></th>
                            <td>
                                <select id="picot_seo_writing_writing_style" name="picot_seo_writing_writing_style">
                                    <?php
                                    $current_style = get_option('picot_seo_writing_writing_style', PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE);
                                    $styles = [
                                        'casual' => __('Casual (friendly)', 'picot-ai-seo-writer'),
                                        'professional' => __('Professional (polished and trustworthy)', 'picot-ai-seo-writer'),
                                        'friendly' => __('Friendly (warm)', 'picot-ai-seo-writer'),
                                        'technical' => __('Technical (precise)', 'picot-ai-seo-writer'),
                                        'humorous' => __('Humorous (light and fun)', 'picot-ai-seo-writer'),
                                        'persuasive' => __('Persuasive (passionate)', 'picot-ai-seo-writer'),
                                        'informative' => __('Informative (factual)', 'picot-ai-seo-writer'),
                                        'detailed_role' => __('Use detailed role settings', 'picot-ai-seo-writer'),
                                    ];
                                    foreach ($styles as $key => $label) {
                                        printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($current_style, $key, false), esc_html($label));
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_image_style"><?php esc_html_e('Image style', 'picot-ai-seo-writer'); ?></label></th>
                            <td>
                                <select id="picot_seo_writing_image_style" name="picot_seo_writing_image_style">
                                    <?php
                                    $current_img_style = get_option('picot_seo_writing_image_style', 'photorealistic');
                                    $img_styles = [
                                        'photorealistic' => __('Photorealistic', 'picot-ai-seo-writer'),
                                        'digital_art' => __('Digital art', 'picot-ai-seo-writer'),
                                        'vector' => __('Vector illustration', 'picot-ai-seo-writer'),
                                        'sketch' => __('Sketch', 'picot-ai-seo-writer'),
                                        'watercolor' => __('Watercolor', 'picot-ai-seo-writer'),
                                        'cyberpunk' => __('Cyberpunk', 'picot-ai-seo-writer'),
                                        'anime' => __('Anime', 'picot-ai-seo-writer'),
                                        'oil_painting' => __('Oil painting', 'picot-ai-seo-writer'),
                                    ];
                                    foreach ($img_styles as $key => $label) {
                                        printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($current_img_style, $key, false), esc_html($label));
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e('Choose the tone for images inserted into articles.', 'picot-ai-seo-writer'); ?></p>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>
                </div>

                <!-- ── 詳細設定（折りたたみ） ── -->
                <details class="picot-settings-card picot-settings-details">
                    <summary class="picot-card-header picot-details-summary">
                        <div class="picot-card-icon icon-settings">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </div>
                        <div class="picot-details-summary-text">
                            <p class="picot-card-title"><?php esc_html_e('Advanced settings', 'picot-ai-seo-writer'); ?></p>
                            <p class="picot-card-desc"><?php esc_html_e('Optional detailed instructions for content generation', 'picot-ai-seo-writer'); ?></p>
                        </div>
                        <span class="picot-details-toggle dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                    </summary>
                    <div class="picot-card-body">
                        <table class="form-table" role="presentation"><tbody>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_writing_style_detail"><?php echo wp_kses(__('Role settings<br>(Writing style details)', 'picot-ai-seo-writer'), ['br' => []]); ?></label></th>
                            <td>
                                <?php
                                $writing_style_detail = get_option('picot_seo_writing_writing_style_detail', '');
                                ?>
                                <textarea
                                    id="picot_seo_writing_writing_style_detail"
                                    name="picot_seo_writing_writing_style_detail"
                                    rows="8"
                                    class="large-text"
                                    placeholder="<?php echo esc_attr__('Example: Use short sentences. Prefer concrete examples. Avoid jargon. Address the reader as “you”.', 'picot-ai-seo-writer'); ?>"
                                ><?php echo esc_textarea($writing_style_detail); ?></textarea>
                                <p class="description"><?php esc_html_e('Describe the writing style in detail. These instructions are added to the generation prompt.', 'picot-ai-seo-writer'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_common_prompt"><?php esc_html_e('Common article generation prompt', 'picot-ai-seo-writer'); ?></label></th>
                            <td>
                                <?php
                                $common_prompt = get_option('picot_seo_writing_common_prompt', '');
                                ?>
                                <textarea
                                    id="picot_seo_writing_common_prompt"
                                    name="picot_seo_writing_common_prompt"
                                    rows="8"
                                    class="large-text"
                                    placeholder="<?php echo esc_attr__('Example: Always include practical tips. Prefer bullet lists for steps. Do not invent statistics.', 'picot-ai-seo-writer'); ?>"
                                ><?php echo esc_textarea($common_prompt); ?></textarea>
                                <p class="description"><?php esc_html_e('These instructions are always added to article generation prompts.', 'picot-ai-seo-writer'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_image_common_prompt"><?php esc_html_e('Shared image prompt', 'picot-ai-seo-writer'); ?></label></th>
                            <td>
                                <?php
                                $image_common_prompt = get_option('picot_seo_writing_image_common_prompt', '');
                                ?>
                                <textarea
                                    id="picot_seo_writing_image_common_prompt"
                                    name="picot_seo_writing_image_common_prompt"
                                    rows="8"
                                    class="large-text"
                                    placeholder="<?php echo esc_attr__('Example: Clean composition, no text in the image, natural lighting, blog-ready illustration.', 'picot-ai-seo-writer'); ?>"
                                ><?php echo esc_textarea($image_common_prompt); ?></textarea>
                                <p class="description"><?php esc_html_e('These instructions are always added to image generation prompts.', 'picot-ai-seo-writer'); ?></p>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>
                </details>

                <div class="picot-settings-actions">
                    <?php submit_button(__('Save settings', 'picot-ai-seo-writer'), 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
<?php
    }

    /**
     * Render Wizard
     */
    public function render_wizard()
    {
        $ai_configured = Ai_Client_Helper::supports_text_generation();
        $ai_settings_url = Ai_Client_Helper::get_settings_url();
?>
        <div class="picot-wizard-wrap">
            <div class="picot-wizard-container">
                <div class="picot-wizard-header">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 20px;">
                        <div class="picot-wizard-logo">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L14.85 9.15L22 12L14.85 14.85L12 22L9.15 14.85L2 12L9.15 9.15L12 2Z" fill="white" />
                                <path d="M12 6L13.1 8.9L16 10L13.1 11.1L12 14L10.9 11.1L8 10L10.9 8.9L12 6Z" fill="#8E75FF" />
                            </svg>
                        </div>
                        <h2 style="margin:0;"><?php esc_html_e('Google Gemini setup', 'picot-ai-seo-writer'); ?></h2>
                    </div>
                    <div class="picot-wizard-steps">
                        <div class="picot-wizard-step-indicator active" data-step="0">1</div>
                        <div class="picot-wizard-step-line"></div>
                        <div class="picot-wizard-step-indicator" data-step="1">2</div>
                    </div>
                </div>

                <form id="picot-wizard-form" method="post" action="options.php">
                    <?php settings_fields(self::OPTION_GROUP); ?>
                    <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url(admin_url('options-general.php?page=' . self::PAGE_SLUG . '&settings-updated=true')); ?>" />
                    <?php
                    // options.php はグループ内の全設定を書き戻すため、ウィザードで扱わない項目は現在値を保持する。
                    $this->render_preserved_settings_fields([
                        'picot_seo_writing_api_plan',
                        'picot_seo_writing_text_model',
                        'picot_seo_writing_image_model',
                    ]);
                    ?>
                    
                    <div class="picot-wizard-content">
                        <!-- Step 1: WordPress AI -->
                        <div class="picot-wizard-screen active" data-step-id="ai_setup">
                        <h3><?php esc_html_e('1. Configure the Google Gemini connector', 'picot-ai-seo-writer'); ?></h3>
                        <p><?php esc_html_e('Install and activate the Google (Gemini) connector under Settings → Connectors, then connect your API key. This plugin uses Gemini.', 'picot-ai-seo-writer'); ?></p>
                        <?php if (!Ai_Client_Helper::is_ai_plugin_active()) : ?>
                            <div class="notice notice-warning inline" style="margin: 12px 0;">
                                <p><?php echo esc_html(Ai_Client_Helper::ai_plugin_required_message()); ?></p>
                                <p>
                                    <a class="button button-primary" href="<?php echo esc_url(Ai_Client_Helper::get_ai_plugin_action_url()); ?>">
                                        <?php echo esc_html(Ai_Client_Helper::get_ai_plugin_action_label()); ?>
                                    </a>
                                </p>
                            </div>
                        <?php endif; ?>
                        <p class="description">
                            <?php
                            printf(
                                wp_kses(
                                    /* translators: 1: Google AI Studio URL, 2: Gemini API pricing URL */
                                    __('To check free-tier quotas, rate limits, and whether a model is free or paid, open <a href="%1$s" target="_blank" rel="noopener noreferrer">Google AI Studio</a> or the <a href="%2$s" target="_blank" rel="noopener noreferrer">Gemini API pricing</a> page.', 'picot-ai-seo-writer'),
                                    array(
                                        'a' => array(
                                            'href'   => true,
                                            'target' => true,
                                            'rel'    => true,
                                        ),
                                    )
                                ),
                                esc_url('https://aistudio.google.com/'),
                                esc_url('https://ai.google.dev/gemini-api/docs/pricing')
                            );
                            ?>
                        </p>

                        <div style="margin: 15px 0;">
                            <a href="<?php echo esc_url($ai_settings_url); ?>" class="button">
                                <?php esc_html_e('Open AI connector settings', 'picot-ai-seo-writer'); ?>
                            </a>
                        </div>

                        <p>
                            <label for="picot_seo_writing_api_plan_wizard"><strong><?php esc_html_e('Gemini API plan', 'picot-ai-seo-writer'); ?></strong></label>
                        </p>
                        <?php $this->render_api_plan_field('picot_seo_writing_api_plan_wizard'); ?>

                        <p style="margin-top: 10px;">
                            <?php if ($ai_configured) : ?>
                                <?php esc_html_e('Google Gemini connector is connected and text generation is available.', 'picot-ai-seo-writer'); ?>
                            <?php else : ?>
                                <?php esc_html_e('After connecting Gemini, run the connection test before continuing.', 'picot-ai-seo-writer'); ?>
                            <?php endif; ?>
                        </p>

                        <button type="button" class="button picot-test-connection-btn" data-provider="ai">
                            <?php esc_html_e('Connection test', 'picot-ai-seo-writer'); ?>
                        </button>
                        <div id="picot-wizard-test-result" style="margin-top: 10px;"></div>
                    </div>

                    <!-- Step 2: Model selection -->
                    <div class="picot-wizard-screen" data-step-id="model_selection" style="display:none;">
                        <h3><?php esc_html_e('2. Choose models', 'picot-ai-seo-writer'); ?></h3>
                        <p><?php esc_html_e('Choose the AI model used for writing.', 'picot-ai-seo-writer'); ?></p>
                        
                        <div class="picot-wizard-field">
                            <label for="picot_seo_writing_text_model"><?php esc_html_e('Text model', 'picot-ai-seo-writer'); ?></label>
                            <div style="display: flex; gap: 10px; flex-direction: column;">
                                <div style="display: flex; gap: 10px;">
                                    <select id="picot_seo_writing_text_model" name="picot_seo_writing_text_model" style="flex: 1;">
                                        <?php
                                        $current_model = get_option('picot_seo_writing_text_model', '');
                                        $models = get_option('picot_seo_writing_available_gemini_models', []);
                                        if (!empty($models) && is_array($models)) {
                                            foreach ($models as $id => $label) {
                                                $val = is_numeric($id) ? $label : $id;
                                                printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($current_model, $val, false), esc_html($label));
                                            }
                                        } else {
                                            echo '<option value="">' . esc_html__('No Gemini models available. Configure the Google Gemini connector, then click Refresh.', 'picot-ai-seo-writer') . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button type="button" id="picot-wizard-fetch-models" class="button"><?php esc_html_e('Refresh', 'picot-ai-seo-writer'); ?></button>
                                </div>
                                <div id="picot_seo_writing_text_model_description" class="picot-model-description" style="margin-top: 8px; font-size: 12px; color: #666; font-style: italic;"></div>
                            </div>
                        </div>

                        <p class="description picot-recommended-text-model" style="margin-top: 15px; margin-bottom: 20px;">
                            <?php 
                            if (!empty($models)) {
                                $recommended_label = Ai_Client_Helper::get_recommended_model_label($models);
                                /* translators: %s: Recommended model name */
                                printf(esc_html__('Recommended text model: %s', 'picot-ai-seo-writer'), '<strong>' . esc_html($recommended_label) . '</strong>');
                            }
                            ?>
                        </p>

                        <div class="picot-wizard-field">
                            <label for="picot_seo_writing_image_model"><?php esc_html_e('Image model', 'picot-ai-seo-writer'); ?></label>
                            <select name="picot_seo_writing_image_model" id="picot_seo_writing_image_model" style="width: 100%;">
                                <?php
                                $current_img_model = get_option('picot_seo_writing_image_model', '');
                                $image_models = get_option('picot_seo_writing_available_image_models', []);
                                if (!empty($image_models) && is_array($image_models)) {
                                    foreach ($image_models as $id => $label) {
                                        $val = is_numeric($id) ? $label : $id;
                                        printf('<option value="%s" %s>%s</option>', esc_attr($val), selected($current_img_model, $val, false), esc_html($label));
                                    }
                                } else {
                                    echo '<option value="">' . esc_html__('No models available.', 'picot-ai-seo-writer') . '</option>';
                                }
                                ?>
                            </select>
                            <div id="picot_seo_writing_image_model_description" class="picot-model-description" style="margin-top: 8px; font-size: 12px; color: #666; font-style: italic;"></div>
                            <p class="description picot-recommended-image-model">
                                <?php
                                if (!empty($image_models) && is_array($image_models)) {
                                    $recommended_image_label = Ai_Client_Helper::get_recommended_model_label($image_models);
                                    /* translators: %s: Recommended image model name */
                                    printf(esc_html__('Recommended image model: %s', 'picot-ai-seo-writer'), '<strong>' . esc_html($recommended_image_label) . '</strong>');
                                }
                                ?>
                            </p>
                            <p class="description"><?php esc_html_e('Image generation models (such as Imagen) require a paid Google AI API plan with billing enabled. Image generation may not work with API keys that only include the free tier.', 'picot-ai-seo-writer'); ?></p>
                        </div>
                    </div>

                    <div class="picot-wizard-footer">
                        <button type="button" id="picot-prev-btn" class="button" style="display:none;"><?php esc_html_e('Back', 'picot-ai-seo-writer'); ?></button>
                        <div style="flex-grow: 1;"></div>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::PAGE_SLUG)); ?>" class="button button-link"><?php esc_html_e('Cancel', 'picot-ai-seo-writer'); ?></a>
                        <button type="button" id="picot-next-btn" class="button button-primary"><?php esc_html_e('Next', 'picot-ai-seo-writer'); ?></button>
                    </div>
                </form>
            </div>
        </div>
<?php
    }

    /**
     * AJAX: Fetch available AI models from WordPress AI Client.
     */
    public function ajax_fetch_gemini_models()
    {
        check_ajax_referer('picot_seo_writing_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'picot-ai-seo-writer')]);
        }

        if (!Ai_Client_Helper::is_ready()) {
            wp_send_json_error(['message' => Ai_Client_Helper::readiness_error_message()]);
        }

        try {
            $manager = new Model_Manager();
            $text_items = $manager->list_models();
            $image_items = $manager->list_image_models();
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => Ai_Client_Helper::localize_api_error_message($e->getMessage())]);
        }

        $text_models = [];
        foreach ($text_items as $item) {
            $text_models[$item['id']] = $item['name'];
        }

        $image_models = [];
        foreach ($image_items as $item) {
            $image_models[$item['id']] = $item['name'];
        }

        if (empty($text_models) && empty($image_models)) {
            wp_send_json_error(['message' => __('No Gemini models were found. Check the Google Gemini connector connection.', 'picot-ai-seo-writer')]);
        }

        update_option('picot_seo_writing_available_gemini_models', $text_models);
        update_option('picot_seo_writing_available_image_models', $image_models);

        wp_send_json_success([
            'models' => $text_models,
            'image_models' => $image_models,
            'descriptions' => [],
        ]);
    }

    /**
     * AJAX: Test WordPress AI Client connection.
     */
    public function ajax_test_connection()
    {
        check_ajax_referer('picot_seo_writing_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'picot-ai-seo-writer')]);
        }

        if (!Ai_Client_Helper::is_ready()) {
            wp_send_json_error(['message' => Ai_Client_Helper::readiness_error_message()]);
        }

        try {
            $builder = Ai_Client_Helper::create_google_prompt_builder(__('Hello', 'picot-ai-seo-writer'));
            if (!$builder || !$builder->is_supported_for_text_generation()) {
                wp_send_json_error(['message' => __('Google Gemini connector is not configured. Connect Gemini under Settings → Connectors.', 'picot-ai-seo-writer')]);
            }

            $result = $builder->generate_text();
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => Ai_Client_Helper::localize_api_error_message($e->getMessage())]);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => Ai_Client_Helper::localize_api_error_message($result->get_error_message())]);
        }

        wp_send_json_success(['message' => __('Successfully connected to the Google Gemini connector.', 'picot-ai-seo-writer')]);
    }
}
