<?php
/**
 * Notifications Module
 *
 * @package ResubscribeControlsWCS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RCWCS_Notifications Class
 */
class RCWCS_Notifications {

	/**
	 * Module instance.
	 *
	 * @var RCWCS_Notifications
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

		// Only initialize hooks if notifications are enabled.
		if ( isset( $this->settings['notifications_enabled'] ) && 'yes' === $this->settings['notifications_enabled'] ) {
			$this->init_hooks();
		}
		
		// Always initialize admin hooks for preview functionality
		$this->init_admin_hooks();
	}

	/**
	 * Initialize admin hooks for email previews.
	 */
	private function init_admin_hooks() {
		// Only register admin hooks if in admin
		if ( is_admin() ) {
			add_action( 'wp_ajax_rcwcs_preview_email', array( $this, 'ajax_preview_email' ) );
		}
	}

	/**
	 * AJAX handler for email preview.
	 */
	public function ajax_preview_email() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rcwcs-admin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'resubscribe-controls-for-wcsubs' ) ) );
			exit;
		}

		// Verify user capability
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'resubscribe-controls-for-wcsubs' ) ) );
			exit;
		}

		// Get form data
		$email_heading = isset( $_POST['email_heading'] ) ? sanitize_text_field( wp_unslash( $_POST['email_heading'] ) ) : '';
		$email_content = isset( $_POST['email_content'] ) ? wp_kses_post( wp_unslash( $_POST['email_content'] ) ) : '';

		// Generate preview with sample data
		$preview = $this->preview_email( $email_heading, $email_content );

		wp_send_json_success( array(
			'preview' => $preview,
		) );
		exit;
	}

	/**
	 * Generate a preview of the email with sample data.
	 *
	 * @param string $heading Email heading.
	 * @param string $content Email content.
	 * @return string Formatted email preview.
	 */
	public function preview_email( $heading, $content ) {
		// Sample data for preview
		$sample_data = array(
			'{product_name}' => 'Premium Subscription',
			'{old_price}'    => wc_price( 19.99 ),
			'{new_price}'    => wc_price( 24.99 ),
			'{customer_name}' => 'John Doe',
			'{order_number}'  => '#1001',
			'{site_title}'    => get_bloginfo( 'name' ),
			'{site_url}'      => get_bloginfo( 'url' ),
		);
		
		// Replace placeholders with sample data
		$content = str_replace(
			array_keys( $sample_data ),
			array_values( $sample_data ),
			$content
		);
		
		// Format the email
		return $this->format_email_html( $heading, $content );
	}

	/**
	 * Get module instance.
	 *
	 * @return RCWCS_Notifications
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
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Notification location hooks.
		$locations = isset( $this->settings['notification_locations'] ) ? $this->settings['notification_locations'] : array( 'cart_page', 'checkout_page' );

		if ( in_array( 'cart_page', $locations, true ) ) {
			add_action( 'woocommerce_before_cart', array( $this, 'display_cart_notice' ) );
		}

		if ( in_array( 'checkout_page', $locations, true ) ) {
			add_action( 'woocommerce_before_checkout_form', array( $this, 'display_checkout_notice' ) );
		}

		if ( in_array( 'order_details', $locations, true ) ) {
			add_action( 'woocommerce_order_details_before_order_table', array( $this, 'display_order_notice' ), 10, 1 );
		}

		if ( in_array( 'my_account_page', $locations, true ) ) {
			add_action( 'woocommerce_before_my_account', array( $this, 'display_account_notice' ) );
		}

		// Email notifications.
		if ( isset( $this->settings['price_notification_type'] ) && 
			( 'email' === $this->settings['price_notification_type'] || 'both' === $this->settings['price_notification_type'] ) ) {
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'send_price_change_email' ), 20, 3 );
		}

		// Add filter for notice text customization.
		add_filter( 'rcwcs_price_notice_text', array( $this, 'get_price_notice_text' ), 10, 3 );
	}

	/**
	 * Display notice on cart page.
	 */
	public function display_cart_notice() {
		$cart_items = WC()->cart->get_cart();
		$this->check_items_and_display_notice( $cart_items );
	}

	/**
	 * Display notice on checkout page.
	 */
	public function display_checkout_notice() {
		$cart_items = WC()->cart->get_cart();
		$this->check_items_and_display_notice( $cart_items );
	}

	/**
	 * Display notice on order details page.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function display_order_notice( $order ) {
		// Check if this is a resubscribe order.
		if ( ! wcs_order_contains_resubscribe( $order ) ) {
			return;
		}

		$items = $order->get_items();
		$has_price_updates = false;

		foreach ( $items as $item ) {
			if ( 'yes' === $item->get_meta( '_price_updated' ) ) {
				$has_price_updates = true;
				break;
			}
		}

		if ( $has_price_updates ) {
			$notice_text = $this->settings['price_notice_text'] ?? __( 'Note: The price for this product has been updated to reflect the current price.', 'resubscribe-controls-for-wcsubs' );
			wc_print_notice( $notice_text, 'notice' );
		}
	}

	/**
	 * Display notice on my account page.
	 */
	public function display_account_notice() {
		// Check if user has any resubscribe orders with price updates.
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$has_price_updates = false;

		// Use HPOS compatible method to check for orders with price updates
		if ( $this->is_hpos_enabled() ) {
			// Use HPOS compatible approach with wc_get_orders
			$orders = wc_get_orders(
				array(
					'customer_id' => $user_id,
					'limit'       => 20, // Limit to recent orders for performance
					'meta_key'    => '_wcs_contains_resubscribe',
					'meta_value'  => 'yes',
				)
			);

			foreach ( $orders as $order ) {
				foreach ( $order->get_items() as $item ) {
					if ( 'yes' === $item->get_meta( '_price_updated' ) ) {
						$has_price_updates = true;
						break 2; // Exit both loops
					}
				}
			}
		} else {
			// Legacy approach for non-HPOS stores
			global $wpdb;
			
			// Get recent orders with price updates.
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
				INNER JOIN {$wpdb->postmeta} pm ON oi.order_id = pm.post_id
				WHERE oim.meta_key = '_price_updated'
				AND oim.meta_value = 'yes'
				AND pm.meta_key = '_customer_user'
				AND pm.meta_value = %d
				AND oi.order_id IN (
					SELECT DISTINCT p.ID FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
					WHERE p.post_type = 'shop_order'
					AND pm2.meta_key = '_wcs_contains_resubscribe'
					AND pm2.meta_value = 'yes'
				)
				LIMIT 1",
				$user_id
			) );

			$has_price_updates = $count > 0;
		}

		if ( $has_price_updates ) {
			$notice_text = $this->settings['price_notice_text'] ?? __( 'Note: Some of your recent resubscriptions have updated prices to reflect current product pricing.', 'resubscribe-controls-for-wcsubs' );
			wc_print_notice( $notice_text, 'notice' );
		}
	}

	/**
	 * Check cart items for price updates and display notice if needed.
	 *
	 * @param array $items Cart items.
	 */
	private function check_items_and_display_notice( $items ) {
		$has_price_updates = false;

		foreach ( $items as $item ) {
			if ( isset( $item['price_updated'] ) && 'yes' === $item['price_updated'] ) {
				$has_price_updates = true;
				break;
			}
		}

		if ( $has_price_updates ) {
			$notice_text = $this->settings['price_notice_text'] ?? __( 'Note: The price for this product has been updated to reflect the current price.', 'resubscribe-controls-for-wcsubs' );
			wc_print_notice( $notice_text, 'notice' );
		}
	}

	/**
	 * Send price change email notification.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order       Order object.
	 */
	public function send_price_change_email( $order_id, $posted_data, $order ) {
		// Check if this is a resubscribe order.
		if ( ! wcs_order_contains_resubscribe( $order ) ) {
			return;
		}

		// Check if any items have updated prices.
		$has_price_updates = false;
		$price_updated_items = array();

		foreach ( $order->get_items() as $item ) {
			if ( 'yes' === $item->get_meta( '_price_updated' ) ) {
				$has_price_updates = true;
				$price_updated_items[] = array(
					'name'          => $item->get_name(),
					'original_price' => $item->get_meta( '_original_price' ),
					'new_price'     => $item->get_subtotal() / $item->get_quantity(),
				);
			}
		}

		if ( ! $has_price_updates ) {
			return;
		}

		// Get email settings.
		$email_subject = $this->settings['price_email_subject'] ?? __( 'Your resubscription price has been updated', 'resubscribe-controls-for-wcsubs' );
		$email_heading = $this->settings['price_email_heading'] ?? __( 'Subscription Price Update', 'resubscribe-controls-for-wcsubs' );
		$email_content = $this->settings['price_email_content'] ?? __( "Hello {customer_name},\n\nThe price for your subscription to {product_name} has been updated from {old_price} to {new_price}.\n\nThis price will be applied to your resubscription and future renewal payments.\n\nThank you for your business.\n\n{site_title}", 'resubscribe-controls-for-wcsubs' );

		// Common replacements for all items
		$customer_name = $order->get_formatted_billing_full_name();
		$customer_name = empty( $customer_name ) ? __( 'Customer', 'resubscribe-controls-for-wcsubs' ) : $customer_name;
		$order_number = $order->get_order_number();
		$site_title = get_bloginfo( 'name' );
		$site_url = get_bloginfo( 'url' );

		$common_replacements = array(
			'{customer_name}' => $customer_name,
			'{order_number}'  => $order_number,
			'{site_title}'    => $site_title,
			'{site_url}'      => $site_url
		);

		// Send email for each updated item.
		foreach ( $price_updated_items as $item ) {
			$product_name = $item['name'];
			$original_price = wc_price( $item['original_price'] );
			$new_price = wc_price( $item['new_price'] );

			// Replace placeholders in content.
			$item_replacements = array(
				'{product_name}' => $product_name,
				'{old_price}'    => $original_price,
				'{new_price}'    => $new_price,
			);

			// Combine all replacements
			$all_replacements = array_merge( $common_replacements, $item_replacements );
			
			// Replace all placeholders
			$content = str_replace(
				array_keys( $all_replacements ),
				array_values( $all_replacements ),
				$email_content
			);

			// Format email content.
			$email_body = $this->format_email_html( $email_heading, $content );

			// Send the email.
			$customer_email = $order->get_billing_email();
			if ( $customer_email ) {
				// Use WooCommerce mailer if available for better template handling
				if ( function_exists( 'wc_get_template_html' ) ) {
					WC()->mailer()->send( 
						$customer_email, 
						$email_subject, 
						$email_body, 
						array(
							'Content-Type: text/html; charset=UTF-8',
							'From: ' . get_option( 'blogname' ) . ' <' . get_option( 'admin_email' ) . '>',
						)
					);
				} else {
					wp_mail(
						$customer_email,
						$email_subject,
						$email_body,
						array(
							'Content-Type: text/html; charset=UTF-8',
							'From: ' . get_option( 'blogname' ) . ' <' . get_option( 'admin_email' ) . '>',
						)
					);
				}
				
				// Log this email being sent
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->info(
						sprintf( 'Price change email sent to %s for order #%s', $customer_email, $order_id ),
						array( 'source' => 'rcwcs-notifications' )
					);
				}
			}
		}
	}

	/**
	 * Format HTML email.
	 *
	 * @param string $heading Email heading.
	 * @param string $content Email content.
	 * @return string Formatted email.
	 */
	private function format_email_html( $heading, $content ) {
		// Don't convert line breaks if content already contains HTML tags
		if ( strpos( $content, '<' ) === false ) {
			$content = wpautop( $content );
		}
		
		// Get WooCommerce email template
		ob_start();
		
		// Use wc_get_template for better template handling
		if ( function_exists( 'wc_get_template' ) ) {
			wc_get_template( 
				'emails/email-header.php', 
				array( 
					'email_heading' => $heading,
					// Pass additional styling parameters
					'email_title' => $heading,
				) 
			);
			
			echo wp_kses_post( $content );
			
			wc_get_template( 'emails/email-footer.php' );
		} else {
			// Fallback if template function is not available
			?>
			<!DOCTYPE html>
			<html <?php language_attributes(); ?>>
				<head>
					<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
					<title><?php echo esc_html( $heading ); ?></title>
				</head>
				<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
					<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
						<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
							<tr>
								<td align="center" valign="top">
									<div id="template_header_image">
										<?php
										if ( function_exists( 'get_custom_logo' ) && get_theme_mod( 'custom_logo' ) ) {
											$custom_logo_id = get_theme_mod( 'custom_logo' );
											$logo = wp_get_attachment_image_src( $custom_logo_id, 'full' );
											if ( $logo ) {
												echo '<p style="margin-top:0;"><img src="' . esc_url( $logo[0] ) . '" alt="' . get_bloginfo( 'name', 'display' ) . '" /></p>';
											}
										}
										?>
									</div>
									<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container">
										<tr>
											<td align="center" valign="top">
												<!-- Header -->
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header">
													<tr>
														<td id="header_wrapper">
															<h1><?php echo esc_html( $heading ); ?></h1>
														</td>
													</tr>
												</table>
												<!-- End Header -->
											</td>
										</tr>
										<tr>
											<td align="center" valign="top">
												<!-- Body -->
												<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_body">
													<tr>
														<td valign="top" id="body_content">
															<!-- Content -->
															<table border="0" cellpadding="20" cellspacing="0" width="100%">
																<tr>
																	<td valign="top">
																		<div id="body_content_inner">
																			<?php echo wp_kses_post( $content ); ?>
																		</div>
																	</td>
																</tr>
															</table>
															<!-- End Content -->
														</td>
													</tr>
												</table>
												<!-- End Body -->
											</td>
										</tr>
										<tr>
											<td align="center" valign="top">
												<!-- Footer -->
												<table border="0" cellpadding="10" cellspacing="0" width="600" id="template_footer">
													<tr>
														<td valign="top">
															<table border="0" cellpadding="10" cellspacing="0" width="100%">
																<tr>
																	<td colspan="2" valign="middle" id="credit">
																		<p><?php echo wp_kses_post( wpautop( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) ); ?></p>
																	</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>
												<!-- End Footer -->
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</div>
				</body>
			</html>
			<?php
		}
		
		return ob_get_clean();
	}

	/**
	 * Get price notice text with substitutions.
	 *
	 * @param string    $text         Original notice text.
	 * @param float     $old_price    Original price.
	 * @param float     $new_price    New price.
	 * @return string   Modified notice text.
	 */
	public function get_price_notice_text( $text, $old_price = null, $new_price = null ) {
		// Use default text if none is set.
		if ( empty( $text ) ) {
			$text = $this->settings['price_notice_text'] ?? __( 'Note: The price for this product has been updated to reflect the current price.', 'resubscribe-controls-for-wcsubs' );
		}

		// Replace placeholders if prices are provided.
		if ( null !== $old_price && null !== $new_price ) {
			$text = str_replace(
				array( '{old_price}', '{new_price}' ),
				array( wc_price( $old_price ), wc_price( $new_price ) ),
				$text
			);
		}

		return $text;
	}

	/**
	 * Check if HPOS is enabled.
	 *
	 * @return bool Whether HPOS is enabled.
	 */
	private function is_hpos_enabled() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}
}

// Initialize module.
// This is commented out because it will be initialized from the main plugin class based on settings.
// RCWCS_Notifications::init(); 