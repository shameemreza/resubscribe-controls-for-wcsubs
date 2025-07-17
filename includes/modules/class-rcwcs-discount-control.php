<?php
/**
 * Discount Control module.
 *
 * @package Resubscribe_Controls_for_WCSubs
 * @since 1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class responsible for applying discounts to resubscriptions.
 *
 * @since 1.1.0
 */
class RCWCS_Discount_Control {

	/**
	 * Settings array.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Single instance of the class.
	 *
	 * @var RCWCS_Discount_Control
	 */
	private static $instance = null;

	/**
	 * Main class instance. Ensures only one instance is loaded.
	 *
	 * @return RCWCS_Discount_Control
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Alias for get_instance() for backward compatibility.
	 *
	 * @return RCWCS_Discount_Control
	 */
	public static function instance() {
		return self::get_instance();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'rcwcs_settings', array() );
		
		// Only hook if discount control is enabled
		if ( ! isset( $this->settings['discount_control_enabled'] ) || 'yes' !== $this->settings['discount_control_enabled'] ) {
			return;
		}
		
		// Add discount to resubscribe orders
		add_filter( 'wcs_resubscribe_order_created', array( $this, 'apply_discount_to_resubscribe_order' ), 10, 3 );
		
		// Add product-level controls - use the same hook as main resubscribe controls
		add_action( 'woocommerce_product_options_tax', array( $this, 'add_product_discount_fields' ), 25 );
		// Use a higher priority (25) to ensure this appears after the main controls which use priority 21
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_discount_fields' ), 25, 3 );
		
		// Save product-level controls
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_discount_fields' ) );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_discount_fields' ), 10, 2 );
		
		// Track discount usage
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_discount_usage' ), 10, 3 );
	}
	
	/**
	 * Add discount fields to simple subscription products.
	 */
	public function add_product_discount_fields() {
		global $post;
		
		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}
		
		// Get the product
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}
		
		// Check if this is a subscription product
		$is_subscription = false;
		
		// Make sure WC_Subscriptions_Product class exists
		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			// Check for subscription type directly
			if ( in_array( $product->get_type(), array( 'subscription' ), true ) ) {
				$is_subscription = true;
			}
		} else {
			// WooCommerce Subscriptions is available, use its functions
			if ( WC_Subscriptions_Product::is_subscription( $product ) && 'variable-subscription' !== $product->get_type() ) {
				$is_subscription = true;
			}
		}
		
		// Exit if not a simple subscription product
		if ( ! $is_subscription ) {
			return;
		}
		
		// Only show if product-level overrides are allowed
		if ( ! isset( $this->settings['discount_product_override'] ) || 'yes' !== $this->settings['discount_product_override'] ) {
			return;
		}

		// Get product settings
		$override_discount = $product->get_meta( '_rcwcs_override_discount', true );
		$discount_type = $product->get_meta( '_rcwcs_discount_type', true );
		$discount_amount = $product->get_meta( '_rcwcs_discount_amount', true );
		$discount_application = $product->get_meta( '_rcwcs_discount_application', true );
		$discount_payment_count = $product->get_meta( '_rcwcs_discount_payment_count', true );
		
		// Set defaults if not set
		if ( '' === $override_discount ) {
			$override_discount = 'no';
		}
		if ( '' === $discount_type ) {
			$discount_type = isset( $this->settings['discount_type'] ) ? $this->settings['discount_type'] : 'percentage';
		}
		if ( '' === $discount_amount ) {
			$discount_amount = isset( $this->settings['discount_amount'] ) ? $this->settings['discount_amount'] : 10;
		}
		if ( '' === $discount_application ) {
			$discount_application = isset( $this->settings['discount_application'] ) ? $this->settings['discount_application'] : 'first_payment';
		}
		if ( '' === $discount_payment_count ) {
			$discount_payment_count = isset( $this->settings['discount_payment_count'] ) ? $this->settings['discount_payment_count'] : 3;
		}
		
		// Add a divider
		echo '<div style="margin: 15px 0; border-top: 1px solid #eee;"></div>';
		
		// Add section subtitle
		echo '<h4 style="padding-left: 12px; margin-bottom: 0;">' . esc_html__( 'Resubscribe Discount', 'resubscribe-controls-for-wcsubs' ) . '</h4>';
		echo '<p style="padding-left: 12px; font-style: italic; margin-top: 0; margin-bottom: 15px; color: #777;">' . esc_html__( 'Configure discount for customers who resubscribe to this product.', 'resubscribe-controls-for-wcsubs' ) . '</p>';
		
		// Override discount checkbox
		woocommerce_wp_checkbox( 
			array(
				'id'          => '_rcwcs_override_discount',
				'label'       => __( 'Override Global Discount', 'resubscribe-controls-for-wcsubs' ),
				'description' => __( 'Override the global resubscribe discount settings for this product.', 'resubscribe-controls-for-wcsubs' ),
				'value'       => $override_discount,
			)
		);
		
		// Discount type
		woocommerce_wp_select( 
			array(
				'id'          => '_rcwcs_discount_type',
				'label'       => __( 'Discount Type', 'resubscribe-controls-for-wcsubs' ),
				'description' => __( 'Select the type of discount to apply.', 'resubscribe-controls-for-wcsubs' ),
				'value'       => $discount_type,
				'options'     => array(
					'percentage' => __( 'Percentage Discount (%)', 'resubscribe-controls-for-wcsubs' ),
					'fixed'      => __( 'Fixed Amount Discount', 'resubscribe-controls-for-wcsubs' ),
				),
				'class'       => 'show_if_override_discount',
			)
		);
		
		// Discount amount
		woocommerce_wp_text_input( 
			array(
				'id'                => '_rcwcs_discount_amount',
				'label'             => __( 'Discount Amount', 'resubscribe-controls-for-wcsubs' ),
				'description'       => __( 'The amount of discount to apply.', 'resubscribe-controls-for-wcsubs' ),
				'value'             => $discount_amount,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => 'percentage' === $discount_type ? '1' : '0.01',
				),
				'class'             => 'show_if_override_discount',
			)
		);
		
		// Discount application
		woocommerce_wp_select( 
			array(
				'id'          => '_rcwcs_discount_application',
				'label'       => __( 'Apply Discount To', 'resubscribe-controls-for-wcsubs' ),
				'description' => __( 'Specify when the discount should be applied.', 'resubscribe-controls-for-wcsubs' ),
				'value'       => $discount_application,
				'options'     => array(
					'first_payment'  => __( 'First Payment Only', 'resubscribe-controls-for-wcsubs' ),
					'all_payments'   => __( 'All Subscription Payments', 'resubscribe-controls-for-wcsubs' ),
					'limited'        => __( 'Limited Number of Payments', 'resubscribe-controls-for-wcsubs' ),
				),
				'class'       => 'show_if_override_discount',
			)
		);
		
		// Number of payments
		woocommerce_wp_text_input( 
			array(
				'id'                => '_rcwcs_discount_payment_count',
				'label'             => __( 'Number of Payments', 'resubscribe-controls-for-wcsubs' ),
				'description'       => __( 'Number of payments to apply the discount to (if "Limited Number of Payments" is selected).', 'resubscribe-controls-for-wcsubs' ),
				'value'             => $discount_payment_count,
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
				'class'             => 'show_if_override_discount show_if_limited_payments',
			)
		);
		
		// Add JavaScript to toggle field visibility
		echo '<script type="text/javascript">
			jQuery(function($) {
				function toggleDiscountFields() {
					if ($("#_rcwcs_override_discount").is(":checked")) {
						$(".show_if_override_discount").closest(".form-field").show();
					} else {
						$(".show_if_override_discount").closest(".form-field").hide();
					}
					
					if ($("#_rcwcs_discount_application").val() === "limited") {
						$(".show_if_limited_payments").closest(".form-field").show();
					} else {
						$(".show_if_limited_payments").closest(".form-field").hide();
					}
				}
				
				// Initial state
				toggleDiscountFields();
				
				// Toggle on change
				$("#_rcwcs_override_discount").change(function() {
					toggleDiscountFields();
				});
				
				$("#_rcwcs_discount_application").change(function() {
					toggleDiscountFields();
				});
				
				// Update step attribute based on discount type
				$("#_rcwcs_discount_type").change(function() {
					if ($(this).val() === "percentage") {
						$("#_rcwcs_discount_amount").attr("step", "1");
					} else {
						$("#_rcwcs_discount_amount").attr("step", "0.01");
					}
				});
			});
		</script>';
	}
	
	/**
	 * Add discount fields to variable subscription products.
	 *
	 * @param int     $loop           Position in the loop.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post object.
	 */
	public function add_variation_discount_fields( $loop, $variation_data, $variation ) {
		// Only show if product-level overrides are allowed
		if ( ! isset( $this->settings['discount_product_override'] ) || 'yes' !== $this->settings['discount_product_override'] ) {
			return;
		}
		
		// Get variation settings
		$variation_id = $variation->ID;
		$variation_product = wc_get_product( $variation_id );
		
		if ( ! $variation_product ) {
			return;
		}
		
		// Only show for subscription variations
		$parent_product_id = $variation_product->get_parent_id();
		$parent_product = wc_get_product( $parent_product_id );
		
		if ( ! $parent_product || 'variable-subscription' !== $parent_product->get_type() ) {
			return;
		}
		
		// Get variation settings
		$override_discount = $variation_product->get_meta( '_rcwcs_override_discount', true );
		$discount_type = $variation_product->get_meta( '_rcwcs_discount_type', true );
		$discount_amount = $variation_product->get_meta( '_rcwcs_discount_amount', true );
		$discount_application = $variation_product->get_meta( '_rcwcs_discount_application', true );
		$discount_payment_count = $variation_product->get_meta( '_rcwcs_discount_payment_count', true );
		
		// Set defaults if not set
		if ( '' === $override_discount ) {
			$override_discount = 'no';
		}
		if ( '' === $discount_type ) {
			$discount_type = isset( $this->settings['discount_type'] ) ? $this->settings['discount_type'] : 'percentage';
		}
		if ( '' === $discount_amount ) {
			$discount_amount = isset( $this->settings['discount_amount'] ) ? $this->settings['discount_amount'] : 10;
		}
		if ( '' === $discount_application ) {
			$discount_application = isset( $this->settings['discount_application'] ) ? $this->settings['discount_application'] : 'first_payment';
		}
		if ( '' === $discount_payment_count ) {
			$discount_payment_count = isset( $this->settings['discount_payment_count'] ) ? $this->settings['discount_payment_count'] : 3;
		}
		
		?>
		<div class="variation_resubscribe_discount">
			<hr style="margin: 10px 0;">
			<p style="margin-bottom: 10px; font-weight: 600; font-size: 14px;"><?php esc_html_e( 'Resubscribe Discount', 'resubscribe-controls-for-wcsubs' ); ?></p>
			<p style="margin-top: 0; font-style: italic; color: #777; margin-bottom: 15px;"><?php esc_html_e( 'Configure discount for customers who resubscribe to this variation.', 'resubscribe-controls-for-wcsubs' ); ?></p>
			
			<table class="form-table" style="margin-top: 0; border-collapse: separate; border-spacing: 0;">
				<tbody>
					<tr>
						<td style="padding: 5px 0; width: 200px; vertical-align: top;">
							<label style="margin-bottom: 0; display: inline-block;">
								<input type="checkbox" 
									class="checkbox" 
									name="variable_rcwcs_override_discount[<?php echo esc_attr( $loop ); ?>]" 
									id="_rcwcs_override_discount_<?php echo esc_attr( $loop ); ?>" 
									value="yes" 
									<?php checked( 'yes', $override_discount ); ?> 
									style="margin-right: 5px;">
								<?php esc_html_e( 'Override Global Discount', 'resubscribe-controls-for-wcsubs' ); ?>
							</label>
						</td>
						<td style="padding: 5px 0; vertical-align: top;">
							<span style="color: #777; font-size: 13px; display: inline-block;">
								<?php esc_html_e( 'Override the global resubscribe discount settings for this variation.', 'resubscribe-controls-for-wcsubs' ); ?>
							</span>
						</td>
					</tr>
					
					<tr class="show_if_override_discount_<?php echo esc_attr( $loop ); ?>" style="<?php echo 'yes' !== $override_discount ? 'display: none;' : ''; ?>">
						<td style="padding: 5px 0; width: 200px; vertical-align: top;">
							<label style="margin-bottom: 0; display: inline-block;">
								<?php esc_html_e( 'Discount Type', 'resubscribe-controls-for-wcsubs' ); ?>
							</label>
						</td>
						<td style="padding: 5px 0; vertical-align: top;">
							<select name="variable_rcwcs_discount_type[<?php echo esc_attr( $loop ); ?>]" id="_rcwcs_discount_type_<?php echo esc_attr( $loop ); ?>" class="select short">
								<option value="percentage" <?php selected( 'percentage', $discount_type ); ?>><?php esc_html_e( 'Percentage Discount (%)', 'resubscribe-controls-for-wcsubs' ); ?></option>
								<option value="fixed" <?php selected( 'fixed', $discount_type ); ?>><?php esc_html_e( 'Fixed Amount Discount', 'resubscribe-controls-for-wcsubs' ); ?></option>
							</select>
						</td>
					</tr>
					
					<tr class="show_if_override_discount_<?php echo esc_attr( $loop ); ?>" style="<?php echo 'yes' !== $override_discount ? 'display: none;' : ''; ?>">
						<td style="padding: 5px 0; width: 200px; vertical-align: top;">
							<label style="margin-bottom: 0; display: inline-block;">
								<?php esc_html_e( 'Discount Amount', 'resubscribe-controls-for-wcsubs' ); ?>
							</label>
						</td>
						<td style="padding: 5px 0; vertical-align: top;">
							<input type="number" class="input-text" name="variable_rcwcs_discount_amount[<?php echo esc_attr( $loop ); ?>]" id="_rcwcs_discount_amount_<?php echo esc_attr( $loop ); ?>" value="<?php echo esc_attr( $discount_amount ); ?>" step="<?php echo 'percentage' === $discount_type ? '1' : '0.01'; ?>" min="0" />
						</td>
					</tr>
					
					<tr class="show_if_override_discount_<?php echo esc_attr( $loop ); ?>" style="<?php echo 'yes' !== $override_discount ? 'display: none;' : ''; ?>">
						<td style="padding: 5px 0; width: 200px; vertical-align: top;">
							<label style="margin-bottom: 0; display: inline-block;">
								<?php esc_html_e( 'Apply Discount To', 'resubscribe-controls-for-wcsubs' ); ?>
							</label>
						</td>
						<td style="padding: 5px 0; vertical-align: top;">
							<select name="variable_rcwcs_discount_application[<?php echo esc_attr( $loop ); ?>]" id="_rcwcs_discount_application_<?php echo esc_attr( $loop ); ?>" class="select short">
								<option value="first_payment" <?php selected( 'first_payment', $discount_application ); ?>><?php esc_html_e( 'First Payment Only', 'resubscribe-controls-for-wcsubs' ); ?></option>
								<option value="all_payments" <?php selected( 'all_payments', $discount_application ); ?>><?php esc_html_e( 'All Subscription Payments', 'resubscribe-controls-for-wcsubs' ); ?></option>
								<option value="limited" <?php selected( 'limited', $discount_application ); ?>><?php esc_html_e( 'Limited Number of Payments', 'resubscribe-controls-for-wcsubs' ); ?></option>
							</select>
						</td>
					</tr>
					
					<tr class="show_if_override_discount_<?php echo esc_attr( $loop ); ?> show_if_limited_payments_<?php echo esc_attr( $loop ); ?>" style="<?php echo 'yes' !== $override_discount || 'limited' !== $discount_application ? 'display: none;' : ''; ?>">
						<td style="padding: 5px 0; width: 200px; vertical-align: top;">
							<label style="margin-bottom: 0; display: inline-block;">
								<?php esc_html_e( 'Number of Payments', 'resubscribe-controls-for-wcsubs' ); ?>
							</label>
						</td>
						<td style="padding: 5px 0; vertical-align: top;">
							<input type="number" class="input-text" name="variable_rcwcs_discount_payment_count[<?php echo esc_attr( $loop ); ?>]" id="_rcwcs_discount_payment_count_<?php echo esc_attr( $loop ); ?>" value="<?php echo esc_attr( $discount_payment_count ); ?>" step="1" min="1" />
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		
		<script type="text/javascript">
			jQuery(function($) {
				// Toggle fields visibility on checkbox change
				$('#_rcwcs_override_discount_<?php echo esc_attr( $loop ); ?>').change(function() {
					if ($(this).is(':checked')) {
						$('.show_if_override_discount_<?php echo esc_attr( $loop ); ?>').show();
					} else {
						$('.show_if_override_discount_<?php echo esc_attr( $loop ); ?>').hide();
					}
					
					// Also trigger the application change to update payment count visibility
					$('#_rcwcs_discount_application_<?php echo esc_attr( $loop ); ?>').trigger('change');
				});
				
				// Toggle payment count field visibility on application change
				$('#_rcwcs_discount_application_<?php echo esc_attr( $loop ); ?>').change(function() {
					if ($(this).val() === 'limited') {
						$('.show_if_limited_payments_<?php echo esc_attr( $loop ); ?>').show();
					} else {
						$('.show_if_limited_payments_<?php echo esc_attr( $loop ); ?>').hide();
					}
				});
				
				// Update step attribute based on discount type
				$('#_rcwcs_discount_type_<?php echo esc_attr( $loop ); ?>').change(function() {
					if ($(this).val() === 'percentage') {
						$('#_rcwcs_discount_amount_<?php echo esc_attr( $loop ); ?>').attr('step', '1');
					} else {
						$('#_rcwcs_discount_amount_<?php echo esc_attr( $loop ); ?>').attr('step', '0.01');
					}
				});
			});
		</script>
		<?php
	}
	
	/**
	 * Save discount fields for simple subscription products.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_discount_fields( $post_id ) {
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		// Check if this is a subscription product
		$is_subscription = false;
		
		// Make sure WC_Subscriptions_Product class exists
		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			// Check for subscription type directly
			if ( in_array( $product->get_type(), array( 'subscription' ), true ) ) {
				$is_subscription = true;
			}
		} else {
			// WooCommerce Subscriptions is available, use its functions
			if ( WC_Subscriptions_Product::is_subscription( $product ) && 'variable-subscription' !== $product->get_type() ) {
				$is_subscription = true;
			}
		}
		
		// Exit if not a simple subscription product
		if ( ! $is_subscription ) {
			return;
		}

		// Save discount fields
		$override_discount = isset( $_POST['_rcwcs_override_discount'] ) ? 'yes' : 'no';
		$product->update_meta_data( '_rcwcs_override_discount', $override_discount );
		
		if ( isset( $_POST['_rcwcs_discount_type'] ) ) {
			$product->update_meta_data( '_rcwcs_discount_type', sanitize_text_field( $_POST['_rcwcs_discount_type'] ) );
		}
		
		if ( isset( $_POST['_rcwcs_discount_amount'] ) ) {
			$product->update_meta_data( '_rcwcs_discount_amount', wc_format_decimal( $_POST['_rcwcs_discount_amount'] ) );
		}
		
		if ( isset( $_POST['_rcwcs_discount_application'] ) ) {
			$product->update_meta_data( '_rcwcs_discount_application', sanitize_text_field( $_POST['_rcwcs_discount_application'] ) );
		}
		
		if ( isset( $_POST['_rcwcs_discount_payment_count'] ) ) {
			$product->update_meta_data( '_rcwcs_discount_payment_count', absint( $_POST['_rcwcs_discount_payment_count'] ) );
		}
		
		$product->save();
	}
	
	/**
	 * Save discount fields for variable subscription products.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $i            Loop index.
	 */
	public function save_variation_discount_fields( $variation_id, $i ) {
		$variation_product = wc_get_product( $variation_id );
		
		if ( ! $variation_product ) {
			return;
		}
		
		// Save discount fields
		$override_discount = isset( $_POST['variable_rcwcs_override_discount'][ $i ] ) ? 'yes' : 'no';
		$variation_product->update_meta_data( '_rcwcs_override_discount', $override_discount );
		
		if ( isset( $_POST['variable_rcwcs_discount_type'][ $i ] ) ) {
			$variation_product->update_meta_data( '_rcwcs_discount_type', sanitize_text_field( $_POST['variable_rcwcs_discount_type'][ $i ] ) );
		}
		
		if ( isset( $_POST['variable_rcwcs_discount_amount'][ $i ] ) ) {
			$variation_product->update_meta_data( '_rcwcs_discount_amount', wc_format_decimal( $_POST['variable_rcwcs_discount_amount'][ $i ] ) );
		}
		
		if ( isset( $_POST['variable_rcwcs_discount_application'][ $i ] ) ) {
			$variation_product->update_meta_data( '_rcwcs_discount_application', sanitize_text_field( $_POST['variable_rcwcs_discount_application'][ $i ] ) );
		}
		
		if ( isset( $_POST['variable_rcwcs_discount_payment_count'][ $i ] ) ) {
			$variation_product->update_meta_data( '_rcwcs_discount_payment_count', absint( $_POST['variable_rcwcs_discount_payment_count'][ $i ] ) );
		}
		
		$variation_product->save();
	}
	
	/**
	 * Apply discount to resubscribe order.
	 *
	 * @param WC_Order        $resubscribe_order The new order for the resubscription.
	 * @param WC_Subscription $subscription      The subscription being resubscribed to.
	 * @param WC_Order        $order             The order in which the subscription was purchased.
	 * @return WC_Order Modified order.
	 */
	public function apply_discount_to_resubscribe_order( $resubscribe_order, $subscription, $order ) {
		// Don't apply discount if not enabled
		if ( ! isset( $this->settings['discount_control_enabled'] ) || 'yes' !== $this->settings['discount_control_enabled'] ) {
			return $resubscribe_order;
		}
		
		// Get the customer ID
		$customer_id = $resubscribe_order->get_customer_id();
		
		// Check if customer has already used maximum discount allowance
		$max_usage = isset( $this->settings['discount_max_usage'] ) ? absint( $this->settings['discount_max_usage'] ) : 1;
		
		// If max_usage is 0, it means unlimited usage
		if ( $max_usage > 0 ) {
			$discount_usage = get_user_meta( $customer_id, '_rcwcs_discount_usage', true );
			$discount_usage = ! empty( $discount_usage ) ? absint( $discount_usage ) : 0;
			
			if ( $discount_usage >= $max_usage ) {
				// Customer has already used their maximum discount allowance
				return $resubscribe_order;
			}
		}
		
		// Get order items and apply discount to each subscription item
		foreach ( $resubscribe_order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();
			$product = wc_get_product( $product_id );
			
			if ( ! $product ) {
				continue;
			}
			
			// Get discount settings for this product
			$discount_type = $this->get_product_discount_type( $product );
			$discount_amount = $this->get_product_discount_amount( $product );
			
			// Skip if no discount amount
			if ( empty( $discount_amount ) || $discount_amount <= 0 ) {
				continue;
			}
			
			// Get the product price from the item
			$item_subtotal = $item->get_subtotal();
			$discount_subtotal = 0;
			
			// Calculate discount amount
			if ( 'percentage' === $discount_type ) {
				// Percentage discount
				$discount_subtotal = $item_subtotal * ( $discount_amount / 100 );
			} else {
				// Fixed amount discount - make sure it doesn't exceed the item price
				$discount_subtotal = min( $discount_amount, $item_subtotal );
			}
			
			// Apply the discount
			if ( $discount_subtotal > 0 ) {
				// Add a meta to the item to indicate this is a resubscribe discount
				$item->add_meta_data( '_rcwcs_resubscribe_discount', 'yes', true );
				$item->add_meta_data( '_rcwcs_discount_type', $discount_type, true );
				$item->add_meta_data( '_rcwcs_discount_amount', $discount_amount, true );
				
				// Adjust the item price
				$new_subtotal = $item_subtotal - $discount_subtotal;
				$item->set_subtotal( $new_subtotal );
				$item->set_total( $new_subtotal );
				
				// Add a note to the order
				if ( 'percentage' === $discount_type ) {
					$note = sprintf(
						/* translators: 1: product name, 2: discount percentage */
						__( 'Applied %2$s%% resubscribe discount to %1$s.', 'resubscribe-controls-for-wcsubs' ),
						$item->get_name(),
						$discount_amount
					);
				} else {
					$note = sprintf(
						/* translators: 1: product name, 2: discount amount */
						__( 'Applied %2$s resubscribe discount to %1$s.', 'resubscribe-controls-for-wcsubs' ),
						$item->get_name(),
						wc_price( $discount_amount )
					);
				}
				
				$resubscribe_order->add_order_note( $note );
				
				// Save payment application settings in the subscription
				$discount_application = $this->get_product_discount_application( $product );
				$discount_payment_count = $this->get_product_discount_payment_count( $product );
				
				// Store discount details in subscription meta for future renewals
				foreach ( wcs_get_subscriptions_for_order( $resubscribe_order ) as $new_subscription ) {
					$new_subscription->update_meta_data( '_rcwcs_has_discount', 'yes' );
					$new_subscription->update_meta_data( '_rcwcs_discount_type', $discount_type );
					$new_subscription->update_meta_data( '_rcwcs_discount_amount', $discount_amount );
					$new_subscription->update_meta_data( '_rcwcs_discount_application', $discount_application );
					
					if ( 'limited' === $discount_application ) {
						$new_subscription->update_meta_data( '_rcwcs_discount_payment_count', $discount_payment_count );
						$new_subscription->update_meta_data( '_rcwcs_discount_payments_remaining', $discount_payment_count );
					}
					
					$new_subscription->save();
				}
			}
		}
		
		// Update order totals
		$resubscribe_order->calculate_totals();
		
		// Track that this customer has used the discount
		$this->update_customer_discount_usage( $customer_id );
		
		return $resubscribe_order;
	}
	
	/**
	 * Get the discount application setting for a product.
	 *
	 * @param WC_Product $product Product object.
	 * @return string Discount application.
	 */
	private function get_product_discount_application( $product ) {
		if ( $this->product_has_discount_override( $product ) ) {
			$discount_application = $product->get_meta( '_rcwcs_discount_application', true );
			return ! empty( $discount_application ) ? $discount_application : 'first_payment';
		}
		
		return isset( $this->settings['discount_application'] ) ? $this->settings['discount_application'] : 'first_payment';
	}
	
	/**
	 * Get the discount payment count for a product.
	 *
	 * @param WC_Product $product Product object.
	 * @return int Discount payment count.
	 */
	private function get_product_discount_payment_count( $product ) {
		if ( $this->product_has_discount_override( $product ) ) {
			$discount_payment_count = $product->get_meta( '_rcwcs_discount_payment_count', true );
			return ! empty( $discount_payment_count ) ? absint( $discount_payment_count ) : 3;
		}
		
		return isset( $this->settings['discount_payment_count'] ) ? absint( $this->settings['discount_payment_count'] ) : 3;
	}
	
	/**
	 * Update the discount usage count for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 */
	private function update_customer_discount_usage( $customer_id ) {
		if ( empty( $customer_id ) ) {
			return;
		}
		
		$discount_usage = get_user_meta( $customer_id, '_rcwcs_discount_usage', true );
		$discount_usage = ! empty( $discount_usage ) ? absint( $discount_usage ) : 0;
		$discount_usage++;
		
		update_user_meta( $customer_id, '_rcwcs_discount_usage', $discount_usage );
	}
	
	/**
	 * Track discount usage for a customer.
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order Order object.
	 */
	public function track_discount_usage( $order_id, $posted_data, $order ) {
		// Check if this is a resubscribe order
		$is_resubscribe = false;
		
		foreach ( $order->get_items() as $item ) {
			if ( $item->get_meta( '_rcwcs_resubscribe_discount' ) === 'yes' ) {
				$is_resubscribe = true;
				break;
			}
		}
		
		if ( ! $is_resubscribe ) {
			return;
		}
		
		// Track usage
		$this->update_customer_discount_usage( $order->get_customer_id() );
	}
	
	/**
	 * Check if a product has discount overrides.
	 *
	 * @param WC_Product $product Product object.
	 * @return bool Whether the product has discount overrides.
	 */
	private function product_has_discount_override( $product ) {
		return 'yes' === $product->get_meta( '_rcwcs_override_discount', true );
	}
	
	/**
	 * Get the discount type for a product.
	 *
	 * @param WC_Product $product Product object.
	 * @return string Discount type.
	 */
	private function get_product_discount_type( $product ) {
		if ( $this->product_has_discount_override( $product ) ) {
			$discount_type = $product->get_meta( '_rcwcs_discount_type', true );
			return ! empty( $discount_type ) ? $discount_type : 'percentage';
		}
		
		return isset( $this->settings['discount_type'] ) ? $this->settings['discount_type'] : 'percentage';
	}
	
	/**
	 * Get the discount amount for a product.
	 *
	 * @param WC_Product $product Product object.
	 * @return float Discount amount.
	 */
	private function get_product_discount_amount( $product ) {
		if ( $this->product_has_discount_override( $product ) ) {
			$discount_amount = $product->get_meta( '_rcwcs_discount_amount', true );
			return ! empty( $discount_amount ) ? floatval( $discount_amount ) : 0;
		}
		
		return isset( $this->settings['discount_amount'] ) ? floatval( $this->settings['discount_amount'] ) : 0;
	}
} 