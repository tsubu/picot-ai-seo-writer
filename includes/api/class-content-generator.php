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

use PICOT_SEO_WRITING\Admin\Admin;

// 親クラスをGemini版に変更
require_once __DIR__ . '/class-gemini-client.php';

/**
 * Content Generator Class
 */
class Content_Generator extends Gemini_Client
{
    /**
     * 有効な出力言語コード
     *
     * @var string[]
     */
    private const VALID_LANGUAGE_CODES = [
        'japanese',
        'english',
        'simplified_chinese',
        'traditional_chinese',
    ];

    /**
     * Generate Title and Headings
     */
    public function generate_title_and_headings($keyword, $urls = [], $additional_notes = '', $style = PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE, $language = null)
    {
        $model = get_option('picot_seo_writing_text_model', '');
        $language_code = $this->resolve_language_code($language);
        $lang = $this->language_to_label($language_code);

        $prompt = $this->build_title_prompt($keyword, $urls, $additional_notes, $style, $lang, $language_code);

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
    public function generate_article($headings, $additional_notes = '', $style = PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE, $language = null)
    {
        $model = get_option('picot_seo_writing_text_model', '');
        $language_code = $this->resolve_language_code($language);
        $lang = $this->language_to_label($language_code);

        $prompt = $this->build_article_prompt($headings, $additional_notes, $style, $lang, $language_code);

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
    private function build_title_prompt($keyword, $urls, $additional_notes, $style, $lang, $language_code)
    {
        $style_desc = $this->get_style_description($style, $lang, $language_code);

        if ($language_code === 'japanese') {
            $prompt = "あなたは世界最高峰のSEOコンサルタントです。ターゲットキーワード「{$keyword}」に対して、競合を圧倒する「見出し構成（サブタイトル）」をまず作成してください。\n\n";
            $prompt .= "ターゲットキーワード: {$keyword}\n";
            if (!empty($additional_notes)) {
                $prompt .= "追加要望: {$additional_notes}\n";
            }
            $prompt .= $this->format_style_prompt_line($style, $style_desc, $language_code);
            $prompt .= $this->format_common_prompt_block($language_code);
            $prompt .= "出力言語: {$lang}\n\n";
            $prompt .= "【任務: 生成の第一歩 - サブタイトルの決定】\n";
            $prompt .= "1. **13件の分析**: Google検索ツールを使用して、国内10件・米国3件の計13件の記事構成を徹底分析してください。\n";
            $prompt .= "2. **サブタイトルの抽出**: 読者の検索意図を完全に満たし、競合を上回るSEO強度を持つ最適なサブタイトル（H2, H3見出し）を、論理的な順序で提案してください。\n";
            $prompt .= "3. **最終目標への布石**: この後の工程で「本文執筆」「記事概要の作成」「最終タイトルの決定」を行うため、その基盤となる盤石な構成にしてください。\n\n";
            $prompt .= "【出力形式】\n";
            $prompt .= "仮タイトル: [暫定的なタイトル]\n";
            $prompt .= "H2: [見出し1]\n";
            $prompt .= "  H3: [小見出し1-1]\n";
            $prompt .= "H2: [見出し2]\n";
            $prompt .= "...\n\n";
            $prompt .= "見出しと仮タイトルは必ず「{$lang}」で出力してください。";

            return $prompt;
        }

        $prompt = "You are a world-class SEO consultant. Create a heading structure (subtitles) for the target keyword \"{$keyword}\" that outperforms competitors.\n\n";
        $prompt .= "Target keyword: {$keyword}\n";
        if (!empty($additional_notes)) {
            $prompt .= "Additional requirements: {$additional_notes}\n";
        }
        $prompt .= $this->format_style_prompt_line($style, $style_desc, $language_code);
        $prompt .= $this->format_common_prompt_block($language_code);
        $prompt .= "Output language: {$lang}\n\n";
        $prompt .= "[Task: Step 1 - Define subtitles]\n";
        $prompt .= "1. **Analyze 13 articles**: Use Google Search to analyze 10 Japan-market and 3 US-market top-ranking article structures.\n";
        $prompt .= "2. **Extract subtitles**: Propose H2/H3 headings that fully satisfy search intent and beat competitors.\n";
        $prompt .= "3. **Foundation for next steps**: This structure will be used for body writing, overview, and final title.\n\n";
        $prompt .= "[Output format]\n";
        $prompt .= "Draft title: [provisional title]\n";
        $prompt .= "H2: [heading 1]\n";
        $prompt .= "  H3: [subheading 1-1]\n";
        $prompt .= "H2: [heading 2]\n";
        $prompt .= "...\n\n";
        $prompt .= "Write all headings and the draft title in \"{$lang}\" only.";

        return $prompt;
    }

    /**
     * Build Article Prompt
     */
    private function build_article_prompt($headings, $additional_notes, $style, $lang, $language_code)
    {
        $style_desc = $this->get_style_description($style, $lang, $language_code);

        if ($language_code === 'japanese') {
            $prompt = "あなたは世界最高峰のWebライター兼SEOスペシャリストです。提示された構成案（サブタイトル）に基づき、以下の順序で記事を完成させてください。\n\n";
            $prompt .= "【生成順序】\n";
            $prompt .= "1. **本文執筆**: 各サブタイトルに対して、競合13件を凌駕する高品質な内容を執筆してください。\n";
            $prompt .= "2. **記事概要の作成**: 執筆した内容を要約し、読者の興味を惹きつけるメタディスクリプション級の概要を作成してください。\n";
            $prompt .= "3. **最終タイトルの決定**: 本文の内容に最も合致し、かつ検索結果でクリックしたくなる最強のタイトルを最後に導き出してください。\n\n";

            $prompt .= "構成:\n{$headings}\n\n";
            if (!empty($additional_notes)) {
                $prompt .= "追加要望: {$additional_notes}\n";
            }
            $prompt .= $this->format_style_prompt_line($style, $style_desc, $language_code);
            $prompt .= $this->format_common_prompt_block($language_code);
            $prompt .= "出力言語: {$lang}\n\n";

            $prompt .= "【執筆ガイドライン】\n";
            $prompt .= "- 13件の分析（国内10・米国3）を反映し、日本国内の競合にはない深い洞察を含めてください。\n";
            $prompt .= "- 本文はWordPressでそのまま使えるHTML形式（p, h2, h3, ul, li等）で出力してください。\n";
            $prompt .= "- 本文・概要・タイトルはすべて「{$lang}」で出力してください。\n";
            $prompt .= "- **最下部**に、以下の形式で「記事概要」と「最終タイトル」を付加してください。\n\n";
            $prompt .= "[SOURCES_START]\n[ここに参照URL]\n[SOURCES_END]\n";
            $prompt .= "[OVERVIEW_START]\n[ここに記事概要]\n[OVERVIEW_END]\n";
            $prompt .= "[FINAL_TITLE_START]\n[ここに最終タイトル]\n[FINAL_TITLE_END]";

            return $prompt;
        }

        $prompt = "You are a world-class web writer and SEO specialist. Complete the article based on the heading structure below.\n\n";
        $prompt .= "[Generation order]\n";
        $prompt .= "1. **Write body**: High-quality content for each heading that beats 13 analyzed competitors.\n";
        $prompt .= "2. **Write overview**: Meta-description-style summary that attracts readers.\n";
        $prompt .= "3. **Decide final title**: SEO-optimized, click-worthy title matching the content.\n\n";

        $prompt .= "Structure:\n{$headings}\n\n";
        if (!empty($additional_notes)) {
            $prompt .= "Additional requirements: {$additional_notes}\n";
        }
        $prompt .= $this->format_style_prompt_line($style, $style_desc, $language_code);
        $prompt .= $this->format_common_prompt_block($language_code);
        $prompt .= "Output language: {$lang}\n\n";

        $prompt .= "[Writing guidelines]\n";
        $prompt .= "- Reflect insights from 13 analyzed articles (10 Japan + 3 US market).\n";
        $prompt .= "- Output body as WordPress-ready HTML (p, h2, h3, ul, li, etc.).\n";
        $prompt .= "- Write ALL body text, overview, and title in \"{$lang}\" only.\n";
        $prompt .= "- Append overview and final title at the bottom in this format:\n\n";
        $prompt .= "[SOURCES_START]\n[reference URLs here]\n[SOURCES_END]\n";
        $prompt .= "[OVERVIEW_START]\n[article overview here]\n[OVERVIEW_END]\n";
        $prompt .= "[FINAL_TITLE_START]\n[final SEO title here]\n[FINAL_TITLE_END]";

        return $prompt;
    }

    /**
     * キーワードから直接記事を生成 (調査ステップなし)
     */
    public function generate_article_direct($keyword, $additional_notes = '', $style = PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE, $language = 'japanese')
    {
        $model = get_option('picot_seo_writing_text_model', '');
        $language_code = $this->resolve_language_code($language);
        $target_lang = $this->language_to_label($language_code);

        $prompt = $this->build_direct_prompt($keyword, $additional_notes, $style, $target_lang, $language_code);

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
    private function build_direct_prompt($keyword, $additional_notes, $style, $lang, $language_code)
    {
        $style_desc = $this->get_style_description($style, $lang, $language_code);

        if ($language_code === 'japanese') {
            $prompt = "あなたは世界最高峰のSEOコンサルタント兼Webライターです。ターゲットキーワード「{$keyword}」について、ユーザーの検索意図（インテント）を深く洞察し、最高の結果をもたらす記事を執筆してください。\n\n";
            $prompt .= "【基本情報】\n";
            $prompt .= "- ターゲットキーワード: {$keyword}\n";
            if (!empty($additional_notes)) {
                $prompt .= "- 追加要望: {$additional_notes}\n";
            }
            $prompt .= $this->format_style_prompt_line($style, $style_desc, $language_code, true);
            $prompt .= $this->format_common_prompt_block($language_code, true);
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

            $prompt .= "執筆を開始してください。本文・概要・タイトルはすべて「{$lang}」で出力してください。";

            return $prompt;
        }

        $prompt = "You are a world-class SEO consultant and web writer. Write an article for the target keyword \"{$keyword}\" that deeply addresses search intent and delivers the best possible result.\n\n";
        $prompt .= "[Basic information]\n";
        $prompt .= "- Target keyword: {$keyword}\n";
        if (!empty($additional_notes)) {
            $prompt .= "- Additional requirements: {$additional_notes}\n";
        }
        $prompt .= $this->format_style_prompt_line($style, $style_desc, $language_code, true);
        $prompt .= $this->format_common_prompt_block($language_code, true);
        $prompt .= "- Output language: {$lang}\n\n";

        $prompt .= "[Writing rules - strict]\n";
        $prompt .= "1. **Ground on latest search results**: Use only facts from Google Search grounding, not speculation.\n";
        $prompt .= "2. **Output as HTML**: Use <p>, <h2>, <h3>, <ul>, <li>, <strong>, <a> tags ready for WordPress. No <html> or <body> tags.\n";
        $prompt .= "3. **External links**: Place useful links inline with <a> tags. Never use vertexaisearch.google.com URLs or add a standalone reference URL section at the end.\n";
        $prompt .= "4. **Integrate trends**: Combine Japan and global insights into practical, actionable content.\n\n";

        $prompt .= "[Article structure]\n";
        $prompt .= "- Lead: empathize with the reader's problem and present a solution\n";
        $prompt .= "- Summary/conclusion upfront: answer early\n";
        $prompt .= "- Detailed sections: accurate, specific information\n";
        $prompt .= "- Closing: suggest next steps\n\n";

        $prompt .= "[Append at the bottom]\n";
        $prompt .= "[SOURCES_START]\n[One source URL per line from grounding. No google.com/search wrapper URLs]\n[SOURCES_END]\n";
        $prompt .= "[OVERVIEW_START]\n[Article overview, about 120 characters]\n[OVERVIEW_END]\n";
        $prompt .= "[FINAL_TITLE_START]\n[SEO-optimized title]\n[FINAL_TITLE_END]\n\n";

        $prompt .= "Begin writing. Output ALL body text, overview, and title in \"{$lang}\" only.";

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
        $prompt .= "- description/featured_text in article language; prompt always in English.\n";
        $prompt .= "- Space images evenly through the article. Never place two body images in adjacent paragraphs or consecutive sections.\n";
        $prompt .= "- Each location must be separated by substantial text (at least one full paragraph between image points).\n\n";

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

    /**
     * Resolve writing style text for prompts.
     *
     * When style is detailed_role, use the Advanced settings role text.
     *
     * @param string $style Style key.
     * @param string $lang Language label (unused, kept for call-site compatibility).
     * @param string $language_code Language code.
     * @return string
     */
    private function get_style_description($style, $lang, $language_code)
    {
        unset($lang);

        if ($style === 'detailed_role') {
            $detail = trim((string) get_option('picot_seo_writing_writing_style_detail', ''));
            if ($detail !== '') {
                return $detail;
            }

            return $language_code === 'japanese'
                ? '設定画面のロール設定（執筆スタイル詳細設定）に従う'
                : 'Follow the role settings (writing style details) from plugin settings';
        }

        if ($language_code === 'japanese') {
            $styles = [
                'professional' => 'プロフェッショナルで信頼感のある',
                'casual' => '親しみやすくカジュアルな',
                'friendly' => 'フレンドリーで優しい',
                'technical' => '専門的で技術的な解説を含む',
                'humorous' => 'ユーモアがあり軽快で面白い',
                'persuasive' => '説得力があり情熱的な',
                'informative' => '事実に基づき分かりやすく解説する',
            ];
        } else {
            $styles = [
                'professional' => 'professional and trustworthy',
                'casual' => 'friendly and casual',
                'friendly' => 'warm and approachable',
                'technical' => 'technical with expert explanations',
                'humorous' => 'light, fun, and humorous',
                'persuasive' => 'persuasive and passionate',
                'informative' => 'factual and informative',
            ];
        }

        return $styles[$style] ?? $styles[PICOT_SEO_WRITING_DEFAULT_WRITING_STYLE];
    }

    /**
     * Format the writing-style / role-settings line for prompts.
     *
     * @param string $style Style key.
     * @param string $style_desc Resolved style description.
     * @param string $language_code Language code.
     * @param bool   $list_item Whether to prefix with "- ".
     * @return string
     */
    private function format_style_prompt_line($style, $style_desc, $language_code, $list_item = false)
    {
        $prefix = $list_item ? '- ' : '';

        if ($style === 'detailed_role') {
            if ($language_code === 'japanese') {
                return "{$prefix}ロール設定（執筆スタイル詳細）:\n{$style_desc}\n";
            }

            return "{$prefix}Role settings (writing style details):\n{$style_desc}\n";
        }

        if ($language_code === 'japanese') {
            return "{$prefix}文章スタイル: {$style_desc}\n";
        }

        return "{$prefix}Writing style: {$style_desc}\n";
    }

    /**
     * Format the common article generation prompt block.
     *
     * @param string $language_code Language code.
     * @param bool   $list_item Whether to prefix with "- ".
     * @return string
     */
    private function format_common_prompt_block($language_code, $list_item = false)
    {
        $common_prompt = trim((string) get_option('picot_seo_writing_common_prompt', ''));
        if ($common_prompt === '') {
            return '';
        }

        $prefix = $list_item ? '- ' : '';

        if ($language_code === 'japanese') {
            return "{$prefix}記事生成共通プロンプト:\n{$common_prompt}\n";
        }

        return "{$prefix}Common article generation prompt:\n{$common_prompt}\n";
    }

    /**
     * 言語コードを正規化
     *
     * @param string|null $language 言語コード
     * @return string
     */
    private function resolve_language_code($language = null)
    {
        if (is_string($language) && in_array($language, self::VALID_LANGUAGE_CODES, true)) {
            return $language;
        }

        return Admin::get_default_output_language();
    }

    /**
     * 言語コードを表示名へ変換
     *
     * @param string $language_code 言語コード
     * @return string
     */
    private function language_to_label($language_code)
    {
        $lang_map = [
            'japanese' => '日本語',
            'english' => 'English',
            'simplified_chinese' => '简体中文',
            'traditional_chinese' => '繁體中文',
        ];

        return $lang_map[$language_code] ?? 'English';
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
            if (preg_match('/^(?:タイトル|仮タイトル|Draft title|Title)[:：]\s*(.+)$/ui', $line, $matches)) {
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
