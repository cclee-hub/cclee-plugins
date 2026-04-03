<?php
/**
 * SF Express carrier adapter.
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCLEE_Shipping_SF_Carrier extends CCLEE_Shipping_Carrier_Abstract {

	/**
	 * SF Express API base URLs.
	 */
	private const SANDBOX_URL = 'http://sfapi-sbox.sf-express.com/std/service';
	private const PRODUCTION_URL = 'https://sfapi.sf-express.com/std/service';

	/**
	 * SF Express product type codes for domestic China.
	 */
	private const PRODUCT_TYPES = array(
		'1'  => '顺丰即日',
		'2'  => '顺丰标快',
		'3'  => '顺丰特惠',
		'5'  => '顺丰次晨',
		'6'  => '顺丰标快（陆运）',
		'7'  => '顺丰特惠（陆运）',
		'9'  => '顺丰同城',
		'14' => '顺丰即日（同城）',
		'33' => '生鲜速配',
		'36' => '跨境速配（国际）',
		'37' => '国际特惠（国际）',
		'38' => '国际标快（国际）',
		'39' => '重货包裹',
		'44' => '国际电商专递（国际）',
	);

	private string $customer_code;
	private string $check_word;
	private string $environment;
	private CCLEE_Shipping_SF_Method $method;

	/**
	 * Constructor.
	 *
	 * @param CCLEE_Shipping_SF_Method $method Shipping method instance.
	 */
	public function __construct( CCLEE_Shipping_SF_Method $method ) {
		$this->method        = $method;
		$this->customer_code = $method->get_option( 'customer_code' );
		$this->check_word    = $method->get_option( 'check_word' );
		$this->environment   = $method->get_option( 'environment' );
	}

	/**
	 * Get base URL for current environment.
	 */
	private function get_base_url(): string {
		return 'production' === $this->environment ? self::PRODUCTION_URL : self::SANDBOX_URL;
	}

	/**
	 * SF Express uses per-request MD5 signature, no token caching needed.
	 *
	 * @return string Always empty (signing is per-request).
	 */
	public function get_token(): string {
		return '';
	}

	/**
	 * Generate MD5 digital signature for SF Express API.
	 *
	 * @param string $msg_data  Business data JSON string.
	 * @param string $timestamp Timestamp string (milliseconds).
	 * @return string Base64-encoded MD5 signature.
	 */
	private function generate_signature( string $msg_data, string $timestamp ): string {
		$raw = $msg_data . $timestamp . $this->check_word;
		return base64_encode( md5( $raw, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Send request to SF Express API.
	 *
	 * @param string $service_code API service code.
	 * @param array  $msg_data     Business data.
	 * @return array{ api_error: string, api_error_msg: string, result: array|null }
	 */
	private function send_request( string $service_code, array $msg_data ): array {
		$timestamp = (string) round( microtime( true ) * 1000 );
		$msg_json  = wp_json_encode( $msg_data );
		$signature = $this->generate_signature( $msg_json, $timestamp );

		$args = array(
			'method'  => 'POST',
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8' ),
			'body'    => array(
				'partnerID'   => $this->customer_code,
				'requestID'   => wp_generate_uuid4(),
				'serviceCode' => $service_code,
				'timestamp'   => $timestamp,
				'msgDigest'   => $signature,
				'msgData'     => $msg_json,
			),
			'timeout' => 15,
		);

		$this->log( $this->method, "SF {$service_code} request: " . $msg_json );

		$response = wp_remote_post( $this->get_base_url(), $args );

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			$this->log( $this->method, "SF {$service_code} WP error: {$error}" );
			return array(
				'api_error'     => 'WP_ERROR',
				'api_error_msg' => $error,
				'result'        => null,
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! $body ) {
			$this->log( $this->method, "SF {$service_code} invalid response" );
			return array(
				'api_error'     => 'INVALID_RESPONSE',
				'api_error_msg' => __( 'Invalid response from SF Express.', 'cclee-shipping' ),
				'result'        => null,
			);
		}

		$api_error = $body['apiErrorCode'] ?? '';
		if ( 'A1000' !== $api_error ) {
			$error_msg = $body['apiErrorMsg'] ?? $api_error;
			$this->log( $this->method, "SF {$service_code} API error: {$api_error} - {$error_msg}" );
			return array(
				'api_error'     => $api_error,
				'api_error_msg' => $error_msg,
				'result'        => null,
			);
		}

		$result = json_decode( $body['apiResultData'] ?? '', true );
		if ( ! $result || empty( $result['success'] ) ) {
			$biz_error   = $result['errorCode'] ?? 'UNKNOWN';
			$biz_msg     = $result['errorMsg'] ?? '';
			$this->log( $this->method, "SF {$service_code} biz error: {$biz_error} - {$biz_msg}" );
			return array(
				'api_error'     => $biz_error,
				'api_error_msg' => $biz_msg,
				'result'        => null,
			);
		}

		return array(
			'api_error'     => '',
			'api_error_msg' => '',
			'result'        => $result['msgData'] ?? array(),
		);
	}

	/**
	 * Get shipping rates from SF Express.
	 *
	 * @param array $origin      Shipper address (city field used directly).
	 * @param array $destination Recipient address (city field used directly).
	 * @param array $packages    Package line items from cart.
	 * @return array<array{ id: string, label: string, cost: float, transit_days?: string }>
	 */
	public function get_rates( array $origin, array $destination, array $packages ): array {
		if ( empty( $this->customer_code ) || empty( $this->check_word ) ) {
			return array();
		}

		$total_weight = 0.0;
		foreach ( $packages as $package ) {
			$total_weight += (float) ( $package['weight'] ?? 0 );
		}
		if ( $total_weight <= 0 ) {
			$total_weight = 1.0;
		}

		// Convert pounds to kilograms for SF Express.
		$wc_unit = get_option( 'woocommerce_weight_unit', 'kg' );
		if ( 'lbs' === $wc_unit || 'lb' === $wc_unit ) {
			$total_weight *= 0.453592;
		} elseif ( 'g' === $wc_unit ) {
			$total_weight *= 0.001;
		}

		$express_type = $this->method->get_option( 'express_type', '' );
		$consigned    = wp_date( 'Y-m-d H:i:s' );

		$msg_data = array(
			'language'      => 'zh-CN',
			'searchPrice'   => '1',
			'consignedTime' => $consigned,
			'srcAddress'    => array(
				'city'     => $origin['city'] ?? '',
				'province' => $origin['state'] ?? '',
			),
			'destAddress'   => array(
				'city'     => $destination['city'] ?? '',
				'province' => $destination['state'] ?? '',
			),
			'weight'        => round( $total_weight, 2 ),
		);

		if ( ! empty( $express_type ) ) {
			$msg_data['businessType'] = $express_type;
		}

		$response = $this->send_request( 'EXP_RECE_QUERY_DELIVERTM', $msg_data );

		if ( ! empty( $response['api_error'] ) || empty( $response['result'] ) ) {
			return array();
		}

		return $this->parse_rate_response( $response['result'] );
	}

	/**
	 * Address validation not supported for SF Express MVP.
	 * Address issues will surface through rate query errors.
	 *
	 * @param array $address Address components.
	 * @return array{ valid: bool, message: string }
	 */
	public function validate_address( array $address ): array {
		return array( 'valid' => true, 'message' => '' );
	}

	/**
	 * Parse SF Express rate response into normalized format.
	 *
	 * @param array $msg_data Response msgData.
	 * @return array<array{ id: string, label: string, cost: float, transit_days?: string }>
	 */
	private function parse_rate_response( array $msg_data ): array {
		$rates    = array();
		$enabled  = $this->get_enabled_types();
		$deliveries = $msg_data['deliverTmList'] ?? $msg_data['deliverTMList'] ?? array();

		if ( ! is_array( $deliveries ) ) {
			$deliveries = array();
		}

		foreach ( $deliveries as $item ) {
			$type_code = $item['businessType'] ?? '';
			$type_desc = $item['businessTypeDesc'] ?? $type_code;

			// Filter by enabled types if configured.
			if ( ! empty( $enabled ) && ! in_array( $type_code, $enabled, true ) ) {
				continue;
			}

			$cost   = (float) ( $item['fee'] ?? 0 );
			$commit = $item['deliverTime'] ?? '';

			$rates[] = array(
				'id'           => $this->method->id . ':' . $type_code,
				'label'        => $type_desc . ( $commit ? ' (' . $commit . ')' : '' ),
				'cost'         => $cost,
				'transit_days' => $commit,
			);
		}

		return $rates;
	}

	/**
	 * Get enabled product types from method settings.
	 *
	 * @return array
	 */
	private function get_enabled_types(): array {
		$types = $this->method->get_option( 'services' );
		if ( empty( $types ) ) {
			return array();
		}
		if ( is_string( $types ) ) {
			$types = explode( ',', $types );
		}
		return array_filter( (array) $types );
	}
}
