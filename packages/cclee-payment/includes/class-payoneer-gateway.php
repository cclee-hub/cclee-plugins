<?php
/**
 * Payoneer Payment Gateway
 *
 * @package CCLEE_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payoneer payment gateway implementation.
 */
class CCLEE_Payment_Payoneer_Gateway extends CCLEE_Payment_Gateway_Abstract {

	/**
	 * @var string
	 */
	public $id = 'cclee_payment_payoneer';

	/**
	 * @var string
	 */
	public $method_title = 'CCLEE Payoneer';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'cclee_payment_payoneer';
		$this->method_title      = 'CCLEE Payoneer';
		$this->method_description = 'Payoneer payment gateway via CCLEE Payment';
		$this->icon               = '';
		parent::__construct();
		$this->supports_refunds = true;
	}

	/**
	 * Get default title.
	 */
	protected function get_default_title(): string {
		return __( 'Payoneer', 'cclee-payment' );
	}

	/**
	 * Get default description.
	 */
	protected function get_default_description(): string {
		return __( 'Pay securely with Payoneer.', 'cclee-payment' );
	}

	/**
	 * Get API endpoint.
	 */
	protected function get_api_endpoint(): string {
		return $this->get_gateway_option( 'sandbox' ) === 'yes'
			? 'https://api.sandbox.payoneer.com'
			: 'https://api.payoneer.com';
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields(): void {
		parent::init_form_fields();

		$this->form_fields['api_key'] = array(
			'title'       => __( 'API Key', 'cclee-payment' ),
			'type'        => 'text',
			'description' => __( 'Your Payoneer API key', 'cclee-payment' ),
			'default'     => '',
			'desc_tip'   => __( 'Get from Payoneer partner portal', 'cclee-payment' ),
		);

		$this->form_fields['api_token'] = array(
			'title'       => __( 'API Token', 'cclee-payment' ),
			'type'        => 'password',
			'description' => __( 'Your Payoneer API token', 'cclee-payment' ),
			'default'     => '',
			'desc_tip'   => __( 'Get from Payoneer partner portal', 'cclee-payment' ),
		);

		$this->form_fields['program_id'] = array(
			'title'       => __( 'Program ID', 'cclee-payment' ),
			'type'        => 'text',
			'description' => __( 'Your Payoneer program ID', 'cclee-payment' ),
			'default'     => '',
			'desc_tip'   => __( 'Get from Payoneer partner portal', 'cclee-payment' ),
		);

		$this->form_fields['sandbox'] = array(
			'title'       => __( 'Sandbox Mode', 'cclee-payment' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable sandbox mode for testing', 'cclee-payment' ),
			'default'     => 'no',
			'description' => __( 'Use Payoneer sandbox environment', 'cclee-payment' ),
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

		$payload = $this->build_payoneer_order( $order );
		$response = $this->api_request( 'v3/payments', $payload );

		if ( is_wp_error( $response ) ) {
			$this->log( 'Payoneer order creation failed: ' . $response->get_error_message(), 'error' );
			return array(
				'result'   => 'failure',
				'messages' => array( $response->get_error_message() ),
			);
		}

		$order->update_meta_data( '_cclee_payoneer_payment_id', $response['paymentId'] );
		$order->save();

		$this->log( 'Payoneer payment created: ' . $response['paymentId'], 'info' );

		if ( ! empty( $response['redirectUrl'] ) ) {
			return array(
				'result'   => 'success',
				'redirect' => $response['redirectUrl'],
			);
		}

		return array(
			'result'   => 'failure',
			'messages' => array( __( 'Failed to get payment URL', 'cclee-payment' ) ),
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

		$payment_id = $order->get_meta( '_cclee_payoneer_payment_id' );
		if ( empty( $payment_id ) ) {
			return new WP_Error( 'no_payment', __( 'No Payoneer payment ID found', 'cclee-payment' ) );
		}

		$payload = array(
			'paymentId'    => $payment_id,
			'refundAmount' => number_format( $amount, 2, '.', '' ),
			'currency'      => $order->get_currency(),
		);

		if ( ! empty( $reason ) ) {
			$payload['reason'] = $reason;
		}

		$response = $this->api_request( 'v3/payments/' . $payment_id . '/refund', $payload );

		if ( is_wp_error( $response ) ) {
			$this->log( 'Payoneer refund failed: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$order->add_order_note( sprintf(
			/* translators: 1: refund amount 2: refund ID */
			__( 'Payoneer refund %1$s completed. Refund ID: %2$s', 'cclee-payment' ),
			wc_price( $amount ),
			$response['refundId'] ?? ''
		) );

		$this->log( 'Payoneer refund successful: ' . ( $response['refundId'] ?? '' ), 'info' );

		return true;
	}

	/**
	 * Build Payoneer order payload.
	 */
	private function build_payoneer_order( WC_Order $order ): array {
		$program_id = $this->get_gateway_option( 'program_id' );
		$timestamp  = time();

		return array(
			'programId'         => $program_id,
			'paymentReference' => (string) $order->get_id(),
			'amount'            => array(
				'currency' => $order->get_currency(),
				'value'     => number_format( $order->get_total(), 2, '.', '' ),
			),
			'customer'          => array(
				'email'     => $order->get_billing_email(),
				'firstName' => $order->get_billing_first_name(),
				'lastName'  => $order->get_billing_last_name(),
				'phone'     => $order->get_billing_phone(),
			),
			'billingAddress'   => array(
				'addressLine1' => $order->get_billing_address_1(),
				'addressLine2' => $order->get_billing_address_2(),
				'city'          => $order->get_billing_city(),
				'state'         => $order->get_billing_state(),
				'country'       => $order->get_billing_country(),
				'zip'           => $order->get_billing_postcode(),
			),
			'callbackUrls'      => array(
				'success' => $this->get_return_url( $order ),
				'cancel'  => $this->get_cancel_url( $order ),
				'notify'  => $this->get_webhook_url(),
			),
			'timestamp'          => $timestamp,
		);
	}

	/**
	 * Make API request.
	 */
	private function api_request( string $endpoint, array $payload ): array|WP_Error {
		$api_key   = $this->get_gateway_option( 'api_key' );
		$api_token = $this->get_gateway_option( 'api_token' );

		if ( empty( $api_key ) || empty( $api_token ) ) {
			return new WP_Error( 'missing_credentials', __( 'Payoneer credentials not configured', 'cclee-payment' ) );
		}

		$url = $this->get_api_endpoint() . '/' . $endpoint;

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_token,
				'X-API-Key'     => $api_key,
				'Content-Type'  => 'application/json',
				'X-Request-Id'  => uniqid(),
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', __( 'API request failed', 'cclee-payment' ) );
		}

		$body = json_decode( $response['body'], true );
		if ( 200 !== $response['response']['code'] ) {
			$error_message = $body['message'] ?? __( 'Unknown API error', 'cclee-payment' );
			return new WP_Error( 'api_error', $error_message );
		}

		return $body;
	}

	/**
	 * Handle webhook.
	 */
	public function handle_webhook( array $payload ): void {
		$status = $payload['status'] ?? '';
		$order_id = $payload['paymentReference'] ?? 0;

		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		switch ( $status ) {
			case 'COMPLETED':
				$order->payment_complete();
				$order->add_order_note( sprintf(
					__( 'Payoneer payment completed. Transaction ID: %s', 'cclee-payment' ),
					$payload['paymentId'] ?? ''
				) );
				break;
			case 'FAILED':
				$order->update_status( 'failed' );
				$order->add_order_note( __( 'Payoneer payment failed', 'cclee-payment' ) );
				break;
			case 'CANCELLED':
				$order->update_status( 'cancelled' );
				$order->add_order_note( __( 'Payoneer payment cancelled', 'cclee-payment' ) );
				break;
			case 'REFUNDED':
				$order->update_status( 'refunded' );
				$order->add_order_note( sprintf(
					__( 'Payoneer payment refunded. Transaction ID: %s', 'cclee-payment' ),
					$payload['paymentId'] ?? ''
				) );
				break;
			default:
				$this->log( 'Unhandled Payoneer status: ' . $status, 'warning' );
		}
	}

	/**
	 * Validate webhook signature.
	 */
	public function verify_webhook_signature( array $payload, string $signature, string $timestamp ): bool {
		$api_token = $this->get_gateway_option( 'api_token' );
		if ( empty( $api_token ) ) {
			return false;
		}

		// Payoneer uses HMAC-SHA256.
		$expected = hash_hmac( 'sha256', wp_json_encode( $payload ), $api_token );
		return hash_equals( $expected, $signature );
	}

	/**
	 * Extract order ID from payload.
	 */
	protected function extract_order_id( array $payload ): ?int {
		return isset( $payload['paymentReference'] ) ? absint( $payload['paymentReference'] ) : null;
	}

	/**
	 * Extract status from payload.
	 */
	protected function extract_status( array $payload ): ?string {
		return $payload['status'] ?? null;
	}

	/**
	 * Extract transaction ID from payload.
	 */
	protected function extract_transaction_id( array $payload ): ?string {
		return $payload['paymentId'] ?? null;
	}

	/**
	 * Extract amount from payload.
	 */
	protected function extract_amount( array $payload ): ?float {
		return isset( $payload['amount']['value'] )
			? (float) $payload['amount']['value']
			: null;
	}
}
