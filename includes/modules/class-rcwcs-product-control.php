<?php
/**
 * Product Control Module
 *
 * @package ResubscribeControlsWCS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RCWCS_Product_Control Class
 */
class RCWCS_Product_Control {

	/**
	 * Module instance.
	 *
	 * @var RCWCS_Product_Control
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
	public function __construct() {
		$this->settings = get_option( 'rcwcs_settings', array() );

		// We're removing these hooks because the fields are now handled by the admin class
		// with the 'Allow Resubscribe' checkbox, which is the inverse of 'Disable Resubscribe'
		// add_action( 'woocommerce_product_options_subscription_pricing', array( $this, 'add_product_fields' ) );
		// add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_fields' ), 10, 3 );

		// Keep the save actions to maintain backward compatibility with existing product meta
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );

		// Only hook resubscribe-related features if product control is enabled.
		if ( ! isset( $this->settings['product_control_enabled'] ) || 'yes' !== $this->settings['product_control_enabled'] ) {
			return;
		}

		// Filter resubscribe capability.
		add_filter( 'wcs_can_user_resubscribe_to_subscription', array( $this, 'check_product_resubscribe_setting' ), 10, 3 );
		
		// Add countdown to My Account subscriptions page
		if ( isset( $this->settings['time_limitation_enabled'] ) && 'yes' === $this->settings['time_limitation_enabled'] ) {
			if ( isset( $this->settings['time_limitation_show_countdown'] ) && 'yes' === $this->settings['time_limitation_show_countdown'] ) {
				add_action( 'woocommerce_my_subscriptions_after_subscription_id', array( $this, 'display_resubscribe_countdown' ), 10, 2 );
			}
		}

		// Add bulk edit functionality.
		add_action( 'woocommerce_product_bulk_edit_start', array( $this, 'add_bulk_edit_field' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'save_bulk_edit_field' ) );
	}

	/**
	 * Get module instance.
	 *
	 * @return RCWCS_Product_Control
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
	 * Add product fields.
	 */
	public function add_product_fields() {
		woocommerce_wp_checkbox(
			array(
				'id'          => '_disable_resubscribe',
				'label'       => __( 'Disable Resubscribe', 'resubscribe-controls-for-wcsubs' ),
				'description' => __( 'Prevent customers from resubscribing to this product once their subscription has ended.', 'resubscribe-controls-for-wcsubs' ),
			)
		);
	}

	/**
	 * Add variation fields.
	 *
	 * @param int     $loop           Position in the loop.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post object.
	 */
	public function add_variation_fields( $loop, $variation_data, $variation ) {
		// Only show for subscription variations.
		$product = wc_get_product( $variation->ID );
		if ( ! $product || ! $product->is_type( 'subscription_variation' ) ) {
			return;
		}

		woocommerce_wp_checkbox(
			array(
				'id'            => '_disable_resubscribe[' . $variation->ID . ']',
				'name'          => '_disable_resubscribe[' . $variation->ID . ']',
				'label'         => __( 'Disable Resubscribe', 'resubscribe-controls-for-wcsubs' ),
				'description'   => __( 'Prevent resubscribing to this variation', 'resubscribe-controls-for-wcsubs' ),
				'value'         => get_post_meta( $variation->ID, '_disable_resubscribe', true ),
				'wrapper_class' => 'form-row form-row-full',
			)
		);
	}

	/**
	 * Save product fields.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_fields( $post_id ) {
		$disable_resubscribe = isset( $_POST['_disable_resubscribe'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_disable_resubscribe', $disable_resubscribe );
	}

	/**
	 * Save variation fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $i            Position in the loop.
	 */
	public function save_variation_fields( $variation_id, $i ) {
		$disable_resubscribe = isset( $_POST['_disable_resubscribe'][ $variation_id ] ) ? 'yes' : 'no';
		update_post_meta( $variation_id, '_disable_resubscribe', $disable_resubscribe );
	}

	/**
	 * Check if a product allows resubscription.
	 *
	 * @param bool             $can_resubscribe Whether the subscription can be resubscribed to.
	 * @param WC_Subscription  $subscription    The subscription object.
	 * @param int              $user_id         The user ID.
	 * @return bool Whether the subscription can be resubscribed to.
	 */
	public function check_product_resubscribe_setting( $can_resubscribe, $subscription, $user_id ) {
		// If they can't resubscribe already, don't change anything.
		if ( ! $can_resubscribe ) {
			return $can_resubscribe;
		}
		
		// Check if this was a trial subscription that was cancelled
		$was_trial = false;
		if ( $subscription->get_trial_period() && $subscription->get_status() === 'cancelled' ) {
			$was_trial = true;
			
			// If trial handling is enabled, allow resubscription regardless of product settings
			if ( isset( $this->settings['allow_trial_resubscribe'] ) && 'yes' === $this->settings['allow_trial_resubscribe'] ) {
				return true;
			}
		}
		
		// Get the subscription's product.
		$product_id = $subscription->get_data()['product_id'];
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return $can_resubscribe;
		}
		
		// Check time limitation
		$time_limitation_enabled = isset( $this->settings['time_limitation_enabled'] ) && 'yes' === $this->settings['time_limitation_enabled'];
		$product_override_allowed = isset( $this->settings['time_limitation_product_override'] ) && 'yes' === $this->settings['time_limitation_product_override'];
		
		if ( $time_limitation_enabled ) {
			// First check if this product has an override
			$has_override = false;
			$product_time_limit_days = 0;
			
			if ( $product_override_allowed ) {
				$override_time_limitation = $product->get_meta( '_rcwcs_override_time_limitation', true );
				if ( 'yes' === $override_time_limitation ) {
					$has_override = true;
					$product_time_limit_days = absint( $product->get_meta( '_rcwcs_time_limitation_days', true ) );
				}
			}
			
			// Use product-specific time limit if it has an override, otherwise use global settings
			$time_limit_days = $has_override 
				? $product_time_limit_days 
				: ( isset( $this->settings['time_limitation_days'] ) ? absint( $this->settings['time_limitation_days'] ) : 30 );
			
			// If time limit is 0, it means unlimited time
			if ( $time_limit_days > 0 ) {
				$subscription_end_time = $subscription->get_date('end');
				
				// If subscription has no end date, use the date it was modified last
				if ( ! $subscription_end_time ) {
					$subscription_end_time = $subscription->get_date_modified()->getTimestamp();
				}
				
				// Check if current time is beyond the time limitation period
				$time_limit_timestamp = $subscription_end_time + ( $time_limit_days * DAY_IN_SECONDS );
				
				if ( time() > $time_limit_timestamp ) {
					// Time limit exceeded, can't resubscribe
					return false;
				}
			}
		}

		// Check if the product allows resubscription.
		$allow_resubscribe = $product->get_meta( '_rcwcs_allow_resubscribe', true );

		// If the meta doesn't exist, default to yes.
		if ( '' === $allow_resubscribe ) {
			$allow_resubscribe = 'yes';
		}

		// Return whether resubscription is allowed.
		return 'yes' === $allow_resubscribe;
	}

	/**
	 * Display resubscribe countdown on the My Account page.
	 *
	 * @param int            $subscription_id Subscription ID.
	 * @param WC_Subscription $subscription    Subscription object.
	 */
	public function display_resubscribe_countdown( $subscription_id, $subscription ) {
		// Only show countdown for cancelled or expired subscriptions
		if ( ! in_array( $subscription->get_status(), array( 'cancelled', 'expired' ), true ) ) {
			return;
		}
		
		// Get the subscription's product
		$product_id = $subscription->get_data()['product_id'];
		$product = wc_get_product( $product_id );
		
		if ( ! $product ) {
			return;
		}
		
		// Check if this product has an override
		$has_override = false;
		$product_time_limit_days = 0;
		$product_override_allowed = isset( $this->settings['time_limitation_product_override'] ) && 'yes' === $this->settings['time_limitation_product_override'];
		
		if ( $product_override_allowed ) {
			$override_time_limitation = $product->get_meta( '_rcwcs_override_time_limitation', true );
			if ( 'yes' === $override_time_limitation ) {
				$has_override = true;
				$product_time_limit_days = absint( $product->get_meta( '_rcwcs_time_limitation_days', true ) );
			}
		}
		
		// Use product-specific time limit if it has an override, otherwise use global settings
		$time_limit_days = $has_override 
			? $product_time_limit_days 
			: ( isset( $this->settings['time_limitation_days'] ) ? absint( $this->settings['time_limitation_days'] ) : 30 );
		
		// If time limit is 0 (unlimited) or not set, don't show countdown
		if ( $time_limit_days <= 0 ) {
			return;
		}
		
		// Get subscription end time
		$subscription_end_time = $subscription->get_date('end');
		
		// If subscription has no end date, use the date it was modified last
		if ( ! $subscription_end_time ) {
			$subscription_end_time = $subscription->get_date_modified()->getTimestamp();
		}
		
		// Calculate deadline timestamp and days remaining
		$deadline_timestamp = $subscription_end_time + ( $time_limit_days * DAY_IN_SECONDS );
		$seconds_remaining = $deadline_timestamp - time();
		$days_remaining = floor( $seconds_remaining / DAY_IN_SECONDS );
		
		// If time has expired, don't show countdown
		if ( $days_remaining < 0 ) {
			return;
		}
		
		// Format the deadline date
		$deadline_date = date_i18n( get_option( 'date_format' ), $deadline_timestamp );
		
		// Show the countdown
		echo '<div class="rcwcs-resubscribe-countdown" style="margin-top: 10px; padding: 8px; background-color: #f8f8f8; border-left: 3px solid #17a2b8; color: #333;">';
		
		if ( $days_remaining > 1 ) {
			printf(
				/* translators: 1: number of days, 2: deadline date */
				esc_html__( 'Resubscribe within %1$d days (before %2$s)', 'resubscribe-controls-for-wcsubs' ),
				$days_remaining,
				esc_html( $deadline_date )
			);
		} elseif ( $days_remaining == 1 ) {
			printf(
				/* translators: %s: deadline date */
				esc_html__( 'Last day to resubscribe (before %s)', 'resubscribe-controls-for-wcsubs' ),
				esc_html( $deadline_date )
			);
		} else {
			printf(
				/* translators: %s: deadline date */
				esc_html__( 'Final hours to resubscribe (until %s)', 'resubscribe-controls-for-wcsubs' ),
				esc_html( $deadline_date )
			);
		}
		
		echo '</div>';
	}

	/**
	 * Add bulk edit field.
	 */
	public function add_bulk_edit_field() {
		?>
		<div class="inline-edit-group">
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Disable Resubscribe', 'resubscribe-controls-for-wcsubs' ); ?></span>
				<span class="input-text-wrap">
					<select name="_disable_resubscribe_bulk">
						<option value=""><?php esc_html_e( '— No Change —', 'resubscribe-controls-for-wcsubs' ); ?></option>
						<option value="yes"><?php esc_html_e( 'Yes', 'resubscribe-controls-for-wcsubs' ); ?></option>
						<option value="no"><?php esc_html_e( 'No', 'resubscribe-controls-for-wcsubs' ); ?></option>
					</select>
				</span>
			</label>
		</div>
		<?php
	}

	/**
	 * Save bulk edit field.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function save_bulk_edit_field( $product ) {
		if ( ! $product->is_type( array( 'subscription', 'variable-subscription' ) ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_REQUEST['_disable_resubscribe_bulk'] ) && '' !== $_REQUEST['_disable_resubscribe_bulk'] ) {
			$disable_resubscribe = wc_clean( wp_unslash( $_REQUEST['_disable_resubscribe_bulk'] ) );
			update_post_meta( $product->get_id(), '_disable_resubscribe', $disable_resubscribe );

			// For variable products, update all variations.
			if ( $product->is_type( 'variable-subscription' ) ) {
				$variations = $product->get_children();
				foreach ( $variations as $variation_id ) {
					update_post_meta( $variation_id, '_disable_resubscribe', $disable_resubscribe );
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Log message to WooCommerce log.
	 *
	 * @param string $message Message to log.
	 */
	private function log_message( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug( $message, array( 'source' => 'rcwcs-product-control' ) );
		}
	}
}

// Initialize module.
// This is commented out because it will be initialized from the main plugin class based on settings.
// RCWCS_Product_Control::init(); 