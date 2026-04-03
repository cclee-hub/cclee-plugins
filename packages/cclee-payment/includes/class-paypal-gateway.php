<?php
/**
 * PayPal Payment Gateway
 *
 * @package CCLEE_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PayPal payment gateway implementation.
 */
class CCLEE_Payment_PayPal_Gateway extends CCLEE_Payment_Gateway_Abstract {

	/**
	 * @var string
	 */
	public $id = 'cclee_payment_paypal';

	/**
	 * @var string
	 */
	public $method_title = 'CCLEE PayPal';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'cclee_payment_paypal';
		$this->method_title      = 'CCLEE PayPal';
		$this->method_description = 'PayPal payment gateway via CCLEE Payment';
		$this->icon               = '';
		parent::__construct();
		$this->supports_refunds = true;
	}

	/**
	 * Get default title.
	 */
	protected function get_default_title(): string {
		return __( 'PayPal / Credit Card', 'cclee-payment' );
	}

	/**
	 * Get default description.
	 */
	protected function get_default_description(): string {
		return __( 'Pay securely with PayPal or your credit card.', 'cclee-payment' );
	}

	/**
	 * Get API endpoint.
	 */
	protected function get_api_endpoint(): string {
		return $this->get_gateway_option( 'sandbox' ) === 'yes'
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields(): void {
		parent::init_form_fields();

		$this->form_fields['client_id'] = array(
			'title'       => __( 'Client ID', 'cclee-payment' ),
			'type'        => 'text',
			'description' => __( 'Your PayPal application Client ID', 'cclee-payment' ),
			'default'     => '',
			'desc_tip'   => __( 'Get from PayPal Developer Dashboard', 'cclee-payment' ),
		);

		$this->form_fields['client_secret'] = array(
			'title'       => __( 'Client Secret', 'cclee-payment' ),
			'type'        => 'password',
			'description' => __( 'Your PayPal application Client Secret', 'cclee-payment' ),
			'default'     => '',
			'desc_tip'   => __( 'Get from PayPal Developer Dashboard', 'cclee-payment' ),
		);

		$this->form_fields['sandbox'] = array(
			'title'       => __( 'Sandbox Mode', 'cclee-payment' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable sandbox mode for testing', 'cclee-payment' ),
			'default'     => 'no',
			'description' => __( 'Use PayPal sandbox environment', 'cclee-payment' ),
		);
	}

	/**
	 * Process payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array(
				'result'   => 'failure',
				'messages' => array( __( 'Order not found', 'cclee-payment' ) ),
			);
		}

		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return array(
				'result'   => 'failure',
				'messages' => array( $access_token->get_error_message() ),
			);
		}

		$payload = $this->build_paypal_order( $order );
		$response = $this->api_request( 'v2/checkout/orders', $payload, $access_token );

		if ( is_wp_error( $response ) ) {
			$this->log( 'PayPal order creation failed: ' . $response->get_error_message(), 'error' );
			return array(
				'result'   => 'failure',
				'messages' => array( $response->get_error_message() ),
			);
		}

		$approve_url = '';
		foreach ( $response['links'] ?? array() as $link ) {
			if ( 'approve' === $link['rel'] ) {
				$approve_url = $link['href'];
				break;
			}
		}

		if ( empty( $approve_url ) ) {
			return array(
				'result'   => 'failure',
				'messages' => array( __( 'Failed to get approval URL', 'cclee-payment' ) ),
			);
		}

		$order->update_meta_data( '_cclee_paypal_order_id', $response['id'] );
		$order->save();

		$this->log( 'PayPal order created: ' . $response['id'], 'info' );

		return array(
			'result'   => 'success',
			'redirect' => $approve_url,
		);
	}

	/**
	 * Process refund.
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount   Refund amount.
	 * @param string $reason  Refund reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = 0.0, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'order_not_found', __( 'Order not found', 'cclee-payment' ) );
		}

		$paypal_order_id = $order->get_meta( '_cclee_paypal_order_id' );
		$capture_id = $order->get_meta( '_cclee_paypal_capture_id' );

		if ( empty( $capture_id ) ) {
			return new WP_Error( 'no_capture', __( 'No PayPal capture ID found', 'cclee-payment' ) );
		}

		$access_token = $this->get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$payload = array(
			'amount' => array(
				'value'    => number_format( $amount, 2, '.', '' ),
				'currency_code' => $order->get_currency(),
			),
		);

		if ( ! empty( $reason ) ) {
			$payload['note'] = $reason;
		}

		$response = $this->api_request(
			'v2/payments/captures/' . $capture_id . '/refund',
			$payload,
			$access_token
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'PayPal refund failed: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$order->add_order_note( sprintf(
			/* translators: 1: refund amount 2: refund ID */
			__( 'PayPal refund %1$s completed. Refund ID: %2$s', 'cclee-payment' ),
			wc_price( $amount ),
			$response['id'] ?? ''
		) );

		$this->log( 'PayPal refund successful: ' . ( $response['id'] ?? '' ), 'info' );

		return true;
	}

	/**
	 * Get PayPal access token.
	 */
	private function get_access_token(): string|WP_Error {
		$client_id     = $this->get_gateway_option( 'client_id' );
		$client_secret = $this->get_gateway_option( 'client_secret' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new WP_Error( 'missing_credentials', __( 'PayPal credentials not configured', 'cclee-payment' ) );
		}

		$response = wp_remote_post( $this->get_api_endpoint() . '/v1/oauth2/token', array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body' => 'grant_type=client_credentials',
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'token_error', __( 'Failed to get access token', 'cclee-payment' ) );
		}

		$body = json_decode( $response['body'], true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'invalid_token', __( 'Invalid access token response', 'cclee-payment' ) );
		}

		return $body['access_token'];
	}

	/**
	 * Build PayPal order payload.
	 */
	private function build_paypal_order( WC_Order $order ): array {
		return array(
			'intent' => 'CAPTURE',
			'purchase_units' => array(
				array(
					'reference_id' => (string) $order->get_id(),
					'amount'        => array(
						'currency_code' => $order->get_currency(),
						'value'          => number_format( $order->get_total(), 2, '.', '' ),
					),
				),
			),
			'application_context' => array(
				'return_url' => $this->get_return_url( $order ),
				'cancel_url' => $this->get_cancel_url( $order ),
				'notify_url' => $this->get_webhook_url(),
			),
		);
	}

	/**
	 * Make API request.
	 */
	private function api_request( string $endpoint, array $payload, string $access_token ): array|WP_Error {
		$response = wp_remote_post( $this->get_api_endpoint() . '/' . $endpoint, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
				'PayPal-Request-Id' => uniqid(),
			),
			'body' => wp_json_encode( $payload ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', __( 'API request failed', 'cclee-payment' ) );
		}

		$body = json_decode( $response['body'], true );
		if ( 201 !== $response['response']['code'] ) {
			$error_message = $body['message'] ?? __( 'Unknown API error', 'cclee-payment' );
			return new WP_Error( 'api_error', $error_message );
		}

		return $body;
	}

	/**
	 * Validate webhook signature.
	 */
	public function verify_webhook_signature( array $payload, string $signature, string $timestamp ): bool {
		// PayPal webhook verification.
		$client_secret = $this->get_gateway_option( 'client_secret' );
		if ( empty( $client_secret ) ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', wp_json_encode( $payload ), $client_secret );
		return hash_equals( $expected, $signature );
	}

	/**
	 * Handle webhook.
	 */
	public function handle_webhook( array $payload ): void {
		$event_type = $payload['event_type'] ?? '';
		$resource = $payload['resource'] ?? array();

		switch ( $event_type ) {
			case 'CHECKOUT.ORDER.APPROVED':
				$this->handle_payment_approved( $resource );
				break;
			case 'PAYMENT.CAPTURE.COMPLETED':
				$this->handle_capture_completed( $resource );
				break;
			case 'PAYMENT.CAPTURE.DENIED':
				$this->handle_capture_denied( $resource );
				break;
			default:
				$this->log( 'Unhandled webhook event: ' . $event_type, 'warning' );
		}
	}

	/**
	 * Handle payment approved.
	 */
	private function handle_payment_approved( array $resource ): void {
		$order_id = $resource['purchase_units'][0]['reference_id'] ?? 0;
		if ( empty( $order_id ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$order->update_meta_data( '_cclee_paypal_order_id', $resource['id'] );
		$order->save();
		$this->log( 'PayPal order approved: ' . $resource['id'], 'info' );
	}

	/**
	 * Handle capture completed.
	 */
	private function handle_capture_completed( array $resource ): void {
		$order_id = $resource['purchase_units'][0]['reference_id'] ?? 0;
		if ( empty( $order_id ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$order->update_meta_data( '_cclee_paypal_capture_id', $resource['id'] );
		$order->payment_complete();
		$order->add_order_note( sprintf(
			__( 'PayPal payment completed. Transaction ID: %s', 'cclee-payment' ),
			$resource['id']
		) );
		$this->log( 'PayPal capture completed: ' . $resource['id'], 'info' );
	}

	/**
	 * Handle capture denied.
	 */
	private function handle_capture_denied( array $resource ): void {
		$order_id = $resource['purchase_units'][0]['reference_id'] ?? 0;
		if ( empty( $order_id ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$order->update_status( 'failed' );
		$order->add_order_note( sprintf(
			__( 'PayPal payment denied. Transaction ID: %s', 'cclee-payment' ),
			$resource['id']
		) );
		$this->log( 'PayPal capture denied: ' . $resource['id'], 'warning' );
	}
}
