<?php
/**
 * Google Search Simulator - Gemini Real Search Edition
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
 * Search Simulator Class
 */
class Search_Simulator extends Gemini_Client
{
    /**
     * Google検索をシミュレート（Gemini Groundingを使用して実データを取得）
     */
    public function simulate_search($keyword, $locale, $count = 10, $translate_keyword = false)
    {
        $model = get_option('picot_seo_writing_text_model', '');
        
        // 英語圏検索時の翻訳
        if ($translate_keyword && $locale === 'en') {
            $keyword = $this->translate_keyword($keyword);
        }

        $prompt = "「{$keyword}」というキーワードでGoogle検索を行い、上位表示されている主なWebサイトのタイトルとURLを{$count}件リストアップしてください。必ず実在するURLを報告してください。";
        
        $contents = [
            ['parts' => [['text' => $prompt]]]
        ];

        $options = [
            'use_search' => true,
            'temperature' => 0.1, // 低い温度で正確性を重視
            'max_tokens' => 1000
        ];

        try {
            $response = $this->generate_content($model, $contents, $options);
            return $this->extract_urls_from_grounding($response);
        } catch (\Exception $e) {
            $this->log_error('Search Simulation Error', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Grounding MetadataからURLを抽出
     */
    private function extract_urls_from_grounding($response)
    {
        $results = [];
        
        // Grounding Metadataがある場合（本物の検索結果）
        if (isset($response['candidates'][0]['groundingMetadata']['searchEntryPoint']['renderedContent'])) {
            // 直接URLリストを取得するのは難しい場合があるため、テキストからも抽出を試みる
        }

        // テキストレスポンスからURLとタイトルを抽出
        $text = $this->extract_text($response);
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            // URLが含まれる行を探す
            if (preg_match('/(https?:\/\/[^\s\)\>\]]+)/i', $line, $matches)) {
                $url = rtrim($matches[1], '.,;:!?');
                // タイトルの抽出（行のURL以外の部分を簡易的にタイトルとする）
                $title = trim(str_replace($url, '', $line));
                $title = preg_replace('/^[-*#\d\.\s:]+/u', '', $title); // 先頭の記号などを除去
                
                if (empty($title)) {
                    $title = $url;
                }

                $results[] = [
                    'url' => $url,
                    'title' => mb_strimwidth($title, 0, 100, '...')
                ];
            }
        }

        // 重複排除
        $unique_results = [];
        $seen_urls = [];
        foreach ($results as $res) {
            if (!in_array($res['url'], $seen_urls)) {
                $unique_results[] = $res;
                $seen_urls[] = $res['url'];
            }
        }

        return $unique_results;
    }

    /**
     * キーワード翻訳
     */
    private function translate_keyword($keyword)
    {
        $model = get_option('picot_seo_writing_text_model', '');
        $prompt = "Translate this Japanese keyword to English for SEO search: {$keyword}. Return only the translated keyword.";
        
        try {
            $response = $this->generate_content($model, [['parts' => [['text' => $prompt]]]], ['temperature' => 0.1]);
            return trim($this->extract_text($response)) ?: $keyword;
        } catch (\Exception $e) {
            return $keyword;
        }
    }
}
