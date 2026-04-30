<?php
/**
 * FedEx label generation test tool.
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCLEE_Shipping_Label_Test {

	/**
	 * FedEx API base URLs.
	 */
	private const SANDBOX_URL    = 'https://apis-sandbox.fedex.com';
	private const PRODUCTION_URL = 'https://apis.fedex.com';

	/**
	 * Hardcoded test addresses for label generation.
	 */
	private const TEST_ORIGIN = array(
		'contact'     => 'Yougu Haohan',
		'company'     => 'Zhongshan Yougu Haohan Co., Ltd.',
		'phone'       => '+86 13622656582',
		'streetLines' => array( 'No. 6 Gangbao Road, Dongsheng Town' ),
		'city'        => 'Zhongshan',
		'state'       => 'GD',
		'postcode'    => '528411',
		'country'     => 'CN',
	);

	private const TEST_DESTINATION = array(
		'contact'     => 'Test Receiver',
		'company'     => 'Acme Corp',
		'phone'       => '9012637906',
		'streetLines' => array( '3621 West Stonebridge Dr' ),
		'city'        => 'Bartlett',
		'state'       => 'TN',
		'postcode'    => '38118',
		'country'     => 'US',
	);

	/**
	 * Create a test shipping label via FedEx Ship API.
	 *
	 * @return array{ success: bool, data?: string, tracking?: string, error?: string }
	 */
	public function create_test_label(): array {
		$credentials = $this->get_fedex_credentials();
		if ( ! $credentials ) {
			return array(
				'success' => false,
				'error'   => __( 'FedEx shipping method not configured. Please add a FedEx instance in Shipping Zones first.', 'cclee-shipping' ),
			);
		}

		$token = $this->get_token( $credentials );
		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'error'   => __( 'FedEx OAuth authentication failed. Check your API Key and Secret.', 'cclee-shipping' ),
			);
		}

		$payload = $this->build_ship_request( $credentials );
		$result  = $this->call_ship_api( $token, $credentials['environment'], $payload );

		if ( ! $result['success'] ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Read FedEx credentials from WooCommerce shipping zone method settings.
	 *
	 * @return array|null Credentials array or null if not found.
	 */
	private function get_fedex_credentials(): ?array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = 'cclee_shipping_fedex'",
			OBJECT
		);

		if ( empty( $results ) ) {
			return null;
		}

		$instance_id = $results[0]->instance_id;
		$settings    = get_option( 'woocommerce_cclee_shipping_fedex_' . $instance_id . '_settings' );

		if ( empty( $settings['api_key'] ) || empty( $settings['secret_key'] ) ) {
			return null;
		}

		return array(
			'api_key'        => $settings['api_key'],
			'secret_key'     => $settings['secret_key'],
			'account_number' => $settings['account_number'] ?? '',
			'environment'    => $settings['environment'] ?? 'sandbox',
		);
	}

	/**
	 * Get OAuth token (with transient caching).
	 *
	 * @param array $credentials FedEx API credentials.
	 */
	private function get_token( array $credentials ): string {
		$transient_key = 'cclee_shipping_fedex_token_' . md5( $credentials['api_key'] . $credentials['environment'] );
		$token         = get_transient( $transient_key );

		if ( false !== $token && is_string( $token ) ) {
			return $token;
		}

		$base_url = $this->get_base_url( $credentials['environment'] );
		$url      = $base_url . '/oauth/token';
		$args     = array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => http_build_query( array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $credentials['api_key'],
				'client_secret' => $credentials['secret_key'],
			) ),
			'timeout' => 10,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code || empty( $body['access_token'] ) ) {
			return '';
		}

		$token = $body['access_token'];
		$ttl   = max( absint( $body['expires_in'] ?? 3600 ) - 60, 60 );
		set_transient( $transient_key, $token, $ttl );

		return $token;
	}

	/**
	 * Build FedEx Ship API request payload.
	 *
	 * @param array $credentials FedEx credentials.
	 */
	private function build_ship_request( array $credentials ): array {
		return array(
			'labelResponseOptions' => 'LABEL',
			'requestedShipment'    => array(
				'shipper'           => array(
					'contact'   => array(
						'personName'  => self::TEST_ORIGIN['contact'],
						'companyName' => self::TEST_ORIGIN['company'],
						'phoneNumber' => self::TEST_ORIGIN['phone'],
					),
					'address'   => array(
						'streetLines'         => self::TEST_ORIGIN['streetLines'],
						'city'                => self::TEST_ORIGIN['city'],
						'stateOrProvinceCode' => self::TEST_ORIGIN['state'],
						'postalCode'          => self::TEST_ORIGIN['postcode'],
						'countryCode'         => self::TEST_ORIGIN['country'],
					),
				),
				'recipients'        => array(
					array(
						'contact' => array(
							'personName'  => self::TEST_DESTINATION['contact'],
							'companyName' => self::TEST_DESTINATION['company'],
							'phoneNumber' => self::TEST_DESTINATION['phone'],
						),
						'address' => array(
							'streetLines'         => self::TEST_DESTINATION['streetLines'],
							'city'                => self::TEST_DESTINATION['city'],
							'stateOrProvinceCode' => self::TEST_DESTINATION['state'],
							'postalCode'          => self::TEST_DESTINATION['postcode'],
							'countryCode'         => self::TEST_DESTINATION['country'],
						),
					),
				),
				'shipDatestamp'     => gmdate( 'Y-m-d' ),
				'serviceType'       => 'FEDEX_INTERNATIONAL_PRIORITY',
				'packagingType'     => 'YOUR_PACKAGING',
				'pickupType'        => 'DROPOFF_AT_FEDEX_LOCATION',
				'shippingChargesPayment' => array(
					'paymentType' => 'SENDER',
					'payor'       => array(
						'responsibleParty' => array(
							'accountNumber' => array( 'value' => $credentials['account_number'] ),
						),
					),
				),
				'customsClearanceDetail' => array(
					'dutiesPayment' => array(
						'paymentType' => 'SENDER',
						'payor'       => array(
							'responsibleParty' => array(
								'accountNumber' => array( 'value' => $credentials['account_number'] ),
							),
						),
					),
					'commodities' => array(
						array(
							'description'          => 'Soldering Products',
							'countryOfManufacture' => 'CN',
							'weight'               => array(
								'units'  => 'KG',
								'value'  => 0.5,
							),
							'numberOfPieces'       => 1,
							'quantity'             => 1,
							'quantityUnits'        => 'PCS',
							'unitPrice'            => array(
								'amount'   => 10.0,
								'currency' => 'USD',
							),
							'customsValue'         => array(
								'amount'   => 10.0,
								'currency' => 'USD',
							),
						),
					),
				),
				'labelSpecification' => array(
					'labelFormatType' => 'COMMON2D',
					'labelStockType'  => 'STOCK_4X6',
					'imageType'       => 'PDF',
				),
				'requestedPackageLineItems' => array(
					array(
						'sequenceNumber' => 1,
						'weight'         => array(
							'units' => 'KG',
							'value' => 0.5,
						),
						'dimensions'     => array(
							'length' => 10,
							'width'  => 8,
							'height' => 4,
							'units'  => 'IN',
						),
						'customerReferences' => array(
							array(
								'customerReferenceType' => 'CUSTOMER_REFERENCE',
								'value'                 => 'TEST-LABEL',
							),
						),
					),
				),
			),
			'accountNumber' => array(
				'value' => $credentials['account_number'],
			),
		);
	}

	/**
	 * Call FedEx Ship API.
	 *
	 * @param string $token       OAuth bearer token.
	 * @param string $environment sandbox or production.
	 * @param array  $payload     Request body.
	 *
	 * @return array{ success: bool, data?: string, tracking?: string, error?: string }
	 */
	private function call_ship_api( string $token, string $environment, array $payload ): array {
		$base_url = $this->get_base_url( $environment );
		$url      = $base_url . '/ship/v1/shipments';

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'X-locale'      => 'en_US',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		);

		$this->log( 'FedEx Ship request URL: ' . $url );
		$this->log( 'FedEx Ship request payload: ' . wp_json_encode( $payload ) );

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code        = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $raw_body, true );

		$this->log( 'FedEx Ship response status: ' . $code );
		$this->log( 'FedEx Ship response body: ' . $raw_body );

		if ( 200 !== $code ) {
			$error_msg = $this->extract_error_message( $body );
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %d: HTTP status code, %s: error message */
					__( 'FedEx API error (%d): %s', 'cclee-shipping' ),
					$code,
					$error_msg
				),
			);
		}

		// Extract label data from response.
		$piece = $body['output']['transactionShipments'][0]['pieceResponses'][0] ?? null;
		if ( empty( $piece ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Label data not found in FedEx response.', 'cclee-shipping' ),
			);
		}

		// Label is in packageDocuments[].encodedLabel.
		$label_data = '';
		foreach ( $piece['packageDocuments'] ?? array() as $doc ) {
			if ( 'LABEL' === ( $doc['contentType'] ?? '' ) && ! empty( $doc['encodedLabel'] ) ) {
				$label_data = $doc['encodedLabel'];
				break;
			}
		}

		if ( empty( $label_data ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Label PDF not found in FedEx response.', 'cclee-shipping' ),
			);
		}

		$tracking_number = $body['output']['transactionShipments'][0]['masterTrackingNumber']
			?? $piece['trackingNumber']
			?? '';

		return array(
			'success' => true,
			'data'    => $label_data,
			'tracking' => $tracking_number,
		);
	}

	/**
	 * Extract human-readable error message from FedEx API response.
	 *
	 * @param array|null $body Decoded response body.
	 */
	private function extract_error_message( ?array $body ): string {
		if ( empty( $body ) ) {
			return __( 'Empty response from FedEx.', 'cclee-shipping' );
		}

		// Try errors array first.
		$errors = $body['errors'] ?? array();
		if ( ! empty( $errors ) ) {
			$messages = array();
			foreach ( $errors as $error ) {
				$messages[] = $error['message'] ?? wp_json_encode( $error );
			}
			return implode( '; ', $messages );
		}

		// Fallback to alerts.
		$alerts = $body['output']['alerts'] ?? array();
		if ( ! empty( $alerts ) ) {
			$messages = array();
			foreach ( $alerts as $alert ) {
				$messages[] = $alert['message'] ?? wp_json_encode( $alert );
			}
			return implode( '; ', $messages );
		}

		return wp_json_encode( $body );
	}

	/**
	 * Get base URL based on environment.
	 *
	 * @param string $environment sandbox or production.
	 */
	private function get_base_url( string $environment ): string {
		return 'production' === $environment ? self::PRODUCTION_URL : self::SANDBOX_URL;
	}

	/**
	 * Log to WooCommerce logger (always, for label test debugging).
	 *
	 * @param string $message Log message.
	 */
	private function log( string $message ): void {
		wc_get_logger()->info( $message, array( 'source' => 'cclee-shipping-label-test' ) );
	}
}
