<?php

/**
 * 調査履歴テーブル管理クラス
 *
 * @package PICOT_SEO_WRITING\Database
 */

namespace PICOT_SEO_WRITING\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 調査履歴テーブルクラス
 */
class Research_Table
{

    /**
     * テーブル名
     *
     * @var string
     */
    private $table_name;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'picot_seo_writing_research';
    }

    /**
     * テーブルを作成
     */
    public function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
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

        \PICOT_SEO_WRITING\Logger::info('Research table created', ['table' => $this->table_name]);
    }

    /**
     * テーブルを削除
     */
    public function drop_table()
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");

        \PICOT_SEO_WRITING\Logger::info('Research table dropped', ['table' => $this->table_name]);
    }

    /**
     * テーブル名を取得
     *
     * @return string
     */
    public function get_table_name()
    {
        return $this->table_name;
    }

    /**
     * 古い調査履歴を削除
     *
     * @param int $days 保持日数
     * @return int 削除件数
     */
    public function delete_old_records($days = PICOT_SEO_WRITING_LOG_RETENTION_DAYS)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        /* phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared */
        $query = $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            $date
        );
        $deleted = $wpdb->query($query);
        /* phpcs:enable */

        if ($deleted) {
            \PICOT_SEO_WRITING\Logger::info('Old research records deleted', [
                'count' => $deleted,
                'before_date' => $date
            ]);
        }

        return $deleted;
    }
}
