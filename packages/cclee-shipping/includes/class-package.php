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
	 * Convert WooCommerce cart package to shipping package line items.
	 *
	 * Aggregates weight, value, and (when available) dimensions from all
	 * cart items into a single package. Dimensions are included only when
	 * every shippable product in the cart has L/W/H configured.
	 *
	 * @param array $wc_package WooCommerce shipping package.
	 * @return array<array{ weight: float, value: float, length?: float, width?: float, height?: float, dim_unit?: string }>
	 */
	public static function from_cart( array $wc_package ): array {
		$total_w = 0.0;
		$total_v = 0.0;

		$all_have_dims = true;
		$max_l         = 0.0;
		$max_w         = 0.0;
		$total_volume  = 0.0;

		foreach ( $wc_package['contents'] ?? array() as $cart_item ) {
			$product = $cart_item['data'];
			if ( ! $product || ! $product->needs_shipping() ) {
				continue;
			}

			$qty     = (int) $cart_item['quantity'];
			$total_w += (float) $product->get_weight() * $qty;
			$total_v += (float) $product->get_price() * $qty;

			$l = (float) $product->get_length();
			$w = (float) $product->get_width();
			$h = (float) $product->get_height();

			if ( $l > 0 && $w > 0 && $h > 0 ) {
				$max_l        = max( $max_l, $l );
				$max_w        = max( $max_w, $w );
				$total_volume += $l * $w * $h * $qty;
			} else {
				$all_have_dims = false;
			}
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

		$result = array(
			'weight' => round( $total_w, 2 ),
			'value'  => round( $total_v, 2 ),
		);

		if ( $all_have_dims && $total_volume > 0 && $max_l > 0 && $max_w > 0 ) {
			$dim_data       = self::convert_dimensions( $max_l, $max_w, $total_volume / ( $max_l * $max_w ) );
			$result['length']   = $dim_data['length'];
			$result['width']    = $dim_data['width'];
			$result['height']   = $dim_data['height'];
			$result['dim_unit'] = $dim_data['unit'];
		}

		return array( $result );
	}

	/**
	 * Convert dimensions from WC dimension unit to FedEx-accepted units (CM or IN).
	 *
	 * @param float $l Length in WC dimension unit.
	 * @param float $w Width in WC dimension unit.
	 * @param float $h Height in WC dimension unit.
	 * @return array{ length: float, width: float, height: float, unit: string }
	 */
	private static function convert_dimensions( float $l, float $w, float $h ): array {
		$wc_unit = get_option( 'woocommerce_dimension_unit', 'cm' );

		switch ( $wc_unit ) {
			case 'm':
				$l *= 100;
				$w *= 100;
				$h *= 100;
				$unit = 'CM';
				break;
			case 'mm':
				$l /= 10;
				$w /= 10;
				$h /= 10;
				$unit = 'CM';
				break;
			case 'yd':
				$l *= 36;
				$w *= 36;
				$h *= 36;
				$unit = 'IN';
				break;
			case 'in':
				$unit = 'IN';
				break;
			case 'cm':
			default:
				$unit = 'CM';
				break;
		}

		return array(
			'length' => round( $l, 1 ),
			'width'  => round( $w, 1 ),
			'height' => round( $h, 1 ),
			'unit'   => $unit,
		);
	}
}
