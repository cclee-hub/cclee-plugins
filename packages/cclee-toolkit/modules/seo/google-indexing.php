<?php
/**
 * Google Indexing API — 直接推送 URL 到 Google 索引
 *
 * 功能：
 * - JWT 签名获取 Access Token（无第三方库）
 * - 发布时自动提交 URL_UPDATED
 * - 删除时自动提交 URL_DELETED
 * - Token 缓存 via transient
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 解析 Service Account JSON，提取 client_email 和 private_key
 *
 * @return array{client_email:string,private_key:string}|false
 */
function cclee_toolkit_google_get_credentials() {
	$json = get_option( 'cclee_toolkit_seo_google_service_account', '' );
	if ( ! $json ) {
		return false;
	}

	$data = json_decode( $json, true );
	if ( ! $data || empty( $data['client_email'] ) || empty( $data['private_key'] ) ) {
		return false;
	}

	return array(
		'client_email' => $data['client_email'],
		'private_key'  => $data['private_key'],
	);
}

/**
 * 获取 Google Access Token（带 transient 缓存）
 *
 * @return string|false Access Token 或 false
 */
function cclee_toolkit_google_get_access_token() {
	$cached = get_transient( 'cclee_toolkit_google_access_token' );
	if ( $cached ) {
		return $cached;
	}

	$credentials = cclee_toolkit_google_get_credentials();
	if ( ! $credentials ) {
		cclee_toolkit_indexing_log_entry(
			home_url( '/' ),
			'fail',
			0,
			'google',
			'auth_error: invalid or missing Service Account JSON'
		);
		return false;
	}

	$now      = time();
	$header   = array(
		'alg' => 'RS256',
		'typ' => 'JWT',
	);
	$claim    = array(
		'iss'   => $credentials['client_email'],
		'scope' => 'https://www.googleapis.com/auth/indexing',
		'aud'   => 'https://oauth2.googleapis.com/token',
		'iat'   => $now,
		'exp'   => $now + 3600,
	);

	$base64_header  = rtrim( strtr( base64_encode( wp_json_encode( $header ) ), '+/', '-_' ), '=' );
	$base64_claim   = rtrim( strtr( base64_encode( wp_json_encode( $claim ) ), '+/', '-_' ), '=' );
	$signature_input = $base64_header . '.' . $base64_claim;

	// 签名
	$signed = openssl_sign(
		$signature_input,
		$signature,
		$credentials['private_key'],
		OPENSSL_ALGO_SHA256
	);

	if ( ! $signed ) {
		cclee_toolkit_indexing_log_entry(
			home_url( '/' ),
			'fail',
			0,
			'google',
			'auth_error: JWT signing failed'
		);
		return false;
	}

	$base64_signature = rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );
	$jwt              = $signature_input . '.' . $base64_signature;

	// 换取 Access Token
	$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
		'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
		'body'    => array(
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion'  => $jwt,
		),
		'timeout' => 15,
	) );

	if ( is_wp_error( $response ) ) {
		cclee_toolkit_indexing_log_entry(
			home_url( '/' ),
			'fail',
			0,
			'google',
			'auth_error: token request failed'
		);
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['access_token'] ) ) {
		$error_msg = ! empty( $body['error_description'] )
			? substr( $body['error_description'], 0, 200 )
			: 'auth_error: no access_token in response';
		cclee_toolkit_indexing_log_entry(
			home_url( '/' ),
			'fail',
			wp_remote_retrieve_response_code( $response ),
			'google',
			$error_msg
		);
		return false;
	}

	// 缓存 50 分钟
	set_transient( 'cclee_toolkit_google_access_token', $body['access_token'], 50 * MINUTE_IN_SECONDS );

	return $body['access_token'];
}

/**
 * 发布/更新时提交 URL_UPDATED
 */
add_action( 'transition_post_status', function ( string $new_status, string $old_status, WP_Post $post ) {
	if ( ! is_admin() ) {
		return;
	}
	if ( $new_status !== 'publish' ) {
		return;
	}
	if ( ! get_option( 'cclee_toolkit_seo_google_indexing_enabled', false ) ) {
		return;
	}

	$allowed_types = array( 'post', 'page', 'case-study', 'product' );
	if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
		return;
	}

	if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
		return;
	}

	$url = get_permalink( $post );
	if ( ! $url ) {
		return;
	}

	cclee_toolkit_google_submit_url( $url, 'URL_UPDATED' );
}, 10, 3 );

/**
 * 删除文章时提交 URL_DELETED
 */
add_action( 'before_delete_post', function ( int $post_id ) {
	if ( ! is_admin() ) {
		return;
	}
	if ( ! get_option( 'cclee_toolkit_seo_google_indexing_enabled', false ) ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}

	$allowed_types = array( 'post', 'page', 'case-study', 'product' );
	if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
		return;
	}

	$url = get_permalink( $post );
	if ( ! $url ) {
		return;
	}

	cclee_toolkit_google_submit_url( $url, 'URL_DELETED' );
} );

/**
 * 提交 URL 到 Google Indexing API
 *
 * @param string $url  页面完整 URL
 * @param string $type URL_UPDATED 或 URL_DELETED
 */
function cclee_toolkit_google_submit_url( string $url, string $type = 'URL_UPDATED' ): void {
	$token = cclee_toolkit_google_get_access_token();
	if ( ! $token ) {
		// 认证失败已在 get_access_token 中记录日志
		return;
	}

	$payload = array(
		'url'  => $url,
		'type' => $type,
	);

	$response = wp_remote_post( 'https://indexing.googleapis.com/v3/urlNotifications:publish', array(
		'headers' => array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
		'body'    => wp_json_encode( $payload ),
		'timeout' => 15,
	) );

	$response_code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
	$status        = 200 === $response_code ? 'success' : 'fail';

	// 失败时记录响应体前 200 字符
	$error_detail = '';
	if ( 'fail' === $status && ! is_wp_error( $response ) ) {
		$body = wp_remote_retrieve_body( $response );
		$error_detail = substr( $body, 0, 200 );
	}

	cclee_toolkit_indexing_log_entry( $url, $status, $response_code, 'google', $error_detail );
}
