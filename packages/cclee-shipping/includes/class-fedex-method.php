<?php
/**
 * FedEx WooCommerce Shipping Method.
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCLEE_Shipping_FedEx_Method extends WC_Shipping_Method {

	/**
	 * Available FedEx service types.
	 */
	private const SERVICES = array(
		'FEDEX_INTERNATIONAL_PRIORITY' => 'FedEx International Priority',
		'FEDEX_INTERNATIONAL_ECONOMY'  => 'FedEx International Economy',
		'FEDEX_GROUND'                 => 'FedEx Ground',
	);

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );

		$this->id                 = 'cclee_shipping_fedex';
		$this->method_title       = __( 'FedEx (CCLEE Shipping)', 'cclee-shipping' );
		$this->method_description = __( 'FedEx real-time shipping rates via API.', 'cclee-shipping' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init();
	}

	/**
	 * Initialize form fields and settings.
	 */
	public function init(): void {
		$this->instance_form_fields = $this->get_settings_fields();
		$this->title                = $this->get_option( 'title', 'FedEx' );
	}

	/**
	 * Define per-instance settings fields.
	 */
	private function get_settings_fields(): array {
		$services = array();
		foreach ( self::SERVICES as $key => $label ) {
			$services[ $key ] = $label;
		}

		return array(
			'title' => array(
				'title'   => __( 'Method Title', 'cclee-shipping' ),
				'type'    => 'text',
				'default' => 'FedEx',
			),
			'api_key' => array(
				'title'       => __( 'API Key (Client ID)', 'cclee-shipping' ),
				'type'        => 'text',
				'description' => __( 'FedEx Developer Portal API Key.', 'cclee-shipping' ),
				'desc_tip'    => true,
			),
			'secret_key' => array(
				'title'       => __( 'Secret Key (Client Secret)', 'cclee-shipping' ),
				'type'        => 'password',
				'description' => __( 'FedEx Developer Portal Secret Key.', 'cclee-shipping' ),
				'desc_tip'    => true,
			),
			'account_number' => array(
				'title'       => __( 'Account Number', 'cclee-shipping' ),
				'type'        => 'text',
				'description' => __( 'FedEx account number.', 'cclee-shipping' ),
				'desc_tip'    => true,
			),
			'environment' => array(
				'title'   => __( 'Environment', 'cclee-shipping' ),
				'type'    => 'select',
				'default' => 'sandbox',
				'options' => array(
					'sandbox'    => __( 'Sandbox', 'cclee-shipping' ),
					'production' => __( 'Production', 'cclee-shipping' ),
				),
			),
			'services' => array(
				'title'   => __( 'Enabled Services', 'cclee-shipping' ),
				'type'    => 'multiselect',
				'class'   => 'wc-enhanced-select',
				'default' => array( 'FEDEX_INTERNATIONAL_PRIORITY', 'FEDEX_INTERNATIONAL_ECONOMY' ),
				'options' => $services,
			),
			'rate_modifier_type' => array(
				'title'   => __( 'Rate Modifier Type', 'cclee-shipping' ),
				'type'    => 'select',
				'default' => 'fixed',
				'options' => array(
					'fixed'      => __( 'Fixed Amount', 'cclee-shipping' ),
					'percentage' => __( 'Percentage', 'cclee-shipping' ),
				),
			),
			'rate_modifier_value' => array(
				'title'             => __( 'Rate Modifier Value', 'cclee-shipping' ),
				'type'              => 'number',
				'default'           => 0,
				'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
			),
			'package_type' => array(
				'title'   => __( 'Default Package Type', 'cclee-shipping' ),
				'type'    => 'select',
				'default' => 'YOUR_PACKAGING',
				'options' => array(
					'YOUR_PACKAGING' => __( 'Your Packaging', 'cclee-shipping' ),
					'FEDEX_ENVELOPE' => __( 'FedEx Envelope', 'cclee-shipping' ),
					'FEDEX_PAK'      => __( 'FedEx Pak', 'cclee-shipping' ),
					'FEDEX_BOX'      => __( 'FedEx Box', 'cclee-shipping' ),
					'FEDEX_TUBE'     => __( 'FedEx Tube', 'cclee-shipping' ),
				),
			),
			'shipping_payment_type' => array(
				'title'       => __( 'Shipping Payment Type', 'cclee-shipping' ),
				'type'        => 'select',
				'default'     => 'SENDER',
				'options'     => array(
					'SENDER'    => __( 'Sender (Prepaid)', 'cclee-shipping' ),
					'RECIPIENT' => __( 'Recipient (Collect)', 'cclee-shipping' ),
				),
				'description' => __( 'Who pays for shipping charges.', 'cclee-shipping' ),
				'desc_tip'    => true,
			),
			'duties_payment_type' => array(
				'title'       => __( 'Duties & Taxes Payment', 'cclee-shipping' ),
				'type'        => 'select',
				'default'     => 'SENDER',
				'options'     => array(
					'SENDER'    => __( 'Sender', 'cclee-shipping' ),
					'RECIPIENT' => __( 'Recipient', 'cclee-shipping' ),
				),
				'description' => __( 'Who pays for customs duties and taxes (international only).', 'cclee-shipping' ),
				'desc_tip'    => true,
			),
			'debug' => array(
				'title'   => __( 'Debug Mode', 'cclee-shipping' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'cclee-shipping' ),
				'default' => 'no',
			),
		);
	}

	/**
	 * Calculate shipping rates.
	 *
	 * @param array $package Package data from WooCommerce.
	 */
	public function calculate_shipping( $package = array() ): void {
		if ( empty( $this->get_option( 'api_key' ) ) || empty( $this->get_option( 'secret_key' ) ) ) {
			return;
		}

		$carrier = new CCLEE_Shipping_FedEx_Carrier( $this );

		$origin = array(
			'country'  => WC()->countries->get_base_country(),
			'state'    => WC()->countries->get_base_state(),
			'city'     => WC()->countries->get_base_city(),
			'postcode' => WC()->countries->get_base_postcode(),
		);

		$destination = array(
			'country'  => $package['destination']['country'] ?? '',
			'state'    => $package['destination']['state'] ?? '',
			'city'     => $package['destination']['city'] ?? '',
			'postcode' => $package['destination']['postcode'] ?? '',
			'address'  => $package['destination']['address_1'] ?? $package['destination']['address'] ?? '',
		);

		$packages = CCLEE_Shipping_Package::from_cart( $package );
		$rates    = $carrier->get_rates( $origin, $destination, $packages );

		$modifier_type  = $this->get_option( 'rate_modifier_type', 'fixed' );
		$modifier_value = (float) $this->get_option( 'rate_modifier_value', 0 );

		foreach ( $rates as $rate ) {
			$cost = CCLEE_Shipping_Rate_Modifier::apply( $rate['cost'], $modifier_type, $modifier_value );

			$this->add_rate( array(
				'id'        => $rate['id'],
				'label'     => $rate['label'],
				'cost'      => $cost,
				'package'   => $package,
				'meta_data' => array( 'transit_days' => $rate['transit_days'] ?? '' ),
			) );
		}
	}
}
