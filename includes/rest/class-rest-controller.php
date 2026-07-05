<?php

/**
 * REST API基底コントローラー
 *
 * @package PICOT_SEO_WRITING\REST
 */

namespace PICOT_SEO_WRITING\REST;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API基底コントローラークラス
 */
abstract class REST_Controller
{

    /**
     * 名前空間
     *
     * @var string
     */
    protected $namespace = 'picot-ai-seo-writer/v1';

    /**
     * ルートを登録
     */
    abstract public function register_routes();

    /**
     * 成功レスポンスを返す
     *
     * @param mixed $data データ
     * @param int $status HTTPステータスコード
     * @return \WP_REST_Response レスポンス
     */
    protected function success_response($data, $status = 200)
    {
        return new \WP_REST_Response([
            'success' => true,
            'data' => $data
        ], $status);
    }

    /**
     * エラーレスポンスを返す
     *
     * @param string $message エラーメッセージ
     * @param int $status HTTPステータスコード
     * @return \WP_Error エラー
     */
    protected function error_response($message, $status = 400)
    {
        return new \WP_Error(
            'picot_seo_writing_error',
            $message,
            ['status' => $status]
        );
    }

    /**
     * 編集権限をチェック
     *
     * @return bool 権限があればtrue
     */
    public function check_edit_permission()
    {
        return current_user_can('edit_posts');
    }

    /**
     * Verify the current user may edit a specific post from the request.
     *
     * @param \WP_REST_Request $request REST request.
     * @param string           $param   Request parameter name for the post ID.
     * @return bool
     */
    public function check_post_edit_permission($request, $param = 'post_id')
    {
        $post_id = (int) $request->get_param($param);

        if ($post_id <= 0) {
            return current_user_can('edit_posts');
        }

        return current_user_can('edit_post', $post_id);
    }

    /**
     * Verify the current user may edit the post linked to a research record.
     *
     * @param \WP_REST_Request $request REST request.
     * @param string           $param   Request parameter name for the research ID.
     * @return bool
     */
    public function check_research_edit_permission($request, $param = 'research_id')
    {
        $research_id = (int) $request->get_param($param);
        if ($research_id <= 0) {
            return false;
        }

        $repository = new \PICOT_SEO_WRITING\Database\Research_Repository();
        $research = $repository->get_by_id($research_id);
        if (!$research || empty($research['post_id'])) {
            return false;
        }

        return current_user_can('edit_post', (int) $research['post_id']);
    }

    /**
     * Verify the current user may edit the post linked to a research record ID route param.
     *
     * @param \WP_REST_Request $request REST request.
     * @return bool
     */
    public function check_research_route_edit_permission($request)
    {
        return $this->check_research_edit_permission($request, 'id');
    }

    /**
     * Verify upload and post edit permissions for image generation.
     *
     * @param \WP_REST_Request $request REST request.
     * @return bool
     */
    public function check_generate_image_permission($request)
    {
        if (!current_user_can('upload_files')) {
            return false;
        }

        return $this->check_post_edit_permission($request, 'post_id');
    }

    /**
     * 管理権限をチェック
     *
     * @return bool 権限があればtrue
     */
    public function check_manage_permission()
    {
        return current_user_can('manage_options');
    }

    /**
     * アップロード権限をチェック
     *
     * @return bool 権限があればtrue
     */
    public function check_upload_permission()
    {
        return current_user_can('upload_files');
    }
}
