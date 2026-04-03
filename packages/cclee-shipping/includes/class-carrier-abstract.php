<?php
/**
 * Abstract carrier class.
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class CCLEE_Shipping_Carrier_Abstract {

	/**
	 * Get OAuth bearer token (cached in transient).
	 */
	abstract public function get_token(): string;

	/**
	 * Get shipping rates from carrier API.
	 *
	 * @param array $origin      Shipper address.
	 * @param array $destination Recipient address.
	 * @param array $packages    Package line items from cart.
	 * @return array<array{ id: string, label: string, cost: float, transit_days?: string }>
	 */
	abstract public function get_rates( array $origin, array $destination, array $packages ): array;

	/**
	 * Validate an address via carrier API.
	 *
	 * @param array $address Address components.
	 * @return array{ valid: bool, message: string, normalized?: array }
	 */
	abstract public function validate_address( array $address ): array;

	/**
	 * Log if debug mode is enabled.
	 *
	 * @param WC_Shipping_Method $method  Shipping method instance.
	 * @param string             $message Log message.
	 */
	protected function log( WC_Shipping_Method $method, string $message ): void {
		if ( 'yes' === $method->get_option( 'debug' ) ) {
			wc_get_logger()->info( $message, array( 'source' => 'cclee-shipping-' . $method->id ) );
		}
	}

	/**
	 * HTTP request wrapper.
	 *
	 * @param string $url  Request URL.
	 * @param array  $args wp_remote_request args.
	 * @return array{ status: int, body: array|null, error?: string }
	 */
	protected function remote_request( string $url, array $args ): array {
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 0,
				'body'   => null,
				'error'  => $response->get_error_message(),
			);
		}

		return array(
			'status' => wp_remote_retrieve_response_code( $response ),
			'body'   => json_decode( wp_remote_retrieve_body( $response ), true ),
		);
	}
}
