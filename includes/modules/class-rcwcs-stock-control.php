<?php
/**
 * Stock Control Module
 *
 * @package ResubscribeControlsWCS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RCWCS_Stock_Control Class
 */
class RCWCS_Stock_Control {

	/**
	 * Module instance.
	 *
	 * @var RCWCS_Stock_Control
	 */
	private static $instance = null;

	/**
	 * Settings array.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings = get_option( 'rcwcs_settings', array() );

		// Hook into resubscribe check.
		add_filter( 'wcs_can_user_resubscribe_to_subscription', array( $this, 'check_stock_status' ), 10, 3 );
	}

	/**
	 * Get module instance.
	 *
	 * @return RCWCS_Stock_Control
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize module.
	 */
	public static function init() {
		self::get_instance();
	}

	/**
	 * Check if product can be resubscribed to based on stock status.
	 *
	 * @param bool     $can_resubscribe Whether subscription can be resubscribed to.
	 * @param WC_Order $order           Order object.
	 * @param array    $subscriptions   Subscriptions.
	 * @return bool Whether subscription can be resubscribed to.
	 */
	public function check_stock_status( $can_resubscribe, $order, $subscriptions ) {
		// If already false, don't change it.
		if ( ! $can_resubscribe ) {
			return $can_resubscribe;
		}

		// Get product type control settings.
		$product_type_control = isset( $this->settings['product_type_control'] ) ? $this->settings['product_type_control'] : array( 'physical' );

		// If empty, use default.
		if ( empty( $product_type_control ) ) {
			$product_type_control = array( 'physical' );
		}

		// Check each subscription.
		foreach ( $subscriptions as $subscription ) {
			// Check each line item.
			foreach ( $subscription->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$variation_id = $item->get_variation_id();
				$product_id_to_check = $variation_id ? $variation_id : $product_id;

				// Get product.
				$product = wc_get_product( $product_id_to_check );

				// Skip if product doesn't exist.
				if ( ! $product ) {
					continue;
				}

				// Check product-level setting.
				$check_stock = $product->get_meta( '_rcwcs_check_stock', true );

				// Use default if not set.
				if ( '' === $check_stock ) {
					$check_stock = 'yes';
				}

				// Skip if stock checking is disabled for this product.
				if ( 'no' === $check_stock ) {
					continue;
				}

				// Check if product type should be checked.
				$should_check = false;

				// Check physical product.
				if ( in_array( 'physical', $product_type_control, true ) && ! $product->is_virtual() && ! $product->is_downloadable() ) {
					$should_check = true;
				}

				// Check virtual product.
				if ( in_array( 'virtual', $product_type_control, true ) && $product->is_virtual() ) {
					$should_check = true;
				}

				// Check downloadable product.
				if ( in_array( 'downloadable', $product_type_control, true ) && $product->is_downloadable() ) {
					$should_check = true;
				}

				// No need for variable check - physical/virtual/downloadable covers all product types

				// Skip if we don't need to check this product type.
				if ( ! $should_check ) {
					continue;
				}

				// Check if product is in stock.
				if ( ! $product->is_in_stock() ) {
					return false;
				}

				// If product manages stock, check stock quantity.
				if ( $product->managing_stock() && $product->get_stock_quantity() <= get_option( 'woocommerce_notify_no_stock_amount', 0 ) ) {
					return false;
				}
			}
		}

		return $can_resubscribe;
	}

	/**
	 * Log message to WooCommerce log.
	 *
	 * @param string $message Message to log.
	 */
	private function log_message( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'rcwcs-stock-control' ) );
		}
	}
}

// Initialize module.
// This is commented out because it will be initialized from the main plugin class based on settings.
// RCWCS_Stock_Control::init(); 