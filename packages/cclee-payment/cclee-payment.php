<?php
/**
 * Plugin Name: CCLEE Payment
 * Plugin URI: https://github.com/cclee-hub/cclee-payment
 * Description: Multi-gateway payment for WooCommerce. PayPal, Alipay+, Payoneer.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: CCLEE
 * Author URI: https://github.com/cclee-hub
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cclee-payment
 * WC requires at least: 8.0
 * WC tested up to: 10.6.2
 *
 * @package CCLEE_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CCLEE_PAYMENT_VERSION', '1.0.0' );
define( 'CCLEE_PAYMENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'CCLEE_PAYMENT_URL', plugin_dir_url( __FILE__ ) );

// Declare HPOS compatibility.
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

// Load gateway classes.
add_action( 'woocommerce_payment_gateways_init', 'cclee_payment_init' );

/**
 * Initialize payment gateways.
 */
function cclee_payment_init(): void {
	require_once CCLEE_PAYMENT_PATH . 'includes/class-gateway-abstract.php';
	require_once CCLEE_PAYMENT_PATH . 'includes/class-paypal-gateway.php';
	require_once CCLEE_PAYMENT_PATH . 'includes/class-alipay-gateway.php';
	require_once CCLEE_PAYMENT_PATH . 'includes/class-payoneer-gateway.php';
	require_once CCLEE_PAYMENT_PATH . 'includes/class-webhook-handler.php';
}

// Register gateways with WooCommerce.
add_filter( 'woocommerce_payment_gateways', 'cclee_payment_register_gateways' );

/**
 * Register payment gateways.
 *
 * @param string[] $gateways Existing gateways.
 * @return string[]
 */
function cclee_payment_register_gateways( array $gateways ): array {
	$gateways[] = 'CCLEE_Payment_PayPal_Gateway';
	$gateways[] = 'CCLEE_Payment_Alipay_Gateway';
	$gateways[] = 'CCLEE_Payment_Payoneer_Gateway';
	return $gateways;
}

// Admin assets.
add_action( 'admin_enqueue_scripts', 'cclee_payment_admin_assets' );

/**
 * Enqueue admin styles.
 *
 * @param string $hook Current admin page hook.
 */
function cclee_payment_admin_assets( string $hook ): void {
	if ( 'woocommerce_page_wc-settings' !== $hook ) {
		return;
	}
	wp_enqueue_style(
		'cclee-payment-admin',
		CCLEE_PAYMENT_URL . 'assets/css/admin.css',
		array(),
		CCLEE_PAYMENT_VERSION
	);
}
