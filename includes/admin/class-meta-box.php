<?php

/**
 * メタボックスクラス
 *
 * @package PICOT_SEO_WRITING\Admin
 */

namespace PICOT_SEO_WRITING\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * メタボックスクラス
 */
class Meta_Box
{

    /**
     * メタボックスを追加
     */
    public function add_meta_boxes()
    {
        // ブロックエディタのサイドパネルに集約するため、
        // 従来のサイドバーメタボックスは非表示にします。
        /*
        add_meta_box(
            'picot_seo_writing_meta_box',
            __('Picot AI SEO Writer', 'picot-ai-seo-writer'),
            [$this, 'render'],
            ['post', 'page'],
            'side',
            'high'
        );
        */
    }

    /**
     * メタボックスを表示
     *
     * @param \WP_Post $post 投稿オブジェクト
     */
    public function render($post)
    {
?>
        <div id="picot-ai-seo-writer-meta-box">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                <div class="picot-card-icon icon-gemini" style="width: 24px; height: 24px; border-radius: 4px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L14.85 9.15L22 12L14.85 14.85L12 22L9.15 14.85L2 12L9.15 9.15L12 2Z" fill="white" />
                    </svg>
                </div>
                <h3 style="margin: 0; font-size: 14px;"><?php esc_html_e('Gemini article generation', 'picot-ai-seo-writer'); ?></h3>
            </div>

            <!-- ターゲットワード -->
            <div class="picot-ai-seo-writer-field">
                <label for="picot-ai-seo-writer-keyword">
                    <?php esc_html_e('Target keyword', 'picot-ai-seo-writer'); ?>
                </label>
                <input type="text"
                    id="picot-ai-seo-writer-keyword"
                    class="widefat"
                    placeholder="<?php esc_attr_e('e.g. WordPress SEO', 'picot-ai-seo-writer'); ?>" />
            </div>

            <!-- 希望追加内容 -->
            <div class="picot-ai-seo-writer-field">
                <label for="picot-ai-seo-writer-additional-notes">
                    <?php esc_html_e('Additional notes (optional)', 'picot-ai-seo-writer'); ?>
                </label>
                <textarea id="picot-ai-seo-writer-additional-notes"
                    class="widefat"
                    rows="4"
                    placeholder="<?php esc_attr_e('Add specific details, tone, or outline notes for the article', 'picot-ai-seo-writer'); ?>"></textarea>
            </div>

            <!-- 生成ボタン -->
            <div class="picot-ai-seo-writer-field" style="margin-top: 20px;">
                <button type="button" id="picot-ai-seo-writer-generate-btn" class="button button-primary widefat" style="height: 40px; font-weight: 600;">
                    <span class="dashicons dashicons-edit" style="margin-top: 8px; margin-right: 5px;"></span>
                    <?php esc_html_e('Generate article', 'picot-ai-seo-writer'); ?>
                </button>
            </div>

            <hr style="margin: 20px 0;" />

            <!-- 画像挿入ポイント探索 -->
            <div class="picot-ai-seo-writer-field">
                <button type="button" id="picot-ai-seo-writer-suggest-images-btn" class="button widefat">
                    <span class="dashicons dashicons-format-image" style="margin-top: 4px; margin-right: 5px;"></span>
                    <?php esc_html_e('Find image placement points', 'picot-ai-seo-writer'); ?>
                </button>
            </div>

            <!-- 画像提案リスト -->
            <div id="picot-ai-seo-writer-image-suggestions" style="margin-top: 15px;"></div>

            <!-- メッセージ -->
            <div id="picot-ai-seo-writer-message" style="margin-top: 15px;"></div>
        </div>
<?php
    }
}
