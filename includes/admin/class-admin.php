<?php

/**
 * 管理画面統合クラス
 *
 * @package PICOT_SEO_WRITING\Admin
 */

namespace PICOT_SEO_WRITING\Admin;

use PICOT_SEO_WRITING\API\Grounding_Url_Resolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 管理画面クラス
 */
class Admin
{

    /**
     * 設定ページ
     *
     * @var Settings_Page
     */
    private $settings_page;

    /**
     * メタボックス
     *
     * @var Meta_Box
     */
    private $meta_box;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->settings_page = new Settings_Page();
        $this->meta_box = new Meta_Box();

        $this->init_hooks();
    }

    /**
     * フックを初期化
     */
    private function init_hooks()
    {
        // 管理メニュー
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // 設定登録
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'register_post_meta']);

        // メタボックス
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);

        // スクリプトとスタイル
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // プラグインリンク
        add_filter('plugin_action_links_' . PICOT_SEO_WRITING_PLUGIN_BASENAME, [$this, 'add_settings_link']);
    }

    /**
     * 管理メニューを追加
     */
    public function add_admin_menu()
    {
        add_options_page(
            __('Picot AI SEO Writer', 'picot-ai-seo-writer'),
            __('Picot AI SEO Writer', 'picot-ai-seo-writer'),
            'manage_options',
            'picot-ai-seo-writer',
            [$this->settings_page, 'render']
        );
    }

    /**
     * 設定を登録
     */
    public function register_settings()
    {
        $this->settings_page->register_settings();
    }

    /**
     * 投稿メタを登録
     */
    public function register_post_meta()
    {
        $auth_callback = function() {
            return current_user_can('edit_posts');
        };

        register_post_meta('post', 'picot_seo_writing_keyword', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => $auth_callback,
        ]);

        register_post_meta('post', 'picot_seo_writing_notes', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => $auth_callback,
        ]);

        register_post_meta('post', 'picot_seo_writing_sources', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string', // JSON文字列として保存して複雑なスキーマを避ける
            'auth_callback' => $auth_callback,
        ]);

        register_post_meta('post', 'picot_seo_writing_style', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => $auth_callback,
        ]);

        register_post_meta('post', 'picot_seo_writing_image_style', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => $auth_callback,
        ]);
    }

    /**
     * メタボックスを追加
     */
    public function add_meta_boxes()
    {
        $this->meta_box->add_meta_boxes();
    }

    /**
     * スクリプトとスタイルを読み込み
     *
     * @param string $hook フック名
     */
    public function enqueue_scripts($hook)
    {
        // 設定ページ
        if ($hook === 'settings_page_picot-ai-seo-writer') {
            $css_path = PICOT_SEO_WRITING_PLUGIN_DIR . 'assets/css/settings-page.css';
            $css_ver = file_exists($css_path) ? filemtime($css_path) : PICOT_SEO_WRITING_VERSION;

            wp_enqueue_style(
                'picot-ai-seo-writer-settings',
                PICOT_SEO_WRITING_PLUGIN_URL . 'assets/css/settings-page.css',
                [],
                $css_ver
            );

            $js_path = PICOT_SEO_WRITING_PLUGIN_DIR . 'assets/js/settings-page.js';
            $js_ver = file_exists($js_path) ? filemtime($js_path) : PICOT_SEO_WRITING_VERSION;

            wp_enqueue_script(
                'picot-ai-seo-writer-settings',
                PICOT_SEO_WRITING_PLUGIN_URL . 'assets/js/settings-page.js',
                ['jquery'],
                $js_ver,
                true
            );

            wp_localize_script('picot-ai-seo-writer-settings', 'picot_seo_writing_settings', [
                'rest_url' => rest_url('picot-ai-seo-writer/v1'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]);

            // ウィザード用アセット
            $ai_configured = \PICOT_SEO_WRITING\Ai_Client_Helper::supports_text_generation();
            $view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $is_wizard = ($view === 'wizard') || (!$ai_configured && $view !== 'standard');

            if ($is_wizard) {
                $wizard_css_path = PICOT_SEO_WRITING_PLUGIN_DIR . 'assets/css/wizard.css';
                $wizard_css_ver = file_exists($wizard_css_path) ? filemtime($wizard_css_path) : PICOT_SEO_WRITING_VERSION;

                wp_enqueue_style(
                    'picot-ai-seo-writer-wizard',
                    PICOT_SEO_WRITING_PLUGIN_URL . 'assets/css/wizard.css',
                    ['picot-ai-seo-writer-settings'],
                    $wizard_css_ver
                );

                $wizard_js_path = PICOT_SEO_WRITING_PLUGIN_DIR . 'assets/js/wizard.js';
                $wizard_js_ver = file_exists($wizard_js_path) ? filemtime($wizard_js_path) : PICOT_SEO_WRITING_VERSION;

                wp_enqueue_script(
                    'picot-ai-seo-writer-wizard',
                    PICOT_SEO_WRITING_PLUGIN_URL . 'assets/js/wizard.js',
                    ['jquery'],
                    $wizard_js_ver,
                    true
                );

                wp_localize_script('picot-ai-seo-writer-wizard', 'picot_seo_writing_wizard', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('picot_seo_writing_admin_nonce'),
                    'model_descriptions' => get_option('picot_seo_writing_gemini_model_descriptions', []),
                    'strings' => [
                        'fetchingModels' => __('モデル一覧を取得中...', 'picot-ai-seo-writer'),
                        'errorFetching' => __('モデル一覧の取得に失敗しました。', 'picot-ai-seo-writer'),
                        'next' => __('次へ進む', 'picot-ai-seo-writer'),
                        'submit' => __('設定を保存して完了する', 'picot-ai-seo-writer'),
                        'testing' => __('接続テスト中...', 'picot-ai-seo-writer'),
                    ]
                ]);
            }
        }

        // 投稿編集画面
        if (in_array($hook, ['post.php', 'post-new.php'])) {
            $common_css_path = PICOT_SEO_WRITING_PLUGIN_DIR . 'assets/css/admin-common.css';
            $common_css_ver = file_exists($common_css_path) ? filemtime($common_css_path) : PICOT_SEO_WRITING_VERSION;

            wp_enqueue_style(
                'picot-ai-seo-writer-admin',
                PICOT_SEO_WRITING_PLUGIN_URL . 'assets/css/admin-common.css',
                [],
                $common_css_ver
            );

            $overlay_js_path = PICOT_SEO_WRITING_PLUGIN_DIR . 'assets/js/loading-overlay.js';
            $overlay_js_ver = file_exists($overlay_js_path) ? filemtime($overlay_js_path) : PICOT_SEO_WRITING_VERSION;

            wp_enqueue_script(
                'picot-ai-seo-writer-loading-overlay',
                PICOT_SEO_WRITING_PLUGIN_URL . 'assets/js/loading-overlay.js',
                [],
                $overlay_js_ver,
                true
            );

            // ブロックエディタ用
            if ($this->is_block_editor()) {
                $block_js_path = PICOT_SEO_WRITING_PLUGIN_DIR . 'assets/js/block-editor.js';
                $block_js_ver = file_exists($block_js_path) ? filemtime($block_js_path) : PICOT_SEO_WRITING_VERSION;

                wp_enqueue_script(
                    'picot-ai-seo-writer-block-editor',
                    PICOT_SEO_WRITING_PLUGIN_URL . 'assets/js/block-editor.js',
                    ['picot-ai-seo-writer-loading-overlay', 'wp-blocks', 'wp-element', 'wp-edit-post', 'wp-plugins', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch'],
                    $block_js_ver,
                    true
                );

                $raw_post_id = filter_input(INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT);
                $post_id = $raw_post_id ? intval($raw_post_id) : get_the_id();
                
                $img_suggestions_json = get_post_meta($post_id, '_picot_aio_optimizer_image_suggestions', true);
                $image_suggestions_data = null;
                if (!empty($img_suggestions_json)) {
                    $img_suggestions_array = json_decode($img_suggestions_json, true);
                    $image_suggestions_data = [
                        'featured_prompt' => get_post_meta($post_id, '_picot_aio_optimizer_featured_prompt', true),
                        'featured_text'   => get_post_meta($post_id, '_picot_aio_optimizer_featured_text', true),
                        'suggestions'     => is_array($img_suggestions_array) ? $img_suggestions_array : []
                    ];
                }

                // スタイル設定を取得
                $global_style = get_option('picot_seo_writing_writing_style', PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE);
                $post_style   = get_post_meta($post_id, 'picot_seo_writing_style', true);
                $current_style = !empty($post_style) ? $post_style : $global_style;

                $global_img_style = get_option('picot_seo_writing_image_style', 'photorealistic');
                $post_img_style   = get_post_meta($post_id, 'picot_seo_writing_image_style', true);
                $current_img_style = !empty($post_img_style) ? $post_img_style : $global_img_style;

                $sources_for_editor = $this->get_resolved_sources_for_post($post_id);

                $meta_data = [
                    'restUrl' => esc_url_raw(rest_url()),
                    'namespace' => 'picot-ai-seo-writer/v1',
                    'nonce' => wp_create_nonce('wp_rest'),
                    'postId' => $post_id,
                    'post_id' => $post_id,
                    'lastKeyword' => get_post_meta($post_id, 'picot_seo_writing_keyword', true),
                    'target_keyword' => get_post_meta($post_id, 'picot_seo_writing_keyword', true),
                    'lastNotes' => get_post_meta($post_id, 'picot_seo_writing_notes', true),
                    'additional_notes' => get_post_meta($post_id, 'picot_seo_writing_notes', true),
                    'lastSources' => $sources_for_editor,
                    'sources' => $sources_for_editor,
                    'imageSuggestions' => $image_suggestions_data,
                    'currentStyle' => $current_style,
                    'currentImageStyle' => $current_img_style,
                    'strings' => $this->get_localized_strings(),
                ];

                wp_localize_script('picot-ai-seo-writer-block-editor', 'picotSeoWriting', $meta_data);
                wp_localize_script('picot-ai-seo-writer-block-editor', 'picot_seo_writing_admin', $meta_data);
            } else {
                // クラシックエディタ用
                $classic_js_path = PICOT_SEO_WRITING_PLUGIN_DIR . 'assets/js/classic-editor.js';
                $classic_js_ver = file_exists($classic_js_path) ? filemtime($classic_js_path) : PICOT_SEO_WRITING_VERSION;

                wp_enqueue_script(
                    'picot-ai-seo-writer-classic-editor',
                    PICOT_SEO_WRITING_PLUGIN_URL . 'assets/js/classic-editor.js',
                    ['jquery', 'picot-ai-seo-writer-loading-overlay'],
                    $classic_js_ver,
                    true
                );
            }

            $handle = $this->is_block_editor() ? 'picot-ai-seo-writer-block-editor' : 'picot-ai-seo-writer-classic-editor';

            // 設定ページの場合は設定用スクリプトのハンドルを使用
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'picot-ai-seo-writer') {
                $handle = 'picot-ai-seo-writer-settings';
            }

            wp_localize_script(
                $handle,
                'picot_seo_writing_admin',
                [
                    'rest_url' => rest_url('picot-ai-seo-writer/v1'),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'post_id' => get_the_ID(),
                    'strings' => $this->get_localized_strings(),
                ]
            );
        }
    }

    /**
     * ブロックエディタかどうかを判定
     *
     * @return bool ブロックエディタの場合true
     */
    private function is_block_editor()
    {
        $screen = get_current_screen();
        return $screen && method_exists($screen, 'is_block_editor') && $screen->is_block_editor();
    }

    /**
     * 翻訳文字列を取得
     *
     * @return array 翻訳文字列
     */
    private function get_localized_strings()
    {
        return [
            'title' => __('Picot AI SEO Writer', 'picot-ai-seo-writer'),
            'targetKeyword' => __('ターゲットワード', 'picot-ai-seo-writer'),
            'matchKeywordPlaceholder' => __('例: WordPress SEO', 'picot-ai-seo-writer'),
            'research' => __('調査', 'picot-ai-seo-writer'),
            'researching' => __('調査中...', 'picot-ai-seo-writer'),
            'researchCompleted' => __('調査が完了しました', 'picot-ai-seo-writer'),
            'researchFailed' => __('調査に失敗しました', 'picot-ai-seo-writer'),
            'researchHistory' => __('調査履歴', 'picot-ai-seo-writer'),
            'researchHistoryEmpty' => __('調査履歴がありません', 'picot-ai-seo-writer'),
            'generateTitle' => __('タイトルと見出しを作成', 'picot-ai-seo-writer'),
            'generateArticle' => __('見出しから記事を作成', 'picot-ai-seo-writer'),
            'generating' => __('生成中...', 'picot-ai-seo-writer'),
            'writingInProgress' => __('Geminiが執筆中...', 'picot-ai-seo-writer'),
            'overlaySubmessage' => __('これには数十秒かかる場合があります。', 'picot-ai-seo-writer'),
            'titleLabel' => __('タイトル: ', 'picot-ai-seo-writer'),
            'titleGenerationFailed' => __('タイトル生成に失敗しました', 'picot-ai-seo-writer'),
            'articleInserted' => __('記事を挿入しました', 'picot-ai-seo-writer'),
            'articleGenerationFailed' => __('記事生成に失敗しました', 'picot-ai-seo-writer'),
            'additionalNotes' => __('希望追加内容', 'picot-ai-seo-writer'),
            'additionalNotesPlaceholder' => __('追加したい情報や要望を入力してください', 'picot-ai-seo-writer'),
            'enterKeyword' => __('ターゲットワードを入力してください', 'picot-ai-seo-writer'),
            'enterContent' => __('記事内容を入力してください', 'picot-ai-seo-writer'),
            'suggestImages' => __('画像挿入ポイント探索', 'picot-ai-seo-writer'),
            'imagePointsLabel' => __('画像挿入ポイント:\n\n', 'picot-ai-seo-writer'),
            'imageLabel' => __('画像: ', 'picot-ai-seo-writer'),
            'suggestionFailed' => __('画像提案に失敗しました', 'picot-ai-seo-writer'),
            'jaLabel' => __('日本語: ', 'picot-ai-seo-writer'),
            'enLabel' => __('英語: ', 'picot-ai-seo-writer'),
            'error' => __('エラーが発生しました', 'picot-ai-seo-writer'),
            'success' => __('成功しました', 'picot-ai-seo-writer'),
            'fetching' => __('取得中...', 'picot-ai-seo-writer'),
            'updateSuccess' => __('モデル一覧を更新しました', 'picot-ai-seo-writer'),
            'updateFailed' => __('モデル一覧の取得に失敗しました', 'picot-ai-seo-writer'),
            'checkReferenceUrls' => __('参照URLを確認', 'picot-ai-seo-writer'),
        ];
    }

    /**
     * 投稿に保存された参照URLを取得（Vertex URL は実URLへ解決）
     *
     * @param int $post_id 投稿ID
     * @return array|string 解決済みソース配列、または空
     */
    private function get_resolved_sources_for_post($post_id)
    {
        if (!current_user_can('edit_post', $post_id)) {
            return [];
        }

        $raw = get_post_meta($post_id, 'picot_seo_writing_sources', true);
        if (empty($raw)) {
            return [];
        }

        $sources = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($sources) || empty($sources)) {
            return is_array($sources) ? $sources : [];
        }

        $url_resolver = new Grounding_Url_Resolver();
        if (!$url_resolver->sources_need_resolution($sources)) {
            return $sources;
        }

        if (function_exists('set_time_limit')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Resolving multiple redirect URLs may exceed host defaults.
            @set_time_limit(PICOT_SEO_WRITING_API_TIMEOUT);
        }

        $resolved = $url_resolver->resolve_source_urls($sources);

        if (!empty($resolved)) {
            update_post_meta(
                $post_id,
                'picot_seo_writing_sources',
                wp_slash(wp_json_encode($resolved, JSON_UNESCAPED_UNICODE))
            );
        }

        return $resolved;
    }

    /**
     * 設定リンクを追加
     *
     * @param array $links リンク配列
     * @return array リンク配列
     */
    public function add_settings_link($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=picot-ai-seo-writer'),
            __('設定', 'picot-ai-seo-writer')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
}
