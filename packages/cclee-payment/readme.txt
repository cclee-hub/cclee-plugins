=== CCLEE Payment ===
Contributors: cclee-hub
Tags: payment, woocommerce, paypal, alipay, payoneer, multi-gateway
Requires at least: 6.4
Tested up to: 6.9.4
Stble tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multi-gateway payment plugin for WooCommerce. Accept PayPal, Alipay+ International, and Payoneer payments with a unified interface.

== Description ==

= PayPal =
Accept PayPal and credit/debit card payments globally. Customers can pay with their PayPal account or use cards directly.

= Alipay+ International =
Accept Alipay payments from customers worldwide. Supports QR code payments through the Alipay app.

= Payoneer =
Accept Payoneer payments for cross-border transactions. Ideal for international B2B commerce.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/cclee-payment/`
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Configure payment methods in WooCommerce > Settings > Payments

== Frequently Asked Questions ==

= How do I set up PayPal? =

1. Go to WooCommerce > Settings > Payments
2. Click on "CCLEE Payment - PayPal"
3. Enable the gateway and enter your Client ID and Secret
4. Save changes

= Is Alipay+ available in my country? =

Alipay+ International is available in [list supported countries]. Check the Alipay documentation for the current list.

= Does this plugin support refunds? =

Yes, all three gateways support partial and full refunds through the WooCommerce order management interface.

= Are my API credentials stored securely? =

Yes, all credentials are encrypted using WordPress/WordPress secure settings storage.

= Do I need a PayPal account to use this plugin? =

Yes, you PayPal Business account is required. Sandbox accounts are available for testing.

== Screenshots ==

1. WooCommerce payment settings with CCLEE Payment gateways
2. Checkout page showing available payment options

== Changelog ==

= 1.0.0 =
* Initial release
* PayPal gateway support
* Alipay+ International gateway support
* Payoneer gateway support
* Partial and full refund support
* Webhook handling for payment notifications

== External Services ==

This plugin connects to the following external payment services:

= PayPal API =
* Endpoint: https://api-m.paypal.com (live), https://api-m.sandbox.paypal.com (sandbox)
* Data sent: Order ID, amount, currency, customer details
* Privacy Policy: https://www.paypal.com/privacy

= Alipay+ International API =
* Endpoint: https://openapi.alipay.com
* Data sent: Order ID, amount, currency, customer details
* Privacy Policy: https://global.alipay.com/privacy

= Payoneer API =
* Endpoint: https://api.payoneer.com
* Data sent: Order ID, amount, currency, customer details
* Privacy Policy: https://www.payoneer.com/privacy/

== Upgrade Notice ==

= 1.0.1 =
* Bug fixes and improvements

== Additional Information ==

= Documentation: https://github.com/cclee-hub/cclee-payment
= Support: https://github.com/cclee-hub/cclee-payment/issues
