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
                    'strings' => self::get_localized_strings(),
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

            $overlay_strings = self::get_localized_strings();
            wp_localize_script('picot-ai-seo-writer-loading-overlay', 'picotSeoWritingOverlayStrings', [
                'defaultMessage' => $overlay_strings['processing'],
                'defaultSubmessage' => $overlay_strings['overlayDefaultMessage'],
            ]);

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
                    'writingStyleOptions' => $this->get_writing_style_options(),
                    'imageStyleOptions' => $this->get_image_style_options(),
                    'strings' => self::get_localized_strings(),
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

            if (!$this->is_block_editor()) {
                wp_localize_script(
                    $handle,
                    'picot_seo_writing_admin',
                    [
                        'rest_url' => rest_url('picot-ai-seo-writer/v1'),
                        'nonce' => wp_create_nonce('wp_rest'),
                        'post_id' => get_the_ID(),
                        'writingStyleOptions' => $this->get_writing_style_options(),
                        'imageStyleOptions' => $this->get_image_style_options(),
                        'strings' => self::get_localized_strings(),
                    ]
                );
            }
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
     * 執筆スタイル選択肢
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function get_writing_style_options()
    {
        return [
            ['label' => __('Casual', 'picot-ai-seo-writer'), 'value' => 'casual'],
            ['label' => __('Professional', 'picot-ai-seo-writer'), 'value' => 'professional'],
            ['label' => __('Friendly', 'picot-ai-seo-writer'), 'value' => 'friendly'],
            ['label' => __('Technical', 'picot-ai-seo-writer'), 'value' => 'technical'],
            ['label' => __('Use detailed role settings', 'picot-ai-seo-writer'), 'value' => 'detailed_role'],
        ];
    }

    /**
     * 出力言語（管理画面のユーザー言語から自動判定）
     *
     * @return string
     */
    public static function get_default_output_language()
    {
        return self::locale_to_output_language(get_user_locale());
    }

    /**
     * WordPress ロケールを出力言語コードへ変換
     *
     * @param string $locale ロケール
     * @return string
     */
    public static function locale_to_output_language($locale)
    {
        if (strpos($locale, 'ja') === 0) {
            return 'japanese';
        }
        if (strpos($locale, 'zh_CN') === 0) {
            return 'simplified_chinese';
        }
        if (strpos($locale, 'zh_TW') === 0 || strpos($locale, 'zh_HK') === 0) {
            return 'traditional_chinese';
        }

        return 'english';
    }

    /**
     * 画像スタイル選択肢
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function get_image_style_options()
    {
        return [
            ['label' => __('Photorealistic', 'picot-ai-seo-writer'), 'value' => 'photorealistic'],
            ['label' => __('Digital art', 'picot-ai-seo-writer'), 'value' => 'digital_art'],
            ['label' => __('Vector illustration', 'picot-ai-seo-writer'), 'value' => 'vector'],
            ['label' => __('Sketch', 'picot-ai-seo-writer'), 'value' => 'sketch'],
            ['label' => __('Watercolor', 'picot-ai-seo-writer'), 'value' => 'watercolor'],
            ['label' => __('Cyberpunk', 'picot-ai-seo-writer'), 'value' => 'cyberpunk'],
            ['label' => __('Anime', 'picot-ai-seo-writer'), 'value' => 'anime'],
            ['label' => __('Oil painting', 'picot-ai-seo-writer'), 'value' => 'oil_painting'],
        ];
    }

    /**
     * 翻訳文字列を取得
     *
     * @return array<string, string> 翻訳文字列
     */
    public static function get_localized_strings()
    {
        return [
            'title' => __('Picot AI SEO Writer', 'picot-ai-seo-writer'),
            'articleGenerationSettings' => __('Article generation settings', 'picot-ai-seo-writer'),
            'writingStylePanel' => __('Writing style', 'picot-ai-seo-writer'),
            'imageGenerationPanel' => __('Image generation', 'picot-ai-seo-writer'),
            'lastUsedInfoPanel' => __('Last used information', 'picot-ai-seo-writer'),
            'referenceUrlsPanel' => __('Reference URLs', 'picot-ai-seo-writer'),
            'targetKeyword' => __('Target keyword', 'picot-ai-seo-writer'),
            'matchKeywordPlaceholder' => __('e.g. WordPress SEO', 'picot-ai-seo-writer'),
            'additionalNotesOptional' => __('Additional notes (optional)', 'picot-ai-seo-writer'),
            'additionalNotesDetailedPlaceholder' => __('Enter specific details or requests to include in the article', 'picot-ai-seo-writer'),
            'writingStyleLabel' => __('Writing style', 'picot-ai-seo-writer'),
            'imageStyleLabel' => __('Image style', 'picot-ai-seo-writer'),
            'generateArticleButton' => __('Generate article', 'picot-ai-seo-writer'),
            'analyzeImagePromptsButton' => __('1. Analyze image prompts', 'picot-ai-seo-writer'),
            'imageGenerationDescription' => __('Analyze the article, suggest images (1 featured + 5 inline), generate them with Gemini, and insert them into the post.', 'picot-ai-seo-writer'),
            'wordLabel' => __('Keyword: ', 'picot-ai-seo-writer'),
            'notesLabel' => __('Notes: ', 'picot-ai-seo-writer'),
            'emptyValue' => __('(empty)', 'picot-ai-seo-writer'),
            'featuredImageLabel' => __('Featured image', 'picot-ai-seo-writer'),
            'generateAndSetFeatured' => __('Generate and set', 'picot-ai-seo-writer'),
            'generateAndInsertImage' => __('Generate and insert', 'picot-ai-seo-writer'),
            'generateAllImagesButton' => __('Generate and insert all images', 'picot-ai-seo-writer'),
            'generationComplete' => __('Generated', 'picot-ai-seo-writer'),
            'research' => __('Research', 'picot-ai-seo-writer'),
            'researching' => __('Researching...', 'picot-ai-seo-writer'),
            'researchCompleted' => __('Research completed', 'picot-ai-seo-writer'),
            'researchFailed' => __('Research failed', 'picot-ai-seo-writer'),
            'researchHistory' => __('Research history', 'picot-ai-seo-writer'),
            'researchHistoryEmpty' => __('No research history', 'picot-ai-seo-writer'),
            'generateTitle' => __('Generate title and headings', 'picot-ai-seo-writer'),
            'generateArticle' => __('Generate article from headings', 'picot-ai-seo-writer'),
            'generating' => __('Generating...', 'picot-ai-seo-writer'),
            'analyzing' => __('Analyzing...', 'picot-ai-seo-writer'),
            'writingInProgress' => __('Gemini is writing...', 'picot-ai-seo-writer'),
            'generatingArticle' => __('AI is generating the article...', 'picot-ai-seo-writer'),
            'analyzingImagePrompts' => __('Analyzing image prompts...', 'picot-ai-seo-writer'),
            'generatingFeaturedImage' => __('Generating featured image...', 'picot-ai-seo-writer'),
            /* translators: %d: image number. */
            'generatingImageNumber' => __('Generating image %d...', 'picot-ai-seo-writer'),
            /* translators: 1: current image number, 2: total image count. */
            'generatingBulkImage' => __('Generating image %1$d of %2$d...', 'picot-ai-seo-writer'),
            'generatingAllImages' => __('Generating all images... (this may take a while)', 'picot-ai-seo-writer'),
            'overlaySubmessage' => __('This may take several tens of seconds.', 'picot-ai-seo-writer'),
            'overlayDefaultMessage' => __('AI is processing. Please wait...', 'picot-ai-seo-writer'),
            'processing' => __('Processing...', 'picot-ai-seo-writer'),
            'titleLabel' => __('Title: ', 'picot-ai-seo-writer'),
            'titleGenerationFailed' => __('Failed to generate title', 'picot-ai-seo-writer'),
            'headingsExpandedMessage' => __('Headings were inserted into the editor. Edit them freely.', 'picot-ai-seo-writer'),
            'articleInserted' => __('Article inserted', 'picot-ai-seo-writer'),
            'articleGenerated' => __('Article generated!', 'picot-ai-seo-writer'),
            'articleGenerationFailed' => __('Failed to generate article', 'picot-ai-seo-writer'),
            'generateArticleFirst' => __('Generate an article first', 'picot-ai-seo-writer'),
            'additionalNotes' => __('Additional notes', 'picot-ai-seo-writer'),
            'additionalNotesPlaceholder' => __('Enter any extra requirements', 'picot-ai-seo-writer'),
            'enterKeyword' => __('Please enter a target keyword', 'picot-ai-seo-writer'),
            'enterContent' => __('Please enter article content', 'picot-ai-seo-writer'),
            'suggestImages' => __('Find image placement points', 'picot-ai-seo-writer'),
            'imagePointsLabel' => __("Image placement points:\n\n", 'picot-ai-seo-writer'),
            'imageLabel' => __('Image: ', 'picot-ai-seo-writer'),
            'suggestionFailed' => __('Failed to suggest images', 'picot-ai-seo-writer'),
            'imageSuggestionsReady' => __('Image suggestions are ready. Generate them from the list below.', 'picot-ai-seo-writer'),
            'imagePromptInsertFailed' => __('Failed to insert image prompts', 'picot-ai-seo-writer'),
            'imageSuggestionsEmbedded' => __('Image placement suggestions were added and markers were inserted in the editor.', 'picot-ai-seo-writer'),
            'imageSuggestionsNotFound' => __('No image placement points were found.', 'picot-ai-seo-writer'),
            'clearMarkersAndSuggestions' => __('Markers and suggestions cleared', 'picot-ai-seo-writer'),
            'clearSuggestionsButton' => __('Clear suggestions and markers', 'picot-ai-seo-writer'),
            'generateAndSetFeaturedClassic' => __('Generate and set as featured image', 'picot-ai-seo-writer'),
            'generateAndPlaceClassic' => __('Generate and place', 'picot-ai-seo-writer'),
            'featuredImageSetClassic' => __('Featured image set', 'picot-ai-seo-writer'),
            'featuredImageSet' => __('Featured image set and inserted!', 'picot-ai-seo-writer'),
            'imageInserted' => __('Image inserted', 'picot-ai-seo-writer'),
            'imageInsertedIntoPost' => __('Image inserted into the post!', 'picot-ai-seo-writer'),
            'imageSkippedAdjacent' => __('Skipped inserting an image next to another image.', 'picot-ai-seo-writer'),
            'generatingImageClassic' => __('Generating image...', 'picot-ai-seo-writer'),
            'imageGenerationFailed' => __('Image generation failed', 'picot-ai-seo-writer'),
            'noImageDataReturned' => __('No image data was returned', 'picot-ai-seo-writer'),
            'allImagesGenerated' => __('All images have already been generated!', 'picot-ai-seo-writer'),
            'allImagesComplete' => __('All images were generated and inserted!', 'picot-ai-seo-writer'),
            'jaLabel' => __('Japanese: ', 'picot-ai-seo-writer'),
            'enLabel' => __('English: ', 'picot-ai-seo-writer'),
            /* translators: %d: Number of Japanese search results. */
            'jaResultsCount' => __('Japanese: %d', 'picot-ai-seo-writer'),
            /* translators: %d: Number of English search results. */
            'enResultsCount' => __('English: %d', 'picot-ai-seo-writer'),
            'error' => __('An error occurred', 'picot-ai-seo-writer'),
            'unknownError' => __('Unknown error', 'picot-ai-seo-writer'),
            'errorPrefix' => __('Error: ', 'picot-ai-seo-writer'),
            'communicationError' => __('Communication error: ', 'picot-ai-seo-writer'),
            'imageGenerationErrorPrefix' => __('Image generation error: ', 'picot-ai-seo-writer'),
            'success' => __('Success', 'picot-ai-seo-writer'),
            'fetching' => __('Fetching...', 'picot-ai-seo-writer'),
            'updateSuccess' => __('Model list updated', 'picot-ai-seo-writer'),
            'updateFailed' => __('Failed to fetch model list', 'picot-ai-seo-writer'),
            'checkReferenceUrls' => __('Review reference URLs', 'picot-ai-seo-writer'),
            /* translators: %s: Target keyword. */
            'referenceUrlsTitle' => __('%s - Reference URLs', 'picot-ai-seo-writer'),
            'jaSearchRankings' => __('Japan search rankings (top 10)', 'picot-ai-seo-writer'),
            'enSearchRankings' => __('English search rankings (top 5)', 'picot-ai-seo-writer'),
            'closeModal' => __('Close', 'picot-ai-seo-writer'),
            'fetchingModels' => __('Fetching model list...', 'picot-ai-seo-writer'),
            'errorFetching' => __('Failed to fetch model list.', 'picot-ai-seo-writer'),
            'next' => __('Next', 'picot-ai-seo-writer'),
            'submit' => __('Save settings and finish', 'picot-ai-seo-writer'),
            'testing' => __('Testing connection...', 'picot-ai-seo-writer'),
            'communicationErrorPlain' => __('A communication error occurred.', 'picot-ai-seo-writer'),
            'communicationErrorIcon' => __('Communication error', 'picot-ai-seo-writer'),
            'sessionExpiredWizard' => __('Your session has expired. Reload the page and try again.', 'picot-ai-seo-writer'),
            'sessionExpiredSettings' => __('Session expired. Please reload the page.', 'picot-ai-seo-writer'),
            'testingConnection' => __('Testing...', 'picot-ai-seo-writer'),
            'communicating' => __('Connecting...', 'picot-ai-seo-writer'),
            'connectionSuccess' => __('Connection successful', 'picot-ai-seo-writer'),
            'connectionFailed' => __('Connection failed', 'picot-ai-seo-writer'),
            'fetchFailedGeneric' => __('Fetch failed', 'picot-ai-seo-writer'),
            'geminiConnectionFailed' => __('Failed to connect to the Google Gemini connector.', 'picot-ai-seo-writer'),
            'geminiConnectionTestFailed' => __('Google Gemini connector connection test failed.', 'picot-ai-seo-writer'),
            'modelFetchFailed' => __('Failed to fetch models.', 'picot-ai-seo-writer'),
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
            __('Settings', 'picot-ai-seo-writer')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
}
