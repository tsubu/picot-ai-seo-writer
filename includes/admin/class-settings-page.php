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
           \PICOT_SEO_WRITING_VERSION
        );

        wp_enqueue_script(
            'picot-ai-seo-writer-admin-settings',
           \PICOT_SEO_WRITING_PLUGIN_URL . 'assets/js/settings-page.js',
            ['jquery'],
           \PICOT_SEO_WRITING_VERSION,
            true
        );

        wp_localize_script('picot-ai-seo-writer-admin-settings', 'picot_seo_writing_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => get_rest_url(null, 'picot-ai-seo-writer/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'ajax_nonce' => wp_create_nonce('picot_seo_writing_admin_nonce'),
            'model_descriptions' => get_option('picot_seo_writing_gemini_model_descriptions', []),
            'strings' => Admin::get_localized_strings(),
        ]);
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_text_model', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_image_model', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_writing_style', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_image_style', ['sanitize_callback' => 'sanitize_text_field']);
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
                                <p class="description">
                                    <?php 
                                    if (!empty($models) && is_array($models)) {
                                        $first_label = reset($models);
                                        /* translators: %s: Recommended model name */
                                        printf(esc_html__('Recommended model: %s', 'picot-ai-seo-writer'), '<strong>' . esc_html($first_label) . '</strong>');
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
                                <p class="description"><?php esc_html_e('Choose the model used for image generation.', 'picot-ai-seo-writer'); ?></p>
                                <p class="description"><?php esc_html_e('Image generation models (such as Imagen) require a paid Google AI API plan with billing enabled. Image generation may not work with API keys that only include the free tier.', 'picot-ai-seo-writer'); ?></p>
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
                    
                    <div class="picot-wizard-content">
                        <!-- Step 1: WordPress AI -->
                        <div class="picot-wizard-screen active" data-step-id="ai_setup">
                        <h3><?php esc_html_e('1. Configure the Google Gemini connector', 'picot-ai-seo-writer'); ?></h3>
                        <p><?php esc_html_e('Install and activate the Google (Gemini) connector under Settings → Connectors, then connect your API key. This plugin uses Gemini.', 'picot-ai-seo-writer'); ?></p>

                        <div style="margin: 15px 0;">
                            <a href="<?php echo esc_url($ai_settings_url); ?>" class="button">
                                <?php esc_html_e('Open AI connector settings', 'picot-ai-seo-writer'); ?>
                            </a>
                        </div>

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

                        <p class="description" style="margin-top: 15px; margin-bottom: 20px;">
                            <?php 
                            if (!empty($models)) {
                                $first_label = reset($models);
                                /* translators: %s: Recommended model name */
                                printf(esc_html__('Recommended text model: %s', 'picot-ai-seo-writer'), '<strong>' . esc_html($first_label) . '</strong>');
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

        if (!Ai_Client_Helper::is_available()) {
            wp_send_json_error(['message' => __('WordPress AI Client is unavailable. Install the Google Gemini connector.', 'picot-ai-seo-writer')]);
        }

        try {
            $manager = new Model_Manager();
            $text_items = $manager->list_models();
            $image_items = $manager->list_image_models();
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
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

        if (!Ai_Client_Helper::is_available()) {
            wp_send_json_error(['message' => __('WordPress AI Client is unavailable. Install the Google Gemini connector.', 'picot-ai-seo-writer')]);
        }

        $builder = Ai_Client_Helper::create_google_prompt_builder(__('Hello', 'picot-ai-seo-writer'));
        if (!$builder || !$builder->is_supported_for_text_generation()) {
            wp_send_json_error(['message' => __('Google Gemini connector is not configured. Connect Gemini under Settings → Connectors.', 'picot-ai-seo-writer')]);
        }

        $result = $builder->generate_text();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Successfully connected to the Google Gemini connector.', 'picot-ai-seo-writer')]);
    }
}
