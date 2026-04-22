<?php
/**
 * SEO 逐页字段 — Post Meta 注册
 *
 * 注册 Meta Title、Meta Description、Robots Meta 字段，
 * 支持所有 public post type，REST API 可读写。
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 为所有 public post type 注册 SEO meta 字段
 */
add_action( 'init', function () {
	if ( ! get_option( 'cclee_toolkit_seo_enabled', true ) ) {
		return;
	}

	$post_types = get_post_types( [ 'public' => true ] );

	$fields = [
		[
			'key'   => 'cclee_seo_meta_title',
			'type'  => 'string',
			'single' => true,
		],
		[
			'key'   => 'cclee_seo_meta_description',
			'type'  => 'string',
			'single' => true,
		],
		[
			'key'   => 'cclee_seo_robots_noindex',
			'type'  => 'boolean',
			'single' => true,
		],
		[
			'key'   => 'cclee_seo_robots_nofollow',
			'type'  => 'boolean',
			'single' => true,
		],
	];

	foreach ( $post_types as $post_type ) {
		foreach ( $fields as $field ) {
			register_post_meta( $post_type, $field['key'], [
				'show_in_rest'      => true,
				'single'            => $field['single'],
				'type'              => $field['type'],
				'sanitize_callback' => 'string' === $field['type'] ? 'sanitize_text_field' : null,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			] );
		}
	}
} );

/**
 * 加载编辑器侧边栏脚本
 */
add_action( 'enqueue_block_editor_assets', function () {
	if ( ! get_option( 'cclee_toolkit_seo_enabled', true ) ) {
		return;
	}

	wp_enqueue_script(
		'cclee-toolkit-editor-seo',
		CCLEE_TOOLKIT_URL . 'modules/seo/assets/editor-seo.js',
		[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data' ],
		CCLEE_TOOLKIT_VERSION,
		true
	);

	wp_localize_script(
		'cclee-toolkit-editor-seo',
		'ccleeToolkitSEOConfig',
		[
			'aiEnabled' => (bool) get_option( 'cclee_toolkit_ai_enabled', false ),
			'restUrl'   => rest_url( 'cclee-toolkit/v1/seo/meta' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
		]
	);
} );

/**
 * REST 端点：AI 生成 SEO Meta Title + Description
 *
 * 路由：POST cclee-toolkit/v1/seo/meta
 * 复用 AI 模块的 provider/model/api_key 配置。
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'cclee-toolkit/v1', '/seo/meta', [
		'methods'             => 'POST',
		'callback'            => 'cclee_toolkit_seo_generate_meta',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	] );

	register_rest_route( 'cclee-toolkit/v1', '/seo/analyze', [
		'methods'             => 'POST',
		'callback'            => 'cclee_toolkit_seo_analyze_content',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	] );

	register_rest_route( 'cclee-toolkit/v1', '/seo/internal-links', [
		'methods'             => 'POST',
		'callback'            => 'cclee_toolkit_seo_internal_links',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	] );
} );

/**
 * AI 生成 SEO Meta 回调
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function cclee_toolkit_seo_generate_meta( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'post_id' ) );
	$content = $request->get_param( 'content' );
	$title   = $request->get_param( 'title' );

	if ( ! $post_id || ! get_post( $post_id ) ) {
		return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	if ( empty( $content ) && empty( $title ) ) {
		return new WP_Error( 'no_content', __( 'Title or content is required.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	$api_key  = get_option( 'cclee_toolkit_ai_api_key', '' );
	$provider = get_option( 'cclee_toolkit_ai_provider', 'openai' );
	$model    = get_option( 'cclee_toolkit_ai_model', '' );
	$base_url = get_option( 'cclee_toolkit_ai_base_url', '' );

	if ( empty( $api_key ) ) {
		return new WP_Error( 'no_api_key', __( 'AI API Key not configured.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	$default_models = [
		'openai'    => 'gpt-4o-mini',
		'deepseek'  => 'deepseek-chat',
		'anthropic' => 'claude-haiku-4-5-20251001',
		'custom'    => '',
	];
	$model = $model ?: ( $default_models[ $provider ] ?? 'gpt-4o-mini' );

	if ( empty( $model ) ) {
		return new WP_Error( 'no_model', __( 'Model name is required.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	$truncated = mb_substr( $content, 0, 500 );

	$prompt = sprintf(
		'为以下页面内容生成 SEO Meta Title（60字符内）和 Meta Description（155字符内），以 JSON 格式返回 {"title": "...", "description": "..."}。页面标题：%s，内容：%s',
		$title,
		$truncated
	);

	$system = 'You are an SEO expert. Return only valid JSON with keys "title" and "description". No markdown, no explanation.';

	// Anthropic
	if ( $provider === 'anthropic' ) {
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body'    => json_encode( [
				'model'      => $model,
				'max_tokens' => 2048,
				'system'     => $system,
				'messages'   => [
					[ 'role' => 'user', 'content' => $prompt ],
				],
			] ),
			'timeout' => 30,
		] );
	} else {
		// OpenAI-compatible
		$endpoints = [
			'openai'   => 'https://api.openai.com/v1/chat/completions',
			'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
		];
		$endpoint = ( 'custom' === $provider && ! empty( $base_url ) )
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
					[ 'role' => 'system', 'content' => $system ],
					[ 'role' => 'user', 'content' => $prompt ],
				],
				'max_tokens' => 2048,
			] ),
			'timeout' => 30,
		] );
	}

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_error', $response->get_error_message(), [ 'status' => 500 ] );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	// Extract text from response based on provider
	$text = ( 'anthropic' === $provider )
		? ( $body['content'][0]['text'] ?? '' )
		: ( $body['choices'][0]['message']['content'] ?? '' );

	if ( empty( $text ) ) {
		return new WP_Error( 'empty_response', __( 'AI returned empty response.', 'cclee-toolkit' ), [ 'status' => 500 ] );
	}

	// Strip markdown code fences if present
	$text = preg_replace( '/^```(?:json)?\s*\n?/m', '', $text );
	$text = preg_replace( '/\n?```\s*$/m', '', $text );
	$text = trim( $text );

	$result = json_decode( $text, true );

	if ( ! $result || ! isset( $result['title'], $result['description'] ) ) {
		return new WP_Error( 'parse_error', __( 'Failed to parse AI response.', 'cclee-toolkit' ), [ 'status' => 500 ] );
	}

	return [
		'title'       => sanitize_text_field( $result['title'] ),
		'description' => sanitize_text_field( $result['description'] ),
	];
}

/**
 * AI 内容分析回调
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function cclee_toolkit_seo_analyze_content( WP_REST_Request $request ) {
	$post_id          = absint( $request->get_param( 'post_id' ) );
	$title            = $request->get_param( 'title' );
	$content          = $request->get_param( 'content' );
	$meta_title       = $request->get_param( 'meta_title' );
	$meta_description = $request->get_param( 'meta_description' );

	if ( ! $post_id || ! get_post( $post_id ) ) {
		return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	if ( empty( $content ) && empty( $title ) ) {
		return new WP_Error( 'no_content', __( 'Title or content is required.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	$api_key  = get_option( 'cclee_toolkit_ai_api_key', '' );
	$provider = get_option( 'cclee_toolkit_ai_provider', 'openai' );
	$model    = get_option( 'cclee_toolkit_ai_model', '' );
	$base_url = get_option( 'cclee_toolkit_ai_base_url', '' );

	if ( empty( $api_key ) ) {
		return new WP_Error( 'no_api_key', __( 'AI API Key not configured.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	$default_models = [
		'openai'    => 'gpt-4o-mini',
		'deepseek'  => 'deepseek-chat',
		'anthropic' => 'claude-haiku-4-5-20251001',
		'custom'    => '',
	];
	$model = $model ?: ( $default_models[ $provider ] ?? 'gpt-4o-mini' );

	if ( empty( $model ) ) {
		return new WP_Error( 'no_model', __( 'Model name is required.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	$truncated = mb_substr( $content, 0, 500 );

	$prompt = sprintf(
		'Analyze the following page for SEO quality. Return JSON with exactly these keys: "keyword_coverage" (string — evaluate keyword coverage in the body), "title_quality" (string — evaluate title quality and SEO fitness), "description_suggestion" (string — optimization suggestions for meta description), "score" (integer 1-100 — overall SEO score). Page title: %s. Content: %s. Current meta title: %s. Current meta description: %s.',
		$title,
		$truncated,
		$meta_title,
		$meta_description
	);

	$system = 'You are an SEO expert. Return only valid JSON with keys "keyword_coverage", "title_quality", "description_suggestion", "score". No markdown, no explanation.';

	// Anthropic
	if ( $provider === 'anthropic' ) {
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body'    => json_encode( [
				'model'      => $model,
				'max_tokens' => 1500,
				'system'     => $system,
				'messages'   => [
					[ 'role' => 'user', 'content' => $prompt ],
				],
			] ),
			'timeout' => 30,
		] );
	} else {
		// OpenAI-compatible
		$endpoints = [
			'openai'   => 'https://api.openai.com/v1/chat/completions',
			'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
		];
		$endpoint = ( 'custom' === $provider && ! empty( $base_url ) )
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
					[ 'role' => 'system', 'content' => $system ],
					[ 'role' => 'user', 'content' => $prompt ],
				],
				'max_tokens' => 1500,
			] ),
			'timeout' => 30,
		] );
	}

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_error', $response->get_error_message(), [ 'status' => 500 ] );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	$text = ( 'anthropic' === $provider )
		? ( $body['content'][0]['text'] ?? '' )
		: ( $body['choices'][0]['message']['content'] ?? '' );

	if ( empty( $text ) ) {
		return new WP_Error( 'empty_response', __( 'AI returned empty response.', 'cclee-toolkit' ), [ 'status' => 500 ] );
	}

	// Strip markdown code fences if present
	$text = preg_replace( '/^```(?:json)?\s*\n?/m', '', $text );
	$text = preg_replace( '/\n?```\s*$/m', '', $text );
	$text = trim( $text );

	$result = json_decode( $text, true );

	if ( ! $result || ! isset( $result['keyword_coverage'], $result['title_quality'], $result['description_suggestion'], $result['score'] ) ) {
		return new WP_Error( 'parse_error', __( 'Failed to parse AI response.', 'cclee-toolkit' ), [ 'status' => 500 ] );
	}

	return [
		'keyword_coverage'      => sanitize_text_field( $result['keyword_coverage'] ),
		'title_quality'         => sanitize_text_field( $result['title_quality'] ),
		'description_suggestion' => sanitize_text_field( $result['description_suggestion'] ),
		'score'                 => absint( $result['score'] ),
	];
}

/**
 * AI 内部链接建议回调
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function cclee_toolkit_seo_internal_links( WP_REST_Request $request ) {
	$post_id = absint( $request->get_param( 'post_id' ) );
	$title   = $request->get_param( 'title' );
	$content = $request->get_param( 'content' );

	if ( ! $post_id || ! get_post( $post_id ) ) {
		return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	if ( empty( $content ) && empty( $title ) ) {
		return new WP_Error( 'no_content', __( 'Title or content is required.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	// 查询站内已发布页面，排除当前 post，最多 50 条
	$site_posts = get_posts( [
		'post_type'      => get_post_types( [ 'public' => true ], 'names' ),
		'post_status'    => 'publish',
		'post__not_in'   => [ $post_id ],
		'posts_per_page' => 50,
		'fields'         => 'ids',
	] );

	$pages_list = [];
	foreach ( $site_posts as $pid ) {
		$pages_list[] = [
			'id'    => $pid,
			'title' => get_the_title( $pid ),
			'url'   => get_permalink( $pid ),
		];
	}

	if ( empty( $pages_list ) ) {
		return new WP_Error( 'no_posts', __( 'No published posts found.', 'cclee-toolkit' ), [ 'status' => 404 ] );
	}

	$api_key  = get_option( 'cclee_toolkit_ai_api_key', '' );
	$provider = get_option( 'cclee_toolkit_ai_provider', 'openai' );
	$model    = get_option( 'cclee_toolkit_ai_model', '' );
	$base_url = get_option( 'cclee_toolkit_ai_base_url', '' );

	if ( empty( $api_key ) ) {
		return new WP_Error( 'no_api_key', __( 'AI API Key not configured.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	$default_models = [
		'openai'    => 'gpt-4o-mini',
		'deepseek'  => 'deepseek-chat',
		'anthropic' => 'claude-haiku-4-5-20251001',
		'custom'    => '',
	];
	$model = $model ?: ( $default_models[ $provider ] ?? 'gpt-4o-mini' );

	if ( empty( $model ) ) {
		return new WP_Error( 'no_model', __( 'Model name is required.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	$truncated = mb_substr( $content, 0, 800 );

	$pages_json = wp_json_encode( $pages_list, JSON_UNESCAPED_UNICODE );

	$prompt = sprintf(
		'Given the current page — Title: "%s", Content: "%s"\n\nHere is a list of published pages on this site (JSON array of {id, title, url}):\n%s\n\nRecommend up to 5 pages from the list that would make good internal links from the current page. Return a JSON array with objects containing: "post_id" (integer), "title" (string), "url" (string), "reason" (string — why this link is relevant to the current content). Return only the JSON array, no markdown, no explanation.',
		$title,
		$truncated,
		$pages_json
	);

	$system = 'You are an SEO expert specializing in internal linking strategy. Return only a valid JSON array. No markdown code fences, no explanation.';

	// Anthropic
	if ( $provider === 'anthropic' ) {
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body'    => json_encode( [
				'model'      => $model,
				'max_tokens' => 500,
				'system'     => $system,
				'messages'   => [
					[ 'role' => 'user', 'content' => $prompt ],
				],
			] ),
			'timeout' => 30,
		] );
	} else {
		// OpenAI-compatible
		$endpoints = [
			'openai'   => 'https://api.openai.com/v1/chat/completions',
			'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
		];
		$endpoint = ( 'custom' === $provider && ! empty( $base_url ) )
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
					[ 'role' => 'system', 'content' => $system ],
					[ 'role' => 'user', 'content' => $prompt ],
				],
				'max_tokens' => 500,
			] ),
			'timeout' => 30,
		] );
	}

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'api_error', $response->get_error_message(), [ 'status' => 500 ] );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	$text = ( 'anthropic' === $provider )
		? ( $body['content'][0]['text'] ?? '' )
		: ( $body['choices'][0]['message']['content'] ?? '' );

	if ( empty( $text ) ) {
		return new WP_Error( 'empty_response', __( 'AI returned empty response.', 'cclee-toolkit' ), [ 'status' => 500 ] );
	}

	// Strip markdown code fences if present
	$text = preg_replace( '/^```(?:json)?\s*\n?/m', '', $text );
	$text = preg_replace( '/\n?```\s*$/m', '', $text );
	$text = trim( $text );

	$result = json_decode( $text, true );

	if ( ! is_array( $result ) ) {
		return new WP_Error( 'parse_error', __( 'Failed to parse AI response.', 'cclee-toolkit' ), [ 'status' => 500 ] );
	}

	// Sanitize each suggestion
	$sanitized = [];
	foreach ( $result as $item ) {
		if ( ! isset( $item['post_id'], $item['title'], $item['url'], $item['reason'] ) ) {
			continue;
		}
		$sanitized[] = [
			'post_id' => absint( $item['post_id'] ),
			'title'   => sanitize_text_field( $item['title'] ),
			'url'     => esc_url_raw( $item['url'] ),
			'reason'  => sanitize_text_field( $item['reason'] ),
		];
	}

	return $sanitized;
}
