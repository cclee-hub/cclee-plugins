<?php
/**
 * Webhook Handler
 *
 * @package CCLEE_Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook handler for payment notifications.
 */
class CCLEE_Payment_Webhook_Handler {

	/**
	 * Supported gateway types.
	 */
    private static array $supported_gateways = array(
        'paypal',
        'alipay',
        'payoneer',
    );

    /**
	 * Initialize webhook handler.
     */
    public static function init(): void {
        add_action( 'woocommerce_api_cclee_payment_webhook', array( __CLASS__, 'handle' ) );
    }

    /**
	 * Handle incoming webhook.
     */
    public static function handle(): void {
        $gateway_type = isset( $_GET['gateway'] ) ? sanitize_text_field( wp_unslash( $_GET['gateway'] ) ) : '';

        if ( ! in_array( $gateway_type, self::$supported_gateways, true ) ) {
            self::send_response( 400, 'Invalid gateway type' );
            return;
        }

        $payload = self::get_payload();
        if ( empty( $payload ) ) {
            self::send_response( 400, 'Empty payload' );
            return;
        }

        $signature = isset( $_SERVER['HTTP_X_SIGNATURE'] ) ? $_SERVER['HTTP_X_SIGNATURE'] : '';
        $timestamp = isset( $_SERVER['HTTP_X_TIMESTAMP'] ) ? $_SERVER['HTTP_X_TIMESTAMP'] : '';

        // Verify signature.
        $gateway_class = 'CCLEE_Payment_' . ucfirst( $gateway_type ) . '_Gateway';
        if ( class_exists( $gateway_class ) ) {
            $gateway = new $gateway_class();
            if ( method_exists( $gateway, 'verify_webhook_signature' ) ) {
                if ( ! $gateway->verify_webhook_signature( $payload, $signature, $timestamp ) ) {
                    self::send_response( 401, 'Invalid signature' );
                    return;
                }
            }
        }

        // Process webhook.
        $result = self::process_webhook( $gateway_type, $payload );
        if ( is_wp_error( $result ) ) {
            self::send_response( 400, $result->get_error_message() );
        } else {
            self::send_response( 200, 'OK' );
        }
    }

    /**
     * Get raw payload.
     */
    private static function get_payload(): array {
        $raw_input = file_get_contents( 'php://input' );
        $input = json_decode( $raw_input, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            $input = array();
        }
        return $input;
    }

    /**
     * Process webhook by gateway type.
     */
    private static function process_webhook( string $gateway_type, array $payload ): bool|WP_Error {
        $order_id = self::extract_order_id( $payload );
        if ( empty( $order_id ) ) {
            return new WP_Error( 'invalid_order', 'Order ID not found in payload' );
        }
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'order_not_found', 'Order not found' );
        }
        if ( 'cclee-payment-' . $gateway_type !== $order->get_payment_method() ) {
            return new WP_Error( 'payment_method_mismatch', 'Payment method does not match' );
        }
        $status = self::extract_status( $payload );
        if ( empty( $status ) ) {
            return new WP_Error( 'status_not_found', 'Status not found in payload' );
        }
        $transaction_id = self::extract_transaction_id( $payload );
        $amount = self::extract_amount( $payload );
        switch ( $status ) {
            case 'completed':
                $order->payment_complete();
                $order->add_order_note( sprintf(
                    __( '%s payment completed. Transaction ID: %s', 'cclee-payment' ),
                    ucfirst( $gateway_type ),
                    $transaction_id
                ) );
                break;
            case 'failed':
                $order->update_status( 'failed' );
                $order->add_order_note( sprintf(
                    __( '%s payment failed. Transaction ID: %s', 'cclee-payment' ),
                    ucfirst( $gateway_type ),
                    $transaction_id
                ) );
                break;
            case 'refunded':
                $order->update_status( 'refunded' );
                $order->add_order_note( sprintf(
                    __( '%s payment refunded. Transaction ID: %s', 'cclee-payment' ),
                    ucfirst( $gateway_type ),
                    $transaction_id
                ) );
                break;
            default:
                return new WP_Error( 'unknown_status', 'Unknown payment status: ' . $status );
        }
        return true;
    }

    /**
     * Extract order ID from payload.
     */
    private static function extract_order_id( array $payload ): ?int {
        return null;
    }

    /**
     * Extract status from payload.
     */
    private static function extract_status( array $payload ): ?string {
        return null;
    }

    /**
     * Extract transaction ID from payload.
     */
    private static function extract_transaction_id( array $payload ): ?string {
        return null;
    }

    /**
     * Extract amount from payload.
     */
    private static function extract_amount( array $payload ): ?float {
        return null;
    }

    /**
     * Send HTTP response.
     */
    private static function send_response( int $status_code, string $message ): void {
        status_header( $status_code );
        echo esc_html( $message );
        exit;
    }
}
