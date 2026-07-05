<?php

/**
 * 調査エンドポイント
 *
 * @package PICOT_SEO_WRITING\REST
 */

namespace PICOT_SEO_WRITING\REST;

use PICOT_SEO_WRITING\API\Search_Simulator;
use PICOT_SEO_WRITING\Database\Research_Repository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 調査エンドポイントクラス
 */
class Research_Endpoint extends REST_Controller
{

    /**
     * ルートを登録
     */
    public function register_routes()
    {
        register_rest_route($this->namespace, '/research', [
            'methods' => 'POST',
            'callback' => [$this, 'create_research'],
            'permission_callback' => [$this, 'check_post_edit_permission'],
        ]);

        register_rest_route($this->namespace, '/research/history', [
            'methods' => 'GET',
            'callback' => [$this, 'get_history'],
            'permission_callback' => [$this, 'check_post_edit_permission'],
        ]);

        register_rest_route($this->namespace, '/research/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_research'],
            'permission_callback' => [$this, 'check_research_route_edit_permission'],
        ]);
    }

    /**
     * 調査を実行
     *
     * 仕様書の要件に基づき、以下の調査を実行します：
     * - WPの設定言語に基づいた国のGoogle検索で、上位10記事のURL
     * - 英語圏のGoogle検索でターゲットワードを英語に翻訳した、上位5記事のURL
     * 調査結果は記事ごとに個別に保存されます（履歴あり）。
     *
     * @param \WP_REST_Request $request リクエスト
     * @return \WP_REST_Response|\WP_Error レスポンス
     */
    public function create_research($request)
    {
        $keyword = $request->get_param('keyword');
        $post_id = $request->get_param('post_id');

        \PICOT_SEO_WRITING\Logger::info('create_research called', [
            'keyword' => $keyword,
            'post_id' => $post_id
        ]);

        if (empty($keyword) || empty($post_id)) {
            \PICOT_SEO_WRITING\Logger::error('Missing keyword or post_id');
            return $this->error_response(esc_html__('キーワードと投稿IDは必須です', 'picot-ai-seo-writer'));
        }

        try {
            $simulator = new Search_Simulator();

            // 仕様書の要件: 「WPの設定言語に基づいた国のGoogle検索で、上位10記事のURL」
            // 日本語圏の検索（10件）
            $urls_ja = $simulator->simulate_search($keyword, 'ja', 10);

            // 仕様書の要件: 「英語圏のGoogle検索でターゲットワードを英語に翻訳した、上位5記事のURL」
            // 英語圏の検索（5件）- キーワードを英語に翻訳して検索
            $urls_en = $simulator->simulate_search($keyword, 'en', 5, true);

            // 仕様書の要件: 「記事ごとに個別に保存する（履歴あり）」
            // データベースに保存
            $repository = new Research_Repository();
            $research_id = $repository->create([
                'post_id' => $post_id,
                'target_keyword' => $keyword,
                'locale_urls_ja' => $urls_ja,
                'locale_urls_en' => $urls_en,
            ]);

            if (!$research_id) {
                \PICOT_SEO_WRITING\Logger::error('Failed to save research data', [
                    'post_id' => $post_id,
                    'keyword' => $keyword
                ]);
                return $this->error_response(esc_html__('調査データの保存に失敗しました', 'picot-ai-seo-writer'), 500);
            }

            \PICOT_SEO_WRITING\Logger::info('Research created successfully', [
                'research_id' => $research_id,
                'urls_ja_count' => count($urls_ja),
                'urls_en_count' => count($urls_en)
            ]);

            return $this->success_response([
                'research_id' => $research_id,
                'keyword' => $keyword,
                'urls_ja' => $urls_ja,
                'urls_en' => $urls_en,
            ]);
        } catch (\Exception $e) {
            \PICOT_SEO_WRITING\Logger::error('Exception in create_research', [
                'message' => $e->getMessage(),
                'keyword' => $keyword,
                'post_id' => $post_id
            ]);
            return $this->error_response($e->getMessage(), 500);
        }
    }

    /**
     * 調査履歴を取得
     *
     * 仕様書の要件に基づき、指定された投稿の調査履歴を新しい順で取得します。
     *
     * @param \WP_REST_Request $request リクエスト
     * @return \WP_REST_Response|\WP_Error レスポンス
     */
    public function get_history($request)
    {
        $post_id = $request->get_param('post_id');

        \PICOT_SEO_WRITING\Logger::info('get_history called', ['post_id' => $post_id]);

        if (empty($post_id)) {
            \PICOT_SEO_WRITING\Logger::error('Missing post_id in get_history');
            return $this->error_response(esc_html__('投稿IDは必須です', 'picot-ai-seo-writer'));
        }

        try {
            $repository = new Research_Repository();
            // 仕様書の要件: 「新しい順」
            $history = $repository->get_history($post_id);

            \PICOT_SEO_WRITING\Logger::debug('History retrieved', ['count' => count($history)]);

            return $this->success_response(['history' => $history]);
        } catch (\Exception $e) {
            \PICOT_SEO_WRITING\Logger::error('Exception in get_history', [
                'message' => $e->getMessage(),
                'post_id' => $post_id
            ]);
            return $this->error_response($e->getMessage(), 500);
        }
    }

    /**
     * 調査データを取得
     *
     * 指定されたIDの調査データを取得します。
     *
     * @param \WP_REST_Request $request リクエスト
     * @return \WP_REST_Response|\WP_Error レスポンス
     */
    public function get_research($request)
    {
        $id = $request->get_param('id');

        try {
            $repository = new Research_Repository();
            $research = $repository->get_by_id($id);

            if (!$research) {
                return $this->error_response(esc_html__('調査データが見つかりません', 'picot-ai-seo-writer'), 404);
            }

            return $this->success_response($research);
        } catch (\Exception $e) {
            \PICOT_SEO_WRITING\Logger::error('Exception in get_research', [
                'message' => $e->getMessage(),
                'id' => $id
            ]);
            return $this->error_response($e->getMessage(), 500);
        }
    }
}
