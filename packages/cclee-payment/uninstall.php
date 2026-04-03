<?php
/**
 * CCLEE Payment Uninstall
 *
 * Clean up plugin data on uninstall.
 *
 * @package CCLEE_Payment
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'cclee_payment_paypal_enabled' );
delete_option( 'cclee_payment_paypal_title' );
delete_option( 'cclee_payment_paypal_description' );
delete_option( 'cclee_payment_paypal_client_id' );
delete_option( 'cclee_payment_paypal_client_secret' );
delete_option( 'cclee_payment_paypal_sandbox' );
delete_option( 'cclee_payment_paypal_debug' );

delete_option( 'cclee_payment_alipay_enabled' );
delete_option( 'cclee_payment_alipay_title' );
delete_option( 'cclee_payment_alipay_description' );
delete_option( 'cclee_payment_alipay_merchant_id' );
delete_option( 'cclee_payment_alipay_private_key' );
delete_option( 'cclee_payment_alipay_public_key' );
delete_option( 'cclee_payment_alipay_sandbox' );
delete_option( 'cclee_payment_alipay_debug' );

delete_option( 'cclee_payment_payoneer_enabled' );
delete_option( 'cclee_payment_payoneer_title' );
delete_option( 'cclee_payment_payoneer_description' );
delete_option( 'cclee_payment_payoneer_api_key' );
delete_option( 'cclee_payment_payoneer_api_token' );
delete_option( 'cclee_payment_payoneer_program_id' );
delete_option( 'cclee_payment_payoneer_debug' );

// Clear scheduled hooks.
wp_clear_scheduled_hook( 'cclee_payment_daily_cleanup' );
