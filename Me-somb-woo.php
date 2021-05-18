<?php

/*
Plugin Name: MeSomb for WooCommerce
Plugin URI: https://mesomb.hachther.com
Description: Plugin to integrate Mobile payment on WooCommerce using Hachther MeSomb
Version: 1.0.1
Author: Hachther LLC
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

//function t($key) {
//    $locale = substr(get_locale(), 0, 2);
//    $transaction = array(
//        'en' => array(
//            'Pay_with_your' => 'Pay with your',
//            'Pay_with_your_mobile_account' => 'Pay with your Mobile/Orange Money account.',
//            'Phone_Number' => 'Phone Number',
//            'Error_invalid_service' => "Invalid operator it should be Mobile Money or Orange Money",
//            'Error_invalid_phone' =>  "Your phone number format is invalid. It should be in the local format of MTN or Orange expl: 670000000",
//            'Success_payment_done' => "Hey, your order is paid! Thank you!",
//            'General_error' => "Error during the payment process!\nPlease try again and contact the admin if the issue is continue",
//            'Title_Title' => 'Title',
//            'Title_Description' => 'This controls the title which the user sees during checkout.',
//            'Title_Default' => 'MeSomb Mobile Payment',
//            'Enable_Disable_Title' => 'Enable/Disable',
//            'Enable_MeSomb_Gateway' => 'Enable MeSomb Gateway',
//            'Description_Title' => 'Description',
//            'Description_Description' => 'This controls the description which the user sees during checkout.',
//            'Description_Default' => 'Pay with your Mobile/Orange Money account.',
//            'Fees_Included_Title' => 'Fees Included',
//            'Fees_Included_Label' => 'Fees are already included in the displayed price',
//            'Fees_Included_Description' => 'This control if the MeSomb fees is already included in the price shown to users',
//            'Application_Title' => 'MeSomb Application Key',
//            'method_description' => 'Allow user to make payment with Mobile Money or Orange Money'
//        ),
//        'fr' => array(
//            'Pay_with_your' => 'Payez avec',
//            'Pay_with_your_mobile_account' => 'Payez avec votre compte Mobile/Orange Money.',
//            'Phone_Number' => 'Numéro de Téléphone',
//            'Error_invalid_service' => "Opérateur non valide, cela devrait être Mobile Money ou Orange Money",
//            'Error_invalid_phone' =>  "Le format de votre numéro de téléphone n'est pas valide. Il doit être au format local MTN ou Orange expl: 670000000",
//            'Success_payment_done' => "Hé, votre commande est payée! Merci!",
//            'General_error' => "Erreur lors du processus de paiement!\nVeuillez réessayer et contacter l'administrateur si le problème persiste",
//            'Title_Title' => 'Titre',
//            'Title_Description' => "Ceci contrôle le titre que l'utilisateur voit lors du paiement.",
//            'Title_Default' => 'Paiement Mobile MeSomb',
//            'Enable_Disable_Title' => 'Activer/Désactiver',
//            'Enable_MeSomb_Gateway' => 'Activer la Passerelle MeSomb',
//            'Description_Title' => 'Description',
//            'Description_Description' => "Ceci contrôle la description que l'utilisateur voit lors du paiement.",
//            'Description_Default' => 'Payez avec votre compte Mobile/Orange Money.',
//            'Fees_Included_Title' => 'Frais inclus',
//            'Fees_Included_Label' => 'Les frais sont déjà inclus dans le prix affiché',
//            'Fees_Included_Description' => 'Ceci contrôle si les frais MeSomb sont déjà inclus dans le prix affiché aux utilisateurs',
//            'Application_Title' => "Clé d'Application MeSomb",
//            'method_description' => "Autoriser l'utilisateur à effectuer un paiement avec Mobile Money ou Orange Money"
//        ),
//    );
//    return $transaction[$locale][$key];
//}


add_action('plugins_loaded', 'mesomb_init_gateway_class');
function mesomb_init_gateway_class()
{
    class WC_MeSomb_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $locale = substr(get_locale(), 0, 2);
            $this->id = 'mesomb';
            $this->icon = plugins_url($locale == 'en' ? 'images/logo-long-en.png' : 'images/logo-long-fr.png', __FILE__); // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true;
            $this->method_title = 'MeSomb Gateway';
            $this->method_description = __('Allow user to make payment with Mobile Money or Orange Money', 'mesomb-for-woocommerce'); // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
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
            $this->application = $this->get_option('application');
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
            wp_register_script('woocommerce_mesomb', plugins_url('mesomb.js', __FILE__) );

            // in most payment processors you have to use PUBLIC KEY to obtain a token
            wp_localize_script('woocommerce_mesomb', 'mesomb_params', array(
                'apiKey' => $this->application
            ));

            wp_enqueue_script('woocommerce_mesomb');
        }

        public function payment_fields()
        {
            $locale = substr(get_locale(), 0, 2);

            // ok, let's display some description before the payment form
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-mesomb-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom payment gateway to support it
            do_action('woocommerce_credit_card_form_start', $this->id);

            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '<div class="form-row form-row-wide">
                    <label>'.__('Phone Number', 'mesomb-for-woocommerce').' <span class="required">*</span></label>
                    <div class="woocommerce-input-wrapper">
                        <input id="mesomb-payer" type="tel" autocomplete="off" name="payer" placeholder="Expl: 670000000" class="input-text" />
                    </div>
                  </div>
                  <div class="form-row form-row-first validate-required">
                    <label class="kt-option">
                        <span class="kt-option__label">
                            <span class="kt-option__head">
                            <span class="kt-option__control">
                            <span class="kt-radio">
                                <input name="service" value="MTN" type="radio" checked class="input-radio"/>
                                <span></span>
                            </span>
                        </span>
                                <span class="kt-option__title">Mobile Money</span>
                                <span class="kt-option__focus">
                                    <img src="'.plugins_url('images/logo-momo.png', __FILE__).'" style="height: 25px; border-radius: 13px;"/>
                                </span>
                            </span>
                            <span class="kt-option__body">'.__('Pay with your', 'mesomb-for-woocommerce').' Mobile Money</span>
                        </span>
                    </label>
                  </div>
                  <div class="form-row form-row-last validate-required">
                  <label class="kt-option">
                        <span class="kt-option__label">
                            <span class="kt-option__head">
                            <span class="kt-option__control">
                            <span class="kt-radio">
                                <input name="service" value="ORANGE" type="radio" class="input-radio"/>
                                <span></span>
                            </span>
                        </span>
                                <span class="kt-option__title">Orange Money</span>
                                <span class="kt-option__focus">
                                    <img src="'.plugins_url('images/logo-orange.jpg', __FILE__).'" style="height: 25px; border-radius: 13px;"/>
                                </span>
                            </span>
                            <span class="kt-option__body">'.__('Pay with your', 'mesomb-for-woocommerce').' Orange Money</span>
                        </span>
                    </label>
                  </div>
                  <img src="'.plugins_url($locale == 'en' ? 'images/logo-long-en.png' : 'images/logo-long-fr.png', __FILE__).'" style="width: 300px; margin-top: 10px;" />
                  <div class="clear" />
                </div>
                <div class="alert alert-success" role="alert" id="mesomb-alert" style="display: none">
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
            return true;

        }

        public function process_payment($order_id)
        {
            global $woocommerce;


            // we need it to get any order detailes
            $locale = substr(get_locale(), 0, 2);
            $order = wc_get_order($order_id);
            $service = $_POST['service'];
            $payer = sanitize_text_field($_POST['payer']);

            if (!in_array($service, ['ORANGE', 'MTN'])) {
                wc_add_notice(__('Invalid operator it should be Mobile Money or Orange Money', 'mesomb-for-woocommerce'), 'error');
                return;
            }

            if (!preg_match("/^[4|6]\d{8}$/", $payer)) {
                wc_add_notice(__('Your phone number format is invalid. It should be in the local format of MTN or Orange expl: 670000000', 'mesomb-for-woocommerce'), 'error');
                return;
            }

            $data = array(
                'amount' => intval($order->get_total()),
                'payer' => '237'.$payer,
                'service' => $service,
                'fees' => $this->fees_included == 'yes' ? true : false,
                'currency' => 'XAF', //$order->get_order_currency(),
                'message' => $order->get_customer_note().' '.get_bloginfo('name'),
                'external_id' => $order->get_id(),
                'country' => 'CM',
            );

            /*
             * Your API interaction could be built with wp_remote_post()
             */
            $url = 'https://mesomb.hachther.com/api/v1.0/payment/online/';
            $url = 'http://127.0.0.1:8000/api/v1.0/payment/online/';
            $response = wp_remote_post($url, array(
                'body' => json_encode($data),
                'headers' => array(
                    'X-MeSomb-Application' => $this->application,
                    'Content-Type' => 'application/json',
                    'Accept-Language' => $locale
                )
            ));

            if (!is_wp_error($response)) {
                $body = json_decode($response['body'], true);

                // it could be different depending on your payment processor
                if ($body['status'] == 'SUCCESS') {
                    // we received the payment
                    $order->payment_complete();
                    wc_reduce_stock_levels($order_id);
//                    $order->reduce_order_stock();

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
                    wc_add_notice($body['message'], 'error');
                    return;
                }
            } else {
                wc_add_notice(__("Error during the payment process!\nPlease try again and contact the admin if the issue is continue", 'mesomb-for-woocommerce'), 'error');
                return;
            }
        }
    }
}