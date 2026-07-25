=== Picot AI SEO Writer ===
Contributors: tsubu
Tags: seo, ai, gemini, writing, content
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.0.5
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
* **WordPress AI Connector Integration**: Supports connector-based provider/model selection, readiness checks, connector-specific error guidance, and WordPress's experimental Connector Approvals feature.
* **Free-Tier Output Mode**: Gemini API free-tier requests are adjusted to request concise, simplified, complete responses.

= Requirements and Gemini API plans =

This plugin requires the official WordPress connector functionality and the official [AI plugin](https://wordpress.org/plugins/ai/). Install the **Google Gemini connector**, connect it under **Settings → Connectors**, and activate the official AI plugin. The plugin also supports WordPress's experimental Connector Approvals feature when that feature is enabled.

Gemini API free-tier usage is supported for text generation. However, free-tier token quotas, rate limits, model availability, and Google policies may change, and the plugin may become temporarily or permanently unavailable under those limits. Google Search Grounding and image generation are disabled when the free plan is selected in this plugin.

For more reliable operation, higher limits, and access to supported paid features, a small amount of paid Gemini API usage with billing enabled is recommended.

== External services ==

This plugin sends AI requests through the **WordPress AI Client** (WordPress 7.0+) and requires both the [Google AI connector plugin](https://wordpress.org/plugins/ai-provider-for-google/) and the official [AI plugin](https://wordpress.org/plugins/ai/). It also supports the AI plugin's experimental Connector Approvals feature. Connect your Gemini API key under **Settings → Connectors** and, when Connector Approvals is enabled, approve connector access when prompted. Picot AI SEO Writer does not store or read provider API keys directly.

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
4. Install and activate the official **AI** plugin. If its experimental Connector Approvals feature is enabled, approve connector access when prompted.
5. Open **Settings → Picot AI SEO Writer**, select a Gemini model and API plan, then use the **Picot AI SEO Writer** sidebar in a post to generate content.

== Frequently Asked Questions ==

= Which AI connector do I need? =

This plugin requires the **Google Gemini connector** (AI Provider for Google) and the official **AI** plugin. It also supports the AI plugin's experimental Connector Approvals feature. Other provider connectors such as OpenAI or Anthropic are not supported.

= Do I need to enter an API key in this plugin? =

No. Configure your Gemini API key under **Settings → Connectors** in WordPress. Picot AI SEO Writer uses the WordPress AI Client and does not manage credentials itself.

= Do I need a separate Google Search API key? =

No. This plugin uses Gemini's built-in Google Search Grounding feature. You only need a Gemini API key configured in the Google connector.

= Does it work with the Classic Editor? =

Yes. The plugin supports both the block editor and the classic editor.

= Can I use the Gemini API free tier? =

Yes. The plugin itself is free, and text generation supports the Gemini API free tier. Free-tier prompts request concise, simplified responses so they are more likely to complete within strict token limits. Google Search Grounding and image generation are disabled when the free plan is selected.

Free-tier quotas, token limits, rate limits, model availability, and Google policies can change without notice, so requests may fail or the service may become unavailable. For stable operation, a small amount of paid Gemini API usage with billing enabled is recommended.

== Screenshots ==

1. Block editor sidebar: enter a target keyword, optional notes, and choose writing and image styles.
2. One-click article generation creates a full SEO-friendly post from your keyword in the editor.
3. Image prompt analysis suggests one featured image and five inline images based on your article.
4. Generate and insert AI images into the post, including the featured image in the block editor.
5. Settings page: connect Google Gemini via WordPress AI Client and select text and image models.

== Changelog ==

= 1.0.5 =
* Added detailed WordPress AI connector integration, including support for the experimental Connector Approvals feature, readiness checks, and clear setup guidance.
* Added explicit requirements for the Google Gemini connector and the official AI plugin.
* Added Gemini API free-tier support guidance and concise, simplified free-tier response instructions.
* Added a recommendation to use a small paid Gemini API allowance for more reliable operation.
* Added security updates for REST permissions, output escaping, SSRF prevention, image upload validation, logging, and uninstall cleanup.
* Improved model selection, editor behavior, error handling, translations, and free/paid plan behavior.

= 1.0.4 =
* Added Advanced settings for role settings (detailed writing style), common article generation prompts, and shared image prompts.
* Added the "Use detailed role settings" writing style option and applied role/common prompts to generation.
* Prevented consecutive image placement by spacing suggestions and skipping adjacent inserts.
* Improved Japanese translations for WordPress.org style guide compliance.
* Fixed missing translators comments for placeholder strings.

= 1.0.3 =
* Improved English UI translations for the settings page, setup wizard, block editor, and classic editor.
* Localized loading overlay default messages.
* Requires PHP 8.3.
* Regenerated en_US and ja translation files (199 strings).

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

= 1.0.5 =
Security and connector integration update. Requires the Google Gemini connector and the official AI plugin; free-tier text use is supported with stricter limits.

= 1.0.4 =
Adds advanced role/common prompts, safer image placement spacing, and Japanese translation style-guide fixes.

= 1.0.3 =
Improved English UI translations for the settings wizard, block editor, and classic editor. Requires PHP 8.3.

= 1.0.2 =
Requires WordPress 7.0+. Requires the Google Gemini connector under Settings → Connectors.

= 1.0.1 =
Review fixes: logging location, REST permissions, and credential handling updates.

= 1.0.0 =
Initial release.
