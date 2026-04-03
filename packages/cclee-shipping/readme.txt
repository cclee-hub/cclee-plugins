=== CCLEE Shipping ===
Contributors: cclee-hub
Tags: shipping, fedex, sf-express, woocommerce, rates
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.0
WC requires at least: 8.0
WC tested up to: 10.6.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multi-carrier shipping for WooCommerce. FedEx + SF Express real-time rates and address validation.

== Description ==

CCLEE Shipping provides real-time shipping rate calculation and address validation for WooCommerce stores.

**FedEx Integration**

* Real-time shipping rates via FedEx Rate API
* Address validation via FedEx Address Validation API
* Multiple service types: International Priority, International Economy, Ground
* Customizable rate modifier (fixed amount or percentage markup)
* Sandbox mode for development and testing
* Debug logging for troubleshooting

= Requirements =

* WooCommerce 8.0 or later
* FedEx Developer Portal API credentials (free to obtain)
* PHP 8.0 or later

= Setup =

1. Install and activate the plugin
2. Go to WooCommerce > Settings > Shipping > Shipping Zones
3. Add or edit a zone and select "FedEx (CCLEE Shipping)"
4. Enter your FedEx API Key, Secret Key, and Account Number
5. Select the services you want to offer
6. Save changes

== Installation ==

1. Upload the `cclee-shipping` folder to `/wp-content/plugins/`
2. Activate through the Plugins menu in WordPress
3. Configure in WooCommerce > Settings > Shipping

== Frequently Asked Questions ==

= How do I get FedEx API credentials? =

Register at the [FedEx Developer Portal](https://developer.fedex.com/) and create an app to obtain your API Key and Secret Key. You will also need a FedEx account number.

= Does this plugin work with WooCommerce Blocks checkout? =

Yes. CCLEE Shipping uses the standard WooCommerce shipping method API and is fully compatible with both classic and Blocks-based checkout.

= Does this plugin support HPOS? =

Yes. CCLEE Shipping is compatible with WooCommerce High-Performance Order Storage (HPOS).

== Changelog ==

= 1.1.0 =
* Added SF Express carrier (real-time rates via EXP_RECE_QUERY_DELIVERTM)
* MD5 signature authentication for SF Express API
* Product type selection (standard, express, economy, international)

= 1.0.0 =
* Initial release
* FedEx real-time shipping rates
* FedEx address validation
* Rate modifier (fixed / percentage)
* Sandbox and production environments
* Debug logging
