<?php
/**
 * Abstract payment gateway base class.
 *
 * @package CCLEE_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract payment gateway base class.
 */
abstract class CCLEE_Payment_Gateway_Abstract extends WC_Payment_Gateway {

	/**
	 * @var string[]
	 */
	public $id;

	/**
	 * @var string|null
	 */
	public $icon;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = strtolower( str_replace( '_Gateway', '', get_class( $this ) ) );
		$this->icon         = '';
		$this->has_fields   = true;
		$this->supports     = array( 'refunds' );

		$this->init_form_fields();
		$this->init_settings();
	}

	/**
	 * Get default gateway title.
	 */
	abstract protected function get_default_title(): string;

	/**
	 * Get default gateway description.
	 */
	abstract protected function get_default_description(): string;

	/**
	 * Get API endpoint.
	 */
	abstract protected function get_api_endpoint(): string;

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'Enable/Disable', 'cclee-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this payment method', 'cclee-payment' ),
				'default' => 'yes',
			),
			'title'        => array(
				'title'       => __( 'Title', 'cclee-payment' ),
				'type'        => 'text',
				'description' => __( 'Payment method title displayed to customers', 'cclee-payment' ),
				'default'     => $this->get_default_title(),
				'desc_tip'   => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'cclee-payment' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description displayed to customers', 'cclee-payment' ),
				'default'     => $this->get_default_description(),
				'desc_tip'   => true,
			),
			'debug'        => array(
				'title'       => __( 'Debug Logging', 'cclee-payment' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable debug logging', 'cclee-payment' ),
				'default'     => 'no',
				'description' => __( 'Log payment events for troubleshooting', 'cclee-payment' ),
			),
		);
	}

	/**
	 * Initialize settings.
	 */
	public function init_settings(): void {
		parent::init_settings();

		foreach ( $this->form_fields as $key => $field ) {
			$this->{$key} = $this->get_option( $key );
		}
	}

	/**
	 * Get gateway option.
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	public function get_gateway_option( string $key ) {
		return $this->get_option( $key );
	}

	/**
	 * Log message.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level.
	 */
	protected function log( string $message, string $level = 'info' ): void {
		if ( 'yes' === $this->debug ) {
			if ( ! class_exists( 'WC_Logger' ) ) {
				return;
			}
			$logger = wc_get_logger();
			$context = array( 'source' => 'cclee-payment-' . $this->id );
			$logger->{$level}( $message, $context );
		}
	}

	/**
	 * Build order payload for API request.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	protected function build_order_payload( WC_Order $order ): array {
		return array(
			'order_id'     => (string) $order->get_id(),
			'amount'       => (float) $order->get_total(),
			'currency'     => (string) $order->get_currency(),
			'return_url'   => $this->get_return_url( $order ),
			'cancel_url'  => $this->get_cancel_url( $order ),
			'notify_url'  => $this->get_webhook_url(),
		);
	}

	/**
	 * Get cancel URL.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	protected function get_cancel_url( WC_Order $order ): string {
		return add_query_arg( 'cancel', 'true', $order->get_cancel_order_url() );
	}

	/**
	 * Get webhook URL.
	 *
	 * @return string
	 */
	protected function get_webhook_url(): string {
		return add_query_arg( 'gateway', $this->id, WC()->api_request_url( 'CCLEE_Payment_Webhook_Handler::handle' ) );
	}

	/**
	 * Process payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		// Subclasses must override this method.
		return array(
			'result'   => 'failure',
			'messages' => 'Payment method not implemented',
		);
	}

	/**
	 * Process refund.
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount   Refund amount.
	 * @param string $reason   Refund reason.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = 0.0, $reason = '' ) {
		// Subclasses must override this method.
		return new \WP_Error( 'not-implemented', 'Refund not implemented for this gateway' );
	}

	/**
	 * Validate webhook signature.
	 *
	 * @param array $payload Webhook payload.
	 * @param string $signature Webhook signature.
	 * @param string $timestamp Webhook timestamp.
	 * @return bool
	 */
	abstract public function verify_webhook_signature( array $payload, string $signature, string $timestamp ): bool;

	/**
	 * Handle webhook.
	 *
	 * @param array $payload Webhook payload.
	 */
	abstract public function handle_webhook( array $payload ): void;

	/**
	 * Extract order ID from payload.
	 */
	protected function extract_order_id( array $payload ): ?int {
		return null;
	}

	/**
	 * Extract status from payload.
	 */
	protected function extract_status( array $payload ): ?string {
		return null;
	}

	/**
	 * Extract transaction ID from payload.
	 */
	protected function extract_transaction_id( array $payload ): ?string {
		return null;
	}

	/**
	 * Extract amount from payload.
	 */
	protected function extract_amount( array $payload ): ?float {
		return null;
	}
}
