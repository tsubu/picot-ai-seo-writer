<?php

/**
 * ロガークラス
 *
 * @package PICOT_SEO_WRITING
 */

namespace PICOT_SEO_WRITING;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ロガークラス
 */
class Logger
{
    /**
     * ログディレクトリ
     */
    private static $log_dir;

    /**
     * ログレベル
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';

    /**
     * 初期化
     */
    public static function init()
    {
        self::$log_dir = PICOT_SEO_WRITING_PLUGIN_DIR . 'logs/';

        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
        }

        self::ensure_log_directory_protection();
    }

    /**
     * Create log directory guard files when missing (runtime only, not shipped in release ZIP).
     */
    private static function ensure_log_directory_protection()
    {
        $index = self::$log_dir . 'index.php';
        if (!file_exists($index)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Bootstrap guard file for logs directory.
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        $htaccess = self::$log_dir . '.htaccess';
        if (!file_exists($htaccess)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Apache guard file for logs directory.
            file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
        }
    }

    /**
     * ログを記録
     *
     * @param string $message メッセージ
     * @param string $level ログレベル
     * @param array $context コンテキスト情報
     */
    public static function log($message, $level = self::LEVEL_INFO, $context = [])
    {
        if (!self::$log_dir) {
            self::init();
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_file = self::$log_dir . 'picot-ai-seo-writer-' . current_time('Y-m-d') . '.log';

        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            $level,
            $message
        );

        // コンテキスト情報があれば追加
        if (!empty($context)) {
            $log_entry .= "Context: " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }

        $log_entry .= str_repeat('-', 80) . "\n";

        // ログファイルに書き込み
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($log_entry, 3, $log_file);
    }

    /**
     * デバッグログ
     *
     * @param string $message メッセージ
     * @param array $context コンテキスト情報
     */
    public static function debug($message, $context = [])
    {
        self::log($message, self::LEVEL_DEBUG, $context);
    }

    /**
     * 情報ログ
     *
     * @param string $message メッセージ
     * @param array $context コンテキスト情報
     */
    public static function info($message, $context = [])
    {
        self::log($message, self::LEVEL_INFO, $context);
    }

    /**
     * 警告ログ
     *
     * @param string $message メッセージ
     * @param array $context コンテキスト情報
     */
    public static function warning($message, $context = [])
    {
        self::log($message, self::LEVEL_WARNING, $context);
    }

    /**
     * エラーログ
     *
     * @param string $message メッセージ
     * @param array $context コンテキスト情報
     */
    public static function error($message, $context = [])
    {
        self::log($message, self::LEVEL_ERROR, $context);
    }

    /**
     * 古いログファイルを削除
     *
     * @param int $days 保持日数
     */
    public static function cleanup_old_logs($days = 30)
    {
        if (!self::$log_dir) {
            self::init();
        }

        $files = glob(self::$log_dir . 'picot-ai-seo-writer-*.log');
        $cutoff = time() - ($days * DAY_IN_SECONDS);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                wp_delete_file($file);
            }
        }
    }

    /**
     * 最新のログを取得
     *
     * @param int $lines 行数
     * @return string ログ内容
     */
    public static function get_recent_logs($lines = 100)
    {
        if (!self::$log_dir) {
            self::init();
        }

        $log_file = self::$log_dir . 'picot-ai-seo-writer-' . current_time('Y-m-d') . '.log';

        if (!file_exists($log_file)) {
            return 'ログファイルが見つかりません。';
        }

        $file_lines = file($log_file);
        $recent_lines = array_slice($file_lines, -$lines);

        return implode('', $recent_lines);
    }
}
