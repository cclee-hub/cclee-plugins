<?php
/**
 * IndexNow — 自动通知搜索引擎内容更新
 *
 * 功能：
 * - 虚拟托管 API Key 文件 (/{key}.txt)
 * - 发布/更新时自动提交 URL 到 IndexNow API
 * - 提交日志记录
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 获取或自动生成 IndexNow API Key
 *
 * @return string 32位 hex key
 */
function cclee_toolkit_indexnow_get_key(): string {
	$key = get_option( 'cclee_toolkit_seo_indexnow_key', '' );
	if ( ! $key ) {
		$key = bin2hex( random_bytes( 16 ) );
		update_option( 'cclee_toolkit_seo_indexnow_key', $key );
	}
	return $key;
}

/**
 * 虚拟托管 Key 文件
 * 监听 /{key}.txt 请求，直接输出 key 内容
 */
add_action( 'init', function () {
	$key = get_option( 'cclee_toolkit_seo_indexnow_key', '' );
	if ( ! $key ) {
		return;
	}

	// 获取当前请求路径
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
	if ( ! $request_path ) {
		return;
	}

	// 匹配 /{key}.txt
	$expected = '/' . $key . '.txt';
	if ( $request_path === $expected ) {
		header( 'Content-Type: text/plain' );
		echo esc_html( $key );
		exit;
	}
} );

/**
 * 自动提交 URL 到 IndexNow
 *
 * Hook: transition_post_status — 仅在状态变为 publish 时触发
 */
add_action( 'transition_post_status', function ( string $new_status, string $old_status, WP_Post $post ) {
	if ( ! is_admin() ) {
		return;
	}
	if ( $new_status !== 'publish' ) {
		return;
	}
	if ( ! get_option( 'cclee_toolkit_seo_indexnow_enabled', false ) ) {
		return;
	}

	// 仅处理指定 post type
	$allowed_types = array( 'post', 'page', 'case-study', 'product' );
	if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
		return;
	}

	// 排除自动保存和修订
	if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
		return;
	}

	$url = get_permalink( $post );
	if ( ! $url ) {
		return;
	}

	cclee_toolkit_indexnow_submit( array( $url ) );
}, 10, 3 );

/**
 * 提交 URL 到 IndexNow API
 *
 * @param array $urls 要提交的 URL 列表
 */
function cclee_toolkit_indexnow_submit( array $urls ): void {
	$key    = cclee_toolkit_indexnow_get_key();
	$domain = wp_parse_url( home_url(), PHP_URL_HOST );

	$payload = array(
		'host'        => $domain,
		'key'         => $key,
		'keyLocation' => home_url( '/' . $key . '.txt' ),
		'urlList'     => $urls,
	);

	$response = wp_remote_post( 'https://api.indexnow.org/indexnow', array(
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( $payload ),
		'timeout' => 10,
	) );

	$response_code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
	$status        = in_array( $response_code, array( 200, 202 ), true ) ? 'success' : 'fail';

	foreach ( $urls as $url ) {
		cclee_toolkit_indexing_log_entry( $url, $status, $response_code, 'indexnow' );
	}
}

/**
 * 记录 Indexing 提交日志（IndexNow / Google Indexing API 共用）
 *
 * @param string $url           提交的 URL
 * @param string $status        success / fail
 * @param int    $response_code HTTP 响应码
 * @param string $source        indexnow / google
 * @param string $detail        可选错误详情（前 200 字符）
 */
function cclee_toolkit_indexing_log_entry( string $url, string $status, int $response_code, string $source = 'indexnow', string $detail = '' ): void {
	$log    = get_option( 'cclee_toolkit_indexing_log', array() );
	$entry  = array(
		'url'           => $url,
		'status'        => $status,
		'response_code' => $response_code,
		'source'        => $source,
		'timestamp'     => time(),
	);
	if ( $detail ) {
		$entry['detail'] = $detail;
	}
	$log[] = $entry;

	// 最多保留 50 条
	if ( count( $log ) > 50 ) {
		$log = array_slice( $log, -50 );
	}

	update_option( 'cclee_toolkit_indexing_log', $log );
}
