<?php
/**
 * Manual URL Submission — AJAX handler for single URL submit
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_cclee_manual_submit_url', function() {
	check_ajax_referer( 'cclee_manual_submit_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cclee-toolkit' ) ) );
	}

	$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
	if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid URL format.', 'cclee-toolkit' ) ) );
	}

	// URL must belong to current site domain
	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$url_host  = wp_parse_url( $url, PHP_URL_HOST );
	if ( $site_host !== $url_host ) {
		wp_send_json_error( array( 'message' => __( 'URL must belong to this site.', 'cclee-toolkit' ) ) );
	}

	// Whitelist channels
	$channels_raw = isset( $_POST['channels'] ) ? (array) wp_unslash( $_POST['channels'] ) : array();
	$channels     = array();
	foreach ( $channels_raw as $ch ) {
		$sanitized = sanitize_text_field( wp_unslash( $ch ) );
		if ( in_array( $sanitized, array( 'indexnow', 'google' ), true ) ) {
			$channels[] = $sanitized;
		}
	}

	if ( empty( $channels ) ) {
		wp_send_json_error( array( 'message' => __( 'Select at least one channel.', 'cclee-toolkit' ) ) );
	}

	$results = array();

	// IndexNow
	if ( in_array( 'indexnow', $channels, true ) ) {
		if ( function_exists( 'cclee_toolkit_indexnow_submit' ) ) {
			cclee_toolkit_indexnow_submit( array( $url ), 'indexnow_manual' );
			$results[] = 'IndexNow: submitted';
		} else {
			$results[] = 'IndexNow: skipped (not enabled)';
		}
	}

	// Google
	if ( in_array( 'google', $channels, true ) ) {
		if ( function_exists( 'cclee_toolkit_google_submit_url' ) ) {
			cclee_toolkit_google_submit_url( $url, 'URL_UPDATED', 'google_manual' );
			$results[] = 'Google: submitted';
		} else {
			$results[] = 'Google: skipped (not enabled)';
		}
	}

	wp_send_json_success( array( 'message' => implode( ' | ', $results ) ) );
} );
