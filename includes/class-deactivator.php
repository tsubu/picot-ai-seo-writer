<?php

/**
 * プラグインディアクティベーション処理
 *
 * @package PICOT_SEO_WRITING
 */

namespace PICOT_SEO_WRITING;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ディアクティベータークラス
 */
class Deactivator
{

    /**
     * プラグインディアクティベーション時の処理
     */
    public static function deactivate()
    {
        // クリーンアップスケジュールの削除
        $timestamp = wp_next_scheduled('picot_seo_writing_cleanup_old_logs');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'picot_seo_writing_cleanup_old_logs');
        }

        // 注: データベーステーブルとオプションは削除しない
        // アンインストール時に削除する（uninstall.php）
    }
}
