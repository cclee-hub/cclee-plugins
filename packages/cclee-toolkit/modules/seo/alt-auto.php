<?php
/**
 * Image Alt 自动生成 — 上传时 AI 填充 + 批量处理
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 调用 AI 生成 alt 文本
 *
 * 复用 provider/model/api_key/base_url 配置，max_tokens = 100。
 *
 * @param string $prompt 用户 prompt
 * @return string|WP_Error
 */
function cclee_toolkit_alt_call_ai( string $prompt ) {
	$api_key  = get_option( 'cclee_toolkit_ai_api_key', '' );
	$provider = get_option( 'cclee_toolkit_ai_provider', 'openai' );
	$model    = get_option( 'cclee_toolkit_ai_model', '' );
	$base_url = get_option( 'cclee_toolkit_ai_base_url', '' );

	if ( empty( $api_key ) ) {
		return new WP_Error( 'no_api_key', __( 'AI API Key not configured.', 'cclee-toolkit' ) );
	}

	$default_models = [
		'openai'    => 'gpt-4o-mini',
		'deepseek'  => 'deepseek-chat',
		'anthropic' => 'claude-haiku-4-5-20251001',
		'custom'    => '',
	];
	$model = $model ?: ( $default_models[ $provider ] ?? 'gpt-4o-mini' );

	if ( empty( $model ) ) {
		return new WP_Error( 'no_model', __( 'Model name is required.', 'cclee-toolkit' ) );
	}

	$system = 'You generate concise, descriptive alt text for images. Return ONLY the alt text as a plain string, max 50 characters. No quotes, no markdown, no explanation, no labels.';

	if ( 'anthropic' === $provider ) {
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body'    => json_encode( [
				'model'      => $model,
				'max_tokens' => 100,
				'system'     => $system,
				'messages'   => [
					[ 'role' => 'user', 'content' => $prompt ],
				],
			] ),
			'timeout' => 30,
		] );
	} else {
		$endpoints = [
			'openai'   => 'https://api.openai.com/v1/chat/completions',
			'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
		];
		$endpoint = ( 'custom' === $provider && ! empty( $base_url ) )
			? rtrim( $base_url, '/' ) . '/chat/completions'
			: ( $endpoints[ $provider ] ?? $endpoints['openai'] );

		$max_tokens = 100;
		if ( 'custom' === $provider && ! empty( $base_url ) ) {
			$max_tokens = 500;
		}

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
				'max_tokens' => $max_tokens,
			] ),
			'timeout' => 30,
		] );
	}

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	$text = ( 'anthropic' === $provider )
		? ( $body['content'][0]['text'] ?? '' )
		: ( $body['choices'][0]['message']['content'] ?? '' );

	if ( empty( $text ) ) {
		return new WP_Error( 'empty_response', __( 'AI returned empty response.', 'cclee-toolkit' ) );
	}

	// Strip markdown fences, quotes, whitespace
	$text = preg_replace( '/^```.*\n?/m', '', $text );
	$text = preg_replace( '/\n?```\s*$/m', '', $text );
	$text = trim( $text, "\"'\n\r " );

	return sanitize_text_field( mb_substr( $text, 0, 50 ) );
}

/**
 * 构造图片 alt 生成 prompt
 *
 * @param int $attachment_id 附件 ID
 * @return string 空字符串表示无法构造
 */
function cclee_toolkit_alt_build_prompt( int $attachment_id ): string {
	$file     = get_attached_file( $attachment_id );
	$filename = $file ? pathinfo( $file, PATHINFO_FILENAME ) : '';
	$filename = str_replace( array( '-', '_' ), ' ', $filename );

	$parts = array();

	if ( $filename ) {
		$parts[] = sprintf( 'Image file name: "%s"', $filename );
	}

	$parent_id = wp_get_post_parent_id( $attachment_id );
	if ( $parent_id ) {
		$parent_title = get_the_title( $parent_id );
		if ( $parent_title ) {
			$parts[] = sprintf( 'Used in post: "%s"', $parent_title );
		}
	}

	if ( empty( $parts ) ) {
		return '';
	}

	return implode( '. ', $parts ) . '. Generate a short, descriptive alt text for this image.';
}

/**
 * 上传时自动生成 alt
 *
 * 触发条件：AI 启用 + alt_auto 启用 + 图片类型 + 当前 alt 为空
 */
add_action( 'add_attachment', function( $post_id ) {
	if ( ! get_option( 'cclee_toolkit_ai_enabled', false ) ) {
		return;
	}
	if ( ! get_option( 'cclee_toolkit_alt_auto_enabled', false ) ) {
		return;
	}
	if ( ! wp_attachment_is_image( $post_id ) ) {
		return;
	}

	$existing_alt = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
	if ( ! empty( $existing_alt ) ) {
		return;
	}

	$prompt = cclee_toolkit_alt_build_prompt( $post_id );
	if ( empty( $prompt ) ) {
		return;
	}

	$result = cclee_toolkit_alt_call_ai( $prompt );
	if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
		update_post_meta( $post_id, '_wp_attachment_image_alt', $result );
	}
} );

/**
 * 注册批量处理 REST 端点
 */
add_action( 'rest_api_init', function() {
	register_rest_route( 'cclee-toolkit/v1', '/seo/alt-batch', [
		'methods'             => 'POST',
		'callback'            => 'cclee_toolkit_alt_batch_process',
		'permission_callback' => function() {
			return current_user_can( 'manage_options' );
		},
	] );
} );

/**
 * 批量处理回调
 *
 * @param WP_REST_Request $request
 * @return array|WP_Error
 */
function cclee_toolkit_alt_batch_process( WP_REST_Request $request ) {
	if ( ! get_option( 'cclee_toolkit_ai_enabled', false ) ) {
		return new WP_Error( 'ai_disabled', __( 'AI module is not enabled.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}
	if ( ! get_option( 'cclee_toolkit_alt_batch_enabled', false ) ) {
		return new WP_Error( 'batch_disabled', __( 'Batch processing is not enabled.', 'cclee-toolkit' ), [ 'status' => 400 ] );
	}

	$batch_size = absint( $request->get_param( 'batch_size' ) );
	if ( $batch_size < 1 ) {
		$batch_size = 10;
	}
	if ( $batch_size > 50 ) {
		$batch_size = 50;
	}

	$meta_query = [
		'relation' => 'OR',
		[
			'key'     => '_wp_attachment_image_alt',
			'compare' => 'NOT EXISTS',
		],
		[
			'key'     => '_wp_attachment_image_alt',
			'value'   => '',
			'compare' => '=',
		],
	];

	$query = new WP_Query( [
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image',
		'posts_per_page' => $batch_size,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => $meta_query,
	] );

	$ids       = $query->posts;
	$processed = 0;
	$success   = 0;
	$failed    = 0;

	foreach ( $ids as $attachment_id ) {
		$processed++;

		$prompt = cclee_toolkit_alt_build_prompt( $attachment_id );
		if ( empty( $prompt ) ) {
			$failed++;
			continue;
		}

		$result = cclee_toolkit_alt_call_ai( $prompt );

		if ( ! is_wp_error( $result ) && ! empty( $result ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $result );
			$success++;
		} else {
			$failed++;
		}
	}

	// 处理后重新查询剩余数量
	$remaining_query = new WP_Query( [
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => $meta_query,
	] );

	return [
		'processed' => $processed,
		'success'   => $success,
		'failed'    => $failed,
		'remaining' => $remaining_query->found_posts,
	];
}

/**
 * 查询 alt 为空的图片总数
 *
 * @return int
 */
function cclee_toolkit_count_empty_alt_images(): int {
	$query = new WP_Query( [
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => [
			'relation' => 'OR',
			[
				'key'     => '_wp_attachment_image_alt',
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => '_wp_attachment_image_alt',
				'value'   => '',
				'compare' => '=',
			],
		],
	] );
	return $query->found_posts;
}
