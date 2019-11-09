<?php

/*
Plugin Name: MeSomb for WooCommerce
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Plugin to integrate MeSomb payment on WooCommerce
Version: 1.0
Author: Hachther LLC
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
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


/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'mesomb_init_gateway_class');
function mesomb_init_gateway_class()
{
    class WC_MeSomb_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'mesomb'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'MeSomb Gateway';
            $this->method_description = 'Allow user to make payment with Mobile Money or Orange Money'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('api_key');
            $this->account = $this->get_option('account');
            $this->fees_included = $this->get_option('fees_included');

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );

        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable MeSomb Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'MeSomb Payment',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with your Mobile/Orange Money account.',
                ),
                'fees_included' => array(
                    'title' => 'Fees Included',
                    'label' => 'Fees is already included to the displayed price',
                    'type' => 'checkbox',
                    'description' => 'This control if the MeSomb fees is already include in the price show to users',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'account' => array(
                    'title' => 'MeSomb Account',
                    'type' => 'text'
                ),
                'testmode' => array(
                    'title' => 'Test mode',
                    'label' => 'Enable Test Mode',
                    'type' => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'test_api_key' => array(
                    'title' => 'Test Api Key',
                    'type' => 'password'
                ),
                'api_key' => array(
                    'title' => 'Live API Key',
                    'type' => 'password'
                )
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
            if (empty($this->api_key)) {
                return;
            }

            // do not work with card detailes without SSL unless your website is in a test mode
            if (!$this->testmode && !is_ssl()) {
                return;
            }

            // let's suppose it is our payment processor JavaScript that allows to obtain a token
            wp_enqueue_script('mesomb_js', 'https://mesomb.hachther.com/api/token.js');

            // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script('woocommerce_mesomb', plugins_url('mesomb.js', __FILE__), array('jquery', 'mesomb_js'));

            // in most payment processors you have to use PUBLIC KEY to obtain a token
            wp_localize_script('woocommerce_mesomb', 'mesomb_params', array(
                'apiKey' => $this->api_key
            ));

            wp_enqueue_script('woocommerce_mesomb');
        }

        public function payment_fields()
        {
            // ok, let's display some description before the payment form
            if ($this->description) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ($this->testmode) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#" target="_blank" rel="noopener noreferrer">documentation</a>.';
                    $this->description = trim($this->description);
                }
                // display the description with <p> tags etc.
                echo wpautop(wp_kses_post($this->description));
            }

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom payment gateway to support it
            do_action('woocommerce_credit_card_form_start', $this->id);

            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '<div class="form-row form-row-wide"><label>Phone Number <span class="required">*</span></label>
                <input id="mesomb_number" type="tel" autocomplete="off" name="mesomb_number">
                </div>
                <div class="form-row form-row-wide">
                    <label>Operator <span class="required">*</span></label>
                    <select name="mesomb_service" id="mesomb_operator">
                        <option value="MOBILE_MONEY">Mobile Money</option>
                        <option value="ORANGE_MONEY">Orange Money</option>
                    </select>
                </div>
                <div class="clear"></div>';

            do_action('woocommerce_credit_card_form_end', $this->id);

            echo '<div class="clear"></div></fieldset>';
        }

        public function validate_fields()
        {
            if (empty($_POST['mesomb_number'])) {
                wc_add_notice('<strong>Mobile/Orange Money Number</strong> is required', 'error');
                return false;
            }
            return true;

        }

        public function process_payment($order_id)
        {
            global $woocommerce;


            // we need it to get any order detailes
            $order = wc_get_order($order_id);

            $data = array(
                'account' => $this->account,
                'amount' => floatval($order->get_total()),
                'payer' => $_POST['mesomb_number'],
                'service' => $_POST['mesomb_service'],
                'fees_included' => $this->fees_included == 'yes' ? true : false,
                'currency' => $order->get_order_currency(),
                'note' => $order->get_customer_note(),
                'external_id' => $order->get_id(),
                'source' => get_bloginfo('name'),
            );

            /*
             * Your API interaction could be built with wp_remote_post()
             */
            $response = wp_remote_post('http://mesomb.hachther.com/api/v1.0/transactions/collect/', array(
                'body' => json_encode($data),
                'headers' => array(
                    'Authorization' => 'Token ' . $this->api_key,
                    'Content-Type' => 'application/json'
                )
            ));

            if (!is_wp_error($response)) {
                $body = json_decode($response['body'], true);

                // it could be different depending on your payment processor
                if ($body['status'] == 'SUCCESSFUL') {
                    // we received the payment
                    $order->payment_complete();
                    $order->reduce_order_stock();

                    // some notes to customer (replace true with false to make it private)
                    $order->add_order_note('Hey, your order is paid! Thank you!', true);

                    // Empty cart
                    $woocommerce->cart->empty_cart();

                    // Redirect to the thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } else {
                    wc_add_notice("Error during the payment process\n Please try aging and contact admin if the issue is continue", 'error');
                    return;
                }
            }
        }
    }
}