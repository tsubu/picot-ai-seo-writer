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
