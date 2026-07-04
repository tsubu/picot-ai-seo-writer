<?php

/**
 * メインプラグインクラス
 *
 * @package PICOT_SEO_WRITING
 */

namespace PICOT_SEO_WRITING;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * プラグインクラス
 */
class Plugin
{

    /**
     * 管理画面インスタンス
     *
     * @var Admin\Admin
     */
    private $admin;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->load_dependencies();
    }

    /**
     * 依存関係を読み込み
     */
    private function load_dependencies()
    {
        // 管理画面クラスのインスタンス化は run() で行う
    }

    /**
     * プラグインを実行
     */
    public function run()
    {
        // Loggerの初期化
        Logger::init();

        // 他プラグインから API 設定を自動引き継ぎ
        Api_Settings_Sync::init();

        // 管理画面の初期化
        if (is_admin()) {
            $this->admin = new Admin\Admin();
        }

        // REST APIルートの登録
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // クリーンアップフックの登録
        add_action('picot_seo_writing_cleanup_old_logs', [$this, 'cleanup_old_logs']);
    }

    /**
     * REST APIルートを登録
     */
    public function register_rest_routes()
    {
        $endpoints = [
            new REST\Models_Endpoint(),
            new REST\Research_Endpoint(),
            new REST\Content_Endpoint(),
            new REST\Image_Endpoint(),
        ];

        foreach ($endpoints as $endpoint) {
            $endpoint->register_routes();
        }
    }

    /**
     * 古いログと調査データをクリーンアップ
     */
    public function cleanup_old_logs()
    {
        // ログファイルのクリーンアップ
        Logger::cleanup_old_logs(PICOT_SEO_WRITING_LOG_RETENTION_DAYS);

        // 調査データのクリーンアップ
        $repository = new Database\Research_Repository();
        $repository->delete_old_records(PICOT_SEO_WRITING_LOG_RETENTION_DAYS);
    }
}
