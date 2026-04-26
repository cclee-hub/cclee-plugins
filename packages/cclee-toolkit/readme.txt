=== CCLEE Toolkit ===
Contributors: ccleeai
Tags: ai, seo, case-study, custom-post-type, block-editor
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A modular enhancement toolkit for business websites: AI-assisted content, SEO optimization, and case study management.

== Description ==

CCLEE Toolkit is the official companion plugin for the CCLEE theme, designed for business and corporate websites. Each module can be toggled independently.

= Modules (individually toggleable) =

* **AI Assistant** - AI-powered content generation in the block editor sidebar (disabled by default; requires API key)
* **SEO Enhancer** - Automatic Open Graph, Twitter Card, and JSON-LD Schema output
* **Case Study CPT** - Custom post type for case studies with client info, results metrics, and testimonials
* **Image Alt AI** - Auto-generate and batch-process image alt text using AI
* **WooCommerce Schema** - Product structured data for rich results in search engines

= Use Cases =

* Business website case study showcases
* B2B product and service presentations
* Content marketing support with AI

= Companion Theme =

This plugin is the official companion for [CCLEE Theme](https://github.com/cclee-hub/cclee-theme) but works independently with any theme.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/cclee-toolkit/`
2. Activate the plugin
3. Navigate to Settings > CCLEE Toolkit to enable desired modules
4. For AI features, configure an API key in the General tab

== Frequently Asked Questions ==

= Do I need the CCLEE theme to use this plugin? =

No. The plugin works with any theme, but pairs best with CCLEE for full design integration.

= Which AI APIs are supported? =

OpenAI, DeepSeek, Anthropic (Claude), or any OpenAI-compatible API endpoint.

= How do I disable a module? =

Go to Settings > CCLEE Toolkit and uncheck the module you want to disable.

== External Services ==

This plugin optionally connects to external AI services for content generation.

= AI Assistant / Image Alt Module =
* Service: User-selected AI provider (OpenAI, DeepSeek, Anthropic, or custom endpoint)
* Data Sent: User-provided prompt or image context for content generation
* Privacy Policy (OpenAI): https://openai.com/privacy
* Privacy Policy (Anthropic): https://www.anthropic.com/privacy
* Opt-in: AI module is disabled by default; API key must be configured manually

= IndexNow =
* Service: IndexNow protocol (Bing, Yandex, and compatible search engines)
* Data Sent: Site URLs when content is published or updated
* Opt-in: Disabled by default

= Google Indexing API =
* Service: Google Indexing API via user-provided Service Account
* Data Sent: Site URLs for indexing requests
* Opt-in: Disabled by default; requires Google Cloud Service Account

== Changelog ==

= 1.1.4 =
* Add WooCommerce Product Schema and BreadcrumbList structured data
* Add Image Alt AI: auto-generate alt text on upload, batch processing
* Add llms.txt generator for LLM crawlers
* Add IndexNow and Google Indexing API integration
* Add manual URL submission to search engines
* Fix output escaping and input sanitization across all modules
* Tested up to WordPress 6.9

= 1.1.0 =
* Add DeepSeek and Anthropic (Claude) AI provider support
* Add custom OpenAI-compatible API endpoint option
* Add Case Study block types (Hero, Metrics, Meta, Testimonial)
* Improve SEO module with per-page meta title, description, and robots controls

= 1.0.0 =
* Initial release
* AI content assistant in block editor
* SEO Enhancer (Open Graph, Twitter Card, JSON-LD)
* Case Study CPT with industry taxonomy

== Upgrade Notice ==

= 1.1.4 =
WooCommerce Schema, Image Alt AI, IndexNow, and llms.txt modules added. Security improvements.

= 1.0.0 =
Initial release.
