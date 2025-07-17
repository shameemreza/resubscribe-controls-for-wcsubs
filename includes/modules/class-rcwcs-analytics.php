<?php
/**
 * Analytics Module
 *
 * @package ResubscribeControlsWCS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RCWCS_Analytics Class
 */
class RCWCS_Analytics {

	/**
	 * Module instance.
	 *
	 * @var RCWCS_Analytics
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

		// Ensure the database table exists.
		$this->maybe_create_table();

		// Track resubscription data.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_resubscription_order' ), 10, 3 );
		
		// Add AJAX handlers for analytics.
		add_action( 'wp_ajax_rcwcs_get_analytics', array( $this, 'ajax_get_analytics' ) );
		add_action( 'wp_ajax_rcwcs_export_analytics', array( $this, 'ajax_export_analytics' ) );
		add_action( 'wp_ajax_rcwcs_get_chart_data', array( $this, 'ajax_get_chart_data' ) );
		
		// Enqueue analytics scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_analytics_scripts' ) );
	}

	/**
	 * Check if the database table exists and create it if not.
	 */
	private function maybe_create_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rcwcs_analytics';
		
		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			$this->create_table();
		}
	}

	/**
	 * Create the database table.
	 */
	private function create_table() {
		global $wpdb;

		// Log that we're attempting to create the table
		error_log('RCWCS: Attempting to create analytics table');

		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'rcwcs_analytics';

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			subscription_id bigint(20) NOT NULL,
			customer_id bigint(20) NOT NULL,
			product_id bigint(20) NOT NULL,
			original_price decimal(19,4) NOT NULL,
			new_price decimal(19,4) NOT NULL,
			price_difference decimal(19,4) NOT NULL,
			resubscribe_date datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// Include WordPress database upgrade API
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		// Execute the SQL with dbDelta
		$result = dbDelta( $sql );
		
		// Log the result for debugging
		error_log('RCWCS: Table creation result: ' . print_r($result, true));
		
		// Double-check the table was created
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
		error_log('RCWCS: Table exists after creation: ' . ($table_exists ? 'Yes' : 'No'));
		
		// If table creation failed, throw an exception to be caught by our error handler
		if (!$table_exists) {
			throw new Exception('Failed to create database table: ' . $table_name);
		}
		
		return $table_exists;
	}

	/**
	 * Get module instance.
	 *
	 * @return RCWCS_Analytics
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
		return self::get_instance();
	}

	/**
	 * Track resubscription order.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $posted_data Posted data.
	 * @param WC_Order $order Order object.
	 */
	public function track_resubscription_order( $order_id, $posted_data, $order ) {
		try {
			// Check if this is a resubscribe order.
			if ( ! function_exists( 'wcs_order_contains_resubscribe' ) || ! wcs_order_contains_resubscribe( $order ) ) {
				return;
			}

			// Get the subscription IDs for the resubscribe order.
			if ( ! function_exists( 'wcs_get_subscriptions_for_resubscribe_order' ) ) {
				error_log( 'RCWCS Analytics: wcs_get_subscriptions_for_resubscribe_order function not available' );
				return;
			}
			
			$subscription_ids = wcs_get_subscriptions_for_resubscribe_order( $order );

			if ( empty( $subscription_ids ) ) {
				error_log( 'RCWCS Analytics: No subscription IDs found for resubscribe order #' . $order->get_id() );
				return;
			}

			// Track each subscription.
			foreach ( $subscription_ids as $subscription_id => $subscription ) {
				$this->track_resubscription( $subscription, $order );
			}
			
			// Log successful tracking
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'RCWCS Analytics: Successfully tracked resubscription for order #' . $order->get_id() );
			}
		} catch ( Exception $e ) {
			error_log( sprintf(
				'RCWCS Analytics: Exception in track_resubscription_order: %s in %s on line %d',
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			));
		}
	}

	/**
	 * Track resubscription.
	 *
	 * @param WC_Subscription $subscription Subscription object.
	 * @param WC_Order        $order Order object.
	 */
	private function track_resubscription( $subscription, $order ) {
		global $wpdb;

		try {
			// Validate input objects
			if ( ! is_a( $subscription, 'WC_Subscription' ) || ! is_a( $order, 'WC_Order' ) ) {
				error_log( 'RCWCS Analytics: Invalid subscription or order object provided to track_resubscription' );
				return;
			}

			// Get customer ID.
			$customer_id = $order->get_customer_id();

			if ( empty( $customer_id ) ) {
				error_log( 'RCWCS Analytics: No customer ID found for order #' . $order->get_id() );
				return;
			}

			// Loop through order items.
			foreach ( $order->get_items() as $item ) {
				// Check if this item has price update metadata.
				$price_updated = $item->get_meta( '_price_updated' ) === 'yes';
				$original_price = $item->get_meta( '_original_price' );
				$new_price = $item->get_subtotal() / $item->get_quantity();
				$product_id = $item->get_product_id();
				$variation_id = $item->get_variation_id();

				// Use variation ID if available.
				if ( $variation_id ) {
					$product_id = $variation_id;
				}

				// Skip if we don't have both prices.
				if ( ! $price_updated || empty( $original_price ) ) {
					continue;
				}

				// Calculate price difference.
				$price_difference = $new_price - (float) $original_price;

				// Insert into database.
				$result = $wpdb->insert(
					$wpdb->prefix . 'rcwcs_analytics',
					array(
						'subscription_id'  => $subscription->get_id(),
						'customer_id'      => $customer_id,
						'product_id'       => $product_id,
						'original_price'   => $original_price,
						'new_price'        => $new_price,
						'price_difference' => $price_difference,
						'resubscribe_date' => current_time( 'mysql' ),
					),
					array(
						'%d',
						'%d',
						'%d',
						'%f',
						'%f',
						'%f',
						'%s',
					)
				);
				
				if ( false === $result ) {
					error_log( sprintf(
						'RCWCS Analytics: Database error inserting analytics record: %s',
						$wpdb->last_error
					));
				} else if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 
						'RCWCS: Tracked resubscription for subscription #%d, product #%d, price change from %f to %f',
						$subscription->get_id(),
						$product_id,
						$original_price,
						$new_price
					));
				}
			}
		} catch ( Exception $e ) {
			error_log( sprintf(
				'RCWCS Analytics: Exception in track_resubscription: %s in %s on line %d',
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			));
		}
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

	/**
	 * Get subscription ID or object based on HPOS compatibility
	 * 
	 * @param int $subscription_id Subscription ID
	 * @return WC_Subscription|false Subscription object or false
	 */
	private function get_subscription_by_id( $subscription_id ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return false;
		}
		
		return wcs_get_subscription( $subscription_id );
	}

	/**
	 * Render analytics page.
	 */
	public function render_analytics_page() {
		try {
			// Ensure database table exists
			$this->maybe_create_table();
			
			// Get analytics data.
			$data = $this->get_analytics_data();

			// Render page.
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Resubscribe Analytics', 'resubscribe-controls-for-wcsubs' ); ?></h1>

				<?php if ( empty( $data['recent_resubscriptions'] ) ) : ?>
					<!-- Show message if no data exists -->
					<div class="notice notice-info">
						<p><?php esc_html_e( 'No resubscription data found. This will be populated as customers resubscribe to products.', 'resubscribe-controls-for-wcsubs' ); ?></p>
					</div>
				<?php endif; ?>
				
				<div class="rcwcs-date-filter" id="rcwcs-date-filter">
					<h2><?php esc_html_e( 'Filter by Date', 'resubscribe-controls-for-wcsubs' ); ?></h2>
					<div class="rcwcs-date-inputs">
						<label>
							<?php esc_html_e( 'From:', 'resubscribe-controls-for-wcsubs' ); ?>
							<input type="date" id="rcwcs-date-start" name="start_date" value="<?php echo esc_attr( date( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>">
						</label>
						<label>
							<?php esc_html_e( 'To:', 'resubscribe-controls-for-wcsubs' ); ?>
							<input type="date" id="rcwcs-date-end" name="end_date" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
						</label>
						<button type="button" id="rcwcs-date-filter-submit" class="button"><?php esc_html_e( 'Apply Filter', 'resubscribe-controls-for-wcsubs' ); ?></button>
					</div>
				</div>

				<div class="rcwcs-analytics-dashboard">
					<div class="rcwcs-analytics-card">
						<h2><?php esc_html_e( 'Resubscriptions', 'resubscribe-controls-for-wcsubs' ); ?></h2>
						<div class="rcwcs-analytics-count"><?php echo esc_html( $data['total_resubscriptions'] ); ?></div>
					</div>

					<div class="rcwcs-analytics-card">
						<h2><?php esc_html_e( 'Price Updates', 'resubscribe-controls-for-wcsubs' ); ?></h2>
						<div class="rcwcs-analytics-count"><?php echo esc_html( $data['total_price_updates'] ); ?></div>
					</div>

					<div class="rcwcs-analytics-card">
						<h2><?php esc_html_e( 'Average Price Increase', 'resubscribe-controls-for-wcsubs' ); ?></h2>
						<div class="rcwcs-analytics-count"><?php echo wc_price( $data['avg_price_increase'] ); ?></div>
					</div>
				</div>

				<div class="rcwcs-analytics-charts">
					<div class="rcwcs-chart-container">
						<h2><?php esc_html_e( 'Price Change History', 'resubscribe-controls-for-wcsubs' ); ?></h2>
						<canvas id="rcwcs-price-chart"></canvas>
					</div>
					
					<div class="rcwcs-chart-container">
						<h2><?php esc_html_e( 'Resubscriptions by Product', 'resubscribe-controls-for-wcsubs' ); ?></h2>
						<canvas id="rcwcs-product-chart"></canvas>
					</div>
				</div>

				<div class="rcwcs-analytics-tables">
					<h2><?php esc_html_e( 'Recent Resubscriptions', 'resubscribe-controls-for-wcsubs' ); ?></h2>
					
					<?php if ( empty( $data['recent_resubscriptions'] ) ) : ?>
						<p><?php esc_html_e( 'No resubscriptions found.', 'resubscribe-controls-for-wcsubs' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'resubscribe-controls-for-wcsubs' ); ?></th>
									<th><?php esc_html_e( 'Subscription', 'resubscribe-controls-for-wcsubs' ); ?></th>
									<th><?php esc_html_e( 'Customer', 'resubscribe-controls-for-wcsubs' ); ?></th>
									<th><?php esc_html_e( 'Product', 'resubscribe-controls-for-wcsubs' ); ?></th>
									<th><?php esc_html_e( 'Original Price', 'resubscribe-controls-for-wcsubs' ); ?></th>
									<th><?php esc_html_e( 'New Price', 'resubscribe-controls-for-wcsubs' ); ?></th>
									<th><?php esc_html_e( 'Difference', 'resubscribe-controls-for-wcsubs' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $data['recent_resubscriptions'] as $item ) : ?>
									<tr>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->resubscribe_date ) ) ); ?></td>
										<td>
											<?php 
											// Get HPOS compatible subscription URL
											$subscription_url = $this->get_subscription_edit_url( $item->subscription_id );
											?>
											<a href="<?php echo esc_url( $subscription_url ); ?>">#<?php echo esc_html( $item->subscription_id ); ?></a>
										</td>
										<td>
											<?php
											if ( class_exists( 'WC_Customer' ) ) {
												try {
													$customer = new WC_Customer( $item->customer_id );
													echo esc_html( $customer->get_first_name() . ' ' . $customer->get_last_name() );
												} catch ( Exception $e ) {
													echo esc_html__( 'Customer not found', 'resubscribe-controls-for-wcsubs' );
												}
											} else {
												echo esc_html__( 'Customer ID: ', 'resubscribe-controls-for-wcsubs' ) . esc_html( $item->customer_id );
											}
											?>
										</td>
										<td>
											<?php
											$product = wc_get_product( $item->product_id );
											if ( $product ) {
												echo esc_html( $product->get_name() );
											} else {
												echo esc_html__( 'Product not found', 'resubscribe-controls-for-wcsubs' );
											}
											?>
										</td>
										<td><?php echo wc_price( $item->original_price ); ?></td>
										<td><?php echo wc_price( $item->new_price ); ?></td>
										<td>
											<?php
											$diff_class = $item->price_difference >= 0 ? 'price-increase' : 'price-decrease';
											echo '<span class="' . esc_attr( $diff_class ) . '">' . wc_price( $item->price_difference ) . '</span>';
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<div class="rcwcs-analytics-export">
					<h2><?php esc_html_e( 'Export Data', 'resubscribe-controls-for-wcsubs' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
						<input type="hidden" name="action" value="rcwcs_export_analytics" />
						<?php wp_nonce_field( 'rcwcs_export_analytics', 'rcwcs_export_nonce' ); ?>
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Export to CSV', 'resubscribe-controls-for-wcsubs' ); ?>
						</button>
					</form>
				</div>
			</div>
			<?php
		} catch ( Exception $e ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Resubscribe Analytics', 'resubscribe-controls-for-wcsubs' ); ?></h1>
				
				<div class="notice notice-error">
					<p><?php esc_html_e( 'An error occurred while loading analytics:', 'resubscribe-controls-for-wcsubs' ); ?> <?php echo esc_html( $e->getMessage() ); ?></p>
					<p><strong><?php esc_html_e( 'Error Details:', 'resubscribe-controls-for-wcsubs' ); ?></strong> <?php echo esc_html( sprintf( '%s in %s on line %d', $e->getMessage(), $e->getFile(), $e->getLine() ) ); ?></p>
				</div>
				
				<div class="notice notice-info">
					<p><?php esc_html_e( 'Troubleshooting steps:', 'resubscribe-controls-for-wcsubs' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'Check that the database table exists. You can try deactivating and reactivating the plugin.', 'resubscribe-controls-for-wcsubs' ); ?></li>
						<li><?php esc_html_e( 'Ensure WooCommerce is properly installed and activated.', 'resubscribe-controls-for-wcsubs' ); ?></li>
						<li><?php esc_html_e( 'Check PHP error logs for more details.', 'resubscribe-controls-for-wcsubs' ); ?></li>
					</ol>
				</div>
				
				<div class="notice notice-warning">
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rcwcs-admin&tab=settings' ) ); ?>" class="button"><?php esc_html_e( 'Go to Settings', 'resubscribe-controls-for-wcsubs' ); ?></a>
					</p>
				</div>
			</div>
			<?php
			
			// Log detailed error information
			error_log( sprintf( 'RCWCS Analytics Error: %s in %s on line %d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
		} catch ( Error $e ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Resubscribe Analytics', 'resubscribe-controls-for-wcsubs' ); ?></h1>
				
				<div class="notice notice-error">
					<p><?php esc_html_e( 'A critical error occurred while loading analytics:', 'resubscribe-controls-for-wcsubs' ); ?> <?php echo esc_html( $e->getMessage() ); ?></p>
					<p><strong><?php esc_html_e( 'Error Details:', 'resubscribe-controls-for-wcsubs' ); ?></strong> <?php echo esc_html( sprintf( '%s in %s on line %d', $e->getMessage(), $e->getFile(), $e->getLine() ) ); ?></p>
				</div>
				
				<div class="notice notice-info">
					<p><?php esc_html_e( 'Troubleshooting steps:', 'resubscribe-controls-for-wcsubs' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'Check that all required plugin files are present.', 'resubscribe-controls-for-wcsubs' ); ?></li>
						<li><?php esc_html_e( 'Try deactivating and reactivating the plugin.', 'resubscribe-controls-for-wcsubs' ); ?></li>
						<li><?php esc_html_e( 'Ensure your PHP version meets the minimum requirements (7.4+).', 'resubscribe-controls-for-wcsubs' ); ?></li>
					</ol>
				</div>
				
				<div class="notice notice-warning">
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rcwcs-admin&tab=settings' ) ); ?>" class="button"><?php esc_html_e( 'Go to Settings', 'resubscribe-controls-for-wcsubs' ); ?></a>
					</p>
				</div>
			</div>
			<?php
			
			// Log detailed error information
			error_log( sprintf( 'RCWCS Analytics Critical Error: %s in %s on line %d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
		}
	}

	/**
	 * Get analytics data.
	 *
	 * @return array Analytics data.
	 */
	private function get_analytics_data() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rcwcs_analytics';
		
		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			$this->create_table();
			return array(
				'total_resubscriptions'  => 0,
				'total_price_updates'    => 0,
				'avg_price_increase'     => 0,
				'recent_resubscriptions' => array(),
			);
		}

		// Get total resubscriptions.
		$total_resubscriptions = $wpdb->get_var( "SELECT COUNT(DISTINCT subscription_id) FROM $table_name" );

		// Get total price updates.
		$total_price_updates = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

		// Get average price increase.
		$avg_price_increase = $wpdb->get_var( "SELECT AVG(price_difference) FROM $table_name" );

		// Get recent resubscriptions.
		$recent_resubscriptions = $wpdb->get_results(
			"SELECT * FROM $table_name ORDER BY resubscribe_date DESC LIMIT 10"
		);

		return array(
			'total_resubscriptions'  => $total_resubscriptions ? $total_resubscriptions : 0,
			'total_price_updates'    => $total_price_updates ? $total_price_updates : 0,
			'avg_price_increase'     => $avg_price_increase ? $avg_price_increase : 0,
			'recent_resubscriptions' => $recent_resubscriptions ? $recent_resubscriptions : array(),
		);
	}

	/**
	 * AJAX handler for getting analytics data.
	 */
	public function ajax_get_analytics() {
		check_ajax_referer( 'rcwcs-admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to access this data.', 'resubscribe-controls-for-wcsubs' ) ) );
		}

		$data = $this->get_analytics_data();
		
		// Process the recent resubscriptions to add customer and product names
		if ( ! empty( $data['recent_resubscriptions'] ) ) {
			foreach ( $data['recent_resubscriptions'] as &$item ) {
				// Format the resubscribe date
				$item->resubscribe_date = date_i18n( get_option( 'date_format' ), strtotime( $item->resubscribe_date ) );
				
				// Add customer name
				try {
					if ( class_exists( 'WC_Customer' ) ) {
						$customer = new WC_Customer( $item->customer_id );
						$item->customer_name = $customer->get_first_name() . ' ' . $customer->get_last_name();
						if ( empty( trim( $item->customer_name ) ) ) {
							// Fallback to user data if WC_Customer doesn't have a name
							$user = get_userdata( $item->customer_id );
							$item->customer_name = $user ? $user->display_name : __( 'Customer #', 'resubscribe-controls-for-wcsubs' ) . $item->customer_id;
						}
					} else {
						$user = get_userdata( $item->customer_id );
						$item->customer_name = $user ? $user->display_name : __( 'Customer #', 'resubscribe-controls-for-wcsubs' ) . $item->customer_id;
					}
				} catch ( Exception $e ) {
					// Fallback if WC_Customer throws an exception
					$user = get_userdata( $item->customer_id );
					$item->customer_name = $user ? $user->display_name : __( 'Customer #', 'resubscribe-controls-for-wcsubs' ) . $item->customer_id;
				}
				
				// Add product name
				$product = wc_get_product( $item->product_id );
				$item->product_name = $product ? $product->get_name() : __( 'Product #', 'resubscribe-controls-for-wcsubs' ) . $item->product_id;
			}
		}
		
		wp_send_json_success( $data );
	}

	/**
	 * AJAX handler for exporting analytics data to CSV.
	 */
	public function ajax_export_analytics() {
		// Verify nonce.
		check_admin_referer( 'rcwcs_export_analytics', 'rcwcs_export_nonce' );

		// Check user capability.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this data.', 'resubscribe-controls-for-wcsubs' ) );
		}

		// Get data from database.
		global $wpdb;
		$table_name = $wpdb->prefix . 'rcwcs_analytics';
		$results = $wpdb->get_results(
			"SELECT * FROM $table_name ORDER BY resubscribe_date DESC",
			ARRAY_A
		);

		if ( empty( $results ) ) {
			wp_die( esc_html__( 'No data to export.', 'resubscribe-controls-for-wcsubs' ) );
		}

		// Set headers for CSV download.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=resubscribe-analytics-' . date( 'Y-m-d' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Create output stream.
		$output = fopen( 'php://output', 'w' );

		// Add CSV headers.
		fputcsv( $output, array(
			__( 'ID', 'resubscribe-controls-for-wcsubs' ),
			__( 'Subscription ID', 'resubscribe-controls-for-wcsubs' ),
			__( 'Customer ID', 'resubscribe-controls-for-wcsubs' ),
			__( 'Product ID', 'resubscribe-controls-for-wcsubs' ),
			__( 'Original Price', 'resubscribe-controls-for-wcsubs' ),
			__( 'New Price', 'resubscribe-controls-for-wcsubs' ),
			__( 'Price Difference', 'resubscribe-controls-for-wcsubs' ),
			__( 'Resubscribe Date', 'resubscribe-controls-for-wcsubs' )
		) );

		// Add data rows.
		foreach ( $results as $row ) {
			fputcsv( $output, $row );
		}

		// Close the output stream.
		fclose( $output );

		// Exit to prevent any additional output.
		exit;
	}

	/**
	 * Add a new method to get chart data for AJAX calls
	 */
	public function ajax_get_chart_data() {
		check_ajax_referer( 'rcwcs-admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to access this data.', 'resubscribe-controls-for-wcsubs' ) ) );
		}

		// Get date range
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : date( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : date( 'Y-m-d' );

		// Get chart data
		$data = $this->get_chart_data( $start_date, $end_date );
		wp_send_json_success( $data );
	}

	/**
	 * New method to get chart data
	 */
	private function get_chart_data( $start_date, $end_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'rcwcs_analytics';
		
		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			$this->create_table();
			return array(
				'price_history' => array(
					'dates' => array(),
					'differences' => array(),
				),
				'products' => array(
					'labels' => array(),
					'data' => array(),
				),
			);
		}

		// Validate dates
		$start_date = date( 'Y-m-d', strtotime( $start_date ) );
		$end_date = date( 'Y-m-d', strtotime( $end_date ) );

		// Price history data
		$price_history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(resubscribe_date) as date, AVG(price_difference) as avg_diff
				FROM {$table_name}
				WHERE resubscribe_date BETWEEN %s AND %s
				GROUP BY DATE(resubscribe_date)
				ORDER BY date ASC",
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			)
		);

		// Product distribution data
		$product_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, COUNT(*) as count
				FROM {$table_name}
				WHERE resubscribe_date BETWEEN %s AND %s
				GROUP BY product_id
				ORDER BY count DESC
				LIMIT 6", // Limit to top 6 for better visualization
				$start_date . ' 00:00:00',
				$end_date . ' 23:59:59'
			)
		);

		// Format data for charts
		$price_history_data = array(
			'dates' => array(),
			'differences' => array(),
		);

		foreach ( $price_history as $record ) {
			$price_history_data['dates'][] = date_i18n( get_option( 'date_format' ), strtotime( $record->date ) );
			$price_history_data['differences'][] = (float) $record->avg_diff;
		}

		// Format product data
		$product_labels = array();
		$product_counts = array();

		foreach ( $product_data as $record ) {
			$product = wc_get_product( $record->product_id );
			$product_name = $product ? $product->get_name() : sprintf( __( 'Product #%d', 'resubscribe-controls-for-wcsubs' ), $record->product_id );
			
			// Limit product name length for display
			if ( strlen( $product_name ) > 30 ) {
				$product_name = substr( $product_name, 0, 27 ) . '...';
			}
			
			$product_labels[] = $product_name;
			$product_counts[] = (int) $record->count;
		}

		return array(
			'price_history' => $price_history_data,
			'products' => array(
				'labels' => $product_labels,
				'data' => $product_counts,
			),
		);
	}

	/**
	 * Enqueue analytics scripts.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_analytics_scripts( $hook ) {
		if ( 'woocommerce_page_rcwcs-admin' !== $hook ) {
			return;
		}

		// Only load on analytics tab
		if ( ! isset( $_GET['tab'] ) || 'analytics' !== $_GET['tab'] ) {
			return;
		}

		wp_enqueue_style(
			'rcwcs-analytics',
			RCWCS_PLUGIN_URL . 'assets/css/analytics.css',
			array(),
			RCWCS_VERSION
		);

		wp_enqueue_script(
			'rcwcs-analytics',
			RCWCS_PLUGIN_URL . 'assets/js/analytics.js',
			array( 'jquery' ),
			RCWCS_VERSION,
			true
		);

		wp_localize_script(
			'rcwcs-analytics',
			'rcwcs_analytics',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'rcwcs-admin' ),
				'admin_url'        => admin_url(),
				'currency_symbol'  => get_woocommerce_currency_symbol(),
			)
		);
	}

	/**
	 * Get the edit URL for a subscription based on whether HPOS is enabled
	 *
	 * @param int $subscription_id Subscription ID
	 * @return string URL to edit the subscription
	 */
	private function get_subscription_edit_url( $subscription_id ) {
		if ( $this->is_hpos_enabled() ) {
			// HPOS-compatible URL structure
			if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
				return \Automattic\WooCommerce\Admin\PageController::get_edit_url( 'shop_subscription', $subscription_id );
			}
		}
		
		// Default/legacy URL
		return admin_url( 'post.php?post=' . absint( $subscription_id ) . '&action=edit' );
	}
}

// Initialize module.
// This is commented out because it will be initialized from the main plugin class based on settings.
// RCWCS_Analytics::init(); 