<?php

/*
Plugin Name: MeSomb for WooCommerce
Plugin URI: https://mesomb.hachther.com
Description: Plugin to integrate Mobile payment on WooCommerce using Hachther MeSomb
Version: 1.2.5-2
Author: Hachther LLC <contact@hachther.com>
Author URI: https://hachther.com
Text Domain: mesomb-for-woocommerce
Domain Path: /languages
*/

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'mesomb_gateway_class');
function mesomb_gateway_class($gateways)
{
    $gateways[] = 'WC_MeSomb_Gateway'; // your class name is here
    return $gateways;
}

add_filter('http_request_timeout', 'mesomb_timeout_extend');
function mesomb_timeout_extend($time)
{
    return 300;
}

class Signature
{
    /**
     * @param string $service service to use can be payment, wallet ... (the list is provide by MeSomb)
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE...)
     * @param string $url the full url of the request with query element https://mesomb.hachther.com/path/to/ressource?highlight=params#url-parsing
     * @param \DateTime $date Datetime of the request
     * @param string $nonce Unique string generated for each request sent to MeSomb
     * @param array $credentials dict containing key => value for the credential provided by MeSOmb. {'access' => access_key, 'secret' => secret_key}
     * @param array $headers Extra HTTP header to use in the signature
     * @param array|null $body The dict containing the body you send in your request body
     * @return string Authorization to put in the header
     */
    public static function signRequest($service, $method, $url, $date, $nonce, $credentials, $headers = [], $body = null)
    {
        $algorithm = 'HMAC-SHA1';
        $parse = parse_url($url);
        $canonicalQuery = isset($parse['query']) ? $parse['query'] : '';

        $timestamp = $date->getTimestamp();

        if (!isset($headers)) {
            $headers = [];
        }
        $headers['host'] = $parse['scheme']."://".$parse['host'].(isset($parse['port']) ? ":".$parse['port'] : '');
        $headers['x-mesomb-date'] = $timestamp;
        $headers['x-mesomb-nonce'] = $nonce;
        ksort($headers);
        $callback = function ($k, $v) {
            return strtolower($k) . ":" . $v;
        };
        $canonicalHeaders = implode("\n", array_map($callback, array_keys($headers), array_values($headers)));

        if (!isset($body)) {
            $body = "{}";
        } else {
            $body = json_encode($body, JSON_UNESCAPED_SLASHES);
        }
        $payloadHash = sha1($body);

        $signedHeaders = implode(";", array_keys($headers));

        $path = implode("/", array_map("rawurlencode", explode("/", $parse['path'])));
        $canonicalRequest = $method."\n".$path."\n".$canonicalQuery."\n".$canonicalHeaders."\n".$signedHeaders."\n".$payloadHash;

        $scope = $date->format("Ymd")."/".$service."/mesomb_request";
        $stringToSign = $algorithm."\n".$timestamp."\n".$scope."\n".sha1($canonicalRequest);

        $signature = hash_hmac('sha1', $stringToSign, $credentials['secretKey'], false);
        $accessKey = $credentials['accessKey'];

        return "$algorithm Credential=$accessKey/$scope, SignedHeaders=$signedHeaders, Signature=$signature";
    }

    /**
     * Generate a random string by the length
     *
     * @param int $length
     * @return string
     */
    public static function nonceGenerator($length = 40) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

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
		return $_SERVER['HTTP_CLIENT_IP'];
	}
	//if user is from the proxy
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	//if user is from the remote address
	else{
		return $_SERVER['REMOTE_ADDR'];
	}
}

add_action('plugins_loaded', 'mesomb_init_gateway_class');
function mesomb_init_gateway_class()
{
    $plugin_rel_path = basename( dirname( __FILE__ ) ) . '/languages';
    load_plugin_textdomain( 'mesomb-for-woocommerce', false, $plugin_rel_path );

    class WC_MeSomb_Gateway extends WC_Payment_Gateway
    {
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

	    public function __construct()
        {
            $this->id = 'mesomb';
            $this->icon = plugins_url('images/logo-long.png', __FILE__); // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true;
            $this->method_title = 'MeSomb Gateway';
            $this->method_description = __('Allow user to make payment with Mobile Money or Orange Money', 'mesomb-for-woocommerce'); // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            $this->supports = array(
                'products',
                'refunds',
            );
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

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();


            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
//            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->application = $this->get_option('application');
            $this->accessKey = $this->get_option('accessKey');
            $this->secretKey = $this->get_option('secretKey');
            $this->selectedCountries = $this->get_option('countries');
            $this->feesIncluded = $this->get_option('fees_included');
            $this->conversion = $this->get_option('conversion');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
        }

        private function get_authorization($method, $url, $date, $nonce, array $headers = [], array $body = null)
        {
            $credentials = ['accessKey' => $this->accessKey, 'secretKey' => $this->secretKey];

            return Signature::signRequest('payment', $method, $url, $date, $nonce, $credentials, $headers, $body);
        }

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

        public function payment_scripts()
        {
            // we need JavaScript to process a token only on cart/checkout pages, right?
            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
                return;
            }

            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ('no' === $this->enabled) {
                return;
            }

            // no reason to enqueue JavaScript if API keys are not set
            if (empty($this->application)) {
                return;
            }

            wp_enqueue_style( 'woocommerce_mesomb', plugins_url('style.css', __FILE__) );

            // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script('woocommerce_mesomb', plugins_url('mesomb.js', __FILE__), ['wp-i18n'] );

            // in most payment processors you have to use PUBLIC KEY to obtain a token
//            wp_localize_script('woocommerce_mesomb', 'mesomb_params', array(
//                'apiKey' => $this->application
//            ));

            wp_enqueue_script('woocommerce_mesomb');
        }

        public function payment_fields()
        {
            // ok, let's display some description before the payment form
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-mesomb-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom payment gateway to support it
            do_action('woocommerce_credit_card_form_start', $this->id);

            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            if (is_array($this->selectedCountries) && count($this->selectedCountries) > 1) {
                echo '<div class="form-row form-row-wide validate-required">
                    <label class="field-label">'.__('Country', 'mesomb-for-woocommerce').' <span class="required">*</span></label>
                    <div class="woocommerce-input-wrapper" id="countries-field">';
                foreach ($this->selectedCountries as $country) {
                    echo '<label>
                        <input required id="mesomb-country-'.$country.'" type="radio" autocomplete="off" name="country" class="input-radio" value="'.$country.'" /> '.$this->availableCountries[$country].'
                    </label>';
                }
                echo '</div>
                </div>';
            }

            echo '<div class="form-row form-row-wide validate-required">
                    <label class="field-label">'.__('Operator', 'mesomb-for-woocommerce').' <span class="required">*</span></label>
                    <div id="providers" style="display: flex; flex-direction: row; flex-wrap: wrap;">';
            $provs = array_filter($this->providers, function($k, $v) {
                return count(array_intersect($k['countries'], (array)$this->selectedCountries)) > 0;
            }, ARRAY_FILTER_USE_BOTH);
            foreach ($provs as $provider) {
                echo '<div class="form-row provider-row '.implode(' ', $provider['countries']).'" style="margin-right: 5px;">
                        <label class="kt-option">
                            <span class="kt-option__label">
                                <span class="kt-option__head">
                                <span class="kt-option__control">
                                <span class="kt-radio">
                                    <input name="service" value="'.$provider['key'].'" type="radio" class="input-radio"/>
                                    <span></span>
                                </span>
                            </span>
                                    <span class="kt-option__title">'.$provider['name'].'</span>
                                    <img width="25" height="25" alt="'.$provider['key'].'" src="'.$provider['icon'].'" style="width: 25px; height: 25px; border-radius: 13px; position: relative; top: -0.75em; right: -0.75em;"/>
                                </span>
                                <span class="kt-option__body">'.__('Pay with your', 'mesomb-for-woocommerce').' '.$provider['name'].'</span>
                            </span>
                        </label>
                    </div>';
            }
            echo '</div></div>';
            echo '<div class="form-row form-row-wide validate-required">
                    <label class="field-label">'.__('Phone Number', 'mesomb-for-woocommerce').' <span class="required">*</span></label>
                    <div class="woocommerce-input-wrapper">
                        <input id="mesomb-payer" required type="tel" autocomplete="off" name="payer" placeholder="Expl: 670000000" class="input-text" />
                    </div>
                </div>';

            echo '<div class="alert alert-success" role="alert" id="mesomb-alert" style="display: none">
                  <h4 class="alert-heading">'.__('Check your phone', 'mesomb-for-woocommerce').'!</h4>
                  <p>'.__('Please check your phone to validate payment from Hachther SARL or MeSomb', 'mesomb-for-woocommerce').'</p>
                </div>';

            do_action('woocommerce_credit_card_form_end', $this->id);

            echo '<div class="clear"></div></fieldset>';
        }

        public function validate_fields()
        {
            if (empty($_POST['payer'])) {
                wc_add_notice('<strong>Mobile/Orange Money Number</strong> is required', 'error');
                return false;
            }
            if (is_array($this->selectedCountries) && count($this->selectedCountries) > 1 && empty($_POST['country'])) {
                wc_add_notice('<strong>Your must select a the country</strong>', 'error');
                return false;
            }
            return true;

        }

        public function process_payment($order_id)
        {
            global $woocommerce;


            // we need it to get any order details
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
            $service = $_POST['service'];
            if (!isset($_POST['country'])) {
				$country = is_array($this->selectedCountries) && count($this->selectedCountries) > 0 ? $this->selectedCountries[0] : 'CM';
			} else {
				$country = $_POST['country'];
			}
            $payer = sanitize_text_field($_POST['payer']);
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
                'message' => $order->get_customer_note().' '.get_bloginfo('name'),
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
            if (empty($this->accessKey)) {
				$url = "https://mesomb.hachther.com/api/$version/payment/$endpoint";
            } else {
                $url = "https://mesomb.hachther.com/api/$version/payment/$endpoint";
            }

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
            }


            $response = wp_remote_post($url, array(
                'body' => json_encode($data),
                'headers' => $headers
            ));

            if (!is_wp_error($response)) {
                $body = json_decode($response['body'], true);

                // it could be different depending on your payment processor
                if ($body['status'] == 'SUCCESS') {
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
                    wc_add_notice(isset($body['detail']) ? $body['detail'] : $body['message'], 'error');
                }
            } else {
                wc_add_notice(__("Error during the payment process!\nPlease try again and contact the admin if the issue is continue", 'mesomb-for-woocommerce'), 'error');
                return;
            }
        }

        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            // Do your refund here. Refund $amount for the order with ID $order_id
            $order = wc_get_order($order_id);
            $locale = substr(get_bloginfo('language'), 0, 2);

            $data = array(
                'id' => $order->get_transaction_id(),
                'conversion' => $this->conversion == 'yes',
                'currency' => $order->get_currency(),
            );
            if ($amount != null) {
                $data['amount'] = $amount;
            }
            $lang = $locale == 'fr' ? 'fr' : 'en';

            /*
             * Your API interaction could be built with wp_remote_post()
             */
//            $url = "http://127.0.0.1:8000/$lang/api/v1.1/payment/refund/";
            $url = "https://mesomb.hachther.com/$lang/api/v1.1/payment/refund/";

            $nonce = Signature::nonceGenerator();
            $date = new DateTime();
            $authorization = $this->get_authorization('POST', $url, $date, $nonce, ['content-type' => 'application/json'], $data);
            $response = wp_remote_post($url, array(
                'body' => json_encode($data),
                'headers' => array(
                    'Accept-Language' => $locale,
                    'x-mesomb-date' => $date->getTimestamp(),
                    'x-mesomb-nonce' => $nonce,
                    'Authorization' => $authorization,
                    'Content-Type'     => 'application/json',
                    'X-MeSomb-Application' => $this->application,
                )
            ));

            $body = json_decode($response['body'], true);

            if (!is_wp_error($response)) {
                // it could be different depending on your payment processor
                if ($body['status'] == 'SUCCESS') {
                    return true;
                } else {
                    return new WP_Error('error', isset($body['detail']) ? $body['detail'] : $body['message']);
                }
            } else {
                return new WP_Error($body['code'], isset($body['detail']) ? $body['detail'] : __("Error during the payment process!\nPlease try again and contact the admin if the issue is continue", 'mesomb-for-woocommerce'));
            }
        }
    }
}