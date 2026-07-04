<?php
if (!defined('ABSPATH')) {
    // WordPressを読み込み
    require_once __DIR__ . '/../../../wp-load.php';
}

if (!defined('ABSPATH')) {
    exit;
}

// 管理者としてログインしているか確認
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die(esc_html__('このページは管理者権限が必要です。先にWordPressにログインしてください。', 'picot-ai-seo-writer'));
}

// ロガーを読み込み
require_once __DIR__ . '/includes/class-logger.php';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$picot_seo_writing_lines = isset($_GET['lines']) ? intval($_GET['lines']) : 200;
$picot_seo_writing_logs = \PICOT_SEO_WRITING\Logger::get_recent_logs($picot_seo_writing_lines);

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>SEO GPT ログビューアー</title>
    <style>
        body {
            font-family: monospace;
            margin: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }

        h1 {
            color: #4ec9b0;
        }

        pre {
            background: #252526;
            padding: 20px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .controls {
            margin: 20px 0;
        }

        .controls a {
            display: inline-block;
            padding: 8px 16px;
            background: #0e639c;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            margin-right: 10px;
        }

        .controls a:hover {
            background: #1177bb;
        }

        .error {
            color: #f48771;
        }

        .warning {
            color: #dcdcaa;
        }

        .info {
            color: #4ec9b0;
        }

        .debug {
            color: #9cdcfe;
        }
    </style>
</head>

<body>
    <h1>SEO GPT ログビューアー</h1>

    <div class="controls">
        <a href="?lines=50">最新50行</a>
        <a href="?lines=100">最新100行</a>
        <a href="?lines=200">最新200行</a>
        <a href="?lines=500">最新500行</a>
        <a href="<?php echo esc_url(admin_url('options-general.php?page=picot-ai-seo-writer')); ?>">設定ページに戻る</a>
    </div>

    <pre><?php
            // ログレベルに応じて色分け（esc_html で XSS 対策した後に置換）
            $picot_seo_writing_logs_escaped = esc_html($picot_seo_writing_logs);
            $picot_seo_writing_logs_escaped = str_replace('[ERROR]', '<span class="error">[ERROR]</span>', $picot_seo_writing_logs_escaped);
            $picot_seo_writing_logs_escaped = str_replace('[WARNING]', '<span class="warning">[WARNING]</span>', $picot_seo_writing_logs_escaped);
            $picot_seo_writing_logs_escaped = str_replace('[INFO]', '<span class="info">[INFO]</span>', $picot_seo_writing_logs_escaped);
            $picot_seo_writing_logs_escaped = str_replace('[DEBUG]', '<span class="debug">[DEBUG]</span>', $picot_seo_writing_logs_escaped);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $picot_seo_writing_logs_escaped;
            ?></pre>
</body>

</html>