<?php
/**
 * Package helper - converts cart items to FedEx package line items.
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCLEE_Shipping_Package {

	/**
	 * Convert WooCommerce cart package to FedEx package line items.
	 *
	 * @param array $wc_package WooCommerce shipping package.
	 * @return array<array{ weight: float, value: float }>
	 */
	public static function from_cart( array $wc_package ): array {
		$total_w = 0.0;
		$total_v = 0.0;

		foreach ( $wc_package['contents'] ?? array() as $cart_item ) {
			$product = $cart_item['data'];
			if ( ! $product || ! $product->needs_shipping() ) {
				continue;
			}

			$qty     = (int) $cart_item['quantity'];
			$total_w += (float) $product->get_weight() * $qty;
			$total_v += (float) $product->get_price() * $qty;
		}

		// Convert to pounds if WC uses kg or g.
		$wc_unit = get_option( 'woocommerce_weight_unit', 'kg' );
		if ( 'kg' === $wc_unit ) {
			$total_w *= 2.20462;
		} elseif ( 'g' === $wc_unit ) {
			$total_w *= 0.00220462;
		}

		if ( $total_w <= 0 ) {
			$total_w = 1.0;
		}

		return array(
			array(
				'weight' => round( $total_w, 2 ),
				'value'  => round( $total_v, 2 ),
			),
		);
	}
}
