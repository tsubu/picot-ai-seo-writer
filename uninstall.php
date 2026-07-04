<?php

/**
 * プラグインアンインストール処理
 *
 * @package PICOT_SEO_WRITING
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

(function() {
    global $wpdb;

    // データベーステーブルの削除
    $picot_seo_writing_table_name = $wpdb->prefix . 'picot_seo_writing_research';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $wpdb->query("DROP TABLE IF EXISTS $picot_seo_writing_table_name");

    // オプションの削除
    $picot_seo_writing_options = [
        'picot_seo_writing_api_key',
        'picot_seo_writing_text_model',
        'picot_seo_writing_writing_style',
        'picot_seo_writing_image_model',
        'picot_seo_writing_image_style',
        'picot_seo_writing_google_cse_api_key',
        'picot_seo_writing_google_cse_cx',
    ];

    foreach ($picot_seo_writing_options as $picot_seo_writing_option) {
        delete_option($picot_seo_writing_option);
    }

    // ログファイルの削除
    $picot_seo_writing_log_dir = plugin_dir_path(__FILE__) . 'logs';
    if (is_dir($picot_seo_writing_log_dir)) {
        $picot_seo_writing_files = glob($picot_seo_writing_log_dir . '/*');
        foreach ($picot_seo_writing_files as $picot_seo_writing_file) {
            if (is_file($picot_seo_writing_file)) {
                wp_delete_file($picot_seo_writing_file);
            }
        }
    }
})();
