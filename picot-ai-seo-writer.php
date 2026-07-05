<?php

/**
 * Plugin Name: Picot AI SEO Writer
 * Plugin URI: https://github.com/tsubu/picot-ai-seo-writer
 * Description: Picot AI SEO Writer — generate research-backed SEO articles with Google Gemini from the post editor.
 * Version: 1.0.1
 * Author: PICOT
 * Author URI: https://picot.tokyo/aio/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: picot-ai-seo-writer
 * Domain Path: /languages
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Tested up to: 7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// require_once __DIR__ . '/fatal-catcher.php';

// プラグイン定数
define('PICOT_SEO_WRITING_VERSION', '1.0.1');
define('PICOT_SEO_WRITING_PLUGIN_FILE', __FILE__);
define('PICOT_SEO_WRITING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PICOT_SEO_WRITING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PICOT_SEO_WRITING_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 設定定数
define('PICOT_SEO_WRITING_HISTORY_LIMIT', 20);
define('PICOT_SEO_WRITING_API_TIMEOUT', 180);
define('PICOT_SEO_WRITING_IMAGE_API_TIMEOUT', 90);
define('PICOT_SEO_WRITING_LOG_RETENTION_DAYS', 90);
define('PICOT_SEO_WRITING_GROUNDING_RESOLVE_LIMIT', 15);
define('PICOT_SEO_WRITING_GROUNDING_RESOLVE_TIMEOUT', 5);
define('PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE', 'casual');

// オートローダー
require_once PICOT_SEO_WRITING_PLUGIN_DIR . 'includes/class-autoloader.php';
PICOT_SEO_WRITING\Autoloader::register();

// アクティベーション・ディアクティベーションフック
register_activation_hook(__FILE__, ['PICOT_SEO_WRITING\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['PICOT_SEO_WRITING\Deactivator', 'deactivate']);

// プラグイン初期化
function picot_ai_seo_writer_init()
{
    $plugin = new PICOT_SEO_WRITING\Plugin();
    $plugin->run();
}
add_action('plugins_loaded', 'picot_ai_seo_writer_init');
