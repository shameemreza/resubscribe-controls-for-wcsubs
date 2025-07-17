<?php
/**
 * Settings class for Resubscribe Controls for WCSubs
 *
 * @package ResubscribeControlsWCS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RCWCS_Settings Class
 */
class RCWCS_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// Add settings link on plugins page.
		add_filter( 'plugin_action_links_' . RCWCS_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
		
		// Enqueue scripts and styles for settings page
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'rcwcs_settings',
			'rcwcs_settings',
			array( $this, 'sanitize_settings' )
		);

		// Add sections.
		add_settings_section(
			'rcwcs_general_section',
			__( 'General Settings', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'general_section_callback' ),
			'rcwcs_settings'
		);

		add_settings_section(
			'rcwcs_advanced_section',
			__( 'Advanced Settings', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'advanced_section_callback' ),
			'rcwcs_settings'
		);
		
		add_settings_section(
			'rcwcs_time_limitation_section',
			__( 'Resubscription Time Limitation', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'time_limitation_section_callback' ),
			'rcwcs_settings'
		);
		
		add_settings_section(
			'rcwcs_discount_section',
			__( 'Recurring Discount Control', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'discount_section_callback' ),
			'rcwcs_settings'
		);

		add_settings_section(
			'rcwcs_notification_section',
			__( 'Email Notification Settings', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'notification_section_callback' ),
			'rcwcs_settings'
		);

		// Add settings fields.
		$this->add_general_settings();
		$this->add_advanced_settings();
		$this->add_time_limitation_settings();
		$this->add_discount_settings();
		$this->add_notification_settings();
	}
	
	/**
	 * Add settings fields for the general section.
	 */
	private function add_general_settings() {
		// Stock Control settings.
		add_settings_field(
			'stock_control_enabled',
			__( 'Stock-Based Control', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_general_section',
			array(
				'id'          => 'stock_control_enabled',
				'description' => __( 'Enable stock-based resubscribe control (prevent resubscription to out-of-stock products)', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'yes',
			)
		);

		// Price Control settings.
		add_settings_field(
			'price_control_enabled',
			__( 'Price Control', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_general_section',
			array(
				'id'          => 'price_control_enabled',
				'description' => __( 'Enable price control (force resubscriptions to use current product prices)', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'yes',
			)
		);

		// Product Control settings.
		add_settings_field(
			'product_control_enabled',
			__( 'Product-Level Control', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_general_section',
			array(
				'id'          => 'product_control_enabled',
				'description' => __( 'Enable product-level control (configure resubscribe behavior per product)', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'yes',
			)
		);

		// Notification settings.
		add_settings_field(
			'notifications_enabled',
			__( 'Customer Notifications', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_general_section',
			array(
				'id'          => 'notifications_enabled',
				'description' => __( 'Enable customer notifications for price changes during resubscription', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'yes',
			)
		);

		// Analytics settings.
		add_settings_field(
			'analytics_enabled',
			__( 'Analytics', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_general_section',
			array(
				'id'          => 'analytics_enabled',
				'description' => __( 'Enable resubscription analytics tracking and reporting. When enabled, an Analytics tab will appear.', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'no',
			)
		);
	}
	
	/**
	 * Add settings fields for the advanced section.
	 */
	private function add_advanced_settings() {
		// Product type control
		add_settings_field(
			'product_type_control',
			__( 'Apply Stock Control To', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'multiple_checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_advanced_section',
			array(
				'id'          => 'product_type_control',
				'description' => __( 'Select which product types should have stock-based restrictions applied', 'resubscribe-controls-for-wcsubs' ),
				'options'     => array(
					'physical'     => __( 'Physical Products', 'resubscribe-controls-for-wcsubs' ),
					'virtual'      => __( 'Virtual Products', 'resubscribe-controls-for-wcsubs' ),
					'downloadable' => __( 'Downloadable Products', 'resubscribe-controls-for-wcsubs' ),
				),
				'defaults'    => array( 'physical' ),
			)
		);
		
		// Allow trial resubscribe setting.
		add_settings_field(
			'allow_trial_resubscribe',
			__( 'Trial Subscription Handling', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_advanced_section',
			array(
				'id'          => 'allow_trial_resubscribe',
				'description' => __( 'Allow resubscription for trial subscriptions even if they were cancelled.', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'no',
			)
		);
	}
	
	/**
	 * Add settings fields for the time limitation section.
	 */
	private function add_time_limitation_settings() {
		// Time limitation enable setting.
		add_settings_field(
			'time_limitation_enabled',
			__( 'Enable Time Limitation', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_time_limitation_section',
			array(
				'id'          => 'time_limitation_enabled',
				'description' => __( 'Limit the time period in which customers can resubscribe to a subscription.', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'no',
			)
		);

		// Time limitation period setting.
		add_settings_field(
			'time_limitation_days',
			__( 'Time Limitation Period', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'number_field_callback' ),
			'rcwcs_settings',
			'rcwcs_time_limitation_section',
			array(
				'id'          => 'time_limitation_days',
				'description' => __( 'Number of days after cancellation/expiration during which resubscription is allowed (0 = unlimited).', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 30,
				'min'         => 0,
				'max'         => 365,
				'step'        => 1,
			)
		);
		
		// Product-level override setting.
		add_settings_field(
			'time_limitation_product_override',
			__( 'Allow Product-Level Overrides', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_time_limitation_section',
			array(
				'id'          => 'time_limitation_product_override',
				'description' => __( 'Allow individual products to override the global time limitation settings.', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'yes',
			)
		);
		
		// Show countdown setting.
		add_settings_field(
			'time_limitation_show_countdown',
			__( 'Show Countdown', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_time_limitation_section',
			array(
				'id'          => 'time_limitation_show_countdown',
				'description' => __( 'Show a countdown timer to customers on their account page indicating how long they have to resubscribe.', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'yes',
			)
		);
	}
	
	/**
	 * Discount section callback.
	 */
	public function discount_section_callback() {
		echo '<p>' . esc_html__( 'Configure discounts for customers who resubscribe to cancelled or expired subscriptions.', 'resubscribe-controls-for-wcsubs' ) . '</p>';
	}
	
	/**
	 * Add settings fields for the discount section.
	 */
	private function add_discount_settings() {
		// Enable discount control
		add_settings_field(
			'discount_control_enabled',
			__( 'Enable Discount Control', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_discount_section',
			array(
				'id'          => 'discount_control_enabled',
				'description' => __( 'Apply special discounts to incentivize customers to resubscribe after cancellation.', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'no',
			)
		);
		
		// Discount type
		add_settings_field(
			'discount_type',
			__( 'Discount Type', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'select_field_callback' ),
			'rcwcs_settings',
			'rcwcs_discount_section',
			array(
				'id'          => 'discount_type',
				'description' => __( 'Select how the discount will be applied.', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'percentage',
				'options'     => array(
					'percentage' => __( 'Percentage Discount (%)', 'resubscribe-controls-for-wcsubs' ),
					'fixed'      => __( 'Fixed Amount Discount', 'resubscribe-controls-for-wcsubs' ),
				),
			)
		);
		
		// Discount amount
		add_settings_field(
			'discount_amount',
			__( 'Discount Amount', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'number_field_callback' ),
			'rcwcs_settings',
			'rcwcs_discount_section',
			array(
				'id'          => 'discount_amount',
				'description' => __( 'Amount to discount (either percentage or fixed amount depending on discount type).', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 10,
				'min'         => 0,
				'max'         => 100,
				'step'        => 'percentage' === $this->get_setting( 'discount_type', 'percentage' ) ? 1 : 0.01,
			)
		);
		
		// Discount application
		add_settings_field(
			'discount_application',
			__( 'Apply Discount To', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'select_field_callback' ),
			'rcwcs_settings',
			'rcwcs_discount_section',
			array(
				'id'          => 'discount_application',
				'description' => __( 'Specify when the discount should be applied.', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'first_payment',
				'options'     => array(
					'first_payment'  => __( 'First Payment Only', 'resubscribe-controls-for-wcsubs' ),
					'all_payments'   => __( 'All Subscription Payments', 'resubscribe-controls-for-wcsubs' ),
					'limited'        => __( 'Limited Number of Payments', 'resubscribe-controls-for-wcsubs' ),
				),
			)
		);
		
		// Number of payments to apply discount to
		add_settings_field(
			'discount_payment_count',
			__( 'Number of Payments', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'number_field_callback' ),
			'rcwcs_settings',
			'rcwcs_discount_section',
			array(
				'id'          => 'discount_payment_count',
				'description' => __( 'Number of payments to apply the discount to (if "Limited Number of Payments" is selected).', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 3,
				'min'         => 1,
				'max'         => 36,
				'step'        => 1,
			)
		);
		
		// Product-level override
		add_settings_field(
			'discount_product_override',
			__( 'Allow Product-Level Overrides', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_discount_section',
			array(
				'id'          => 'discount_product_override',
				'description' => __( 'Allow individual products to override the global discount settings.', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'yes',
			)
		);
		
		// Maximum discount usage
		add_settings_field(
			'discount_max_usage',
			__( 'Maximum Discount Usage', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'number_field_callback' ),
			'rcwcs_settings',
			'rcwcs_discount_section',
			array(
				'id'          => 'discount_max_usage',
				'description' => __( 'Maximum number of times a customer can receive the resubscribe discount (0 = unlimited).', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 1,
				'min'         => 0,
				'max'         => 100,
				'step'        => 1,
			)
		);
	}
	
	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Setting value.
	 */
	private function get_setting( $key, $default = '' ) {
		$settings = get_option( 'rcwcs_settings', array() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
	
	/**
	 * Heading callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function heading_callback( $args ) {
		echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
	}
	
	/**
	 * Divider callback.
	 */
	public function divider_callback() {
		echo '<hr style="border: 0; height: 1px; background-color: #ddd; margin: 20px 0;">';
	}
	
	/**
	 * Add settings fields for the notification section.
	 */
	private function add_notification_settings() {
		// Price increase notification type - moved from advanced section to notification section
		add_settings_field(
			'price_notification_type',
			__( 'Price Change Notification', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'select_field_callback' ),
			'rcwcs_settings',
			'rcwcs_notification_section',
			array(
				'id'          => 'price_notification_type',
				'description' => __( 'How to notify customers about price changes during resubscription', 'resubscribe-controls-for-wcsubs' ),
				'default'     => 'notice',
				'options'     => array(
					'notice'   => __( 'WooCommerce Notice', 'resubscribe-controls-for-wcsubs' ),
					'email'    => __( 'Email Notification', 'resubscribe-controls-for-wcsubs' ),
					'both'     => __( 'Both Notice and Email', 'resubscribe-controls-for-wcsubs' ),
					'none'     => __( 'No Notification', 'resubscribe-controls-for-wcsubs' ),
				),
			)
		);

		// Display locations
		add_settings_field(
			'notification_locations',
			__( 'Display Notifications On', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'multiple_checkbox_field_callback' ),
			'rcwcs_settings',
			'rcwcs_notification_section',
			array(
				'id'          => 'notification_locations',
				'description' => __( 'Where to display price change notifications', 'resubscribe-controls-for-wcsubs' ),
				'options'     => array(
					'cart_page'       => __( 'Cart Page', 'resubscribe-controls-for-wcsubs' ),
					'checkout_page'   => __( 'Checkout Page', 'resubscribe-controls-for-wcsubs' ),
					'order_details'   => __( 'Order Details Page', 'resubscribe-controls-for-wcsubs' ),
					'my_account_page' => __( 'My Account Page', 'resubscribe-controls-for-wcsubs' ),
				),
				'defaults'    => array( 'cart_page', 'checkout_page' ),
			)
		);

		// Price notice text field
		add_settings_field(
			'price_notice_text',
			__( 'Price Change Notice Text', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'text_field_callback' ),
			'rcwcs_settings',
			'rcwcs_notification_section',
			array(
				'id'          => 'price_notice_text',
				'description' => __( 'Text displayed when product price has changed for resubscription', 'resubscribe-controls-for-wcsubs' ),
				'default'     => __( 'Note: The price for this product has been updated to reflect the current price.', 'resubscribe-controls-for-wcsubs' ),
			)
		);

		// Email subject field
		add_settings_field(
			'price_email_subject',
			__( 'Price Change Email Subject', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'text_field_callback' ),
			'rcwcs_settings',
			'rcwcs_notification_section',
			array(
				'id'          => 'price_email_subject',
				'description' => __( 'Subject line for price change notification emails', 'resubscribe-controls-for-wcsubs' ),
				'default'     => __( 'Your resubscription price has been updated', 'resubscribe-controls-for-wcsubs' ),
			)
		);

		// Email heading field
		add_settings_field(
			'price_email_heading',
			__( 'Price Change Email Heading', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'text_field_callback' ),
			'rcwcs_settings',
			'rcwcs_notification_section',
			array(
				'id'          => 'price_email_heading',
				'description' => __( 'Heading for price change notification emails', 'resubscribe-controls-for-wcsubs' ),
				'default'     => __( 'Subscription Price Update', 'resubscribe-controls-for-wcsubs' ),
			)
		);

		// Email content field
		add_settings_field(
			'price_email_content',
			__( 'Price Change Email Content', 'resubscribe-controls-for-wcsubs' ),
			array( $this, 'textarea_field_callback' ),
			'rcwcs_settings',
			'rcwcs_notification_section',
			array(
				'id'          => 'price_email_content',
				'description' => __( 'Content for price change notification emails. You can use HTML and the following placeholders: {product_name}, {old_price}, {new_price}, {customer_name}, {order_number}, {site_title}, {site_url}', 'resubscribe-controls-for-wcsubs' ),
				'default'     => __( "Hello {customer_name},\n\nThe price for your subscription to {product_name} has been updated from {old_price} to {new_price}.\n\nThis price will be applied to your resubscription and future renewal payments.\n\nThank you for your business.\n\n{site_title}", 'resubscribe-controls-for-wcsubs' ),
			)
		);
	}

	/**
	 * General section callback.
	 */
	public function general_section_callback() {
		echo '<p>' . esc_html__( 'Configure the main features of Resubscribe Controls for WCSubs.', 'resubscribe-controls-for-wcsubs' ) . '</p>';
	}

	/**
	 * Advanced section callback.
	 */
	public function advanced_section_callback() {
		echo '<p>' . esc_html__( 'Fine-tune advanced settings for resubscription behavior.', 'resubscribe-controls-for-wcsubs' ) . '</p>';
	}

	/**
	 * Time Limitation section callback.
	 */
	public function time_limitation_section_callback() {
		echo '<p>' . esc_html__( 'Control how long customers have to resubscribe after cancellation or expiration.', 'resubscribe-controls-for-wcsubs' ) . '</p>';
	}

	/**
	 * Notification section callback.
	 */
	public function notification_section_callback() {
		echo '<p>' . esc_html__( 'Configure how customers are notified about price changes during resubscription.', 'resubscribe-controls-for-wcsubs' ) . '</p>';
	}

	/**
	 * Checkbox field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function checkbox_field_callback( $args ) {
		$option_name = 'rcwcs_settings';
		$options     = get_option( $option_name );
		$id          = $args['id'];
		$default     = isset( $args['default'] ) ? $args['default'] : 'no';
		$value       = isset( $options[ $id ] ) ? $options[ $id ] : $default;
		$class       = isset( $args['class'] ) ? ' ' . $args['class'] : '';
		
		?>
		<fieldset class="<?php echo esc_attr( $class ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $id ); ?>]" value="yes" <?php checked( $value, 'yes' ); ?> />
				<?php echo wp_kses_post( $args['description'] ); ?>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Select field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function select_field_callback( $args ) {
		$option_name = 'rcwcs_settings';
		$options     = get_option( $option_name );
		$id          = $args['id'];
		$default     = isset( $args['default'] ) ? $args['default'] : '';
		$value       = isset( $options[ $id ] ) ? $options[ $id ] : $default;
		$class       = isset( $args['class'] ) ? ' ' . $args['class'] : '';
		
		?>
		<fieldset class="<?php echo esc_attr( $class ); ?>">
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $id ); ?>]">
				<?php foreach ( $args['options'] as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<p class="description"><?php echo wp_kses_post( $args['description'] ); ?></p>
			<?php endif; ?>
		</fieldset>
		<?php
	}

	/**
	 * Number field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function number_field_callback( $args ) {
		$option_name = 'rcwcs_settings';
		$options     = get_option( $option_name );
		$id          = $args['id'];
		$default     = isset( $args['default'] ) ? $args['default'] : '';
		$value       = isset( $options[ $id ] ) ? $options[ $id ] : $default;
		$min         = isset( $args['min'] ) ? ' min="' . $args['min'] . '"' : '';
		$max         = isset( $args['max'] ) ? ' max="' . $args['max'] . '"' : '';
		$step        = isset( $args['step'] ) ? ' step="' . $args['step'] . '"' : '';
		$class       = isset( $args['class'] ) ? ' ' . $args['class'] : '';
		
		?>
		<fieldset class="<?php echo esc_attr( $class ); ?>">
			<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $value ); ?>"<?php echo $min . $max . $step; ?> />
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<p class="description"><?php echo wp_kses_post( $args['description'] ); ?></p>
			<?php endif; ?>
		</fieldset>
		<?php
	}

	/**
	 * Text field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function text_field_callback( $args ) {
		$settings = get_option( 'rcwcs_settings', array() );
		$id       = $args['id'];
		$default  = isset( $args['default'] ) ? $args['default'] : '';
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : $default;

		?>
		<input type="text" id="<?php echo esc_attr( $id ); ?>" name="rcwcs_settings[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Textarea field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function textarea_field_callback( $args ) {
		$settings = get_option( 'rcwcs_settings', array() );
		$id       = $args['id'];
		$default  = isset( $args['default'] ) ? $args['default'] : '';
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : $default;

		// Use rich editor for email content
		if ( 'price_email_content' === $id ) {
			$editor_settings = array(
				'textarea_name' => 'rcwcs_settings[' . esc_attr( $id ) . ']',
				'textarea_rows' => 10,
				'media_buttons' => true,
				'teeny'         => false,
				'quicktags'     => true,
			);
			wp_editor( wp_kses_post( $value ), 'rcwcs_' . esc_attr( $id ), $editor_settings );
		} else {
			?>
			<textarea id="<?php echo esc_attr( $id ); ?>" name="rcwcs_settings[<?php echo esc_attr( $id ); ?>]" rows="5" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
			<?php
		}
		?>
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php
	}

	/**
	 * Multiple checkbox field callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function multiple_checkbox_field_callback( $args ) {
		$option_name = 'rcwcs_settings';
		$options     = get_option( $option_name );
		$id          = $args['id'];
		$defaults    = isset( $args['defaults'] ) ? $args['defaults'] : array();
		$values      = isset( $options[ $id ] ) ? $options[ $id ] : $defaults;
		$class       = isset( $args['class'] ) ? ' ' . $args['class'] : '';
		
		?>
		<fieldset class="<?php echo esc_attr( $class ); ?>">
			<?php foreach ( $args['options'] as $key => $label ) : ?>
				<label for="<?php echo esc_attr( $id . '_' . $key ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id . '_' . $key ); ?>" name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $id ); ?>][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $values ), true ); ?> />
					<?php echo esc_html( $label ); ?>
				</label><br>
			<?php endforeach; ?>
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<p class="description"><?php echo wp_kses_post( $args['description'] ); ?></p>
			<?php endif; ?>
		</fieldset>
		<?php
	}

	/**
	 * Header callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function header_callback( $args ) {
		echo '<p class="description" style="margin-bottom: 12px; font-style: italic; max-width: 800px;">' . esc_html( $args['description'] ) . '</p>';
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Checkbox fields.
		$checkbox_fields = array(
			'stock_control_enabled',
			'price_control_enabled',
			'product_control_enabled',
			'notifications_enabled',
			'analytics_enabled',
			'allow_trial_resubscribe',
			'time_limitation_enabled',
			'time_limitation_product_override',
			'time_limitation_show_countdown',
			'discount_control_enabled',
			'discount_product_override',
		);

		foreach ( $checkbox_fields as $field ) {
			$sanitized[ $field ] = isset( $input[ $field ] ) ? 'yes' : 'no';
		}

		// Select fields.
		if ( isset( $input['price_notification_type'] ) ) {
			$valid_options = array( 'notice', 'email', 'both', 'none' );
			$sanitized['price_notification_type'] = in_array( $input['price_notification_type'], $valid_options, true ) ? $input['price_notification_type'] : 'notice';
		}

		if ( isset( $input['discount_type'] ) ) {
			$valid_options = array( 'percentage', 'fixed' );
			$sanitized['discount_type'] = in_array( $input['discount_type'], $valid_options, true ) ? $input['discount_type'] : 'percentage';
		}

		if ( isset( $input['discount_application'] ) ) {
			$valid_options = array( 'first_payment', 'all_payments', 'limited' );
			$sanitized['discount_application'] = in_array( $input['discount_application'], $valid_options, true ) ? $input['discount_application'] : 'first_payment';
		}

		// Number fields.
		if ( isset( $input['time_limitation_days'] ) ) {
			$sanitized['time_limitation_days'] = intval( $input['time_limitation_days'] );
		}

		if ( isset( $input['discount_amount'] ) ) {
			$sanitized['discount_amount'] = floatval( $input['discount_amount'] );
		}

		if ( isset( $input['discount_payment_count'] ) ) {
			$sanitized['discount_payment_count'] = intval( $input['discount_payment_count'] );
		}

		if ( isset( $input['discount_max_usage'] ) ) {
			$sanitized['discount_max_usage'] = intval( $input['discount_max_usage'] );
		}

		// Text fields.
		$text_fields = array(
			'price_notice_text',
			'price_email_subject',
			'price_email_heading',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}

		// Textarea fields.
		if ( isset( $input['price_email_content'] ) ) {
			$sanitized['price_email_content'] = wp_kses_post( $input['price_email_content'] );
		}

		// Multiple checkbox fields.
		if ( isset( $input['notification_locations'] ) && is_array( $input['notification_locations'] ) ) {
			$valid_locations = array( 'cart_page', 'checkout_page', 'order_details', 'my_account_page' );
			$sanitized['notification_locations'] = array_intersect( $input['notification_locations'], $valid_locations );
		} else {
			$sanitized['notification_locations'] = array();
		}

		// Add a settings updated message
		add_settings_error(
			'rcwcs_settings',
			'rcwcs_settings_updated',
			__( 'Settings saved successfully.', 'resubscribe-controls-for-wcsubs' ),
			'success'
		);

		return $sanitized;
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		// Check user permissions.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'resubscribe-controls-for-wcsubs' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Resubscribe Controls for WCSubs Settings', 'resubscribe-controls-for-wcsubs' ); ?></h1>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( 'rcwcs_settings' );
				do_settings_sections( 'rcwcs_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add settings link to plugin actions.
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=rcwcs-admin&tab=settings' ) . '">' . esc_html__( 'Settings', 'resubscribe-controls-for-wcsubs' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Enqueue scripts and styles for the settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only enqueue on our settings page
		if ( 'woocommerce_page_rcwcs-admin' !== $hook ) {
			return;
		}
		
		// Check if we're on the settings tab
		if ( ! isset( $_GET['tab'] ) || 'settings' === $_GET['tab'] ) {
			wp_enqueue_editor();
			wp_enqueue_media();
			
			// Add custom styling for the editor
			wp_add_inline_style( 'wp-editor', '
				.wp-editor-container {
					border: 1px solid #ddd;
				}
				.wp-editor-area {
					min-height: 200px;
				}
			' );
		}
	}
}

// Initialize settings.
new RCWCS_Settings(); 