<?php
/**
 * WC_Gateway_MeSomb class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce MeSomb Payments Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'utils/signature.php';

function get_provider($providers, $id) {
	foreach ( $providers as $element ) {
		if ( $id == $element['key'] ) {
			return $element;
		}
	}
	return null;
}

class PaymentMethod {
	public $id;
	public $title;

	public function __construct($id, $title)
	{
		$this->id = $id;
		$this->title = $title;
	}

	/**
	 * @return mixed
	 */
	public function get_title()
	{
		return $this->title;
	}
}

function get_client_ip() {
	if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
		return wp_unslash($_SERVER['HTTP_CLIENT_IP']);
	}
	//if user is from the proxy
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		return wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']);
	}
	//if user is from the remote address
	else{
		return wp_unslash($_SERVER['REMOTE_ADDR']);
	}
}

/**
 * MeSomb Gateway.
 *
 * @class    WC_Gateway_MeSomb
 * @version  1.10.0
 */
class WC_Gateway_MeSomb extends WC_Payment_Gateway {

	/**
	 * @var array
	 */
	private $availableCountries;

	/**
	 * @var array[]
	 */
	private $providers;

	/**
	 * @var string
	 */
	private $application;

	/**
	 * @var string
	 */
	private $accessKey;

	/**
	 * @var string
	 */
	private $secretKey;

	/**
	 * @var boolean
	 */
	private $feesIncluded;

	/**
	 * @var boolean
	 */
	private $conversion;

	/**
	 * @var array
	 */
	private $selectedCountries;

	/**
	 * Unique id for the gateway.
	 * @var string
	 *
	 */
	public $id = 'mesomb';

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {

		$this->icon = plugins_url('images/logo-long.png', __FILE__); // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields         = true;
		$this->supports           = array(
			'pre-orders',
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'multiple_subscriptions',
			'refunds',
		);

		$this->method_title         = 'MeSomb Gateway';
		$this->method_description   = __('Allow user to make payment with Mobile Money or Orange Money', 'mesomb-for-woocommerce'); // will be displayed on the options page

		$this->availableCountries = array(
			'CM' => __('Cameroon', 'mesomb-for-woocommerce'),
			'NE' => __('Niger', 'mesomb-for-woocommerce')
		);
		$this->providers = array(
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
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title                    = $this->get_option( 'title' );
		$this->description              = $this->get_option( 'description' );
		$this->enabled                  = $this->get_option('enabled');
//            $this->testmode = 'yes' === $this->get_option('testmode');
		$this->application              = $this->get_option('application');
		$this->accessKey                = $this->get_option('accessKey');
		$this->secretKey                = $this->get_option('secretKey');
		$this->selectedCountries        = $this->get_option('countries');
		$this->feesIncluded             = $this->get_option('fees_included');
		$this->conversion               = $this->get_option('conversion');

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_scheduled_subscription_payment_mesomb', array( $this, 'process_subscription_payment' ), 10, 2 );
		add_action ( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ), 10 );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'mesomb-for-woocommerce'),
				'label' => __('Enable MeSomb Gateway', 'mesomb-for-woocommerce'),
				'type' => 'checkbox',
				'description' => '',
				'default' => 'no'
			),
			'title' => array(
				'title' => __('Title', 'mesomb-for-woocommerce'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'mesomb-for-woocommerce'),
				'default' => __('MeSomb Mobile Payment', 'mesomb-for-woocommerce'),
				'desc_tip' => true,
			),
			'description' => array(
				'title' => __('Description', 'mesomb-for-woocommerce'),
				'type' => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'mesomb-for-woocommerce'),
				'default' => __('Pay with your Mobile/Orange Money account.', 'mesomb-for-woocommerce'),
			),
			'fees_included' => array(
				'title' => __('Fees Included', 'mesomb-for-woocommerce'),
				'label' => __('Fees are already included in the displayed price', 'mesomb-for-woocommerce'),
				'type' => 'checkbox',
				'description' => __('This control if the MeSomb fees is already included in the price shown to users', 'mesomb-for-woocommerce'),
				'default' => 'yes',
				'desc_tip' => true,
			),
			'application' => array(
				'title' => __('MeSomb Application Key', 'mesomb-for-woocommerce'),
				'type' => 'password'
			),
			'accessKey' => array(
				'title' => __('MeSomb Access Key', 'mesomb-for-woocommerce'),
				'type' => 'password',
				'description' => __('API Access key obtained from MeSomb', 'mesomb-for-woocommerce'),
			),
			'secretKey' => array(
				'title' => __('MeSomb Secret Key', 'mesomb-for-woocommerce'),
				'type' => 'password',
				'description' => __('API Secret key obtained from MeSomb', 'mesomb-for-woocommerce'),
			),
			'countries' => array(
				'title' => __('Countries', 'mesomb-for-woocommerce'),
				'type' => 'multiselect',
				'default' => 'CM',
				'options' => $this->availableCountries,
				'description' => __('You can receive payments from which countries', 'mesomb-for-woocommerce'),
			),
			'conversion' => array(
				'title' => __('Currency Conversion', 'mesomb-for-woocommerce'),
				'label' => __('Rely on MeSomb to automatically convert foreign currencies', 'mesomb-for-woocommerce'),
				'type' => 'checkbox',
				'default' => 'yes',
			),
		);
	}

	private function get_authorization($method, $url, $date, $nonce, array $headers = [], array $body = null)
	{
		$credentials = ['accessKey' => $this->accessKey, 'secretKey' => $this->secretKey];

		return Signature::signRequest('payment', $method, $url, $date, $nonce, $credentials, $headers, $body);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int  $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;

		$payment_result = $this->get_option( 'result' );
		$paymentData = $this->get_post_data();

		$locale = substr(get_bloginfo('language'), 0, 2);
		$order = wc_get_order($order_id);
		$products = [];
		foreach ($order->get_items() as $item) {
			$products[] = [
				'id' => $item->get_product_id(),
				'name' => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'amount' => $item->get_total()
			];
		}
		$service = $paymentData['service'];
//		if (!isset($_POST['country'])) {
//			$country = is_array($this->selectedCountries) && count($this->selectedCountries) > 0 ? $this->selectedCountries[0] : 'CM';
//		} else {
//			$country = $_POST['country'];
//		}
		$country = $paymentData['country'] ?? 'CM';
		$payer = sanitize_text_field($paymentData['payer']);
		$payer = ltrim($payer, '00');
		$payer = ltrim($payer, array(
			'CM' => '237',
			'NE' => '227'
		)[$country]);

		if (!in_array($service, ['ORANGE', 'MTN', 'AIRTEL'])) {
			wc_add_notice(__('Invalid operator it should be on of the following Mobile Money, Orange Money and Airtel Money', 'mesomb-for-woocommerce'), 'error');
			return;
		}

		if (!preg_match("/^\d{8,9}$/", $payer)) {
			wc_add_notice(__('Your phone number format is invalid. It should be in the local format', 'mesomb-for-woocommerce'), 'error');
			return;
		}

		$data = array(
			'amount' => intval($order->get_total()),
			'payer' => $payer,
			'service' => $service,
			'fees' => $this->feesIncluded == 'yes',
			'conversion' => $this->conversion == 'yes',
			'currency' => $order->get_currency(),
			'message' => $order->get_customer_note() . ' Me-somb-woo.php' . get_bloginfo('name'),
			'reference' => $order->get_id(),
			'country' => $country,
			'customer' => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name' => $order->get_billing_last_name(),
				'town' => $order->get_billing_city(),
				'region' => $order->get_billing_state(),
				'country' => $order->get_billing_country(),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
				'address_1' => $order->get_billing_address_1(),
				'postcode' => $order->get_billing_postcode(),
			),
			'products' => $products,
			'location' => [
				'ip' => get_client_ip()
			],
			'source' => 'WordPress/v'.get_bloginfo('version'),
		);
		$lang = $locale == 'fr' ? 'fr' : 'en';

		/*
		 * Your API interaction could be built with wp_remote_post()
		 */
		$version = empty($this->accessKey) ? 'v1.0' : 'v1.1';
		$endpoint = empty($this->accessKey) ? 'online/' : 'collect/';
		$url = "https://mesomb.hachther.com/api/$version/payment/$endpoint";

		$headers = array(
			'Accept-Language' => $locale,
			'Content-Type'     => 'application/json',
			'X-MeSomb-Application' => $this->application,
			'X-MeSomb-TrxID' => $order_id,
		);

		if (!empty($this->accessKey)) {
			$nonce = Signature::nonceGenerator();
			$date = new DateTime();
			$authorization = $this->get_authorization('POST', $url, $date, $nonce, ['content-type' => 'application/json'], $data);

			$headers['x-mesomb-date'] = $date->getTimestamp();
			$headers['x-mesomb-nonce'] = $nonce;
			$headers['Authorization'] = $authorization;
			$headers['Accept-Language'] = $lang;
		}

		$response = wp_remote_post($url, array(
			'body' => wp_json_encode($data),
			'headers' => $headers,
			'timeout' => 60,
		));

		if (!is_wp_error($response)) {
			$body = json_decode($response['body'], true);

			// it could be different depending on your payment processor
			if (isset($body['status']) && $body['status'] == 'SUCCESS') {
				$provider_name = get_provider($this->providers, $body['transaction']['service'])['name'];
				$payer = $body['transaction']['b_party'];
				$order->set_payment_method(new PaymentMethod($body['transaction']['service'], "$provider_name ($payer)"));
				// we received the payment
				$order->payment_complete($body['transaction']['pk']);
				wc_reduce_stock_levels($order_id);

				// some notes to customer (replace true with false to make it private)
				$order->add_order_note(__('Hey, your order is paid! Thank you!', 'mesomb-for-woocommerce'), true);

				// Empty cart
				$woocommerce->cart->empty_cart();

				// Redirect to the thank you page
				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url($order)
				);
			} else {
				$message = esc_html($body['detail'] ?? $body['message']);
				$order->update_status( 'failed', $message );
				throw new Exception( $message );
			}
		} else {
			$message = esc_html__("Error during the payment process!\nPlease try again and contact the admin if the issue continues", 'mesomb-for-woocommerce');
			$order->update_status( 'failed', $message );
			throw new Exception( $message );
		}
	}

	/**
	 * Process subscription payment.
	 *
	 * @param  float     $amount
	 * @param  WC_Order  $order
	 * @return void
	 */
	public function process_subscription_payment( $amount, $order ) {
		$payment_result = $this->get_option( 'result' );

		if ( 'success' === $payment_result ) {
			$order->payment_complete();
		} else {
			$order->update_status( 'failed', __( 'Subscription payment failed. To make a successful payment using MeSomb Payments, please review the gateway settings.', 'mesomb-for-woocommerce' ) );
		}
	}

	/**
	 * Process pre-order payment upon order release.
	 *
	 * Processes the payment for pre-orders charged upon release.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function process_pre_order_release_payment( $order ) {
		$payment_result = $this->get_option( 'result' );

		if ( 'success' === $payment_result ) {
			$order->payment_complete();
		} else {
			$message = __( 'Order payment failed. To make a successful payment using MeSomb Payments, please review the gateway settings.', 'mesomb-for-woocommerce' );
			$order->update_status( 'failed', $message );
		}
	}
}
