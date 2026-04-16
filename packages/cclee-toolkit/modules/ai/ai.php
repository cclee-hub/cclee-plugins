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
	if ( ! get_option( 'cclee_toolkit_ai_enabled', false ) ) {
		return;
	}

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

	$api_key  = get_option( 'cclee_toolkit_ai_api_key', '' );
	$provider = get_option( 'cclee_toolkit_ai_provider', 'openai' );
	$model    = get_option( 'cclee_toolkit_ai_model', '' );
	$base_url = get_option( 'cclee_toolkit_ai_base_url', '' );

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

	$default_models = [
		'openai'    => 'gpt-4o-mini',
		'deepseek'  => 'deepseek-chat',
		'anthropic' => 'claude-haiku-4-5-20251001',
		'custom'    => '',
	];
	$model = $model ?: ( $default_models[ $provider ] ?? 'gpt-4o-mini' );

	if ( empty( $model ) ) {
		return new WP_Error( 'no_model', 'Model name is required. Please configure it in Settings > CCLEE Toolkit.', [ 'status' => 400 ] );
	}

	// Anthropic uses different endpoint and headers
	if ( $provider === 'anthropic' ) {
		return cclee_toolkit_ai_call_anthropic( $api_key, $model, $full_prompt );
	}

	// OpenAI-compatible providers (openai, deepseek, custom)
	$endpoints = [
		'openai'   => 'https://api.openai.com/v1/chat/completions',
		'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
	];

	$endpoint = ( $provider === 'custom' && ! empty( $base_url ) )
		? rtrim( $base_url, '/' ) . '/chat/completions'
		: ( $endpoints[ $provider ] ?? $endpoints['openai'] );

	$response = wp_remote_post( $endpoint, [
		'headers' => [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		],
		'body'    => json_encode( [
			'model'      => $model,
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

/**
 * Anthropic API 调用
 */
function cclee_toolkit_ai_call_anthropic( string $api_key, string $model, string $prompt ) {
	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'headers' => [
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		],
		'body'    => json_encode( [
			'model'      => $model,
			'max_tokens' => 500,
			'system'     => 'You are a helpful content writing assistant.',
			'messages'   => [
				[ 'role' => 'user', 'content' => $prompt ],
			],
		] ),
	] );

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_error', $response->get_error_message(), [ 'status' => 500 ] );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	return [
		'content' => $body['content'][0]['text'] ?? '',
	];
}
