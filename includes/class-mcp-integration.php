<?php

/**
 * Picot MCP integration for SEO Writer.
 *
 * @package PICOT_SEO_WRITING
 */

namespace PICOT_SEO_WRITING;

use PICOT_SEO_WRITING\REST\Content_Endpoint;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the picot_seo_writer MCP tool via Picot MCP.
 */
class Mcp_Integration
{

    const FEATURE = 'seo_writer';
    const ABILITY = 'picot-ai-seo-writer/article';
    const TOOL    = 'picot_seo_writer';

    /**
     * Hook into Picot MCP when available.
     *
     * @return void
     */
    public static function init()
    {
        add_action('plugins_loaded', [self::class, 'boot'], 20);
    }

    /**
     * Register filters after Picot MCP has loaded.
     *
     * @return void
     */
    public static function boot()
    {
        if (!class_exists('Picot_Mcp_Abilities')) {
            return;
        }

        add_filter('picot_mcp_ability_names', [self::class, 'filter_ability_names']);
        add_filter('picot_mcp_tool_name_map', [self::class, 'filter_tool_name_map']);
        add_filter('picot_mcp_tool_feature_map', [self::class, 'filter_tool_feature_map']);
        add_action('wp_abilities_api_categories_init', [self::class, 'register_category']);
        add_action('wp_abilities_api_init', [self::class, 'register_ability']);
    }

    /**
     * @param string[] $names Ability names.
     * @return string[]
     */
    public static function filter_ability_names($names)
    {
        if (!is_array($names)) {
            $names = [];
        }
        if (self::is_feature_enabled()) {
            $names[] = self::ABILITY;
        }
        return $names;
    }

    /**
     * @param array<string, string> $map Ability => tool.
     * @return array<string, string>
     */
    public static function filter_tool_name_map($map)
    {
        if (!is_array($map)) {
            $map = [];
        }
        $map[self::ABILITY] = self::TOOL;
        return $map;
    }

    /**
     * @param array<string, string> $map Tool => feature.
     * @return array<string, string>
     */
    public static function filter_tool_feature_map($map)
    {
        if (!is_array($map)) {
            $map = [];
        }
        $map[self::TOOL] = self::FEATURE;
        return $map;
    }

    /**
     * @return bool
     */
    private static function is_feature_enabled()
    {
        return class_exists('Picot_Mcp_Settings')
            && \Picot_Mcp_Settings::instance()->is_feature_enabled(self::FEATURE);
    }

    /**
     * @return void
     */
    public static function register_category()
    {
        if (!function_exists('wp_register_ability_category')) {
            return;
        }
        wp_register_ability_category(
            'picot-ai-seo-writer',
            [
                'label'       => __('Picot AI SEO Writer', 'picot-ai-seo-writer'),
                'description' => __('Keyword-based SEO article generation.', 'picot-ai-seo-writer'),
            ]
        );
    }

    /**
     * @return void
     */
    public static function register_ability()
    {
        if (!function_exists('wp_register_ability') || !self::is_feature_enabled()) {
            return;
        }

        wp_register_ability(
            self::ABILITY,
            [
                'label'               => __('SEO article generation', 'picot-ai-seo-writer'),
                'description'         => __('Generate a research-backed SEO article from a target keyword. Optionally save it as a post or page.', 'picot-ai-seo-writer'),
                'category'            => 'picot-ai-seo-writer',
                'input_schema'        => \Picot_Mcp_Abilities::action_input_schema(
                    ['generate'],
                    [
                        'keyword' => [
                            'type'        => 'string',
                            'description' => __('Target SEO keyword (required).', 'picot-ai-seo-writer'),
                        ],
                        'notes' => [
                            'type'        => 'string',
                            'description' => __('Optional additional writing notes.', 'picot-ai-seo-writer'),
                        ],
                        'writing_style' => [
                            'type'        => 'string',
                            'description' => __('Writing style key (e.g. casual).', 'picot-ai-seo-writer'),
                        ],
                        'post_id' => [
                            'type'        => 'integer',
                            'description' => __('Existing post/page ID to attach metadata or update when apply is true.', 'picot-ai-seo-writer'),
                        ],
                        'type' => [
                            'type'        => 'string',
                            'enum'        => ['post', 'page'],
                            'description' => __('Content type when creating a new item (default: post).', 'picot-ai-seo-writer'),
                        ],
                        'status' => [
                            'type'        => 'string',
                            'description' => __('Post status when apply is true (default: draft).', 'picot-ai-seo-writer'),
                        ],
                        'apply' => [
                            'type'        => 'boolean',
                            'description' => __('When true, create or update the post/page with the generated title and content.', 'picot-ai-seo-writer'),
                        ],
                    ],
                    ['keyword']
                ),
                'output_schema'       => \Picot_Mcp_Abilities::output_schema(),
                'execute_callback'    => [self::class, 'execute'],
                'permission_callback' => [self::class, 'permission'],
                'meta'                => [
                    'annotations' => [
                        'readonly' => false,
                    ],
                ],
            ]
        );
    }

    /**
     * @param array $input Input.
     * @return bool|\WP_Error
     */
    public static function permission($input)
    {
        $action = isset($input['action']) ? sanitize_key($input['action']) : '';
        $check  = \Picot_Mcp_Permissions::assert(self::FEATURE, $action, is_array($input) ? $input : []);
        if (is_wp_error($check)) {
            return $check;
        }

        $post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
        if ($post_id > 0) {
            return \Picot_Mcp_Permissions::assert_post_cap('edit', $post_id);
        }

        $type = isset($input['type']) ? sanitize_key($input['type']) : 'post';
        if ('page' === $type && !current_user_can('edit_pages')) {
            return \Picot_Mcp_Errors::make(
                'wordpress_permission_denied',
                __('WordPress capability "edit_pages" is required.', 'picot-ai-seo-writer')
            );
        }

        return true;
    }

    /**
     * @param array $input Input.
     * @return array
     */
    public static function execute($input)
    {
        $action = sanitize_key($input['action'] ?? '');
        if ('generate' !== $action) {
            return \Picot_Mcp_Abilities::respond(
                self::FEATURE,
                $action,
                \Picot_Mcp_Errors::make('invalid_parameter', __('Unsupported action.', 'picot-ai-seo-writer'))
            );
        }

        $keyword = sanitize_text_field((string) ($input['keyword'] ?? ''));
        if ('' === $keyword) {
            return \Picot_Mcp_Abilities::respond(
                self::FEATURE,
                $action,
                \Picot_Mcp_Errors::make('invalid_parameter', __('Target keyword is required.', 'picot-ai-seo-writer'))
            );
        }

        $request = new \WP_REST_Request('POST');
        $request->set_param('keyword', $keyword);
        $request->set_param('additional_notes', sanitize_textarea_field((string) ($input['notes'] ?? '')));
        $request->set_param('post_id', absint($input['post_id'] ?? 0));
        if (!empty($input['writing_style'])) {
            $request->set_param('writing_style', sanitize_key((string) $input['writing_style']));
        }

        $endpoint = new Content_Endpoint();
        $response = $endpoint->generate_article_direct($request);

        if (is_wp_error($response)) {
            return \Picot_Mcp_Abilities::respond(self::FEATURE, $action, $response);
        }

        $payload = self::unwrap_rest_data($response);
        if (!is_array($payload) || empty($payload['success'])) {
            $message = is_array($payload) && !empty($payload['error'])
                ? (string) $payload['error']
                : __('Article generation failed.', 'picot-ai-seo-writer');
            return \Picot_Mcp_Abilities::respond(
                self::FEATURE,
                $action,
                \Picot_Mcp_Errors::make('internal_error', $message)
            );
        }

        $data    = (isset($payload['data']) && is_array($payload['data'])) ? $payload['data'] : $payload;
        $title   = isset($data['title']) ? (string) $data['title'] : '';
        $content = isset($data['article_content']) ? (string) $data['article_content'] : '';
        $excerpt = isset($data['excerpt']) ? (string) $data['excerpt'] : '';
        $result  = [
            'title'       => $title,
            'content'     => $content,
            'excerpt'     => $excerpt,
            'sources'     => isset($data['sources']) ? $data['sources'] : [],
            'research_id' => isset($data['research_id']) ? (int) $data['research_id'] : 0,
            'post_id'     => absint($input['post_id'] ?? 0),
            'applied'     => false,
        ];

        if (!empty($input['apply'])) {
            $saved = self::apply_to_post($input, $title, $content, $excerpt);
            if (is_wp_error($saved)) {
                return \Picot_Mcp_Abilities::respond(self::FEATURE, $action, $saved);
            }
            $result['post_id'] = (int) $saved;
            $result['applied'] = true;
        }

        return \Picot_Mcp_Abilities::respond(self::FEATURE, $action, $result);
    }

    /**
     * @param mixed $response REST response.
     * @return mixed
     */
    private static function unwrap_rest_data($response)
    {
        if ($response instanceof \WP_REST_Response) {
            return $response->get_data();
        }
        return $response;
    }

    /**
     * @param array  $input   Input.
     * @param string $title   Title.
     * @param string $content Content.
     * @param string $excerpt Excerpt.
     * @return int|\WP_Error Post ID.
     */
    private static function apply_to_post(array $input, $title, $content, $excerpt)
    {
        $post_id = absint($input['post_id'] ?? 0);
        $status  = isset($input['status']) ? sanitize_key((string) $input['status']) : 'draft';
        if (!in_array($status, ['draft', 'pending', 'private', 'publish'], true)) {
            $status = 'draft';
        }

        $postarr = [
            'post_title'   => $title !== '' ? $title : __('Untitled', 'picot-ai-seo-writer'),
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $status,
        ];

        if ($post_id > 0) {
            $cap = \Picot_Mcp_Permissions::assert_post_cap('edit', $post_id);
            if (is_wp_error($cap)) {
                return $cap;
            }
            $postarr['ID'] = $post_id;
            $updated       = wp_update_post(wp_slash($postarr), true);
            return is_wp_error($updated) ? $updated : (int) $updated;
        }

        $type = isset($input['type']) ? sanitize_key((string) $input['type']) : 'post';
        if (!in_array($type, ['post', 'page'], true)) {
            $type = 'post';
        }
        $postarr['post_type'] = $type;

        $created = wp_insert_post(wp_slash($postarr), true);
        return is_wp_error($created) ? $created : (int) $created;
    }
}
