<?php
/**
 * CCLEE Shipping Uninstall
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_cclee_shipping_%',
		'_transient_timeout_cclee_shipping_%'
	)
);
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'woocommerce_cclee_shipping_%'
	)
);
