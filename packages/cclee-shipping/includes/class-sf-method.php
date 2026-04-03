<?php
/**
 * SF Express WooCommerce Shipping Method.
 *
 * @package CCLEE_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CCLEE_Shipping_SF_Method extends WC_Shipping_Method {

	/**
	 * Available SF Express product types.
	 */
	private const PRODUCT_TYPES = array(
		'1'  => '顺丰即日',
		'2'  => '顺丰标快',
		'3'  => '顺丰特惠',
		'5'  => '顺丰次晨',
		'6'  => '顺丰标快（陆运）',
		'7'  => '顺丰特惠（陆运）',
		'33' => '生鲜速配',
		'36' => '跨境速配（国际）',
		'37' => '国际特惠（国际）',
		'38' => '国际标快（国际）',
		'39' => '重货包裹',
		'44' => '国际电商专递（国际）',
	);

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		parent::__construct( $instance_id );

		$this->id                 = 'cclee_shipping_sf';
		$this->method_title       = __( 'SF Express (CCLEE Shipping)', 'cclee-shipping' );
		$this->method_description = __( 'SF Express real-time shipping rates via API.', 'cclee-shipping' );
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
		$this->title                = $this->get_option( 'title', 'SF Express' );
	}

	/**
	 * Define per-instance settings fields.
	 */
	private function get_settings_fields(): array {
		$products = array();
		foreach ( self::PRODUCT_TYPES as $code => $label ) {
			$products[ $code ] = $label;
		}

		return array(
			'title' => array(
				'title'   => __( 'Method Title', 'cclee-shipping' ),
				'type'    => 'text',
				'default' => 'SF Express',
			),
			'customer_code' => array(
				'title'       => __( 'Customer Code', 'cclee-shipping' ),
				'type'        => 'text',
				'description' => __( 'SF Express customer code (顾客编码).', 'cclee-shipping' ),
				'desc_tip'    => true,
			),
			'check_word' => array(
				'title'       => __( 'Check Word (校验码)', 'cclee-shipping' ),
				'type'        => 'password',
				'description' => __( 'Sandbox or production check word from SF Express portal.', 'cclee-shipping' ),
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
				'default' => array( '2', '3' ),
				'options' => $products,
			),
			'express_type' => array(
				'title'       => __( 'Default Product Type', 'cclee-shipping' ),
				'type'        => 'select',
				'default'     => '',
				'options'     => array_merge( array( '' => __( 'All (query all types)', 'cclee-shipping' ) ), $products ),
				'description' => __( 'Restrict rate query to a single product type, or query all.', 'cclee-shipping' ),
				'desc_tip'    => true,
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
		if ( empty( $this->get_option( 'customer_code' ) ) || empty( $this->get_option( 'check_word' ) ) ) {
			return;
		}

		$carrier = new CCLEE_Shipping_SF_Carrier( $this );

		$origin = array(
			'city'  => WC()->countries->get_base_city(),
			'state' => WC()->countries->get_base_state(),
		);

		$destination = array(
			'city'  => $package['destination']['city'] ?? '',
			'state' => $package['destination']['state'] ?? '',
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
