<?php
/**
 * WooCommerce 增强模块
 *
 * @package CCLEE_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 输出 WooCommerce Product Schema
 *
 * Controlled by: cclee_toolkit_woo_schema_enabled
 */
add_action( 'wp_head', function () {
	if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	if ( ! get_option( 'cclee_toolkit_woo_schema_enabled', true ) ) {
		return;
	}

	global $product;
	if ( ! $product ) {
		return;
	}

	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'name'        => wp_strip_all_tags( get_the_title() ),
		'url'         => get_permalink(),
		'description' => wp_strip_all_tags( get_the_content() ?: '' ),
	);

	// Image
	if ( has_post_thumbnail() ) {
		$schema['image'] = get_the_post_thumbnail_url( null, 'large' );
	}

	// Offers
	$price = $product->get_price();
	if ( $price !== '' ) {
		$schema['offers'] = array(
			'@type'         => 'Offer',
			'price'         => $price,
			'priceCurrency' => get_woocommerce_currency(),
			'availability'  => $product->is_in_stock()
				? 'https://schema.org/InStock'
				: 'https://schema.org/OutOfStock',
		);
	}

	// AggregateRating
	$rating_count = $product->get_rating_count();
	if ( $rating_count > 0 ) {
		$schema['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $product->get_average_rating(),
			'reviewCount' => $rating_count,
		);
	}

	echo "\n<!-- CCLEE Toolkit: WooCommerce Product Schema -->\n";
	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
	);
}, 1 );
