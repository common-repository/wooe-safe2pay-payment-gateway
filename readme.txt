=== WooCommerce Safe2Pay Gateway ===
Contributors: safe2pay
Tags: ecommerce, woocommerce, safe2pay, payment gateway, australia, subscriptions, wcsubscriptions
Requires at least: 3.8
Tested up to: 5.4.2
Requires PHP: 5.6
Stable tag: trunk

The WooCommerce Safe2Pay Gateway plug-in enabled integration with WooCommerce and the Safe2Pay Payment Gateway (for Australian Merchants).

Now supports WooCommerce Subscriptions.

== Description ==

This plug-in provides integration with WooCommerce and the Safe2Pay Payment Gateway, an Australian online payment gateway.

Support has now been added for WooCommerce Subscriptions, allowing you to create recurring products in your WooCommerce store.

![WooCommerce Subscriptions](http://wcdocs.woothemes.com/wp-content/uploads/2012/06/supports-subscriptions-badge.png)

Tested with WooCommerce version 4.1.1

Visit [https://www.safe2pay.com.au](https://www.safe2pay.com.au "Safe2Pay Online Payment Gateway") for more details on using Safe2Pay.


== Installation ==

There are two methods to install the plug-in:

**Copy the file to the WordPress plug-ins directory:**

1. Make a new directory in [SITE_ROOT]/wp-content/wooe-safe2pay-payment-gateway
2. Copy the files woocommerce-safe2pay.php and woocommerce-safe2pay-gateway.php to this new directory.
3. Activate the newly installed plug-in in the WordPress Plugin Manager.


**Install the plug-in from WordPress:**

1. Search for the WooCommerce Safe2Pay Payment Gateway plug-in from the WordPress Plugin Manager.
2. Install the plug-in.
3. Activate the newly installed plug-in.

== Configuration ==

1. Visit the WooCommerce settings page, and click on the Payment Gateways tab.
2. Click on Safe2Pay to edit the settings. If you do not see Safe2Pay in the list at the top of the screen make sure you have activated the plug in in the WordPress Plug in Manager.
3. Enable the Payment Method, name it Credit Card (this will show up on the payment page your customer sees) and add in your credentials. Click Save.
4. Optional: Bring confidence to every customer purchase by displaying the Safe2Pay logo on your checkout page. This will let them know that they’re details are safe and their purchase is secure.

You should now be able to test the purchases via Safe2Pay.

== Screenshots ==

1. Admin panel where you can modify various settings of the payment plug-in, such as retry fee for failed recurring payments, etc.
2. Front-end credit card prompt (the look and feel of this page depends on your theme)

== Changelog ==

= 1.25 =
* Minor bug fixes

= 1.24 =
* Minor bug fixes

= 1.23 =
* Removed notification hook

= 1.22 =
* Increased timeouts to 45 seconds

= 1.21 =
* General improvements

= 1.20 =
* General improvements

= 1.19 =
* General improvements

= 1.18 =
* Fixed issue with debug logs

= 1.17 =
* Fixed issue with notification hook typo

= 1.16 =
* Plug-in now advises the user if a new version is available

= 1.15 =
* Added ability to change payment method through user dashboard

= 1.14 =
* Added support for WooCommerce subscriptions

= 1.0 =
* Initial release

== Upgrade Notice ==

= 1.18 =
This is a bug fix

= 1.17 =
This is a bug fix

== Frequently Asked Questions ==

=  Does this plug-in support recurring payments and subscriptions? =
Yes!

= Can users cancel their subscriptions through this plug-in? =
Yes!

= Can a subscriber change their card number through this plug-in? =
Yes! This plug-in adds a page to My Account section in user's dashboard to let the users change their stored card number.

= Is a test environment available to make sure the plug-in is installed correctly before accepting payments? =
Yes! Get in touch with Safe2Pay and we will provide you with sandbox credentials.

== Support ==

If you have any issue with the Safe2Pay Gateway for WooCommerce please contact us at support@safe2pay.com.au and we will be more then happy to help out.
