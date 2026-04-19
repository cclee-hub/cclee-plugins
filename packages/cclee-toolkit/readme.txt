=== CCLEE Toolkit ===
Contributors: cclee-hub
Tags: ai, seo, case-study, custom-post-type, block-editor
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

B端企业官网增强工具包：AI内容辅助、SEO优化、案例展示CPT。

== Description ==

CCLEE Toolkit 是 CCLEE 主题的官方配套插件，专为 B 端企业官网设计。

= 功能模块（可独立开关）=

* **AI Assistant** - 编辑器内 AI 辅助内容生成（默认关闭，需手动配置 API Key）
* **SEO Enhancer** - 自动输出 Open Graph、Twitter Card、JSON-LD Schema
* **Case Study CPT** - 案例展示自定义文章类型，含客户信息、成果指标、评价字段

= 使用场景 =

* 企业官网案例展示
* B2B 产品/服务介绍
* 内容营销支持

= 配套主题 =

本插件为 [CCLEE Theme](https://github.com/cclee-hub/cclee-theme) 的官方配套插件，但也可独立使用。

== Installation ==

1. 上传插件到 `/wp-content/plugins/cclee-toolkit/`
2. 激活插件
3. 进入 设置 → CCLEE Toolkit 启用所需模块
4. 如需 AI 功能，配置 OpenAI API Key

== Frequently Asked Questions ==

= 是否必须配合 CCLEE 主题使用？ =

不必须。插件可独立使用，但与 CCLEE 主题配合使用效果最佳。

= AI 功能支持哪些 API？ =

支持 OpenAI API 或兼容的 API 服务（如 Azure OpenAI）。

= 如何禁用某个模块？ =

进入 设置 → CCLEE Toolkit，取消勾选对应模块即可。

== External Services ==

This plugin optionally connects to OpenAI API for AI content generation.

= AI Assistant Module =
* Service: OpenAI API
* API Endpoint: https://api.openai.com/v1/chat/completions
* Data Sent: User-provided prompt/topic for content generation
* Privacy Policy: https://openai.com/privacy
* Opt-in: Module is disabled by default; API Key must be configured manually

== Changelog ==

= 1.0.0 =
* 首次发布
* 从 CCLEE 主题分离 AI、SEO、Case Study 功能
* 添加模块独立开关

== Upgrade Notice ==

= 1.0.0 =
首次发布版本。

== Additional Info ==

= 模块说明 =

**AI Assistant**
- 在块编辑器侧边栏添加 AI 辅助面板
- 支持生成段落、标题、列表、CTA、FAQ
- API Key 安全存储在服务端

**SEO Enhancer**
- 自动输出 Open Graph 标签
- 自动输出 Twitter Card 标签
- 自动输出 JSON-LD Schema（Article/WebPage）

**Case Study CPT**
- 自定义文章类型：case-study
- 自定义分类法：case-industry（行业分类）
- Meta 字段：客户名称、项目周期、公司规模、成果指标、客户评价
