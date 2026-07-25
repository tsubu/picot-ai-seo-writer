<?php

/**
 * プラグインアンインストール処理
 *
 * @package PICOT_SEO_WRITING
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

(function () {
    global $wpdb;

    // データベーステーブルの削除
    $picot_seo_writing_table_name = $wpdb->prefix . 'picot_seo_writing_research';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $wpdb->query("DROP TABLE IF EXISTS $picot_seo_writing_table_name");

    // オプションの削除
    $picot_seo_writing_options = [
        'picot_seo_writing_api_key',
        'picot_seo_writing_api_plan',
        'picot_seo_writing_text_model',
        'picot_seo_writing_writing_style',
        'picot_seo_writing_writing_style_detail',
        'picot_seo_writing_common_prompt',
        'picot_seo_writing_image_common_prompt',
        'picot_seo_writing_image_model',
        'picot_seo_writing_image_style',
        'picot_seo_writing_google_cse_api_key',
        'picot_seo_writing_google_cse_cx',
        'picot_seo_writing_available_gemini_models',
        'picot_seo_writing_available_image_models',
        'picot_seo_writing_gemini_model_descriptions',
        'picot_seo_writing_api_sync_notice',
    ];

    foreach ($picot_seo_writing_options as $picot_seo_writing_option) {
        delete_option($picot_seo_writing_option);
    }

    // 投稿メタの削除
    $picot_seo_writing_meta_keys = [
        'picot_seo_writing_keyword',
        'picot_seo_writing_notes',
        'picot_seo_writing_sources',
        'picot_seo_writing_style',
        'picot_seo_writing_image_style',
        '_picot_aio_optimizer_image_suggestions',
        '_picot_aio_optimizer_featured_prompt',
        '_picot_aio_optimizer_featured_text',
        '_picot_aio_optimizer_image_suggestions_updated',
    ];

    foreach ($picot_seo_writing_meta_keys as $picot_seo_writing_meta_key) {
        delete_post_meta_by_key($picot_seo_writing_meta_key);
    }

    // スケジュールの解除
    wp_clear_scheduled_hook('picot_seo_writing_cleanup_old_logs');

    // ログファイルの削除（旧: プラグイン配下 / 現在: uploads 配下）
    $picot_seo_writing_log_dirs = [plugin_dir_path(__FILE__) . 'logs'];

    $picot_seo_writing_upload_dir = wp_upload_dir();
    if (empty($picot_seo_writing_upload_dir['error'])) {
        $picot_seo_writing_log_dirs[] = trailingslashit($picot_seo_writing_upload_dir['basedir']) . 'picot-ai-seo-writer/logs';
    }

    foreach ($picot_seo_writing_log_dirs as $picot_seo_writing_log_dir) {
        if (!is_dir($picot_seo_writing_log_dir)) {
            continue;
        }

        // GLOB_BRACE でドットファイル（.htaccess 等）も含めて削除する。
        $picot_seo_writing_files = glob($picot_seo_writing_log_dir . '/{,.}[!.,]*', GLOB_BRACE);
        if (!is_array($picot_seo_writing_files)) {
            $picot_seo_writing_files = [];
        }
        // 既知のドットファイルを明示的に補完（環境により GLOB_BRACE 非対応の場合の保険）。
        foreach (['.htaccess', 'web.config', 'index.php', 'index.html'] as $picot_seo_writing_known) {
            $picot_seo_writing_known_path = $picot_seo_writing_log_dir . '/' . $picot_seo_writing_known;
            if (is_file($picot_seo_writing_known_path)) {
                $picot_seo_writing_files[] = $picot_seo_writing_known_path;
            }
        }
        foreach (array_unique($picot_seo_writing_files) as $picot_seo_writing_file) {
            if (is_file($picot_seo_writing_file)) {
                wp_delete_file($picot_seo_writing_file);
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- 自プラグインのログディレクトリのみ削除。
        @rmdir($picot_seo_writing_log_dir);
    }

    if (empty($picot_seo_writing_upload_dir['error'])) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- 自プラグインのアップロードディレクトリのみ削除。
        @rmdir(trailingslashit($picot_seo_writing_upload_dir['basedir']) . 'picot-ai-seo-writer');
    }
})();
