<?php
/**
 * FedEx shipping method and carrier adapter.
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCLEE_Shipping_FedEx_Carrier extends CCLEE_Shipping_Carrier_Abstract {

	/**
	 * FedEx API base URLs.
	 */
	private const SANDBOX_URL = 'https://apis-sandbox.fedex.com';
	private const PRODUCTION_URL = 'https://apis.fedex.com';

	/**
	 * FedEx preset service types.
	 */
	private const SERVICE_TYPES = array(
		'FEDEX_INTERNATIONAL_PRIORITY',
		'FEDEX_INTERNATIONAL_ECONOMY',
		'FEDEX_GROUND',
	);

	private string $api_key;
	private string $secret_key;
	private string $account_number;
	private string $environment;
	private CCLEE_Shipping_FedEx_Method $method;

	/**
	 * Constructor.
	 *
	 * @param CCLEE_Shipping_FedEx_Method $method Shipping method instance.
	 */
	public function __construct( CCLEE_Shipping_FedEx_Method $method ) {
		$this->method         = $method;
		$this->api_key        = $method->get_option( 'api_key' );
		$this->secret_key     = $method->get_option( 'secret_key' );
		$this->account_number = $method->get_option( 'account_number' );
		$this->environment    = $method->get_option( 'environment' );
	}

	/**
	 * Get base URL for current environment.
	 */
	private function get_base_url(): string {
		return 'production' === $this->environment ? self::PRODUCTION_URL : self::SANDBOX_URL;
	}

	/**
	 * Get cached OAuth token or fetch a new one.
	 */
	public function get_token(): string {
		$transient_key = 'cclee_shipping_fedex_token';
		$token         = get_transient( $transient_key );

		if ( false !== $token && is_string( $token ) ) {
			return $token;
		}

		$url  = $this->get_base_url() . '/oauth/token';
		$args = array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => http_build_query( array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $this->api_key,
				'client_secret' => $this->secret_key,
			) ),
			'timeout' => 10,
		);

		$this->log( $this->method, 'FedEx OAuth: requesting new token' );
		$result = $this->remote_request( $url, $args );

		if ( 200 !== $result['status'] || empty( $result['body']['access_token'] ) ) {
			$this->log( $this->method, 'FedEx OAuth failed: ' . wp_json_encode( $result ) );
			return '';
		}

		$token = $result['body']['access_token'];
		$ttl   = max( absint( $result['body']['expires_in'] ?? 3600 ) - 60, 60 );
		set_transient( $transient_key, $token, $ttl );

		$this->log( $this->method, 'FedEx OAuth: token cached, TTL=' . $ttl );
		return $token;
	}

	/**
	 * Get rates from FedEx Rate and Transit Times API.
	 */
	public function get_rates( array $origin, array $destination, array $packages ): array {
		$token = $this->get_token();
		if ( empty( $token ) ) {
			return array();
		}

		$url     = $this->get_base_url() . '/rate/v1/rates/quotes';
		$payload = $this->build_rate_request( $origin, $destination, $packages );

		$this->log( $this->method, 'FedEx Rate request: ' . wp_json_encode( $payload ) );

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'X-locale'      => 'en_US',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 15,
		);

		$result = $this->remote_request( $url, $args );

		if ( 200 !== $result['status'] || empty( $result['body']['output']['rateReplyDetails'] ) ) {
			$this->log( $this->method, 'FedEx Rate failed: ' . wp_json_encode( $result ) );
			return array();
		}

		return $this->parse_rate_response( $result['body']['output']['rateReplyDetails'] );
	}

	/**
	 * Validate address via FedEx Address Validation API.
	 */
	public function validate_address( array $address ): array {
		$token = $this->get_token();
		if ( empty( $token ) ) {
			return array( 'valid' => false, 'message' => __( 'Unable to authenticate with FedEx.', 'cclee-shipping' ) );
		}

		$url  = $this->get_base_url() . '/address/v1/addresses/resolve';
		$body = array(
			'addressesToValidate' => array(
				array(
					'address' => array(
						'streetLines'         => array( $address['address_1'] ?? '' ),
						'city'                => $address['city'] ?? '',
						'stateOrProvinceCode' => $address['state'] ?? '',
						'postalCode'          => $address['postcode'] ?? '',
						'countryCode'         => strtoupper( $address['country'] ?? '' ),
					),
				),
			),
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'X-locale'      => 'en_US',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 10,
		);

		$this->log( $this->method, 'FedEx Address Validation request' );
		$result = $this->remote_request( $url, $args );

		if ( 200 !== $result['status'] || empty( $result['body']['output']['resolvedAddresses'] ) ) {
			$this->log( $this->method, 'FedEx Address Validation failed: ' . wp_json_encode( $result ) );
			return array( 'valid' => false, 'message' => __( 'Address validation unavailable.', 'cclee-shipping' ) );
		}

		$resolved = $result['body']['output']['resolvedAddresses'][0];
		$messages = $resolved['customerMessage'] ?? array();
		$is_valid = empty( $messages );

		$normalized = array();
		if ( ! empty( $resolved['streetLinesToken'][0] ) ) {
			$normalized['address_1'] = $resolved['streetLinesToken'][0];
		}
		if ( ! empty( $resolved['city'] ) ) {
			$normalized['city'] = $resolved['city'];
		}
		if ( ! empty( $resolved['stateOrProvinceCode'] ) ) {
			$normalized['state'] = $resolved['stateOrProvinceCode'];
		}
		if ( ! empty( $resolved['postalCode'] ) ) {
			$normalized['postcode'] = $resolved['postalCode'];
		}

		return array(
			'valid'      => $is_valid,
			'message'    => $is_valid ? '' : implode( '; ', $messages ),
			'normalized' => $normalized,
		);
	}

	/**
	 * Build the Rate API request payload.
	 */
	private function build_rate_request( array $origin, array $destination, array $packages ): array {
		$is_international = strtoupper( $origin['country'] ) !== strtoupper( $destination['country'] );

		$shipping_payment = $this->method->get_option( 'shipping_payment_type', 'SENDER' );

		$request = array(
			'accountNumber' => array( 'value' => $this->account_number ),
			'requestedShipment' => array(
				'shipper'    => array( 'address' => $this->format_address( $origin ) ),
				'recipient'  => array( 'address' => $this->format_address( $destination ) ),
				'pickupType' => 'DROPOFF_AT_FEDEX_LOCATION',
				'shipmentPaymentType' => $shipping_payment,
				'rateRequestType' => array( 'ACCOUNT', 'LIST' ),
				'packagingType'   => $this->method->get_option( 'package_type', 'YOUR_PACKAGING' ),
				'totalWeight'     => $this->calculate_total_weight( $packages ),
				'totalPackageCount' => count( $packages ),
				'requestedPackageLineItems' => $this->format_packages( $packages ),
			),
		);

		if ( $is_international ) {
			$duties_payment = $this->method->get_option( 'duties_payment_type', 'SENDER' );
			$request['requestedShipment']['customsClearanceDetail'] = array(
				'dutiesPayment' => array(
					'paymentType' => $duties_payment,
				),
				'commodities'   => array(
					array(
						'description'          => __( 'Merchandise', 'cclee-shipping' ),
						'countryOfManufacture' => $origin['country'],
						'quantity'             => 1,
						'quantityUnits'        => 'PCS',
						'weight'               => array(
							'units' => 'LB',
							'value' => $this->calculate_total_weight( $packages ),
						),
						'customsValue'         => array(
							'amount'   => $this->calculate_total_value( $packages ),
							'currency' => get_woocommerce_currency(),
						),
					),
				),
			);
		}

		return $request;
	}

	/**
	 * Format address for FedEx API.
	 */
	private function format_address( array $addr ): array {
		$address = array( 'countryCode' => strtoupper( $addr['country'] ?? '' ) );
		if ( ! empty( $addr['postcode'] ) ) {
			$address['postalCode'] = $addr['postcode'];
		}
		if ( ! empty( $addr['state'] ) ) {
			$address['stateOrProvinceCode'] = $addr['state'];
		}
		if ( ! empty( $addr['city'] ) ) {
			$address['city'] = $addr['city'];
		}
		if ( ! empty( $addr['address'] ) ) {
			$address['streetLines'] = array( $addr['address'] );
		}
		return $address;
	}

	/**
	 * Format cart packages for FedEx API.
	 */
	private function format_packages( array $packages ): array {
		$items = array();
		foreach ( $packages as $package ) {
			$items[] = array(
				'weight' => array( 'units' => 'LB', 'value' => $package['weight'] ),
			);
		}
		if ( empty( $items ) ) {
			$items[] = array( 'weight' => array( 'units' => 'LB', 'value' => 1.0 ) );
		}
		return $items;
	}

	/**
	 * Sum total weight from packages.
	 */
	private function calculate_total_weight( array $packages ): float {
		return (float) array_sum( array_column( $packages, 'weight' ) ) ?: 1.0;
	}

	/**
	 * Sum total value from packages.
	 */
	private function calculate_total_value( array $packages ): float {
		return (float) array_sum( array_column( $packages, 'value' ) ) ?: 1.0;
	}

	/**
	 * Parse FedEx rate response into normalized format.
	 */
	private function parse_rate_response( array $reply_details ): array {
		$rates   = array();
		$enabled = $this->get_enabled_services();

		foreach ( $reply_details as $detail ) {
			$service_type = $detail['serviceType'] ?? '';
			$service_name = $detail['serviceName'] ?? $service_type;

			if ( ! empty( $enabled ) && ! in_array( $service_type, $enabled, true ) ) {
				continue;
			}

			// Prefer ACCOUNT rate, fallback to LIST.
			$cost = 0.0;
			foreach ( $detail['ratedShipmentDetails'] ?? array() as $rated ) {
				if ( 'ACCOUNT' === ( $rated['rateType'] ?? '' ) ) {
					$cost = (float) ( $rated['totalNetCharge'] ?? 0 );
					break;
				}
				if ( 'LIST' === ( $rated['rateType'] ?? '' ) ) {
					$cost = (float) ( $rated['totalNetCharge'] ?? 0 );
				}
			}

			$transit = $detail['commit']['transitDays']['description'] ?? '';

			$rates[] = array(
				'id'           => $this->method->id . ':' . $service_type,
				'label'        => $service_name . ( $transit ? ' (' . $transit . ')' : '' ),
				'cost'         => $cost,
				'transit_days' => $transit,
			);
		}

		return $rates;
	}

	/**
	 * Get enabled service types from method settings.
	 */
	private function get_enabled_services(): array {
		$services = $this->method->get_option( 'services' );
		if ( empty( $services ) ) {
			return array();
		}
		if ( is_string( $services ) ) {
			$services = explode( ',', $services );
		}
		return array_filter( (array) $services );
	}
}
