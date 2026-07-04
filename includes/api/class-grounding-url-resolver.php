<?php
/**
 * Gemini Grounding URL resolver
 *
 * @package PICOT_SEO_WRITING\API
 */

namespace PICOT_SEO_WRITING\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vertex リダイレクト URL を実 URL へ解決する
 */
class Grounding_Url_Resolver
{
    /**
     * 参照 URL 一覧の Vertex リダイレクトを実 URL へ解決する
     *
     * @param array $sources [['url' => '...', 'title' => '...'], ...]
     * @return array
     */
    public function resolve_source_urls(array $sources)
    {
        $resolved       = [];
        $seen           = [];
        $resolve_budget = PICOT_SEO_WRITING_GROUNDING_RESOLVE_LIMIT;

        foreach ($sources as $item) {
            if (!is_array($item)) {
                continue;
            }

            $url   = isset($item['url']) ? esc_url_raw(trim((string) $item['url'])) : '';
            $title = isset($item['title']) ? sanitize_text_field(trim((string) $item['title'])) : '';

            if ($url === '') {
                continue;
            }

            if (self::is_internal_grounding_url($url)) {
                if ($resolve_budget > 0) {
                    $resolve_budget--;
                    $url = $this->resolve_redirect_url($url);
                }
                if (self::is_internal_grounding_url($url)) {
                    $fallback = $this->url_from_grounding_title($title);
                    if ($fallback !== '') {
                        $url = $fallback;
                    } else {
                        continue;
                    }
                }
            }

            if (self::is_internal_grounding_url($url) || isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;
            $resolved[] = [
                'url'   => $url,
                'title' => $title,
            ];
        }

        return $resolved;
    }

    /**
     * 一覧に Vertex 等の内部 Grounding URL が含まれるか
     *
     * @param array $sources 参照 URL 配列
     * @return bool
     */
    public function sources_need_resolution(array $sources)
    {
        foreach ($sources as $item) {
            if (!empty($item['url']) && self::is_internal_grounding_url($item['url'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Google Grounding の内部リダイレクト URL かどうか
     *
     * @param string $url URL
     * @return bool
     */
    public static function is_internal_grounding_url($url)
    {
        return (bool) preg_match(
            '/vertexaisearch|googleapis\.com|\.cloud\.google\.com|google\.com\/search/i',
            $url
        );
    }

    /**
     * Vertex リダイレクトを追跡して最終 URL を取得
     *
     * @param string $url リダイレクト URL
     * @return string
     */
    private function resolve_redirect_url($url)
    {
        if (!self::is_internal_grounding_url($url)) {
            return $url;
        }

        return $this->resolve_redirect_url_wp($url);
    }

    /**
     * wp_remote_* でリダイレクトを手動追跡
     *
     * @param string $url      開始 URL
     * @param int    $max_hops 最大ホップ数
     * @return string
     */
    private function resolve_redirect_url_wp($url, $max_hops = 5)
    {
        $current = $url;
        $args    = [
            'timeout'     => PICOT_SEO_WRITING_GROUNDING_RESOLVE_TIMEOUT,
            'redirection' => 0,
            'user-agent'  => 'Mozilla/5.0 (compatible; PICOT-SEO-Bot/1.0)',
        ];

        for ($hop = 0; $hop < $max_hops; $hop++) {
            $response = wp_remote_head($current, $args);
            if (is_wp_error($response)) {
                $response = wp_remote_get($current, array_merge($args, [
                    'headers' => ['Range' => 'bytes=0-0'],
                ]));
            }

            if (is_wp_error($response)) {
                break;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if (in_array($code, [301, 302, 303, 307, 308], true)) {
                $location = wp_remote_retrieve_header($response, 'location');
                if (empty($location)) {
                    break;
                }
                if (is_array($location)) {
                    $location = $location[0];
                }
                $current = $this->make_absolute_url($current, $location);
                if (!self::is_internal_grounding_url($current)) {
                    return esc_url_raw($current);
                }
                continue;
            }

            if ($code >= 200 && $code < 400) {
                return esc_url_raw($current);
            }

            break;
        }

        return $url;
    }

    /**
     * Grounding の title（ドメイン名）から表示用 URL を推定
     *
     * @param string $title Grounding chunk title
     * @return string
     */
    private function url_from_grounding_title($title)
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $title)) {
            return esc_url_raw($title);
        }

        if (preg_match('/^[a-z0-9][-a-z0-9.]*\.[a-z]{2,}(?:\/[^\s]*)?$/i', $title)) {
            return esc_url_raw('https://' . ltrim($title, '/'));
        }

        return '';
    }

    /**
     * 相対 Location を絶対 URL に変換
     *
     * @param string $base     基準 URL
     * @param string $location Location ヘッダー
     * @return string
     */
    private function make_absolute_url($base, $location)
    {
        $location = trim($location);
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = wp_parse_url($base);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return $location;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];

        if (strpos($location, '//') === 0) {
            return $parts['scheme'] . ':' . $location;
        }

        if (strpos($location, '/') === 0) {
            return $origin . $location;
        }

        $path = isset($parts['path']) ? $parts['path'] : '/';
        $dir  = preg_replace('#/[^/]*$#', '/', $path);

        return $origin . $dir . $location;
    }
}
