<?php
/**
 * Settings Page Class - Gemini Edition
 *
 * @package PicotSEOWriting
 */

namespace PICOT_SEO_WRITING\Admin;

use PICOT_SEO_WRITING\Api_Settings_Sync;

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
            'strings' => [
                'fetching' => __('取得中...', 'picot-ai-seo-writer'),
                'updateSuccess' => __('モデル一覧を更新しました', 'picot-ai-seo-writer'),
                'updateFailed' => __('モデル一覧の取得に失敗しました', 'picot-ai-seo-writer'),
            ],
        ]);
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting(self::OPTION_GROUP, 'picot_seo_writing_gemini_api_key', ['sanitize_callback' => 'sanitize_text_field']);
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

        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'standard';

        if ($view === 'wizard') {
            $this->render_wizard();
            return;
        }

        // キー破棄処理
        if (isset($_POST['picot_seo_writing_clear_key']) && check_admin_referer('picot-ai-seo-writer-options-options')) {
            delete_option('picot_seo_writing_gemini_api_key');
            wp_safe_redirect(add_query_arg('picot-ai-seo-writer-key-cleared', '1', admin_url('options-general.php?page=' . self::PAGE_SLUG)));
            exit;
        }

        if (isset($_GET['picot-ai-seo-writer-key-cleared'])) {
            add_settings_error('picot_seo_writing_messages', 'key_cleared', __('APIキーを破棄しました。', 'picot-ai-seo-writer'), 'updated');
        }

        settings_errors('picot_seo_writing_messages');

        $gemini_key = get_option('picot_seo_writing_gemini_api_key', '');
        $has_key = !empty($gemini_key);
?>
        <div class="wrap picot-settings-page">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <!-- ── Gemini連携カード ── -->
                <div class="picot-settings-card">
                    <div class="picot-card-header">
                        <div class="picot-card-icon icon-gemini">
                            <span class="dashicons dashicons-rest-api"></span>
                        </div>
                        <div>
                            <p class="picot-card-title"><?php esc_html_e('Google Gemini 連携', 'picot-ai-seo-writer'); ?></p>
                            <p class="picot-card-desc"><?php esc_html_e('Googleの最新AIによる執筆と検索機能の設定', 'picot-ai-seo-writer'); ?></p>
                        </div>
                    </div>
                    <div class="picot-card-body">
                        <table class="form-table" role="presentation"><tbody>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_gemini_api_key"><?php esc_html_e('APIキー', 'picot-ai-seo-writer'); ?></label></th>
                            <td>
                                <div class="picot-field-with-guide">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <input type="password" id="picot_seo_writing_gemini_api_key" name="picot_seo_writing_gemini_api_key" 
                                               value="<?php echo esc_attr($gemini_key); ?>" 
                                               placeholder="AIza...." class="regular-text picot-hover-show" autocomplete="new-password">
                                        <a href="<?php echo esc_url(add_query_arg(['view' => 'wizard'], admin_url('options-general.php?page=' . self::PAGE_SLUG))); ?>" class="picot-guide-btn">
                                            <?php esc_html_e('取得ガイド', 'picot-ai-seo-writer'); ?>
                                        </a>
                                        <button type="button" class="button picot-test-connection-btn" data-provider="gemini">
                                            <?php esc_html_e('接続テスト', 'picot-ai-seo-writer'); ?>
                                        </button>
                                        <span class="picot-connection-test-result" id="picot-test-result-gemini"></span>
                                    </div>
                                </div>
                                <p class="description"><?php esc_html_e('Google AI Studioで取得したAPIキーを入力してください。', 'picot-ai-seo-writer'); ?></p>
                                <?php if (Api_Settings_Sync::has_external_api_key_source() && empty($gemini_key)) : ?>
                                    <p class="description"><?php esc_html_e('Picot AIO AI Content Optimizer や WordPress AI（Google コネクター）など、他の AI プラグインで Gemini API キーが設定済みの場合、未設定時に自動で引き継ぎます。', 'picot-ai-seo-writer'); ?></p>
                                <?php endif; ?>
                                <?php if ($has_key) : ?>
                                    <div style="margin-top: 10px;">
                                        <button type="submit" name="picot_seo_writing_clear_key" value="1" class="button button-secondary" onclick="return confirm('APIキーを破棄しますか？');">
                                            <?php esc_html_e('APIキーを破棄する', 'picot-ai-seo-writer'); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_text_model"><?php esc_html_e('テキストモデル', 'picot-ai-seo-writer'); ?></label></th>
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
                                            echo '<option value="">' . esc_html__('利用可能なモデルがありません。まずモデル一覧を更新してください。', 'picot-ai-seo-writer') . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button type="button" id="fetch-gemini-models" class="button"><?php esc_html_e('モデル一覧を更新', 'picot-ai-seo-writer'); ?></button>
                                </div>
                                <div id="picot_seo_writing_text_model_description" class="picot-model-description" style="margin-top: 8px; font-size: 12px; color: #666; font-style: italic;"></div>
                                <p class="description">
                                    <?php 
                                    if (!empty($models) && is_array($models)) {
                                        $first_label = reset($models);
                                        /* translators: %s: Recommended model name */
                                        printf(esc_html__('推奨モデル: %s', 'picot-ai-seo-writer'), '<strong>' . esc_html($first_label) . '</strong>');
                                    } else {
                                        esc_html_e('「モデル一覧を更新」をクリックして、利用可能なモデルを取得してください。', 'picot-ai-seo-writer');
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_image_model"><?php esc_html_e('画像生成モデル', 'picot-ai-seo-writer'); ?></label></th>
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
                                <p class="description"><?php esc_html_e('画像生成に使用するモデルを選択します。', 'picot-ai-seo-writer'); ?></p>
                                <p class="description"><?php esc_html_e('画像生成モデル（Imagen など）の利用には、Google AI の有料 API プラン（課金設定済み）が必要です。無料枠のみの API キーでは画像生成が利用できない場合があります。', 'picot-ai-seo-writer'); ?></p>
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
                            <p class="picot-card-title"><?php esc_html_e('執筆スタイル設定', 'picot-ai-seo-writer'); ?></p>
                            <p class="picot-card-desc"><?php esc_html_e('生成される文章のトーンなどを設定', 'picot-ai-seo-writer'); ?></p>
                        </div>
                    </div>
                    <div class="picot-card-body">
                        <table class="form-table" role="presentation"><tbody>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_writing_style"><?php esc_html_e('文章スタイル', 'picot-ai-seo-writer'); ?></label></th>
                            <td>
                                <select id="picot_seo_writing_writing_style" name="picot_seo_writing_writing_style">
                                    <?php
                                    $current_style = get_option('picot_seo_writing_writing_style', PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE);
                                    $styles = [
                                        'casual' => __('カジュアル (親しみやすい)', 'picot-ai-seo-writer'),
                                        'professional' => __('プロフェッショナル (丁寧・信頼)', 'picot-ai-seo-writer'),
                                        'friendly' => __('フレンドリー (温かみ)', 'picot-ai-seo-writer'),
                                        'technical' => __('テクニカル (専門・正確)', 'picot-ai-seo-writer'),
                                        'humorous' => __('ユーモア (軽快・面白い)', 'picot-ai-seo-writer'),
                                        'persuasive' => __('説得力 (情熱・主張)', 'picot-ai-seo-writer'),
                                        'informative' => __('インフォマティブ (事実・解説)', 'picot-ai-seo-writer'),
                                    ];
                                    foreach ($styles as $key => $label) {
                                        printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($current_style, $key, false), esc_html($label));
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="picot_seo_writing_image_style"><?php esc_html_e('画像スタイル', 'picot-ai-seo-writer'); ?></label></th>
                            <td>
                                <select id="picot_seo_writing_image_style" name="picot_seo_writing_image_style">
                                    <?php
                                    $current_img_style = get_option('picot_seo_writing_image_style', 'photorealistic');
                                    $img_styles = [
                                        'photorealistic' => __('実写風 (Photorealistic)', 'picot-ai-seo-writer'),
                                        'digital_art' => __('デジタルアート', 'picot-ai-seo-writer'),
                                        'vector' => __('ベクターイラスト', 'picot-ai-seo-writer'),
                                        'sketch' => __('スケッチ風', 'picot-ai-seo-writer'),
                                        'watercolor' => __('水彩画風', 'picot-ai-seo-writer'),
                                        'cyberpunk' => __('サイバーパンク', 'picot-ai-seo-writer'),
                                        'anime' => __('アニメ風', 'picot-ai-seo-writer'),
                                        'oil_painting' => __('油絵風', 'picot-ai-seo-writer'),
                                    ];
                                    foreach ($img_styles as $key => $label) {
                                        printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($current_img_style, $key, false), esc_html($label));
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e('記事内に挿入される画像のトーンを指定します。', 'picot-ai-seo-writer'); ?></p>
                            </td>
                        </tr>
                        </tbody></table>
                    </div>
                </div>

                <div class="picot-settings-actions">
                    <?php submit_button(__('設定を保存', 'picot-ai-seo-writer'), 'primary', 'submit', false); ?>
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
        $gemini_key = get_option('picot_seo_writing_gemini_api_key', '');
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
                        <h2 style="margin:0;"><?php esc_html_e('Gemini 連携セットアップ', 'picot-ai-seo-writer'); ?></h2>
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
                        <!-- Step 1: API Key -->
                        <div class="picot-wizard-screen active" data-step-id="api_key">
                        <h3><?php esc_html_e('1. Gemini APIキーを設定', 'picot-ai-seo-writer'); ?></h3>
                        <p><?php esc_html_e('Google AI Studioから無料でAPIキーを取得し、以下に貼り付けてください。', 'picot-ai-seo-writer'); ?></p>
                        
                        <div style="margin: 15px 0;">
                            <a href="https://aistudio.google.com/app/apikey" target="_blank" class="button">
                                <span class="dashicons dashicons-external" style="margin-top: 4px;"></span>
                                <?php esc_html_e('APIキーを取得する (Google AI Studio)', 'picot-ai-seo-writer'); ?>
                            </a>
                        </div>

                        <div class="picot-wizard-field">
                            <label for="picot_seo_writing_gemini_api_key"><?php esc_html_e('APIキー', 'picot-ai-seo-writer'); ?></label>
                            <input type="password" id="picot_seo_writing_gemini_api_key" name="picot_seo_writing_gemini_api_key" 
                                   value="<?php echo esc_attr($gemini_key); ?>" class="widefat picot-hover-show" autocomplete="new-password">
                        </div>
                        
                        <div id="picot-wizard-test-result" style="margin-top: 10px;"></div>
                    </div>

                    <!-- Step 2: Model selection -->
                    <div class="picot-wizard-screen" data-step-id="model_selection" style="display:none;">
                        <h3><?php esc_html_e('2. 使用するモデルを選択', 'picot-ai-seo-writer'); ?></h3>
                        <p><?php esc_html_e('執筆に使用するGeminiモデルを選択してください。', 'picot-ai-seo-writer'); ?></p>
                        
                        <div class="picot-wizard-field">
                            <label for="picot_seo_writing_text_model"><?php esc_html_e('テキストモデル', 'picot-ai-seo-writer'); ?></label>
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
                                            echo '<option value="">' . esc_html__('利用可能なモデルがありません。まずAPIキーを入力してください。', 'picot-ai-seo-writer') . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button type="button" id="picot-wizard-fetch-models" class="button"><?php esc_html_e('再取得', 'picot-ai-seo-writer'); ?></button>
                                </div>
                                <div id="picot_seo_writing_text_model_description" class="picot-model-description" style="margin-top: 8px; font-size: 12px; color: #666; font-style: italic;"></div>
                            </div>
                        </div>

                        <p class="description" style="margin-top: 15px; margin-bottom: 20px;">
                            <?php 
                            if (!empty($models)) {
                                $first_label = reset($models);
                                /* translators: %s: Recommended model name */
                                printf(esc_html__('推奨テキストモデル: %s', 'picot-ai-seo-writer'), '<strong>' . esc_html($first_label) . '</strong>');
                            }
                            ?>
                        </p>

                        <div class="picot-wizard-field">
                            <label for="picot_seo_writing_image_model"><?php esc_html_e('画像生成モデル', 'picot-ai-seo-writer'); ?></label>
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
                                    echo '<option value="">' . esc_html__('利用可能なモデルがありません。', 'picot-ai-seo-writer') . '</option>';
                                }
                                ?>
                            </select>
                            <div id="picot_seo_writing_image_model_description" class="picot-model-description" style="margin-top: 8px; font-size: 12px; color: #666; font-style: italic;"></div>
                            <p class="description"><?php esc_html_e('画像生成モデル（Imagen など）の利用には、Google AI の有料 API プラン（課金設定済み）が必要です。無料枠のみの API キーでは画像生成が利用できない場合があります。', 'picot-ai-seo-writer'); ?></p>
                        </div>
                    </div>

                    <div class="picot-wizard-footer">
                        <button type="button" id="picot-prev-btn" class="button" style="display:none;"><?php esc_html_e('戻る', 'picot-ai-seo-writer'); ?></button>
                        <div style="flex-grow: 1;"></div>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=' . self::PAGE_SLUG)); ?>" class="button button-link"><?php esc_html_e('キャンセル', 'picot-ai-seo-writer'); ?></a>
                        <button type="button" id="picot-next-btn" class="button button-primary"><?php esc_html_e('次へ進む', 'picot-ai-seo-writer'); ?></button>
                    </div>
                </form>
            </div>
        </div>
<?php
    }

    /**
     * AJAX: Fetch Gemini Models
     */
    public function ajax_fetch_gemini_models()
    {
        check_ajax_referer('picot_seo_writing_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('この操作を実行する権限がありません。', 'picot-ai-seo-writer')]);
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : get_option('picot_seo_writing_gemini_api_key');

        if (empty($api_key)) {
            wp_send_json_error(['message' => __('APIキーが必要です。', 'picot-ai-seo-writer')]);
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;
        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('通信エラーが発生しました。', 'picot-ai-seo-writer')]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            wp_send_json_error(['message' => $body['error']['message']]);
        }

        $models = [];
        $text_models = [];
        $image_models = [];
        $model_descriptions = [];

        if (isset($body['models'])) {
            foreach ($body['models'] as $model) {
                $id = str_replace('models/', '', $model['name']);
                $display_name = isset($model['displayName']) ? $model['displayName'] : $id;
                $description = isset($model['description']) ? $model['description'] : '';
                
                // 通称 (正式名) の形式で表示名を組み立て
                $full_label = ($display_name !== $id) ? "{$display_name} ({$id})" : $id;
                
                // 判定用の文字列（名前と表示名を結合）
                $check_str = $id . ' ' . $display_name;

                // 画像モデル判定 (Imagen, Banana, nano-系, image-preview系)
                if (preg_match('/(imagen|banana|nano-|image-preview)/i', $check_str)) {
                    $image_models[$id] = $full_label;
                } else {
                    // それ以外はすべてテキストモデルとして扱う
                    $text_models[$id] = $full_label;
                }
                
                // 説明文を保存
                $model_descriptions[$id] = $description;
            }
        }

        if (empty($text_models) && empty($image_models)) {
            wp_send_json_error(['message' => __('利用可能なモデルが見つかりませんでした。', 'picot-ai-seo-writer')]);
        }

        update_option('picot_seo_writing_available_gemini_models', $text_models);
        update_option('picot_seo_writing_available_image_models', $image_models);
        update_option('picot_seo_writing_gemini_model_descriptions', $model_descriptions);
        
        wp_send_json_success([
            'models' => $text_models,
            'image_models' => $image_models,
            'descriptions' => $model_descriptions
        ]);
    }

    /**
     * Resolve a Gemini model ID for connection tests.
     *
     * @param string $api_key Gemini API key used to discover models when needed.
     * @return string Model ID or empty string.
     */
    private function resolve_gemini_test_model($api_key)
    {
        $saved_model = get_option('picot_seo_writing_text_model', '');
        if (!empty($saved_model)) {
            return $saved_model;
        }

        $models = get_option('picot_seo_writing_available_gemini_models', []);
        if (!empty($models) && is_array($models)) {
            $first_model = array_key_first($models);
            if (!empty($first_model)) {
                return $first_model;
            }
        }

        if (empty($api_key)) {
            return '';
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode($api_key);
        $response = wp_remote_get($url, ['timeout' => 30]);

        if (is_wp_error($response)) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['models']) || !is_array($body['models'])) {
            return '';
        }

        foreach ($body['models'] as $model) {
            if (empty($model['name'])) {
                continue;
            }

            $id = str_replace('models/', '', $model['name']);
            $display_name = isset($model['displayName']) ? $model['displayName'] : $id;
            $check_str = $id . ' ' . $display_name;

            if (!preg_match('/(imagen|banana|nano-|image-preview)/i', $check_str)) {
                return $id;
            }
        }

        return '';
    }

    /**
     * AJAX: Test Connection
     */
    public function ajax_test_connection()
    {
        check_ajax_referer('picot_seo_writing_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('この操作を実行する権限がありません。', 'picot-ai-seo-writer')]);
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : get_option('picot_seo_writing_gemini_api_key');

        if (empty($api_key)) {
            wp_send_json_error(['message' => __('APIキーが入力されていません。', 'picot-ai-seo-writer')]);
        }

        $test_model = $this->resolve_gemini_test_model($api_key);
        if (empty($test_model)) {
            wp_send_json_error(['message' => __('利用可能なモデルがありません。APIキーを確認するか、モデル一覧を更新してください。', 'picot-ai-seo-writer')]);
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($test_model) . ':generateContent?key=' . rawurlencode($api_key);
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'contents' => [['parts' => [['text' => 'Hi']]]],
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('通信エラー: ', 'picot-ai-seo-writer') . $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            wp_send_json_success(['message' => __('✅ Geminiへの接続に成功しました', 'picot-ai-seo-writer')]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $msg = isset($body['error']['message']) ? $body['error']['message'] : __('接続に失敗しました。', 'picot-ai-seo-writer');
        wp_send_json_error(['message' => '❌ ' . $msg]);
    }
}
