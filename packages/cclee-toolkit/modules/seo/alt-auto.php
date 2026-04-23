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
 * 查找图片关联的 WooCommerce 商品
 *
 * @param int $attachment_id 附件 ID
 * @return \WC_Product|null
 */
function cclee_toolkit_alt_find_product( int $attachment_id ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return null;
	}

	// 1. 直接父级
	$parent_id = wp_get_post_parent_id( $attachment_id );
	if ( $parent_id && 'product' === get_post_type( $parent_id ) ) {
		return wc_get_product( $parent_id );
	}

	global $wpdb;
	$id_str = (string) $attachment_id;

	// 2. 作为商品主图
	$parent = $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s LIMIT 1",
		$id_str
	) );
	if ( $parent && 'product' === get_post_type( $parent ) ) {
		return wc_get_product( $parent );
	}

	// 3. 在商品图片画廊中
	$like   = '%' . $wpdb->esc_like( $id_str ) . '%';
	$parent = $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND meta_value LIKE %s LIMIT 1",
		$like
	) );
	if ( $parent && 'product' === get_post_type( $parent ) ) {
		return wc_get_product( $parent );
	}

	return null;
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

	$product = cclee_toolkit_alt_find_product( $attachment_id );

	if ( $product ) {
		// 商品名
		$name = $product->get_name();
		if ( $name ) {
			$parts[] = sprintf( 'Product: "%s"', $name );
		}

		// 简短描述 (前200字符)
		$short_desc = strip_tags( $product->get_short_description() );
		$short_desc = trim( mb_substr( $short_desc, 0, 200 ) );
		if ( $short_desc ) {
			$parts[] = sprintf( 'Short description: %s', $short_desc );
		}

		// 完整描述 (前300字符)
		$desc = strip_tags( $product->get_description() );
		$desc = trim( mb_substr( $desc, 0, 300 ) );
		if ( $desc ) {
			$parts[] = sprintf( 'Description: %s', $desc );
		}

		// 分类 (前5个)
		$cat_ids = $product->get_category_ids();
		if ( ! empty( $cat_ids ) ) {
			$cat_ids  = array_slice( $cat_ids, 0, 5 );
			$cat_names = array_filter( array_map( function( $id ) {
				$term = get_term( $id );
				return $term && ! is_wp_error( $term ) ? $term->name : '';
			}, $cat_ids ) );
			if ( ! empty( $cat_names ) ) {
				$parts[] = sprintf( 'Categories: %s', implode( ', ', $cat_names ) );
			}
		}

		// 标签 (前5个)
		$tag_ids = $product->get_tag_ids();
		if ( ! empty( $tag_ids ) ) {
			$tag_ids  = array_slice( $tag_ids, 0, 5 );
			$tag_names = array_filter( array_map( function( $id ) {
				$term = get_term( $id );
				return $term && ! is_wp_error( $term ) ? $term->name : '';
			}, $tag_ids ) );
			if ( ! empty( $tag_names ) ) {
				$parts[] = sprintf( 'Tags: %s', implode( ', ', $tag_names ) );
			}
		}

		// 文件名
		if ( $filename ) {
			$parts[] = sprintf( 'Image file name: "%s"', $filename );
		}
	} else {
		// 回退：WP 原有逻辑
		if ( $filename ) {
			$parts[] = sprintf( 'Image file name: "%s"', $filename );
		}

		$parent_id = wp_get_post_parent_id( $attachment_id );
		if ( $parent_id ) {
			$parent_title = get_the_title( $parent_id );
			if ( $parent_title ) {
				$parts[] = sprintf( 'Used in post: "%s"', $parent_title );
			}
			$parent_content = strip_tags( get_post_field( 'post_content', $parent_id ) );
			$parent_content = trim( mb_substr( $parent_content, 0, 300 ) );
			if ( $parent_content ) {
				$parts[] = sprintf( 'Post content: %s', $parent_content );
			}
		}
	}

	if ( empty( $parts ) ) {
		return '';
	}

	return implode( '. ', $parts ) . '. Generate a concise alt text under 50 characters for this image.';
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
