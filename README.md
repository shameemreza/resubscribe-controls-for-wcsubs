# Resubscribe Controls for WCSubs

Advanced control over resubscription behavior in WooCommerce Subscriptions, including stock-based restrictions, price updates, per-product settings, recurring discounts, and time limitations.

## Features

- **Stock-Based Control**: Prevent resubscription to out-of-stock products
- **Price Control**: Force resubscriptions to use current product prices instead of original subscription prices
- **Product-Level Control**: Configure resubscribe behavior on a per-product basis
- **Customer Notifications**: Clear notifications to customers about price changes during resubscription
- **Analytics**: Track resubscription data and price changes for business insights
- **Recurring Discount Control**: Apply special discounts to incentivize customers to resubscribe after cancellation
- **Resubscription Time Limitation**: Limit how long after cancellation a customer can resubscribe

## Requirements

- WordPress 5.8+
- WooCommerce 8.0+
- WooCommerce Subscriptions 4.0+
- PHP 7.4+

## Installation

1. Upload the plugin files to the `/wp-content/plugins/resubscribe-controls-for-wcsubs` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to WooCommerce > Settings > Resubscribe Controls to configure plugin settings.
4. For product-specific settings, edit any subscription product and find the "Resubscribe Controls" section in the product data.

## Features in Detail

### Recurring Discount Control

The Recurring Discount Control feature allows store owners to offer special discounts to customers who resubscribe to a canceled or expired subscription. This can be a powerful tool to win back customers and reduce churn.

Key options include:

- **Discount Type**: Percentage or Fixed Amount
- **Discount Amount**: The amount to discount
- **Apply Discount To**: First payment only, all payments, or a limited number of payments
- **Product-Level Overrides**: Configure different discounts for different products
- **Maximum Usage**: Limit how many times a customer can use the resubscription discount

### Resubscription Time Limitation

The Resubscription Time Limitation feature allows you to set a time window during which customers can resubscribe to a canceled or expired subscription. After this window expires, resubscription is no longer available.

Key options include:

- **Time Limitation Period**: Number of days after cancellation/expiration during which resubscription is allowed
- **Product-Level Overrides**: Set different time limitations for different products
- **Countdown Display**: Show a countdown timer to customers on their account page

## Developer Documentation

### Hooks

The plugin provides various hooks for developers to extend its functionality:

#### Filters

```php
// Modify stock control behavior
apply_filters('rcwcs_stock_control_enabled', bool $enabled, int $product_id);

// Modify price control behavior
apply_filters('rcwcs_price_control_enabled', bool $enabled, int $product_id);

// Modify product control behavior
apply_filters('rcwcs_product_control_enabled', bool $enabled, int $product_id);

// Modify price change notification message
apply_filters('rcwcs_price_change_message', string $message, float $old_price, float $new_price, WC_Product $product);

// Modify discount amount
apply_filters('rcwcs_discount_amount', float $discount_amount, WC_Product $product, WC_Order $order);

// Modify time limitation period
apply_filters('rcwcs_time_limitation_days', int $days, int $product_id, WC_Subscription $subscription);
```

#### Actions

```php
// Fired when a resubscription is blocked due to stock control
do_action('rcwcs_resubscription_blocked_stock', int $product_id, WC_Product $product);

// Fired when a price is updated during resubscription
do_action('rcwcs_resubscription_price_updated', int $product_id, float $old_price, float $new_price);

// Fired when a resubscription is blocked due to product settings
do_action('rcwcs_resubscription_blocked_product', int $product_id, WC_Product $product);

// Fired when a discount is applied to a resubscription
do_action('rcwcs_discount_applied', float $discount_amount, string $discount_type, WC_Order $order, WC_Subscription $subscription);

// Fired when a resubscription is blocked due to time limitation
do_action('rcwcs_resubscription_blocked_time_limit', int $product_id, WC_Subscription $subscription, int $days_since_end);
```

### Custom Product Meta

The plugin uses the following product meta fields:

- `_rcwcs_allow_resubscribe`: Whether resubscription is allowed for the product (yes/no)
- `_rcwcs_enforce_current_price`: Whether to enforce current product price during resubscription (yes/no)
- `_rcwcs_check_stock`: Whether to check stock during resubscription (yes/no)
- `_rcwcs_override_discount`: Whether to override global discount settings (yes/no)
- `_rcwcs_discount_type`: Discount type (percentage/fixed)
- `_rcwcs_discount_amount`: Discount amount
- `_rcwcs_discount_application`: How to apply the discount (first_payment/all_payments/limited)
- `_rcwcs_discount_payment_count`: Number of payments to apply the discount to
- `_rcwcs_override_time_limitation`: Whether to override global time limitation settings (yes/no)
- `_rcwcs_time_limitation_days`: Number of days to allow resubscription after cancellation

### Database Tables

The plugin creates the following custom table for analytics:

```sql
CREATE TABLE {$wpdb->prefix}rcwcs_analytics (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    subscription_id bigint(20) NOT NULL,
    customer_id bigint(20) NOT NULL,
    product_id bigint(20) NOT NULL,
    original_price decimal(19,4) NOT NULL,
    new_price decimal(19,4) NOT NULL,
    price_difference decimal(19,4) NOT NULL,
    resubscribe_date datetime NOT NULL,
    PRIMARY KEY  (id)
);
```

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## HPOS Compatibility

This plugin is fully compatible with WooCommerce High-Performance Order Storage (HPOS). It declares compatibility through the WooCommerce Features API:

```php
add_action(
    'before_woocommerce_init',
    function() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', __FILE__, true );
        }
    }
);
```
