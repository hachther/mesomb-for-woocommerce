=== MeSomb for WooCommerce ===
Contributors: hachther
Tags: ecommerce, payment, mobile money, orange money, woo commerce
Tested up to: 6.8.1
Requires PHP: 7.0
Stable tag: 1.2.7
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

MeSomb for WooCommerce is an easy to integrate mobile payment (Mobile Money or Orange Money) on Woo Commerce.


== Description ==
MeSomb for WooCommerce is a fast and easy way to integrate mobile payment (Mobile Money Orange Money, Airtel Money) in your Woo Commerce shop.

This will help your add the mobile payment on your shop by relying on MeSomb services which is currently available in Cameroon and Niger.

https://www.youtube.com/watch?v=J9ECfNIbPDU

== Installation ==
1. First you must register your service on MeSomb and create API access Key: [follow this tutorial](https://mesomb.com/how-to-register-your-service-on-mesomb/)
2. Once your service is registered, you must get those three pieces of information: Application Key, Access Key and Secret Key
3. Activate your MeSomb Payment Method in your WordPress settings. On the left menu in the admin panel go to WooCommerce -> Settings -> Payments and click On *MeSomb Gateway*
4. Set up the gateway by filling out the form. You must set the following parameters:
- *Title*: The title to give to your payment gateway (what customers will see)
- *Description*: A quick description of the gateway
- *Fees Included*: Check this if you want to say that amount shown to the customer already included MeSomb fees. Otherwise, the amount asks the customer will be greater than what your shop shows.
- *MeSomb Application Key*: Got from MeSomb.
- *MeSomb Access Key*: from MeSomb.
- *MeSomb Secret Key*: from MeSomb.
- *Countries*: Select countries which you want to receive payment from.
- *Currency Conversion*: In case your shop is in foreign currency, check this if you want MeSomb to convert to the local currency before debiting the customer otherwise you must set your shop to the local currency.


== Frequently Asked Questions ==
= MeSomb is available in which countries? =

Cameroon and Niger for the moment.

= Which operators supported by MeSomb? =

Orange Money and Mobile Money for the Cameroon and Airtel Money for the Niger

= Does MeSomb has installation fees? =

No, Installation is free.

== Screenshots ==
1. WooCommerce MeSomb gateway setting
2. Your service in MeSomb

== Changelog ==

= 1.2.7 =
- Add compatibility to the checkout classic
- Display payment icon

= 1.2.6 =
- Update to support woocommerce blocks

= 1.2.5 =
- Fix stylesheet issue

= 1.2.4 =
- Fix translations issue

= 1.2.3 =
- Make operations radio responsive on the payment form

= 1.2.2 =
- Add payment information payment in order
- Change payment input field placeholder on payment method changed

= 1.2.0 =
- Migrate to version v1.1 of the MeSomb which implements new MeSomb security standards.
- Integration of refund feature

= 1.1.0 =
Integration of Niger and Airtel Money

= 1.0.0 =
Accept payment for Orange Money and mobile money Cameroon

== Upgrade Notice ==
= 1.2.0 =
You must create API access in MeSomb and update your WooCommerce gateway settings.