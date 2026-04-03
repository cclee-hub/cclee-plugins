<?php
/**
 * Alipay+ International Payment Gateway
 *
 * @package CCLEE_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alipay+ International payment gateway implementation.
 */
class CCLEE_Payment_Alipay_Gateway extends CCLEE_Payment_Gateway_Abstract {

	/**
	 * @var string
	 */
	public $id = 'cclee_payment_alipay';

	/**
	 * @var string
	 */
	public $method_title = 'CCLEE Alipay+';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'cclee_payment_alipay';
		$this->method_title      = 'CCLEE Alipay+';
		$this->method_description = 'Alipay+ International payment gateway via CCLEE Payment';
		$this->icon               = '';
		parent::__construct();
		$this->supports_refunds = true;
	}

	/**
	 * Get default title.
	 */
	protected function get_default_title(): string {
		return __( 'Alipay+', 'cclee-payment' );
	}

	/**
	 * Get default description.
	 */
	protected function get_default_description(): string {
		return __( 'Pay with Alipay app via QR code.', 'cclee-payment' );
	}

	/**
	 * Get API endpoint.
	 */
	protected function get_api_endpoint(): string {
		return $this->get_gateway_option( 'sandbox' ) === 'yes'
			? 'https://openapi.alipaydev.com'
			: 'https://openapi.alipay.com';
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields(): void {
		parent::init_form_fields();

		$this->form_fields['merchant_id'] = array(
			'title'       => __( 'Merchant ID', 'cclee-payment' ),
			'type'        => 'text',
			'description' => __( 'Your Alipay+ merchant ID', 'cclee-payment' ),
			'default'     => '',
			'desc_tip'   => __( 'Get from Alipay+ merchant portal', 'cclee-payment' ),
		);

		$this->form_fields['private_key'] = array(
			'title'       => __( 'Private Key', 'cclee-payment' ),
			'type'        => 'textarea',
			'description' => __( 'Your RSA private key for signing requests', 'cclee-payment' ),
			'default'     => '',
			'desc_tip'   => __( 'Generate RSA key pair and upload private key', 'cclee-payment' ),
		);

		$this->form_fields['alipay_public_key'] = array(
			'title'       => __( 'Alipay Public Key', 'cclee-payment' ),
			'type'        => 'textarea',
			'description' => __( 'Alipay public key for verifying signatures', 'cclee-payment' ),
			'default'     => '',
			'desc_tip'   => __( 'Get from Alipay+ merchant portal', 'cclee-payment' ),
		);

		$this->form_fields['sandbox'] = array(
			'title'       => __( 'Sandbox Mode', 'cclee-payment' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable sandbox mode for testing', 'cclee-payment' ),
			'default'     => 'no',
			'description' => __( 'Use Alipay+ sandbox environment', 'cclee-payment' ),
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

		$payload = $this->build_alipay_order( $order );
		$response = $this->api_request( 'v1/payments/pay', $payload );

		if ( is_wp_error( $response ) ) {
			$this->log( 'Alipay+ order creation failed: ' . $response->get_error_message(), 'error' );
			return array(
				'result'   => 'failure',
				'messages' => array( $response->get_error_message() ),
			);
		}

		$order->update_meta_data( '_cclee_alipay_order_id', $response['orderId'] );
		$order->save();

		$this->log( 'Alipay+ order created: ' . $response['orderId'], 'info' );

		// Return QR code or redirect URL.
		if ( ! empty( $response['qrCode'] ) ) {
			return array(
				'result'   => 'success',
				'redirect' => $response['qrCode'],
			);
		}

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

		$alipay_order_id = $order->get_meta( '_cclee_alipay_order_id' );
		if ( empty( $alipay_order_id ) ) {
			return new WP_Error( 'no_order', __( 'No Alipay+ order ID found', 'cclee-payment' ) );
		}

		$payload = array(
			'orderId'     => $alipay_order_id,
			'refundAmount' => number_format( $amount, 2, '.', '' ),
			'currency'     => $order->get_currency(),
		);

		if ( ! empty( $reason ) ) {
			$payload['refundReason'] = $reason;
		}

		$response = $this->api_request( 'v1/payments/refund', $payload );

		if ( is_wp_error( $response ) ) {
			$this->log( 'Alipay+ refund failed: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$order->add_order_note( sprintf(
			/* translators: 1: refund amount 2: refund ID */
			__( 'Alipay+ refund %1$s completed. Refund ID: %2$s', 'cclee-payment' ),
			wc_price( $amount ),
			$response['refundId'] ?? ''
		) );

		$this->log( 'Alipay+ refund successful: ' . ( $response['refundId'] ?? '' ), 'info' );

		return true;
	}

	/**
	 * Build Alipay+ order payload.
	 */
	private function build_alipay_order( WC_Order $order ): array {
		$merchant_id = $this->get_gateway_option( 'merchant_id' );
		$timestamp    = time();
		$nonce        = wp_generate_uuid4();

		$payload = array(
			'productCode'        => 'CROSS_BORDER',
			'paymentNotifyUrl'   => $this->get_webhook_url(),
			'paymentRequestId'   => (string) $order->get_id(),
			'order'              => array(
				'referenceOrderId' => (string) $order->get_id(),
				'orderDescription' => sprintf(
					/* translators: %s: order number */
					__( 'Order %s', 'cclee-payment' ),
					$order->get_order_number()
				),
				'amount'            => array(
					'currency' => $order->get_currency(),
					'value'     => number_format( $order->get_total(), 2, '.', '' ),
				),
			),
			'merchant'           => array(
				'merchantId' => $merchant_id,
			),
			'timestamp'          => $timestamp,
			'nonce'              => $nonce,
		);

		$payload['signature'] = $this->generate_signature( $payload );

		return $payload;
	}

	/**
	 * Generate RSA signature.
	 */
	private function generate_signature( array $payload ): string {
		$private_key = $this->get_gateway_option( 'private_key' );
		if ( empty( $private_key ) ) {
			return '';
		}

		$private_key = "-----BEGIN RSA PRIVATE KEY-----\n" .
			wordwrap( $private_key, 64, "\n", true ) .
			"\n-----END RSA PRIVATE KEY-----";

		$data = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		openssl_sign( $data, $signature, $private_key, OPENSSL_ALGO_SHA256 );

		return base64_encode( $signature );
	}

	/**
	 * Verify RSA signature.
	 */
	public function verify_webhook_signature( array $payload, string $signature, string $timestamp ): bool {
		$alipay_public_key = $this->get_gateway_option( 'alipay_public_key' );
		if ( empty( $alipay_public_key ) ) {
			return false;
		}

		$public_key = "-----BEGIN PUBLIC KEY-----\n" .
			wordwrap( $alipay_public_key, 64, "\n", true ) .
			"\n-----END PUBLIC KEY-----";

		$data = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$result = openssl_verify( $data, base64_decode( $signature ), $public_key, OPENSSL_ALGO_SHA256 );

		return 1 === $result;
	}

	/**
	 * Make API request.
	 */
	private function api_request( string $endpoint, array $payload ): array|WP_Error {
		$url = $this->get_api_endpoint() . '/' . $endpoint;

		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'        => 'application/json',
		);

		if ( ! empty( $payload['signature'] ) ) {
			$headers['X-Signature'] = $payload['signature'];
		}

		$response = wp_remote_post( $url, array(
			'headers' => $headers,
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
		$status = $payload['paymentStatus'] ?? '';
		$order_id = $payload['paymentRequestId'] ?? 0;

		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		switch ( $status ) {
			case 'SUCCESS':
				$order->payment_complete();
				$order->add_order_note( sprintf(
					__( 'Alipay+ payment completed. Transaction ID: %s', 'cclee-payment' ),
					$payload['paymentId'] ?? ''
				) );
				break;
			case 'FAIL':
				$order->update_status( 'failed' );
				$order->add_order_note( __( 'Alipay+ payment failed', 'cclee-payment' ) );
				break;
			case 'CANCEL':
				$order->update_status( 'cancelled' );
				$order->add_order_note( __( 'Alipay+ payment cancelled by user', 'cclee-payment' ) );
				break;
			default:
				$this->log( 'Unhandled Alipay+ status: ' . $status, 'warning' );
		}
	}

	/**
	 * Extract order ID from payload.
	 */
	protected function extract_order_id( array $payload ): ?int {
		return isset( $payload['paymentRequestId'] ) ? absint( $payload['paymentRequestId'] ) : null;
	}

	/**
	 * Extract status from payload.
	 */
	protected function extract_status( array $payload ): ?string {
		return $payload['paymentStatus'] ?? null;
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
		return isset( $payload['order']['amount']['value'] )
			? (float) $payload['order']['amount']['value']
			: null;
	}
}
