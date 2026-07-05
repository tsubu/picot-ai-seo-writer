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
     *
     * @var string|false
     */
    private static $log_dir = false;

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
        self::$log_dir = self::get_log_directory();
        if (self::$log_dir) {
            self::ensure_log_directory_protection();
        }
    }

    /**
     * Resolve the uploads-based log directory path.
     *
     * @return string|false
     */
    private static function get_log_directory()
    {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return false;
        }

        return trailingslashit($upload_dir['basedir']) . 'picot-ai-seo-writer/logs/';
    }

    /**
     * Create log directory guard files when missing.
     */
    private static function ensure_log_directory_protection()
    {
        if (!self::$log_dir) {
            return;
        }

        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
        }

        $index = self::$log_dir . 'index.php';
        if (!file_exists($index)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Bootstrap guard file for logs directory.
            file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }

    /**
     * ログを記録
     *
     * @param string $message メッセージ
     * @param string $level ログレベル
     * @param array  $context コンテキスト情報
     */
    public static function log($message, $level = self::LEVEL_INFO, $context = [])
    {
        if (!self::$log_dir) {
            self::init();
        }

        if (!self::$log_dir) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_file = self::$log_dir . 'picot-ai-seo-writer-' . current_time('Y-m-d') . '.log';

        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            $level,
            $message
        );

        if (!empty($context)) {
            $log_entry .= 'Context: ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }

        $log_entry .= str_repeat('-', 80) . "\n";

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($log_entry, 3, $log_file);
    }

    /**
     * デバッグログ
     *
     * @param string $message メッセージ
     * @param array  $context コンテキスト情報
     */
    public static function debug($message, $context = [])
    {
        self::log($message, self::LEVEL_DEBUG, $context);
    }

    /**
     * 情報ログ
     *
     * @param string $message メッセージ
     * @param array  $context コンテキスト情報
     */
    public static function info($message, $context = [])
    {
        self::log($message, self::LEVEL_INFO, $context);
    }

    /**
     * 警告ログ
     *
     * @param string $message メッセージ
     * @param array  $context コンテキスト情報
     */
    public static function warning($message, $context = [])
    {
        self::log($message, self::LEVEL_WARNING, $context);
    }

    /**
     * エラーログ
     *
     * @param string $message メッセージ
     * @param array  $context コンテキスト情報
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

        if (!self::$log_dir) {
            return;
        }

        $files = glob(self::$log_dir . 'picot-ai-seo-writer-*.log');
        if (!is_array($files)) {
            return;
        }

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

        if (!self::$log_dir) {
            return '';
        }

        $log_file = self::$log_dir . 'picot-ai-seo-writer-' . current_time('Y-m-d') . '.log';

        if (!file_exists($log_file)) {
            return '';
        }

        $file_lines = file($log_file);
        if (!is_array($file_lines)) {
            return '';
        }

        $recent_lines = array_slice($file_lines, -$lines);

        return implode('', $recent_lines);
    }
}
