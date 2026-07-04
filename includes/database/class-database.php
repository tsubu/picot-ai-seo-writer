<?php

/**
 * データベース基底クラス
 *
 * @package PICOT_SEO_WRITING\Database
 */

namespace PICOT_SEO_WRITING\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * データベース基底クラス
 */
class Database
{

    /**
     * WordPressデータベースオブジェクト
     *
     * @var \wpdb
     */
    protected $wpdb;

    /**
     * テーブル名
     *
     * @var string
     */
    protected $table_name;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * テーブル名を取得
     *
     * @return string テーブル名
     */
    public function get_table_name()
    {
        return $this->table_name;
    }
}
