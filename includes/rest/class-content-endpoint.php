<?php

/**
 * コンテンツ生成エンドポイント
 *
 * @package PICOT_SEO_WRITING\REST
 */

namespace PICOT_SEO_WRITING\REST;

use PICOT_SEO_WRITING\API\Content_Generator;
use PICOT_SEO_WRITING\API\Grounding_Url_Resolver;
use PICOT_SEO_WRITING\Database\Research_Repository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * コンテンツ生成エンドポイントクラス
 */
class Content_Endpoint extends REST_Controller
{

    /**
     * ルートを登録
     */
    public function register_routes()
    {
        register_rest_route($this->namespace, '/generate-title', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_title'],
            'permission_callback' => [$this, 'check_edit_permission'],
        ]);

        register_rest_route($this->namespace, '/generate-article', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_article'],
            'permission_callback' => [$this, 'check_edit_permission'],
        ]);

        // 直接生成エンドポイント
        register_rest_route($this->namespace, '/generate-article-direct', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_article_direct'],
            'permission_callback' => [$this, 'check_edit_permission'],
        ]);

        // メタデータ保存専用エンドポイント
        register_rest_route($this->namespace, '/save-meta', [
            'methods' => 'POST',
            'callback' => [$this, 'save_post_meta'],
            'permission_callback' => [$this, 'check_edit_permission'],
        ]);

        register_rest_route($this->namespace, '/insert-image-prompts', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_insert_image_prompts'],
            'permission_callback' => [$this, 'check_edit_permission'],
        ]);
    }

    /**
     * メタデータ保存専用ハンドラ
     * wp.data.dispatch に依存せず、確実にDBに保存する
     */
    public function save_post_meta($request)
    {
        $post_id  = intval($request->get_param('post_id'));
        $keyword  = sanitize_text_field($request->get_param('keyword'));
        $notes    = sanitize_textarea_field($request->get_param('notes'));
        $sources  = $request->get_param('sources'); // JSON 文字列または配列

        if ($post_id <= 0) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Invalid post_id'], 400);
        }

        // post が実在するか確認
        if (!get_post($post_id)) {
            return new \WP_REST_Response(['success' => false, 'error' => 'Post not found'], 404);
        }

        update_post_meta($post_id, 'picot_seo_writing_keyword', $keyword);
        update_post_meta($post_id, 'picot_seo_writing_notes', $notes);

        $sources_json = is_string($sources) ? $sources : wp_json_encode($sources, JSON_UNESCAPED_UNICODE);
        update_post_meta($post_id, 'picot_seo_writing_sources', wp_slash($sources_json));

        \PICOT_SEO_WRITING\Logger::info('save-meta: Saved', [
            'post_id' => $post_id,
            'keyword' => $keyword
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'post_id' => $post_id,
            'keyword' => $keyword,
        ], 200);
    }

    /**
     * Handle Insert Image Prompts
     */
    public function handle_insert_image_prompts($request)
    {
        $http_timeout_filter = $this->extend_execution_time();
        ob_start();

        try {
            $content = $request->get_param('content');
            $post_id = (int) $request->get_param('post_id');
            if (empty($content)) {
                throw new \Exception(esc_html__('コンテンツが空です。', 'picot-ai-seo-writer'));
            }

            $generator = new Content_Generator();
            $result    = $generator->insert_image_prompts($content);

            if (is_wp_error($result)) {
                ob_end_clean();
                remove_filter('http_request_timeout', $http_timeout_filter);
                return new \WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 500);
            }

            // --- JSON 抽出 ---
            $raw_json = is_string($result) ? $result : (is_array($result) ? wp_json_encode($result, JSON_UNESCAPED_UNICODE) : '');

            // ① Markdown コードブロック除去
            $clean_json = preg_replace('/^```json\s*/i', '', trim($raw_json));
            $clean_json = preg_replace('/\s*```\s*$/i', '', $clean_json);

            // ② BOM・制御文字除去
            $clean_json = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean_json);
            $clean_json = ltrim($clean_json, "\xEF\xBB\xBF");

            // ③ { ... } の範囲だけを取り出す
            $first_brace = strpos($clean_json, '{');
            $last_brace  = strrpos($clean_json, '}');
            if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
                $clean_json = substr($clean_json, $first_brace, $last_brace - $first_brace + 1);
            }

            // ④ 余分なカンマ（Trailing commas）の除去
            $clean_json = preg_replace('/,\s*([\]}])/m', '$1', $clean_json);

            $data = json_decode($clean_json, true);

            if (!is_array($data)) {
                $json_err = json_last_error_msg();
                \PICOT_SEO_WRITING\Logger::error('insert-image-prompts: JSON parse failed', [
                    'error' => $json_err,
                ]);
                ob_end_clean();
                remove_filter('http_request_timeout', $http_timeout_filter);
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => sprintf(
                        /* translators: %s: JSON parse error message. */
                        esc_html__('JSON解析失敗 (%s)。', 'picot-ai-seo-writer'),
                        $json_err
                    ),
                ], 500);
            }

            if (!isset($data['suggestions'])) {
                $data['suggestions'] = [];
            }

            // Optimizer のメタキーに保存
            if ($post_id > 0) {
                update_post_meta($post_id, '_picot_aio_optimizer_image_suggestions',         wp_slash(wp_json_encode($data['suggestions'], JSON_UNESCAPED_UNICODE)));
                update_post_meta($post_id, '_picot_aio_optimizer_featured_text',              $data['featured_text']   ?? '');
                update_post_meta($post_id, '_picot_aio_optimizer_featured_prompt',            $data['featured_prompt'] ?? '');
                update_post_meta($post_id, '_picot_aio_optimizer_image_suggestions_updated', current_time('mysql'));
            }

            ob_end_clean();
            remove_filter('http_request_timeout', $http_timeout_filter);
            return new \WP_REST_Response([
                'success' => true,
                'message' => esc_html__('画像提案を Optimizer に保存しました。', 'picot-ai-seo-writer'),
                'data'    => $data
            ], 200);

        } catch (\Throwable $e) {
            ob_end_clean();
            remove_filter('http_request_timeout', $http_timeout_filter);
            \PICOT_SEO_WRITING\Logger::error('insert-image-prompts failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
                'file'    => basename($e->getFile()),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * キーワードから直接記事本文を生成
     *
     * @param \WP_REST_Request $request リクエスト
     * @return \WP_REST_Response|\WP_Error レスポンス
     */
    public function generate_article_direct($request)
    {
        $http_timeout_filter = $this->extend_execution_time();

        // 出力バッファリングを開始し、意図しないPHPの警告出力を防ぐ
        ob_start();

        $keyword = $request->get_param('keyword');
        $additional_notes = $request->get_param('additional_notes') ?? '';
        $language = $request->get_param('language') ?? 'japanese';
        $post_id = (int) $request->get_param('post_id');
        $writing_style_param = $request->get_param('writing_style');

        \PICOT_SEO_WRITING\Logger::info('REST API: generate_article_direct start', [
            'keyword' => $keyword,
            'language' => $language,
            'post_id' => $post_id,
        ]);

        if (empty($keyword)) {
            if (ob_get_length()) {
                ob_end_clean();
            }
            remove_filter('http_request_timeout', $http_timeout_filter);
            return $this->error_response(esc_html__('ターゲットワードは必須です', 'picot-ai-seo-writer'));
        }

        try {
            $style = !empty($writing_style_param)
                ? sanitize_text_field($writing_style_param)
                : get_option('picot_seo_writing_writing_style', PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE);

            $generator = new Content_Generator();
            $url_resolver = new Grounding_Url_Resolver();

            $result = $generator->generate_article_direct(
                $keyword,
                $additional_notes,
                $style,
                $language
            );

            // 新しい戻り値形式（配列）に対応
            if (is_array($result) && isset($result['text'])) {
                $raw_content     = $result['text'];
                $grounding_urls  = $result['grounding_urls'] ?? [];
            } else {
                // 後方互換（文字列の場合）
                $raw_content    = $result;
                $grounding_urls = [];
            }

            // タイトルと本文の抽出
            $title = '';
            $clean_content = $raw_content;

            // 参照元URL：groundingMetadata から取得した実URLを使用（テキストパース不要）
            // $grounding_urls には ['url' => '...', 'title' => '...'] の配列が入っている
            $sources = $grounding_urls;

            // grounding が空の場合のみテキストからフォールバック抽出
            if (empty($sources) && preg_match('/\[SOURCES_START\](.*?)\[SOURCES_END\]/si', $raw_content, $matches)) {
                foreach (explode("\n", $matches[1]) as $line) {
                    $line = trim($line);
                    if (preg_match('/(https?:\/\/[^\s\)\"\'<>\]]+)/i', $line, $url_m)) {
                        $sources[] = ['url' => $url_m[1], 'title' => ''];
                    }
                }
            }

            // Vertex リダイレクトを実 URL へ解決
            if (!empty($sources)) {
                $sources = $url_resolver->resolve_source_urls($sources);
            }

            // タイトル抽出
            $title = '';
            if (preg_match('/\[FINAL_TITLE_START\](.*?)\[FINAL_TITLE_END\]/si', $raw_content, $matches)) {
                $title = trim($matches[1]);
                $raw_content = str_replace($matches[0], '', $raw_content);
            } else {
                // フォールバック: 最初の見出し (h1, h2, h3) を探す
                if (preg_match('/<h[1-3][^>]*>(.*?)<\/h[1-3]>/i', $raw_content, $m)) {
                    $title = wp_strip_all_tags($m[1]);
                }
            }

            // 本文のみを抽出（タグが含まれるブロックをすべて確実に削除）
            $clean_content = $raw_content;
            
            // 削除すべきタグパターンのリスト
            $patterns_to_strip = [
                '/\[SOURCES_START\].*?\[SOURCES_END\]/si',
                '/\[OVERVIEW_START\].*?\[OVERVIEW_END\]/si',
                '/\[FINAL_TITLE_START\].*?\[FINAL_TITLE_END\]/si',
                '/\[IMAGE_PROMPT:.*?\]/i' // 古い形式の画像プロンプトも念のため
            ];

            foreach ($patterns_to_strip as $pattern) {
                $clean_content = preg_replace($pattern, '', $clean_content);
            }

            // 万が一、開始タグまたは終了タグだけが残ってしまった場合の掃除
            $tags_only = [
                '/\[SOURCES_START\]/i', '/\[SOURCES_END\]/i',
                '/\[OVERVIEW_START\]/i', '/\[OVERVIEW_END\]/i',
                '/\[FINAL_TITLE_START\]/i', '/\[FINAL_TITLE_END\]/i'
            ];
            $clean_content = preg_replace($tags_only, '', $clean_content);

            $clean_content = trim($clean_content);

            // 【強力な掃除】本文中に漏れ出したGoogle内部URLや、不要なURLを強制排除
            // まずは <a> タグ全体を削除
            $aggressive_a_tag_patterns = [
                '/<a[^>]*href=[\'"][^\'"]*vertexaisearch\.[\w\-\.]*google\.com[^\'"]*[\'"][^>]*>.*?<\/a>/i',
                '/<a[^>]*href=[\'"][^\'"]*google\.com\/search\?[^\'"]*[\'"][^>]*>.*?<\/a>/i',
                '/<a[^>]*href=[\'"][^\'"]*\.cloud\.google\.com[^\'"]*[\'"][^>]*>.*?<\/a>/i'
            ];
            foreach ($aggressive_a_tag_patterns as $a_pattern) {
                $clean_content = preg_replace($a_pattern, '', $clean_content);
            }
            // その後、タグで囲まれていない生のURL文字列も削除
            $aggressive_url_patterns = [
                '/https?:\/\/[\w\-\.]*vertexaisearch\.[\w\-\.]*google\.com\/[^\s\)\"\'\>]+/i',
                '/https?:\/\/www\.google\.com\/search\?[^\s\)\"\'\>]+/i',
                '/https?:\/\/[\w\-\.]+\.cloud\.google\.com\/[^\s\)\"\'\>]+/i'
            ];
            foreach ($aggressive_url_patterns as $url_pattern) {
                $clean_content = preg_replace($url_pattern, '', $clean_content);
            }
            $clean_content = trim($clean_content);

            \PICOT_SEO_WRITING\Logger::info('REST API: Article cleaned', [
                'original_length' => strlen($raw_content),
                'cleaned_length' => strlen($clean_content)
            ]);

            // 概要の抽出と削除（本文に残さない）
            $excerpt = '';
            if (preg_match('/\[OVERVIEW_START\](.*?)\[OVERVIEW_END\]/s', $clean_content, $matches)) {
                $excerpt = trim($matches[1]);
                $clean_content = str_replace($matches[0], '', $clean_content);
            }

            \PICOT_SEO_WRITING\Logger::info('REST API: Article generated successfully', [
                'title' => $title,
                'sources_count' => count($sources)
            ]);

            // メタデータをデータベースに直接保存（強制永続化）
            if ($post_id > 0) {
                update_post_meta($post_id, 'picot_seo_writing_keyword', $keyword);
                update_post_meta($post_id, 'picot_seo_writing_notes', $additional_notes);
                update_post_meta($post_id, 'picot_seo_writing_sources', wp_slash(wp_json_encode($sources, JSON_UNESCAPED_UNICODE)));
                
                // 新しい記事を生成したため、古い画像プロンプト分析結果はリセットする
                delete_post_meta($post_id, '_picot_aio_optimizer_image_suggestions');
                delete_post_meta($post_id, '_picot_aio_optimizer_featured_text');
                delete_post_meta($post_id, '_picot_aio_optimizer_featured_prompt');
                delete_post_meta($post_id, '_picot_aio_optimizer_image_suggestions_updated');
                
                \PICOT_SEO_WRITING\Logger::info('REST API: Post meta updated directly', [
                    'post_id' => $post_id,
                    'keyword' => $keyword
                ]);
            }

            // 履歴として保存 (名前空間を明示)
            try {
                $repository = new Research_Repository();
                $research_id = $repository->create([
                    'post_id' => $post_id > 0 ? $post_id : 0,
                    'target_keyword' => $keyword,
                    'additional_notes' => $additional_notes,
                    'locale_urls_ja' => $sources,
                    'locale_urls_en' => [],
                    'status' => 'completed',
                    'generated_title' => $title
                ]);
            } catch (\Throwable $te) {
                \PICOT_SEO_WRITING\Logger::error('History saving failed', ['msg' => $te->getMessage()]);
                $research_id = 0;
            }

            if (ob_get_length()) {
                ob_end_clean();
            }

            remove_filter('http_request_timeout', $http_timeout_filter);

            return $this->success_response([
                'title' => $title,
                'article_content' => trim($clean_content),
                'excerpt' => $excerpt,
                'sources' => $sources,
                'research_id' => $research_id,
                'success' => true
            ]);
        } catch (\Throwable $e) {
            if (ob_get_length()) {
                ob_end_clean();
            }
            remove_filter('http_request_timeout', $http_timeout_filter);
            \PICOT_SEO_WRITING\Logger::error('REST API: generate_article_direct failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            return new \WP_Error('gemini_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * 記事生成など長時間処理向けに実行時間制限を緩和する
     *
     * @return callable http_request_timeout フィルター（finally/remove用）
     */
    private function extend_execution_time()
    {
        if (function_exists('set_time_limit')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Gemini API calls may exceed host defaults.
            @set_time_limit(0);
        }
        if (function_exists('ini_set')) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Gemini API calls may exceed host defaults.
            @ini_set('max_execution_time', (string) (PICOT_SEO_WRITING_API_TIMEOUT + 120));
        }
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        $filter = static function () {
            return PICOT_SEO_WRITING_API_TIMEOUT;
        };
        add_filter('http_request_timeout', $filter);

        return $filter;
    }

    /**
     * タイトルと見出しを生成
     *
     * 仕様書の要件に基づき、調査で取得した全URLから取得した内容を考慮して、
     * EEATとGoogle検索品質ガイドラインに準拠したタイトルと見出しを生成します。
     *
     * @param \WP_REST_Request $request リクエスト
     * @return \WP_REST_Response|\WP_Error レスポンス
     */
    public function generate_title($request)
    {
        $research_id = $request->get_param('research_id');
        $additional_notes = $request->get_param('additional_notes') ?? '';

        if (empty($research_id)) {
            return $this->error_response(esc_html__('調査IDは必須です', 'picot-ai-seo-writer'));
        }

        try {
            // 調査データを取得
            $repository = new Research_Repository();
            $research = $repository->get_by_id($research_id);

            if (!$research) {
                return $this->error_response(esc_html__('調査データが見つかりません', 'picot-ai-seo-writer'), 404);
            }

            // 仕様書の要件: 全URLから取得した内容を考慮
            // 日本語圏と英語圏のURLを結合
            $urls = array_merge(
                $research['locale_urls_ja'] ?? [],
                $research['locale_urls_en'] ?? []
            );

            if (empty($urls)) {
                return $this->error_response(esc_html__('調査データにURLが含まれていません', 'picot-ai-seo-writer'), 400);
            }

            // スタイルを取得
            $style = get_option('picot_seo_writing_writing_style', PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE);

            // タイトルと見出しを生成
            // 仕様書の要件: 「全URLから取得した内容から」「EEATやGoogle検索品質ガイドラインを元に適切な内容」
            // 「希望追加内容」フォームの内容を追加
            $generator = new Content_Generator();
            $result = $generator->generate_title_and_headings(
                $research['target_keyword'],
                $urls,
                $additional_notes,
                $style
            );

            // データベースを更新
            $repository->update($research_id, [
                'generated_title' => $result['title'],
                'generated_headings' => $result['headings'],
                'additional_notes' => $additional_notes,
            ]);

            return $this->success_response($result);
        } catch (\Exception $e) {
            \PICOT_SEO_WRITING\Logger::error('Error in generate_title', [
                'message' => $e->getMessage(),
                'research_id' => $research_id
            ]);
            return $this->error_response($e->getMessage(), 500);
        }
    }

    /**
     * 記事本文を生成
     *
     * 仕様書の要件に基づき、生成された見出し構成から記事本文を生成します。
     * EEATとGoogle検索品質ガイドラインに準拠した内容を生成します。
     *
     * @param \WP_REST_Request $request リクエスト
     * @return \WP_REST_Response|\WP_Error レスポンス
     */
    public function generate_article($request)
    {
        $research_id = $request->get_param('research_id');
        $additional_notes = $request->get_param('additional_notes') ?? '';
        $current_content = $request->get_param('current_content') ?? '';

        if (empty($research_id)) {
            return $this->error_response(esc_html__('調査IDは必須です', 'picot-ai-seo-writer'));
        }

        try {
            // 調査データを取得
            $repository = new Research_Repository();
            $research = $repository->get_by_id($research_id);

            if (!$research) {
                return $this->error_response(esc_html__('調査データが見つかりません', 'picot-ai-seo-writer'), 404);
            }

            if (empty($research['generated_headings']) && empty($current_content)) {
                return $this->error_response(esc_html__('先にタイトルと見出しを生成するか、エディタに見出しを入力してください', 'picot-ai-seo-writer'));
            }

            // 見出し構成を決定
            // クライアントから送信された current_content があればそれを優先
            $target_headings = !empty($current_content) ? $current_content : $this->format_headings($research['generated_headings']);

            // スタイルを取得
            $style = get_option('picot_seo_writing_writing_style', PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE);

            // 記事本文を生成
            // 仕様書の要件: 「希望追加内容」フォームの内容を追加
            $generator = new Content_Generator();
            $content = $generator->generate_article(
                $target_headings,
                $additional_notes,
                $style
            );

            return $this->success_response(['content' => $content]);
        } catch (\Exception $e) {
            \PICOT_SEO_WRITING\Logger::error('Error in generate_article', [
                'message' => $e->getMessage(),
                'research_id' => $research_id
            ]);
            return $this->error_response($e->getMessage(), 500);
        }
    }

    /**
     * 見出し配列をテキストに変換
     *
     * @param array $headings 見出し配列
     * @return string 見出しテキスト
     */
    private function format_headings($headings)
    {
        $text = '';
        foreach ($headings as $heading) {
            $level = $heading['level'];
            $indent = str_repeat('  ', $level - 2);
            $text .= $indent . "H{$level}: " . $heading['text'] . "\n";
        }
        return $text;
    }
}
