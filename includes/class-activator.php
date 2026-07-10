<?php

/**
 * プラグインアクティベーション処理
 *
 * @package PICOT_SEO_WRITING
 */

namespace PICOT_SEO_WRITING;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * アクティベータークラス
 */
class Activator
{

    /**
     * プラグインアクティベーション時の処理
     */
    public static function activate()
    {
        // データベーステーブルの作成
        self::create_tables();

        // デフォルトオプションの設定
        self::set_default_options();

        // 他プラグインの Gemini 設定を引き継ぎ
        Api_Settings_Sync::sync(true);

        // クリーンアップスケジュールの設定
        if (!wp_next_scheduled('picot_seo_writing_cleanup_old_logs')) {
            wp_schedule_event(time(), 'daily', 'picot_seo_writing_cleanup_old_logs');
        }
    }

    /**
     * データベーステーブルを作成
     */
    private static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'picot_seo_writing_research';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            target_keyword VARCHAR(255) NOT NULL,
            locale_urls_ja TEXT,
            locale_urls_en TEXT,
            generated_title TEXT,
            generated_headings TEXT,
            additional_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * デフォルトオプションを設定
     */
    private static function set_default_options()
    {
        $defaults = [
            'picot_seo_writing_text_model' => '',
            'picot_seo_writing_writing_style' => PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE,
            'picot_seo_writing_writing_style_detail' => '',
            'picot_seo_writing_common_prompt' => '',
            'picot_seo_writing_image_common_prompt' => '',
            'picot_seo_writing_image_style' => 'photorealistic',
            'picot_seo_writing_available_gemini_models' => [],
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
