=== Picot AI SEO Writer ===
Contributors: tsubu
Tags: seo, ai, gemini, writing, content
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI SEO writing assistant by Picot. Generate research-backed articles, headings, and image prompts with Google Gemini.

== Description ==

Picot AI SEO Writer helps site administrators create high-quality, SEO-friendly blog posts using the Google Gemini API. Enter a target keyword in the block or classic editor, and the plugin generates article content grounded in live Google Search results.

= Key Features =

* **Keyword-to-Article Generation**: Create a full article directly from a target keyword and optional notes.
* **Google Search Grounding**: Uses Gemini's built-in search grounding so content reflects current web sources.
* **Editor Sidebar Workflow**: Works in the block editor and classic editor with keyword, style, and source tracking.
* **Writing Style Presets**: Choose from professional, casual, friendly, technical, and other tone options.
* **Image Prompt Suggestions**: Analyze generated content and save featured/body image prompts for downstream workflows.
* **Reference URL List**: Review resolved source URLs used during generation.

== External services ==

This plugin connects to the **Google Generative Language API (Gemini)** provided by Google LLC.

* **What the service is used for**: Keyword research grounding, title/heading generation, article writing, and image prompt suggestions.
* **What data is sent and when**: Your target keyword, optional writing notes, and post content are sent to Google only when you manually trigger generation or image prompt analysis in the editor. No data is sent automatically in the background.
* **Legal links**:
    * Service provider: Google LLC
    * Terms of Service: https://ai.google.dev/terms
    * Privacy Policy: https://policies.google.com/privacy

== Installation ==

1. Upload the `picot-ai-seo-writer` folder to the `/wp-content/plugins/` directory, or install through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Open **Settings > Picot AI SEO Writer** and enter your Gemini API key from Google AI Studio.
4. Select a text model, then open a post and use the **Picot AI SEO Writer** sidebar to generate content.

== Frequently Asked Questions ==

= Do I need a separate Google Search API key? =

No. This plugin uses Gemini's built-in Google Search Grounding feature. You only need a Gemini API key from Google AI Studio.

= Where do I get a Gemini API key? =

Create a free API key at [Google AI Studio](https://aistudio.google.com/).

= Does it work with the Classic Editor? =

Yes. The plugin supports both the block editor and the classic editor.

= Is the plugin free? =

The plugin is free. Gemini API usage may incur costs depending on your Google AI Studio plan and usage.

== Screenshots ==

1. Settings page for Gemini API key and model selection.
2. Picot AI SEO Writer sidebar in the block editor.
3. Generated article content and reference URL list.

== Changelog ==

= 1.0.1 =
* Fixed Plugin URI and moved runtime logs to the uploads directory.
* Removed dev-only log viewer and plugin-directory file writes.
* Strengthened REST API permission checks for post-specific access.
* Stopped reading WordPress AI Client connector API keys directly.

= 1.0.0 =
* Initial public release.
* Gemini-powered article generation with Google Search grounding.
* Block editor and classic editor sidebar integration.
* Writing style presets and image prompt suggestions.
* Reference URL resolution for grounded sources.

== Upgrade Notice ==

= 1.0.1 =
Review fixes: logging location, REST permissions, and credential handling updates.

= 1.0.0 =
Initial release.
