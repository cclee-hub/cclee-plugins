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
		$sender      = self::get_default_sender();
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
							<td><span class="cclee-shipping-env-badge cclee-shipping-env-<?php echo esc_attr( $credentials['environment'] ); ?>"><?php echo esc_html( ucfirst( $credentials['environment'] ) ); ?></span></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Account Number', 'cclee-shipping' ); ?></th>
							<td><code><?php echo esc_html( $credentials['account_number'] ); ?></code></td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
				<p class="description">
					<?php esc_html_e( 'Environment and credentials are read from WooCommerce > Settings > Shipping > FedEx settings.', 'cclee-shipping' ); ?>
				</p>
			</div>

			<form id="cclee-shipping-label-form" class="cclee-shipping-tools-form">
				<input type="hidden" name="action" value="cclee_shipping_test_label">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'cclee_shipping_test_label' ) ); ?>">

				<div class="cclee-shipping-form-grid">
					<!-- Sender -->
					<div class="cclee-shipping-form-section">
						<h3><?php esc_html_e( 'Sender', 'cclee-shipping' ); ?></h3>
						<table class="form-table">
							<tbody>
								<tr>
									<th><label for="sender_contact"><?php esc_html_e( 'Name', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="sender_contact" name="sender[contact]" value="<?php echo esc_attr( $sender['contact'] ); ?>" class="regular-text" required></td>
								</tr>
								<tr>
									<th><label for="sender_company"><?php esc_html_e( 'Company', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="sender_company" name="sender[company]" value="<?php echo esc_attr( $sender['company'] ); ?>" class="regular-text"></td>
								</tr>
								<tr>
									<th><label for="sender_street"><?php esc_html_e( 'Street', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="sender_street" name="sender[street]" value="<?php echo esc_attr( $sender['street'] ); ?>" class="regular-text" required></td>
								</tr>
								<tr>
									<th><label for="sender_city"><?php esc_html_e( 'City', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="sender_city" name="sender[city]" value="<?php echo esc_attr( $sender['city'] ); ?>" class="regular-text" required></td>
								</tr>
								<tr>
									<th><label for="sender_state"><?php esc_html_e( 'State / Province', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="sender_state" name="sender[state]" value="<?php echo esc_attr( $sender['state'] ); ?>" class="regular-text" required></td>
								</tr>
								<tr>
									<th><label for="sender_postcode"><?php esc_html_e( 'Postal Code', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="sender_postcode" name="sender[postcode]" value="<?php echo esc_attr( $sender['postcode'] ); ?>" class="regular-text" required></td>
								</tr>
								<tr>
									<th><label for="sender_country"><?php esc_html_e( 'Country', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="sender_country" name="sender[country]" value="<?php echo esc_attr( $sender['country'] ); ?>" class="small-text" maxlength="2" placeholder="US" required></td>
								</tr>
								<tr>
									<th><label for="sender_phone"><?php esc_html_e( 'Phone', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="sender_phone" name="sender[phone]" value="<?php echo esc_attr( $sender['phone'] ); ?>" class="regular-text" required></td>
								</tr>
							</tbody>
						</table>
					</div>

					<!-- Receiver -->
					<div class="cclee-shipping-form-section">
						<h3><?php esc_html_e( 'Recipient', 'cclee-shipping' ); ?></h3>
						<table class="form-table">
							<tbody>
								<tr>
									<th><label for="receiver_contact"><?php esc_html_e( 'Name', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="receiver_contact" name="receiver[contact]" value="" class="regular-text" required></td>
								</tr>
								<tr>
									<th><label for="receiver_company"><?php esc_html_e( 'Company', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="receiver_company" name="receiver[company]" value="" class="regular-text"></td>
								</tr>
								<tr>
									<th><label for="receiver_street"><?php esc_html_e( 'Street', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="receiver_street" name="receiver[street]" value="" class="regular-text" required></td>
								</tr>
								<tr>
									<th><label for="receiver_city"><?php esc_html_e( 'City', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="receiver_city" name="receiver[city]" value="" class="regular-text" required></td>
								</tr>
								<tr>
									<th><label for="receiver_state"><?php esc_html_e( 'State / Province', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="receiver_state" name="receiver[state]" value="" class="regular-text" required></td>
								</tr>
								<tr>
									<th><label for="receiver_postcode"><?php esc_html_e( 'Postal Code', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="receiver_postcode" name="receiver[postcode]" value="" class="regular-text" required></td>
								</tr>
								<tr>
									<th><label for="receiver_country"><?php esc_html_e( 'Country', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="receiver_country" name="receiver[country]" value="" class="small-text" maxlength="2" placeholder="US" required></td>
								</tr>
								<tr>
									<th><label for="receiver_phone"><?php esc_html_e( 'Phone', 'cclee-shipping' ); ?></label></th>
									<td><input type="text" id="receiver_phone" name="receiver[phone]" value="" class="regular-text" required></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Package -->
				<div class="cclee-shipping-form-section cclee-shipping-form-package">
					<h3><?php esc_html_e( 'Package', 'cclee-shipping' ); ?></h3>
					<table class="form-table">
						<tbody>
							<tr>
								<th><label for="package_weight"><?php esc_html_e( 'Weight (lbs)', 'cclee-shipping' ); ?></label></th>
								<td><input type="number" id="package_weight" name="package[weight]" value="0.5" class="small-text" step="0.1" min="0.1" required></td>
							</tr>
							<tr>
								<th><label for="package_length"><?php esc_html_e( 'Length (in)', 'cclee-shipping' ); ?></label></th>
								<td><input type="number" id="package_length" name="package[length]" value="10" class="small-text" step="0.1" min="1" required></td>
							</tr>
							<tr>
								<th><label for="package_width"><?php esc_html_e( 'Width (in)', 'cclee-shipping' ); ?></label></th>
								<td><input type="number" id="package_width" name="package[width]" value="8" class="small-text" step="0.1" min="1" required></td>
							</tr>
							<tr>
								<th><label for="package_height"><?php esc_html_e( 'Height (in)', 'cclee-shipping' ); ?></label></th>
								<td><input type="number" id="package_height" name="package[height]" value="6" class="small-text" step="0.1" min="1" required></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="cclee-shipping-tools-action">
					<p>
						<button type="submit" id="cclee-shipping-generate-btn" class="button button-primary button-large" <?php echo $credentials['configured'] ? '' : 'disabled'; ?>>
							<?php esc_html_e( 'Generate Test Label', 'cclee-shipping' ); ?>
						</button>
						<span id="cclee-shipping-spinner" class="spinner" style="float:none;"></span>
					</p>
				</div>
			</form>

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

		$params = array(
			'sender'   => array(
				'contact'  => sanitize_text_field( wp_unslash( $_POST['sender']['contact'] ?? '' ) ),
				'company'  => sanitize_text_field( wp_unslash( $_POST['sender']['company'] ?? '' ) ),
				'street'   => sanitize_text_field( wp_unslash( $_POST['sender']['street'] ?? '' ) ),
				'city'     => sanitize_text_field( wp_unslash( $_POST['sender']['city'] ?? '' ) ),
				'state'    => sanitize_text_field( wp_unslash( $_POST['sender']['state'] ?? '' ) ),
				'postcode' => sanitize_text_field( wp_unslash( $_POST['sender']['postcode'] ?? '' ) ),
				'country'  => strtoupper( sanitize_text_field( wp_unslash( $_POST['sender']['country'] ?? '' ) ) ),
				'phone'    => sanitize_text_field( wp_unslash( $_POST['sender']['phone'] ?? '' ) ),
			),
			'receiver' => array(
				'contact'  => sanitize_text_field( wp_unslash( $_POST['receiver']['contact'] ?? '' ) ),
				'company'  => sanitize_text_field( wp_unslash( $_POST['receiver']['company'] ?? '' ) ),
				'street'   => sanitize_text_field( wp_unslash( $_POST['receiver']['street'] ?? '' ) ),
				'city'     => sanitize_text_field( wp_unslash( $_POST['receiver']['city'] ?? '' ) ),
				'state'    => sanitize_text_field( wp_unslash( $_POST['receiver']['state'] ?? '' ) ),
				'postcode' => sanitize_text_field( wp_unslash( $_POST['receiver']['postcode'] ?? '' ) ),
				'country'  => strtoupper( sanitize_text_field( wp_unslash( $_POST['receiver']['country'] ?? '' ) ) ),
				'phone'    => sanitize_text_field( wp_unslash( $_POST['receiver']['phone'] ?? '' ) ),
			),
			'package'  => array(
				'weight' => (float) ( $_POST['package']['weight'] ?? 0.5 ),
				'length' => (float) ( $_POST['package']['length'] ?? 10 ),
				'width'  => (float) ( $_POST['package']['width'] ?? 8 ),
				'height' => (float) ( $_POST['package']['height'] ?? 6 ),
			),
		);

		require_once CCLEE_SHIPPING_PATH . 'includes/class-label-test.php';

		$test   = new CCLEE_Shipping_Label_Test();
		$result = $test->create_test_label( $params );

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

	/**
	 * Get default sender address from WooCommerce store settings.
	 *
	 * @return array Default sender fields.
	 */
	private static function get_default_sender(): array {
		return array(
			'contact'  => get_bloginfo( 'name' ),
			'company'  => get_bloginfo( 'name' ),
			'street'   => WC()->countries->get_base_address(),
			'city'     => WC()->countries->get_base_city(),
			'state'    => WC()->countries->get_base_state(),
			'postcode' => WC()->countries->get_base_postcode(),
			'country'  => WC()->countries->get_base_country(),
			'phone'    => '',
		);
	}
}
