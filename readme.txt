=== Picot AI SEO Writer ===
Contributors: tsubu
Tags: seo, ai, gemini, writing, content
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI SEO writing assistant by Picot. Generate research-backed articles with Google Gemini via the WordPress AI Client.

== Description ==

Picot AI SEO Writer helps site administrators create high-quality, SEO-friendly blog posts using **Google Gemini** through WordPress. Enter a target keyword in the block or classic editor, and the plugin generates article content grounded in live Google Search results.

= Key Features =

* **Keyword-to-Article Generation**: Create a full article directly from a target keyword and optional notes.
* **Google Search Grounding**: Uses Gemini's web search grounding so content reflects current sources.
* **Editor Sidebar Workflow**: Works in the block editor and classic editor with keyword, style, and source tracking.
* **Writing Style Presets**: Choose from professional, casual, friendly, technical, and other tone options.
* **Image Prompt Suggestions**: Analyze generated content and save featured/body image prompts for downstream workflows.
* **Reference URL List**: Review resolved source URLs used during generation.

== External services ==

This plugin sends AI requests through the **WordPress AI Client** (WordPress 7.0+) and **requires the Google Gemini connector**. Install and activate the [Google AI connector plugin](https://wordpress.org/plugins/ai-provider-for-google/), then configure your Gemini API key under **Settings → Connectors**. Picot AI SEO Writer does not store or read provider API keys directly.

This plugin connects to the **Google Generative Language API (Gemini)** provided by Google LLC.

* **What the service is used for**: Keyword research grounding, title/heading generation, article writing, and image prompt suggestions.
* **What data is sent and when**: Your target keyword, optional writing notes, and post content are sent to Google Gemini only when you manually trigger generation or image prompt analysis in the editor. No data is sent automatically in the background.
* **Legal links**:
    * Service provider: Google LLC
    * Terms of Service: https://ai.google.dev/terms
    * Privacy Policy: https://policies.google.com/privacy

== Installation ==

1. Upload the `picot-ai-seo-writer` folder to the `/wp-content/plugins/` directory, or install through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress (requires WordPress 7.0 or later).
3. Install and activate the **Google (Gemini) AI connector** plugin, then open **Settings → Connectors** and connect your Gemini API key.
4. Open **Settings → Picot AI SEO Writer**, select a Gemini model, then use the **Picot AI SEO Writer** sidebar in a post to generate content.

== Frequently Asked Questions ==

= Which AI connector do I need? =

This plugin requires the **Google Gemini connector** (AI Provider for Google). Other connectors such as OpenAI or Anthropic are not supported.

= Do I need to enter an API key in this plugin? =

No. Configure your Gemini API key under **Settings → Connectors** in WordPress. Picot AI SEO Writer uses the WordPress AI Client and does not manage credentials itself.

= Do I need a separate Google Search API key? =

No. This plugin uses Gemini's built-in Google Search Grounding feature. You only need a Gemini API key configured in the Google connector.

= Does it work with the Classic Editor? =

Yes. The plugin supports both the block editor and the classic editor.

= Is the plugin free? =

The plugin is free. Gemini API usage may incur costs depending on your Google AI plan and usage.

== Screenshots ==

1. Settings page for Gemini model selection and connector link.
2. Picot AI SEO Writer sidebar in the block editor.
3. Generated article content and reference URL list.

== Changelog ==

= 1.0.2 =
* Migrated all AI features to the WordPress AI Client (no direct provider HTTP calls).
* Removed plugin-owned API key settings; credentials are managed under Settings → Connectors.
* Requires WordPress 7.0 or later.

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

= 1.0.2 =
Requires WordPress 7.0+. Requires the Google Gemini connector under Settings → Connectors.

= 1.0.1 =
Review fixes: logging location, REST permissions, and credential handling updates.

= 1.0.0 =
Initial release.
