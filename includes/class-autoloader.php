<?php

/**
 * PSR-4準拠のオートローダー
 *
 * @package PICOT_SEO_WRITING
 */

namespace PICOT_SEO_WRITING;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * オートローダークラス
 */
class Autoloader
{

    /**
     * オートローダーを登録
     */
    public static function register()
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * クラスを自動読み込み
     *
     * @param string $class クラス名（名前空間含む）
     */
    private static function autoload($class)
    {
        $prefix = 'PICOT_SEO_WRITING\\';
        $base_dir = PICOT_SEO_WRITING_PLUGIN_DIR . 'includes/';

        // 名前空間プレフィックスチェック
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        // クラス名からファイルパスを生成
        $relative_class = substr($class, $len);

        // 名前空間の区切りをディレクトリ区切りに変換
        $relative_class = str_replace('\\', '/', $relative_class);

        // クラス名をファイル名に変換（例: Model_Manager -> class-model-manager.php）
        $file_parts = explode('/', $relative_class);
        $class_name = array_pop($file_parts);

        // キャメルケースをケバブケースに変換
        $file_name = 'class-' . self::camel_to_kebab($class_name) . '.php';

        // ディレクトリパスを小文字に変換
        $dir_path = strtolower(implode('/', $file_parts));

        // 完全なファイルパスを構築
        if (!empty($dir_path)) {
            $file = $base_dir . $dir_path . '/' . $file_name;
        } else {
            $file = $base_dir . $file_name;
        }

        // ファイルが存在すれば読み込み
        if (file_exists($file)) {
            require $file;
        }
    }

    /**
     * キャメルケースをケバブケースに変換
     *
     * @param string $input キャメルケース文字列
     * @return string ケバブケース文字列
     */
    private static function camel_to_kebab($input)
    {
        // アンダースコアをハイフンに変換
        $input = str_replace('_', '-', $input);
        // キャメルケースをケバブケースに変換
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $input));
    }
}
