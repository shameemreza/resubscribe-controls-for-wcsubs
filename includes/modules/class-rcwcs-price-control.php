<?php
/**
 * Price Control Module
 *
 * @package ResubscribeControlsWCS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RCWCS_Price_Control Class
 */
class RCWCS_Price_Control {

	/**
	 * Module instance.
	 *
	 * @var RCWCS_Price_Control
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

		// Update the price when adding a resubscribed product to the cart.
		add_filter( 'woocommerce_add_cart_item', array( $this, 'update_resubscribe_price' ), 20, 1 );

		// Ensure the price is current on cart calculations.
		add_filter( 'woocommerce_before_calculate_totals', array( $this, 'update_cart_resubscribe_prices' ), 20, 1 );

		// Add notification that price has been updated.
		if ( isset( $this->settings['cart_notifications'] ) && 'yes' === $this->settings['cart_notifications'] ) {
			add_filter( 'woocommerce_add_to_cart_message_html', array( $this, 'add_price_update_notice' ), 10, 2 );
		}

		// Add order details notice.
		if ( isset( $this->settings['order_notifications'] ) && 'yes' === $this->settings['order_notifications'] ) {
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_price_update_notice_to_order' ), 10, 1 );
		}

		// Add notices to emails.
		if ( isset( $this->settings['email_notifications'] ) && 'yes' === $this->settings['email_notifications'] ) {
			add_action( 'woocommerce_email_order_details', array( $this, 'add_price_update_notice_to_email' ), 10, 4 );
		}

		// Save price update metadata during checkout.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_price_update_status' ), 10, 4 );
	}

	/**
	 * Get module instance.
	 *
	 * @return RCWCS_Price_Control
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
	 * Update price when a resubscribed product is added to the cart.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @return array Modified cart item data.
	 */
	public function update_resubscribe_price( $cart_item_data ) {
		// Check if this is a resubscribe cart item.
		if ( isset( $cart_item_data['subscription_resubscribe'] ) ) {
			// Get current product price.
			$product_id   = $cart_item_data['product_id'];
			$variation_id = isset( $cart_item_data['variation_id'] ) ? $cart_item_data['variation_id'] : 0;

			$product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );

			if ( $product ) {
				// Store original price for reference.
				$cart_item_data['original_price'] = $cart_item_data['data']->get_price();

				// Update to current price.
				$cart_item_data['data']->set_price( $product->get_price() );

				// Store that we've updated the price.
				$cart_item_data['price_updated'] = true;

				// Log the price change.
				$this->log_message( sprintf(
					'Resubscribe price updated for %s (ID: %d) from %s to %s',
					$product->get_name(),
					$product->get_id(),
					wc_price( $cart_item_data['original_price'] ),
					wc_price( $product->get_price() )
				) );
			}
		}

		return $cart_item_data;
	}

	/**
	 * Update prices in the cart for resubscribed items.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function update_cart_resubscribe_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! empty( $cart->cart_contents ) ) {
			foreach ( $cart->cart_contents as $key => $cart_item ) {
				if ( isset( $cart_item['subscription_resubscribe'] ) ) {
					$product_id   = $cart_item['product_id'];
					$variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;

					$product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );

					if ( $product ) {
						// Update to current price.
						$cart_item['data']->set_price( $product->get_price() );
					}
				}
			}
		}
	}

	/**
	 * Add notification that the price has been updated.
	 *
	 * @param string $message Add to cart message.
	 * @param array  $products Product IDs.
	 * @return string Modified message.
	 */
	public function add_price_update_notice( $message, $products ) {
		if ( ! empty( WC()->cart->cart_contents ) ) {
			$updated = false;

			foreach ( WC()->cart->cart_contents as $cart_item ) {
				if ( isset( $cart_item['price_updated'] ) && $cart_item['price_updated'] ) {
					$updated = true;
					break;
				}
			}

			if ( $updated ) {
				$notice_text = isset( $this->settings['price_notice_text'] ) ? $this->settings['price_notice_text'] : __( 'Note: Subscription price has been updated to the current price.', 'resubscribe-controls-for-wcsubs' );
				$message    .= ' <strong>' . esc_html( $notice_text ) . '</strong>';
			}
		}

		return $message;
	}

	/**
	 * Save price update status to order item meta.
	 *
	 * @param WC_Order_Item_Product $item          Order item object.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order object.
	 */
	public function save_price_update_status( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['price_updated'] ) && $values['price_updated'] ) {
			$item->add_meta_data( '_price_updated', 'yes', true );

			if ( isset( $values['original_price'] ) ) {
				$item->add_meta_data( '_original_price', $values['original_price'], true );
			}
		}
	}

	/**
	 * Add price update notice to order details.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function add_price_update_notice_to_order( $order ) {
		$price_updated = false;

		foreach ( $order->get_items() as $item ) {
			if ( 'yes' === $item->get_meta( '_price_updated' ) ) {
				$price_updated = true;
				break;
			}
		}

		if ( $price_updated ) {
			$notice_text = isset( $this->settings['price_notice_text'] ) ? $this->settings['price_notice_text'] : __( 'Note: Subscription price has been updated to the current price.', 'resubscribe-controls-for-wcsubs' );
			echo '<p class="price-updated-notice">' . esc_html( $notice_text ) . '</p>';
		}
	}

	/**
	 * Add price update notice to email.
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Whether the email is being sent to admin.
	 * @param bool     $plain_text    Whether the email is plain text.
	 * @param WC_Email $email         Email object.
	 */
	public function add_price_update_notice_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		$price_updated = false;

		foreach ( $order->get_items() as $item ) {
			if ( 'yes' === $item->get_meta( '_price_updated' ) ) {
				$price_updated = true;
				break;
			}
		}

		if ( $price_updated ) {
			$notice_text = isset( $this->settings['price_notice_text'] ) ? $this->settings['price_notice_text'] : __( 'Note: Subscription price has been updated to the current price.', 'resubscribe-controls-for-wcsubs' );

			if ( $plain_text ) {
				echo "\n\n" . esc_html( $notice_text ) . "\n\n";
			} else {
				echo '<p style="margin-bottom: 1em;">' . esc_html( $notice_text ) . '</p>';
			}
		}
	}

	/**
	 * Log message to WooCommerce log.
	 *
	 * @param string $message Message to log.
	 */
	private function log_message( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'rcwcs-price-control' ) );
		}
	}

	/**
	 * Check if price has changed.
	 *
	 * @param float $old_price Original price.
	 * @param float $new_price New price.
	 * @return bool Whether the price has changed.
	 */
	private function price_has_changed( $old_price, $new_price ) {
	    // Any price change should trigger a notification
	    return $old_price !== $new_price;
	}

	/**
	 * Update the item price and add metadata.
	 */
	private function update_item_price( $cart_item_key, $cart_item, $original_price, $new_price ) {
	    // Check if price has changed
	    if ( $this->price_has_changed( $original_price, $new_price ) ) {
	        // Add price update flag and original price to cart item
	        WC()->cart->cart_contents[ $cart_item_key ]['price_updated'] = 'yes';
	        WC()->cart->cart_contents[ $cart_item_key ]['original_price'] = $original_price;
	        
	        // Add notice if notifications are enabled
	        if ( isset( $this->settings['notifications_enabled'] ) && 'yes' === $this->settings['notifications_enabled'] ) {
	            $product_name = $cart_item['data']->get_name();
	            $notice_text = apply_filters( 'rcwcs_price_notice_text', '', $original_price, $new_price );
	            
	            // Determine notification type
	            $notification_type = isset( $this->settings['price_notification_type'] ) ? $this->settings['price_notification_type'] : 'notice';
	            
	            // Add WooCommerce notice if needed
	            if ( 'notice' === $notification_type || 'both' === $notification_type ) {
	                wc_add_notice( 
	                    sprintf( 
	                        __( '%s: %s', 'resubscribe-controls-for-wcsubs' ), 
	                        $product_name, 
	                        $notice_text 
	                    ), 
	                    'notice' 
	                );
	            }
	        }
	    }
	}
}

// Initialize module.
// This is commented out because it will be initialized from the main plugin class based on settings.
// RCWCS_Price_Control::init(); 