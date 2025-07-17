<?php
/**
 * Plugin Name: Resubscribe Controls for WCSubs
 * Plugin URI: https://example.com/resubscribe-controls-for-wcsubs
 * Description: Advanced controls for WooCommerce Subscriptions resubscribe functionality.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: resubscribe-controls-for-wcsubs
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 * WC tested up to: 7.7
 * Requires Plugins: woocommerce, woocommerce-subscriptions
 * 
 * @package ResubscribeControlsWCS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if WooCommerce and WooCommerce Subscriptions are active.
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) || 
	 ! in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	add_action( 'admin_notices', 'rcwcs_missing_dependencies_notice' );
	return;
}

// Define plugin constants.
define( 'RCWCS_VERSION', '1.0.0' );
define( 'RCWCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RCWCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RCWCS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'RCWCS_PLUGIN_FILE', __FILE__ );

/**
 * Declare HPOS compatibility
 */
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', __FILE__, true );
		}
	}
);

/**
 * Display missing dependencies notice.
 */
function rcwcs_missing_dependencies_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Resubscribe Controls for WCSubs requires both WooCommerce and WooCommerce Subscriptions to be installed and active.', 'resubscribe-controls-for-wcsubs' ); ?></p>
	</div>
	<?php
}

/**
 * Create required directories on activation.
 */
function rcwcs_activate() {
	// Create log directory if it doesn't exist.
	$log_dir = WP_CONTENT_DIR . '/uploads/rcwcs-logs';
	
	if ( ! file_exists( $log_dir ) ) {
		wp_mkdir_p( $log_dir );
		
		// Create .htaccess file to protect logs.
		$htaccess_file = $log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = 'deny from all';
			file_put_contents( $htaccess_file, $htaccess_content );
		}
	}
	
	// Set initial options if they don't exist.
	if ( ! get_option( 'rcwcs_settings' ) ) {
		$default_settings = array(
			'stock_control_enabled'  => 'yes',
			'price_control_enabled'  => 'yes',
			'product_control_enabled' => 'yes',
			'notifications_enabled'  => 'yes',
			'analytics_enabled'      => 'no',
			'product_type_control'   => array( 'physical' ),
			'allow_trial_resubscribe' => 'no',
			'price_notification_type' => 'notice',
			'price_notice_text'      => __( 'Note: The price for this product has been updated to reflect the current price.', 'resubscribe-controls-for-wcsubs' ),
			'price_email_subject'    => __( 'Your resubscription price has been updated', 'resubscribe-controls-for-wcsubs' ),
			'price_email_heading'    => __( 'Subscription Price Update', 'resubscribe-controls-for-wcsubs' ),
			'price_email_content'    => __( "Hello {customer_name},\n\nThe price for your subscription to {product_name} has been updated from {old_price} to {new_price}.\n\nThis price will be applied to your resubscription and future renewal payments.\n\nThank you for your business.\n\n{site_title}", 'resubscribe-controls-for-wcsubs' ),
			'notification_locations' => array( 'cart_page', 'checkout_page' ),
		);
		
		// Use add_option instead of update_option for better performance - it won't autoload by default
		add_option( 'rcwcs_settings', $default_settings, '', 'yes' );
	}
}
register_activation_hook( __FILE__, 'rcwcs_activate' );

/**
 * Clean up on deactivation.
 */
function rcwcs_deactivate() {
	// Nothing to do yet.
}
register_deactivation_hook( __FILE__, 'rcwcs_deactivate' );

/**
 * Include required files.
 */
function rcwcs_init() {
	// Include settings class.
	require_once RCWCS_PLUGIN_DIR . 'includes/class-rcwcs-settings.php';
	
	// Include admin class.
	require_once RCWCS_PLUGIN_DIR . 'includes/admin/class-rcwcs-admin.php';
	
	// Load module classes
	require_once RCWCS_PLUGIN_DIR . 'includes/modules/class-rcwcs-stock-control.php';
	require_once RCWCS_PLUGIN_DIR . 'includes/modules/class-rcwcs-price-control.php';
	require_once RCWCS_PLUGIN_DIR . 'includes/modules/class-rcwcs-product-control.php';
	require_once RCWCS_PLUGIN_DIR . 'includes/modules/class-rcwcs-discount-control.php';

	// Initialize modules
	RCWCS_Stock_Control::get_instance();
	RCWCS_Price_Control::get_instance();
	RCWCS_Product_Control::get_instance();
	RCWCS_Discount_Control::get_instance();
}
add_action( 'plugins_loaded', 'rcwcs_init' );

/**
 * Enqueue admin scripts only for product pages
 */
function rcwcs_enqueue_product_admin_scripts( $hook ) {
	// Only load on product edit pages
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	
	// Check if we're editing a product
	global $post;
	if ( ! $post || 'product' !== $post->post_type ) {
		return;
	}
	
	// Enqueue product admin script
	wp_enqueue_script(
		'rcwcs-product-admin',
		RCWCS_PLUGIN_URL . 'assets/js/product-admin.js',
		array( 'jquery' ),
		RCWCS_VERSION,
		true
	);
	
	// Add script data
	wp_localize_script(
		'rcwcs-product-admin',
		'rcwcs_product_admin',
		array(
			'is_subscription_page' => true,
			'product_id' => $post->ID,
			'plugin_url' => RCWCS_PLUGIN_URL,
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'rcwcs_product_admin' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'rcwcs_enqueue_product_admin_scripts', 30 ); 