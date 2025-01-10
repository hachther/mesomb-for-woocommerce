<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * MeSomb Payments Blocks integration
 *
 * @since 1.0.3
 */
final class WC_Gateway_MeSomb_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_MeSomb
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'mesomb';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_mesomb_settings', [] );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = WC_MeSomb_Payments::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '1.2.0'
			);
		$script_url        = WC_MeSomb_Payments::plugin_url() . $script_path;

		wp_register_script(
			'wc-mesomb-payments-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-mesomb-payments-blocks', 'mesomb-for-woocommerce', WC_MeSomb_Payments::plugin_abspath() . 'languages/' );
		}

		return [ 'wc-mesomb-payments-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
			'providers' => array(
				array(
					'key' => 'MTN',
					'name' => 'Mobile Money',
					'icon' => plugins_url('images/logo-momo.png', __FILE__),
					'countries' => array('CM')
				),
				array(
					'key' => 'ORANGE',
					'name' => 'Orange Money',
					'icon' => plugins_url('images/logo-orange.jpg', __FILE__),
					'countries' => array('CM')
				),
				array(
					'key' => 'AIRTEL',
					'name' => 'Airtel Money',
					'icon' => plugins_url('images/logo-airtel.jpg', __FILE__),
					'countries' => array('NE')
				)
			),
			'countries' => $this->get_setting( 'countries' ),
			'icon'		=> plugins_url( 'images/logo-long.png', __FILE__ ),
		];
	}
}
