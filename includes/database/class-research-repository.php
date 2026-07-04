<?php

/**
 * 調査データリポジトリ
 *
 * @package PICOT_SEO_WRITING\Database
 */

namespace PICOT_SEO_WRITING\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 調査データリポジトリクラス
 */
class Research_Repository extends Database
{

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'picot_seo_writing_research';
    }

    /**
     * 調査データを作成
     *
     * @param array $data 調査データ
     * @return int|false 挿入されたID、失敗時はfalse
     */
    public function create($data)
    {
        $post_id = isset($data['post_id']) ? (int) $data['post_id'] : 0;

        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'post_id' => $post_id,
                'target_keyword' => $data['target_keyword'],
                'locale_urls_ja' => isset($data['locale_urls_ja']) ? json_encode($data['locale_urls_ja']) : null,
                'locale_urls_en' => isset($data['locale_urls_en']) ? json_encode($data['locale_urls_en']) : null,
                'generated_title' => $data['generated_title'] ?? null,
                'generated_headings' => isset($data['generated_headings']) ? json_encode($data['generated_headings']) : null,
                'additional_notes' => $data['additional_notes'] ?? null,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * 調査履歴を取得
     *
     * @param int $post_id 投稿ID
     * @param int $limit 取得件数
     * @return array 調査履歴配列
     */
    public function get_history($post_id, $limit = PICOT_SEO_WRITING_HISTORY_LIMIT)
    {
        /* phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching */
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d ORDER BY created_at DESC LIMIT %d",
            $post_id,
            $limit
        );
        $results = $this->wpdb->get_results($query, ARRAY_A);
        /* phpcs:enable */

        // JSONデータをデコード
        foreach ($results as &$result) {
            if (!empty($result['locale_urls_ja'])) {
                $result['locale_urls_ja'] = json_decode($result['locale_urls_ja'], true);
            }
            if (!empty($result['locale_urls_en'])) {
                $result['locale_urls_en'] = json_decode($result['locale_urls_en'], true);
            }
            if (!empty($result['generated_headings'])) {
                $result['generated_headings'] = json_decode($result['generated_headings'], true);
            }
        }

        return $results;
    }

    /**
     * IDで調査データを取得
     *
     * @param int $id 調査ID
     * @return array|null 調査データ、見つからない場合はnull
     */
    public function get_by_id($id)
    {
        /* phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching */
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );
        $result = $this->wpdb->get_row($query, ARRAY_A);
        /* phpcs:enable */

        if (!$result) {
            return null;
        }

        // JSONデータをデコード
        if (!empty($result['locale_urls_ja'])) {
            $result['locale_urls_ja'] = json_decode($result['locale_urls_ja'], true);
        }
        if (!empty($result['locale_urls_en'])) {
            $result['locale_urls_en'] = json_decode($result['locale_urls_en'], true);
        }
        if (!empty($result['generated_headings'])) {
            $result['generated_headings'] = json_decode($result['generated_headings'], true);
        }

        return $result;
    }

    /**
     * 調査データを更新
     *
     * @param int $id 調査ID
     * @param array $data 更新データ
     * @return bool 成功時はtrue
     */
    public function update($id, $data)
    {
        $update_data = [];
        $format = [];

        if (isset($data['generated_title'])) {
            $update_data['generated_title'] = $data['generated_title'];
            $format[] = '%s';
        }

        if (isset($data['generated_headings'])) {
            $update_data['generated_headings'] = json_encode($data['generated_headings']);
            $format[] = '%s';
        }

        if (isset($data['additional_notes'])) {
            $update_data['additional_notes'] = $data['additional_notes'];
            $format[] = '%s';
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * 古い調査データを削除
     *
     * @param int $days 保持日数
     * @return int 削除された件数
     */
    public function delete_old_records($days = PICOT_SEO_WRITING_LOG_RETENTION_DAYS)
    {
        /* phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching */
        $query = $this->wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );
        $this->wpdb->query($query);
        /* phpcs:enable */

        return $this->wpdb->rows_affected;
    }
}
