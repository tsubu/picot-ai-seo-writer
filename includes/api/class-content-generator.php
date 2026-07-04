<?php
/**
 * Content Generator Class - Gemini Edition
 *
 * @package PICOT_SEO_WRITING\API
 */

namespace PICOT_SEO_WRITING\API;

if (!defined('ABSPATH')) {
    exit;
}

// 親クラスをGemini版に変更
require_once __DIR__ . '/class-gemini-client.php';

/**
 * Content Generator Class
 */
class Content_Generator extends Gemini_Client
{
    /**
     * Generate Title and Headings
     */
    public function generate_title_and_headings($keyword, $urls = [], $additional_notes = '', $style = PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE)
    {
        $model = get_option('picot_seo_writing_text_model', '');
        $locale = get_locale();
        $lang = (strpos($locale, 'ja') === 0) ? '日本語' : 'English';

        $prompt = $this->build_title_prompt($keyword, $urls, $additional_notes, $style, $lang);

        $contents = [
            ['parts' => [['text' => $prompt]]]
        ];

        // 検索機能を有効化
        $options = [
            'use_search' => true,
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];

        $response = $this->generate_content($model, $contents, $options);
        $text = $this->extract_text($response);

        return $this->parse_title_response($text);
    }

    /**
     * Generate Article Content
     */
    public function generate_article($headings, $additional_notes = '', $style = PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE)
    {
        $model = get_option('picot_seo_writing_text_model', '');
        $locale = get_locale();
        $lang = (strpos($locale, 'ja') === 0) ? '日本語' : 'English';

        $prompt = $this->build_article_prompt($headings, $additional_notes, $style, $lang);

        $contents = [
            ['parts' => [['text' => $prompt]]]
        ];

        $options = [
            'use_search' => true, // 本文執筆時も検索結果を参照可能にする
            'temperature' => 0.7,
            'max_tokens' => 8192
        ];

        $response = $this->generate_content($model, $contents, $options);
        $content = $this->extract_text($response);
        
        // Markdownコードブロックの除去
        $content = preg_replace('/^```[a-zA-Z]*\n?/', '', $content);
        $content = preg_replace('/\n?```$/', '', trim($content));
        
        return trim($content);
    }

    /**
     * Build Title Prompt
     */
    private function build_title_prompt($keyword, $urls, $additional_notes, $style, $lang)
    {
        $style_desc = $this->get_style_description($style, $lang);

        $prompt = "あなたは世界最高峰のSEOコンサルタントです。ターゲットキーワード「{$keyword}」に対して、競合を圧倒する「見出し構成（サブタイトル）」をまず作成してください。\n\n";
        $prompt .= "ターゲットキーワード: {$keyword}\n";
        if (!empty($additional_notes)) {
            $prompt .= "追加要望: {$additional_notes}\n";
        }
        $prompt .= "文章スタイル: {$style_desc}\n\n";
        $prompt .= "【任務: 生成の第一歩 - サブタイトルの決定】\n";
        $prompt .= "1. **13件の分析**: Google検索ツールを使用して、国内10件・米国3件の計13件の記事構成を徹底分析してください。\n";
        $prompt .= "2. **サブタイトルの抽出**: 読者の検索意図を完全に満たし、競合を上回るSEO強度を持つ最適なサブタイトル（H2, H3見出し）を、論理的な順序で提案してください。\n";
        $prompt .= "3. **最終目標への布石**: この後の工程で「本文執筆」「記事概要の作成」「最終タイトルの決定」を行うため、その基盤となる盤石な構成にしてください。\n\n";
        $prompt .= "【出力形式】\n";
        $prompt .= "仮タイトル: [暫定的なタイトル]\n";
        $prompt .= "H2: [見出し1]\n";
        $prompt .= "  H3: [小見出し1-1]\n";
        $prompt .= "H2: [見出し2]\n";
        $prompt .= "...";

        return $prompt;
    }

    /**
     * Build Article Prompt
     */
    private function build_article_prompt($headings, $additional_notes, $style, $lang)
    {
        $style_desc = $this->get_style_description($style, $lang);

        $prompt = "あなたは世界最高峰のWebライター兼SEOスペシャリストです。提示された構成案（サブタイトル）に基づき、以下の順序で記事を完成させてください。\n\n";
        $prompt .= "【生成順序】\n";
        $prompt .= "1. **本文執筆**: 各サブタイトルに対して、競合13件を凌駕する高品質な内容を執筆してください。\n";
        $prompt .= "2. **記事概要の作成**: 執筆した内容を要約し、読者の興味を惹きつけるメタディスクリプション級の概要を作成してください。\n";
        $prompt .= "3. **最終タイトルの決定**: 本文の内容に最も合致し、かつ検索結果でクリックしたくなる最強のタイトルを最後に導き出してください。\n\n";

        $prompt .= "構成:\n{$headings}\n\n";
        if (!empty($additional_notes)) {
            $prompt .= "追加要望: {$additional_notes}\n";
        }
        $prompt .= "文章スタイル: {$style_desc}\n\n";

        $prompt .= "【執筆ガイドライン】\n";
        $prompt .= "- 13件の分析（国内10・米国3）を反映し、日本国内の競合にはない深い洞察を含めてください。\n";
        $prompt .= "- 本文はWordPressでそのまま使えるHTML形式（p, h2, h3, ul, li等）で出力してください。\n";
        $prompt .= "- **最下部**に、以下の形式で「記事概要」と「最終タイトル」を付加してください。\n\n";
        $prompt .= "[SOURCES_START]\n[ここに参照URL]\n[SOURCES_END]\n";
        $prompt .= "[OVERVIEW_START]\n[ここに記事概要]\n[OVERVIEW_END]\n";
        $prompt .= "[FINAL_TITLE_START]\n[ここに最終タイトル]\n[FINAL_TITLE_END]";

        return $prompt;
    }

    /**
     * キーワードから直接記事を生成 (調査ステップなし)
     */
    public function generate_article_direct($keyword, $additional_notes = '', $style = PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE, $language = 'japanese')
    {
        $model = get_option('picot_seo_writing_text_model', '');
        
        $lang_map = [
            'japanese' => '日本語',
            'english' => 'English',
            'simplified_chinese' => '简体中文',
            'traditional_chinese' => '繁體中文'
        ];
        $target_lang = $lang_map[$language] ?? '日本語';

        $prompt = $this->build_direct_prompt($keyword, $additional_notes, $style, $target_lang);

        $contents = [
            ['parts' => [['text' => $prompt]]]
        ];

        $options = [
            'use_search' => true,
            'temperature' => 0.7,
            'max_tokens' => 8192
        ];

        $response = $this->generate_content($model, $contents, $options);
        $content = $this->extract_text($response);
        
        // Markdownコードブロックの除去
        $content = preg_replace('/^```[a-zA-Z]*\n?/', '', $content);
        $content = preg_replace('/\n?```$/', '', trim($content));

        // groundingMetadata から実際のソースURLを抽出
        $grounding_urls = [];
        $candidates = $response['candidates'] ?? [];
        foreach ($candidates as $candidate) {
            $chunks = $candidate['groundingMetadata']['groundingChunks'] ?? [];
            foreach ($chunks as $chunk) {
                $uri = $chunk['web']['uri'] ?? '';
                $title = $chunk['web']['title'] ?? '';
                if (!empty($uri)) {
                    $grounding_urls[] = ['url' => $uri, 'title' => $title];
                }
            }
        }

        return [
            'text'           => trim($content),
            'grounding_urls' => $grounding_urls,
        ];
    }

    /**
     * Build Direct Article Prompt
     */
    private function build_direct_prompt($keyword, $additional_notes, $style, $lang)
    {
        $style_desc = $this->get_style_description($style, $lang);

        $prompt = "あなたは世界最高峰のSEOコンサルタント兼Webライターです。ターゲットキーワード「{$keyword}」について、ユーザーの検索意図（インテント）を深く洞察し、最高の結果をもたらす記事を執筆してください。\n\n";
        $prompt .= "【基本情報】\n";
        $prompt .= "- ターゲットキーワード: {$keyword}\n";
        if (!empty($additional_notes)) {
            $prompt .= "- 追加要望: {$additional_notes}\n";
        }
        $prompt .= "- 文章スタイル: {$style_desc}\n";
        $prompt .= "- 出力言語: {$lang}\n\n";

        $prompt .= "【執筆ルール（厳守）】\n";
        $prompt .= "1. **最新の検索結果（Grounding）に基づく**: 独自の推測ではなく、Google検索で得られた最新の事実のみを記述してください。\n";
        $prompt .= "2. **HTML形式で出力**: WordPressでそのまま使えるよう <p>, <h2>, <h3>, <ul>, <li>, <strong>, <a> タグを使用してください。<html> や <body> タグは不要です。\n";
        $prompt .= "3. **外部リンクの配置**: 公式サイトや引用元など、読者にとって必要なリンクは `<a>` タグを用いて本文の文脈内に自然に配置してください。ただし、`vertexaisearch.google.com` などの内部検索用URLや、記事末尾に「参考URL」や「情報元一覧」といった単独のリンクリスト・セクションは**絶対に作成しないでください**。\n";
        $prompt .= "4. **情報の統合**: 国内外の最新トレンドを統合し、読者の課題を解決する実用的な内容にしてください。\n\n";

        $prompt .= "【記事構成】\n";
        $prompt .= "- リード文: 読者の悩みに共感し、解決策を提示\n";
        $prompt .= "- 結論/概要: 最初に答えを提示\n";
        $prompt .= "- 詳細解説: 具体的かつ正確な情報を提供\n";
        $prompt .= "- まとめ: 次のアクションを提示\n\n";

        $prompt .= "【データ付加（最下部に必ず記述）】\n";
        $prompt .= "[SOURCES_START]\n[AIが検索に使用した実際の情報元URLを1行ずつリストアップ。google.com/search等のラッパーURLは禁止]\n[SOURCES_END]\n";
        $prompt .= "[OVERVIEW_START]\n[記事概要（120文字程度）]\n[OVERVIEW_END]\n";
        $prompt .= "[FINAL_TITLE_START]\n[SEOに強いタイトル]\n[FINAL_TITLE_END]\n\n";

        $prompt .= "執筆を開始してください。必ず「{$lang}」で出力してください。";

        return $prompt;
    }

    /**
     * 既存のコンテンツから画像提案JSONを生成する (Optimizer互換)
     */
    public function insert_image_prompts($content) {
        $model = get_option('picot_seo_writing_text_model', 'gemini-1.5-flash');
        $plain = mb_substr(wp_strip_all_tags($content), 0, 4000); // 短くして応答トークンを確保

        $prompt  = "You are a visual content editor for a WordPress blog.\n";
        $prompt .= "Analyze the article and return image placement suggestions as JSON.\n\n";

        $prompt .= "JSON structure (return ONLY this JSON, no extra text):\n";
        $prompt .= "{\n";
        $prompt .= "  \"featured_prompt\": \"REQUIRED. English prompt for the FEATURED IMAGE. MAX 120 CHARACTERS.\",\n";
        $prompt .= "  \"featured_text\": \"REQUIRED. Short catchy phrase (5-8 words) in the article's language.\",\n";
        $prompt .= "  \"suggestions\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"location\": \"Exact text snippet (15-30 chars) from the article body.\",\n";
        $prompt .= "      \"description\": \"Brief image description in the article's language. MAX 60 CHARS.\",\n";
        $prompt .= "      \"prompt\": \"English image generation prompt. MAX 120 CHARACTERS.\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";

        $prompt .= "Rules:\n";
        $prompt .= "- suggestions: exactly 5 items.\n";
        $prompt .= "- Total = 1 featured + 5 body = 6 images.\n";
        $prompt .= "- ALL prompt values MUST be under 120 characters. Be concise.\n";
        $prompt .= "- location must be exact verbatim text from the article.\n";
        $prompt .= "- description/featured_text in article language; prompt always in English.\n\n";

        $prompt .= "Article:\n" . $plain;

        $contents = [['parts' => [['text' => $prompt]]]];
        $options  = [
            'temperature'       => 0.2,
            'max_tokens'        => 4096,
            'response_mime_type' => 'application/json', // JSON強制
        ];

        $response        = $this->generate_content($model, $contents, $options);
        $updated_content = $this->extract_text($response);

        // responseMimeType=application/json のときは extract_text が空になる場合があるため
        // candidates[0].content.parts[0].text を直接確認
        if (empty($updated_content)) {
            $updated_content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        }

        // Markdown コードブロック除去（念のため）
        $updated_content = preg_replace('/^```[a-zA-Z]*\n?/', '', $updated_content);
        $updated_content = preg_replace('/\n?```$/', '', trim($updated_content));

        return trim($updated_content);
    }

    private function get_style_description($style, $lang)
    {
        $styles = [
            'professional' => 'プロフェッショナルで信頼感のある',
            'casual' => '親しみやすくカジュアルな',
            'friendly' => 'フレンドリーで優しい',
            'technical' => '専門的で技術的な解説を含む',
        ];
        return $styles[$style] ?? $styles[PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE];
    }

    /**
     * Parse Title Response
     */
    private function parse_title_response($content)
    {
        $lines = explode("\n", $content);
        $result = ['title' => '', 'headings' => []];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^タイトル[:：]\s*(.+)$/u', $line, $matches)) {
                $result['title'] = trim($matches[1]);
            } elseif (preg_match('/^H2[:：]\s*(.+)$/i', $line, $matches)) {
                $result['headings'][] = ['level' => 2, 'text' => trim($matches[1])];
            } elseif (preg_match('/^H3[:：]\s*(.+)$/i', $line, $matches)) {
                $result['headings'][] = ['level' => 3, 'text' => trim($matches[1])];
            }
        }
        return $result;
    }
}
