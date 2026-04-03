<?php
/**
 * AI 编辑器辅助 — 后端支持
 *
 * 功能：
 * - 加载 editor-ai.js 脚本（仅编辑器）
 * - 提供 API Key 配置选项
 * - 后端代理 API 调用（生产环境推荐）
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 在块编辑器中加载 AI 辅助脚本
 * 仅在编辑器环境加载，不影响前端性能
 */
add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script(
		'cclee-toolkit-editor-ai',
		CCLEE_TOOLKIT_URL . 'modules/ai/assets/editor-ai.js',
		[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api' ],
		CCLEE_TOOLKIT_VERSION,
		true
	);

	// 传递配置到前端
	wp_localize_script(
		'cclee-toolkit-editor-ai',
		'ccleeToolkitAIConfig',
		[
			'isEnabled' => get_option( 'cclee_toolkit_ai_enabled', false ),
		]
	);
} );

/**
 * 后端代理 API 调用
 * 避免在前端暴露 API Key
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'cclee-toolkit/v1', '/ai/generate', [
		'methods'             => 'POST',
		'callback'            => 'cclee_toolkit_ai_generate_content',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	] );
} );

/**
 * AI 内容生成回调
 */
function cclee_toolkit_ai_generate_content( WP_REST_Request $request ) {
	$prompt = $request->get_param( 'prompt' );
	$type   = $request->get_param( 'type' ) ?: 'paragraph';

	$api_key = get_option( 'cclee_toolkit_ai_api_key', '' );
	if ( empty( $api_key ) ) {
		return new WP_Error( 'no_api_key', 'API Key not configured', [ 'status' => 400 ] );
	}

	$prompts = [
		'paragraph' => 'Write a clear, SEO-friendly paragraph about: ',
		'headline'  => 'Write an attention-grabbing headline for: ',
		'list'      => 'Create a list of key points about: ',
		'cta'       => 'Write a compelling call-to-action for: ',
		'faq'       => 'Generate 3 FAQ items with answers about: ',
	];

	$full_prompt = ( $prompts[ $type ] ?? '' ) . $prompt;

	$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
		'headers' => [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		],
		'body'    => json_encode( [
			'model'      => 'gpt-3.5-turbo',
			'messages'   => [
				[ 'role' => 'system', 'content' => 'You are a helpful content writing assistant.' ],
				[ 'role' => 'user', 'content' => $full_prompt ],
			],
			'max_tokens' => 500,
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_error', $response->get_error_message(), [ 'status' => 500 ] );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	return [
		'content' => $body['choices'][0]['message']['content'] ?? '',
	];
}
