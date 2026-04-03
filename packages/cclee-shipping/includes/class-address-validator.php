<?php
/**
 * Address validator - AJAX endpoint for async FedEx address validation.
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCLEE_Shipping_Address_Validator {

	/**
	 * AJAX handler for address validation.
	 */
	public static function ajax_validate(): void {
		check_ajax_referer( 'cclee_shipping_validate', 'nonce' );

		$address = array(
			'address_1' => sanitize_text_field( wp_unslash( $_POST['address_1'] ?? '' ) ),
			'city'      => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
			'state'     => sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) ),
			'postcode'  => sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) ),
			'country'   => sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ),
		);

		if ( empty( $address['country'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Country is required.', 'cclee-shipping' ) ) );
		}

		$method = self::get_fedex_method();
		if ( ! $method ) {
			wp_send_json_error( array( 'message' => __( 'No FedEx shipping method configured.', 'cclee-shipping' ) ) );
		}

		$carrier = new CCLEE_Shipping_FedEx_Carrier( $method );
		$result  = $carrier->validate_address( $address );

		if ( $result['valid'] ) {
			wp_send_json_success( array(
				'valid'      => true,
				'normalized' => $result['normalized'] ?? array(),
			) );
		} else {
			wp_send_json_success( array(
				'valid'   => false,
				'message' => $result['message'],
			) );
		}
	}

	/**
	 * Get the first active FedEx shipping method instance.
	 *
	 * @return CCLEE_Shipping_FedEx_Method|null
	 */
	private static function get_fedex_method(): ?CCLEE_Shipping_FedEx_Method {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return null;
		}

		$zones   = WC_Shipping_Zones::get_zones();
		$zones[] = array( 'zone_id' => 0 );

		foreach ( $zones as $zone_data ) {
			$zone    = WC_Shipping_Zones::get_zone( $zone_data['zone_id'] );
			$methods = $zone->get_shipping_methods();

			foreach ( $methods as $instance ) {
				if ( $instance instanceof CCLEE_Shipping_FedEx_Method
					&& ! empty( $instance->get_option( 'api_key' ) ) ) {
					return $instance;
				}
			}
		}

		return null;
	}
}
