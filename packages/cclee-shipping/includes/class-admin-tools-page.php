<?php
/**
 * Admin tools page for label generation testing.
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCLEE_Shipping_Admin_Tools_Page {

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'wp_ajax_cclee_shipping_test_label', array( __CLASS__, 'ajax_create_test_label' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu page under WooCommerce.
	 */
	public static function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Shipping Label Test', 'cclee-shipping' ),
			__( 'Label Test', 'cclee-shipping' ),
			'manage_woocommerce',
			'cclee-shipping-label-test',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets for the tools page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_cclee-shipping-label-test' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cclee-shipping-admin-tools',
			CCLEE_SHIPPING_URL . 'assets/css/admin-tools.css',
			array(),
			CCLEE_SHIPPING_VERSION
		);

		wp_enqueue_script(
			'cclee-shipping-admin-tools',
			CCLEE_SHIPPING_URL . 'assets/js/admin-tools.js',
			array(),
			CCLEE_SHIPPING_VERSION,
			true
		);

		wp_localize_script( 'cclee-shipping-admin-tools', 'ccleeShippingTools', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cclee_shipping_test_label' ),
		) );
	}

	/**
	 * Render the tools page.
	 */
	public static function render_page(): void {
		$credentials = self::get_fedex_status();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Shipping Label Test', 'cclee-shipping' ); ?></h1>

			<div class="cclee-shipping-tools-status">
				<h2><?php esc_html_e( 'FedEx Connection Status', 'cclee-shipping' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Credentials', 'cclee-shipping' ); ?></th>
							<td>
								<?php if ( $credentials['configured'] ) : ?>
									<span class="cclee-shipping-status-ok"><?php esc_html_e( 'Configured', 'cclee-shipping' ); ?></span>
								<?php else : ?>
									<span class="cclee-shipping-status-err"><?php esc_html_e( 'Not configured — add FedEx in Shipping Zones first', 'cclee-shipping' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( $credentials['configured'] ) : ?>
						<tr>
							<th><?php esc_html_e( 'Environment', 'cclee-shipping' ); ?></th>
							<td><?php echo esc_html( ucfirst( $credentials['environment'] ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Account Number', 'cclee-shipping' ); ?></th>
							<td><code><?php echo esc_html( $credentials['account_number'] ); ?></code></td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="cclee-shipping-tools-action">
				<h2><?php esc_html_e( 'Generate Test Label', 'cclee-shipping' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Generate a shipping label using FedEx Ship API with hardcoded test addresses. The FedEx credentials from your shipping zone settings will be used.', 'cclee-shipping' ); ?>
				</p>
				<p>
					<button type="button" id="cclee-shipping-generate-btn" class="button button-primary button-large" <?php echo $credentials['configured'] ? '' : 'disabled'; ?>>
						<?php esc_html_e( 'Generate Test Label', 'cclee-shipping' ); ?>
					</button>
					<span id="cclee-shipping-spinner" class="spinner" style="float:none;"></span>
				</p>
			</div>

			<div id="cclee-shipping-result" class="cclee-shipping-tools-result" style="display:none;">
				<h2><?php esc_html_e( 'Result', 'cclee-shipping' ); ?></h2>

				<div id="cclee-shipping-error" class="notice notice-error" style="display:none;">
					<p></p>
				</div>

				<div id="cclee-shipping-success" style="display:none;">
					<table class="widefat striped">
						<tbody>
							<tr>
								<th><?php esc_html_e( 'Tracking Number', 'cclee-shipping' ); ?></th>
								<td id="cclee-shipping-tracking"></td>
							</tr>
						</tbody>
					</table>

					<div class="cclee-shipping-label-actions">
						<button type="button" id="cclee-shipping-preview-btn" class="button button-secondary">
							<?php esc_html_e( 'Preview', 'cclee-shipping' ); ?>
						</button>
						<button type="button" id="cclee-shipping-download-btn" class="button button-secondary">
							<?php esc_html_e( 'Download', 'cclee-shipping' ); ?>
						</button>
						<button type="button" id="cclee-shipping-print-btn" class="button button-secondary">
							<?php esc_html_e( 'Print', 'cclee-shipping' ); ?>
						</button>
					</div>

					<div id="cclee-shipping-preview-container" style="display:none;">
						<iframe id="cclee-shipping-preview-frame" width="100%" height="600" style="border:1px solid #ccc;"></iframe>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for test label generation.
	 */
	public static function ajax_create_test_label(): void {
		check_ajax_referer( 'cclee_shipping_test_label', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cclee-shipping' ) ) );
		}

		require_once CCLEE_SHIPPING_PATH . 'includes/class-label-test.php';

		$test  = new CCLEE_Shipping_Label_Test();
		$result = $test->create_test_label();

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'label'    => $result['data'],
				'tracking' => $result['tracking'] ?? '',
			) );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}
	}

	/**
	 * Get FedEx connection status for display.
	 *
	 * @return array{ configured: bool, environment: string, account_number: string }
	 */
	private static function get_fedex_status(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = 'cclee_shipping_fedex'",
			OBJECT
		);

		if ( empty( $results ) ) {
			return array( 'configured' => false, 'environment' => '', 'account_number' => '' );
		}

		$settings = get_option( 'woocommerce_cclee_shipping_fedex_' . $results[0]->instance_id . '_settings' );

		if ( empty( $settings['api_key'] ) || empty( $settings['secret_key'] ) ) {
			return array( 'configured' => false, 'environment' => '', 'account_number' => '' );
		}

		return array(
			'configured'     => true,
			'environment'    => $settings['environment'] ?? 'sandbox',
			'account_number' => $settings['account_number'] ?? '',
		);
	}
}
