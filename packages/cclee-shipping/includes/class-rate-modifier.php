<?php
/**
 * Rate modifier - applies markup to carrier rates.
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCLEE_Shipping_Rate_Modifier {

	/**
	 * Apply rate modifier to a base cost.
	 *
	 * @param float  $base_cost Carrier rate.
	 * @param string $type      'fixed' or 'percentage'.
	 * @param float  $value     Modifier value.
	 * @return float
	 */
	public static function apply( float $base_cost, string $type, float $value ): float {
		if ( $value <= 0 ) {
			return $base_cost;
		}

		if ( 'percentage' === $type ) {
			return round( $base_cost * ( 1 + $value / 100 ), 2 );
		}

		return round( $base_cost + $value, 2 );
	}
}
