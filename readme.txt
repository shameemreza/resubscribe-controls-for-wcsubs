=== Resubscribe Controls for WCSubs ===
Contributors: shameemreza
Tags: woocommercesubscriptions, resubscribe, stock, pricing, discount, time-limitation
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 8.0
WC tested up to: 10.0.2

Advanced control over resubscription behavior including stock-based restrictions, price updates, recurring discounts, time limitations, and per-product settings.

== Description ==

Resubscribe Controls for WCSubs provides advanced control over resubscription behavior in WooCommerce Subscriptions, giving store owners more flexibility and control over how customers can resubscribe to previously canceled or expired subscriptions.

= Key Features =

* **Stock-Based Control**: Prevent resubscription to out-of-stock products
* **Price Control**: Force resubscriptions to use current product prices instead of original subscription prices
* **Product-Level Control**: Configure resubscribe behavior on a per-product basis
* **Customer Notifications**: Clear notifications to customers about price changes during resubscription
* **Analytics**: Track resubscription data and price changes for business insights
* **Recurring Discount Control**: Apply special discounts to incentivize customers to resubscribe after cancellation
* **Resubscription Time Limitation**: Limit how long after cancellation a customer can resubscribe

= Recurring Discount Control =

Win back canceled subscribers with special incentives:

* Configure percentage or fixed amount discounts for resubscribing customers
* Apply discounts to first payment only, all payments, or a limited number of payments
* Set different discount rules for different products
* Limit how many times a customer can use the resubscription discount

= Resubscription Time Limitation =

Create a sense of urgency to encourage faster resubscriptions:

* Set a time window during which customers can resubscribe after cancellation
* Configure different time limitations for different products
* Display a countdown timer on the customer's account page
* Block resubscriptions automatically after the time window expires

= Compatibility =

* Fully compatible with WooCommerce High-Performance Order Storage (HPOS)
* Compatible with WooCommerce 5.0+
* Compatible with WooCommerce Subscriptions 4.0+

= Use Cases =

* Prevent customers from resubscribing to physical products that are currently out of stock
* Ensure customers always pay current market prices when they resubscribe
* Allow resubscription for digital products with free trials without giving multiple free trials
* Configure resubscription behavior differently for each product in your store
* Offer a 10% discount to win back customers who have canceled their subscriptions
* Create urgency with a 30-day resubscription window after cancellation

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/resubscribe-controls-for-wcsubs` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to WooCommerce > Settings > Resubscribe Controls to configure plugin settings.
4. For product-specific settings, edit any subscription product and find the "Resubscribe Controls" section in the product data.

== Frequently Asked Questions ==

= Is this compatible with WooCommerce HPOS? =

Yes, this plugin is fully compatible with WooCommerce High-Performance Order Storage (HPOS).

= Will this affect existing subscriptions? =

No, this plugin only affects the resubscription process for canceled or expired subscriptions. Existing active subscriptions are not affected.

= Can I control resubscription settings per product? =

Yes, when the Product-Level Control feature is enabled, you can configure resubscription behavior individually for each product from the product edit screen.

= Does this work with variable subscription products? =

Yes, the plugin works with both simple and variable subscription products.

= Can customers still see the resubscribe button for out-of-stock products? =

When stock-based control is enabled, the resubscribe button will be hidden for out-of-stock products, preventing customers from attempting to resubscribe.

= How does the discount control work? =

When a customer resubscribes to a canceled or expired subscription, the plugin can automatically apply a discount to their new subscription. You can configure the discount amount, type, and whether it applies to just the first payment or all payments.

= Can I limit how long after cancellation a customer can resubscribe? =

Yes, with the Resubscription Time Limitation feature, you can set a specific number of days after cancellation during which customers can resubscribe. After this period, the resubscribe option will no longer be available.

== Screenshots ==

1. Plugin settings page
2. Product-level resubscribe controls
3. Analytics dashboard
4. Customer notification for price changes
5. Discount control settings
6. Resubscription time limitation settings

== Changelog ==

= 1.0.0 =
* Initial release