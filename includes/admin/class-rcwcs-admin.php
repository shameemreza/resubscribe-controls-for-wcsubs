<?php
/**
 * Admin functionality
 *
 * @package ResubscribeControlsWCS
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RCWCS_Admin Class
 */
class RCWCS_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Register AJAX handlers
		add_action( 'wp_ajax_rcwcs_preview_email', array( $this, 'ajax_preview_email' ) );
		add_action( 'wp_ajax_rcwcs_analytics_data', array( $this, 'ajax_get_analytics_data' ) );
		
		// Hook directly to show fields after tax fields - more reliable approach
		add_action( 'woocommerce_product_options_tax', array( $this, 'output_subscription_product_fields' ), 20 );
		
		// Hook into variation subscription product fields - after tax fields
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variable_subscription_product_fields' ), 21, 3 );
		
		// Save product settings
		add_action( 'woocommerce_process_product_meta_subscription', array( $this, 'save_product_data' ) );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_product_variation' ), 20, 2 );
		add_action( 'woocommerce_process_product_meta_variable-subscription', array( $this, 'process_product_meta_variable_subscription' ) );
}

	/**
	 * Add resubscribe control fields to the subscription product.
	 * 
	 * Note: This method is no longer used directly, see output_subscription_product_fields
	 */
	public function add_subscription_product_fields() {
		// This method is kept for backward compatibility but no longer used directly
	}
	
	/**
	 * Output resubscribe control fields for simple subscription products.
	 */
	public function output_subscription_product_fields() {
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

		// Get product settings.
		$allow_resubscribe = $product->get_meta( '_rcwcs_allow_resubscribe', true );
		$enforce_current_price = $product->get_meta( '_rcwcs_enforce_current_price', true );
		$check_stock = $product->get_meta( '_rcwcs_check_stock', true );
		
		// Get time limitation settings
		$override_time_limitation = $product->get_meta( '_rcwcs_override_time_limitation', true );
		$time_limitation_days = $product->get_meta( '_rcwcs_time_limitation_days', true );

		// Set defaults if not set.
		if ( '' === $allow_resubscribe ) {
			$allow_resubscribe = 'yes';
		}
		if ( '' === $enforce_current_price ) {
			$enforce_current_price = 'yes';
		}
		if ( '' === $check_stock ) {
			$check_stock = 'yes';
		}
		if ( '' === $override_time_limitation ) {
			$override_time_limitation = 'no';
		}
		if ( '' === $time_limitation_days ) {
			// Get the global default
			$settings = get_option( 'rcwcs_settings', array() );
			$time_limitation_days = isset( $settings['time_limitation_days'] ) ? $settings['time_limitation_days'] : 30;
		}

		echo '<div class="options_group subscription_resubscribe_control" style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px;">';
		
		// Add section title
		echo '<h4 style="padding-left: 12px; margin-bottom: 0;">' . esc_html__( 'Resubscribe Controls', 'resubscribe-controls-for-wcsubs' ) . '</h4>';
		echo '<p style="padding-left: 12px; font-style: italic; margin-top: 0; margin-bottom: 15px; color: #777;">' . esc_html__( 'Control how customers can resubscribe to this product.', 'resubscribe-controls-for-wcsubs' ) . '</p>';
		
		woocommerce_wp_checkbox( 
			array(
				'id'          => '_rcwcs_allow_resubscribe',
				'label'       => __( 'Allow Resubscribe', 'resubscribe-controls-for-wcsubs' ),
				'description' => __( 'Allow customers to resubscribe to this product', 'resubscribe-controls-for-wcsubs' ),
				'value'       => $allow_resubscribe,
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id'          => '_rcwcs_enforce_current_price',
				'label'       => __( 'Enforce Current Price', 'resubscribe-controls-for-wcsubs' ),
				'description' => __( 'Use current price instead of original subscription price when resubscribing', 'resubscribe-controls-for-wcsubs' ),
				'value'       => $enforce_current_price,
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id'          => '_rcwcs_check_stock',
				'label'       => __( 'Check Stock', 'resubscribe-controls-for-wcsubs' ),
				'description' => __( 'Check product stock status before allowing resubscription', 'resubscribe-controls-for-wcsubs' ),
				'value'       => $check_stock,
			)
		);
		
		// Get plugin settings to check if time limitation is enabled globally
		$settings = get_option( 'rcwcs_settings', array() );
		$time_limitation_enabled = isset( $settings['time_limitation_enabled'] ) && 'yes' === $settings['time_limitation_enabled'];
		$product_override_allowed = isset( $settings['time_limitation_product_override'] ) && 'yes' === $settings['time_limitation_product_override'];
		
		// Only show time limitation fields if enabled globally and product overrides are allowed
		if ( $time_limitation_enabled && $product_override_allowed ) {
			woocommerce_wp_checkbox(
				array(
					'id'          => '_rcwcs_override_time_limitation',
					'label'       => __( 'Override Time Limitation', 'resubscribe-controls-for-wcsubs' ),
					'description' => __( 'Override the global time limitation settings for this product', 'resubscribe-controls-for-wcsubs' ),
					'value'       => $override_time_limitation,
					'class'       => 'show_if_override_time_limitation',
				)
			);
			
			woocommerce_wp_text_input(
				array(
					'id'                => '_rcwcs_time_limitation_days',
					'label'             => __( 'Time Limitation (Days)', 'resubscribe-controls-for-wcsubs' ),
					'description'       => __( 'Number of days after cancellation/expiration during which resubscription is allowed (0 = unlimited)', 'resubscribe-controls-for-wcsubs' ),
					'value'             => $time_limitation_days,
					'type'              => 'number',
					'custom_attributes' => array(
						'min'  => '0',
						'max'  => '365',
						'step' => '1',
					),
					'class'             => 'show_if_override_time_limitation',
				)
			);
			
			// Add JavaScript to toggle time limitation days field visibility
			echo '<script type="text/javascript">
				jQuery(function($) {
					function toggleTimeLimitationFields() {
						if ($("#_rcwcs_override_time_limitation").is(":checked")) {
							$("._rcwcs_time_limitation_days_field").show();
						} else {
							$("._rcwcs_time_limitation_days_field").hide();
						}
					}
					
					// Initial state
					toggleTimeLimitationFields();
					
					// Toggle on change
					$("#_rcwcs_override_time_limitation").change(function() {
						toggleTimeLimitationFields();
					});
				});
			</script>';
		}

		echo '</div>';
	}

	/**
	 * Add resubscribe control fields to variable subscription products.
	 *
	 * @param int $loop Position in the loop.
	 * @param array $variation_data Variation data.
	 * @param WP_Post $variation Variation post.
	 */
	public function add_variable_subscription_product_fields( $loop, $variation_data, $variation ) {
		// Get product
		$parent_product = wc_get_product( $variation->post_parent );
		
		// Only show for variable subscription products
		if ( ! $parent_product || 'variable-subscription' !== $parent_product->get_type() ) {
			return;
		}
		
		// Get product settings.
		$variation_product = wc_get_product( $variation->ID );
		
		if ( ! $variation_product ) {
			return;
		}
		
		$allow_resubscribe = $variation_product->get_meta( '_rcwcs_allow_resubscribe', true );
		$enforce_current_price = $variation_product->get_meta( '_rcwcs_enforce_current_price', true );
		$check_stock = $variation_product->get_meta( '_rcwcs_check_stock', true );

		// Set defaults if not set.
		if ( '' === $allow_resubscribe ) {
			$allow_resubscribe = 'yes';
		}
		if ( '' === $enforce_current_price ) {
			$enforce_current_price = 'yes';
		}
		if ( '' === $check_stock ) {
			$check_stock = 'yes';
		}

		// Section title with improved styling
		echo '<div class="variable_subscription_resubscribe_control" style="border-top: 1px solid #eee; margin-top: 15px; padding-top: 15px; clear: both;">';
		echo '<p style="margin-bottom: 10px; font-weight: 600; font-size: 14px;">' . esc_html__( 'Resubscribe Controls', 'resubscribe-controls-for-wcsubs' ) . '</p>';
		echo '<p style="margin-top: 0; font-style: italic; color: #777; margin-bottom: 15px;">' . esc_html__( 'Control how customers can resubscribe to this variation.', 'resubscribe-controls-for-wcsubs' ) . '</p>';
		
		// Custom checkbox field styling with table layout for better alignment
		?>
		<table class="form-table" style="margin-top: 0; border-collapse: separate; border-spacing: 0;">
			<tbody>
				<tr>
					<td style="padding: 5px 0; width: 200px; vertical-align: top;">
						<label style="margin-bottom: 0; display: inline-block;">
							<input type="checkbox" 
								class="checkbox" 
								name="_rcwcs_allow_resubscribe[<?php echo esc_attr( $loop ); ?>]" 
								id="_rcwcs_allow_resubscribe_<?php echo esc_attr( $loop ); ?>" 
								value="yes" 
								<?php checked( 'yes', $allow_resubscribe ); ?> 
								style="margin-right: 5px;">
							<?php esc_html_e( 'Allow Resubscribe', 'resubscribe-controls-for-wcsubs' ); ?>
						</label>
					</td>
					<td style="padding: 5px 0; vertical-align: top;">
						<span style="color: #777; font-size: 13px; display: inline-block;">
							<?php esc_html_e( 'Allow customers to resubscribe to this variation', 'resubscribe-controls-for-wcsubs' ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<td style="padding: 5px 0; width: 200px; vertical-align: top;">
						<label style="margin-bottom: 0; display: inline-block;">
							<input type="checkbox" 
								class="checkbox" 
								name="_rcwcs_enforce_current_price[<?php echo esc_attr( $loop ); ?>]" 
								id="_rcwcs_enforce_current_price_<?php echo esc_attr( $loop ); ?>" 
								value="yes" 
								<?php checked( 'yes', $enforce_current_price ); ?> 
								style="margin-right: 5px;">
							<?php esc_html_e( 'Enforce Current Price', 'resubscribe-controls-for-wcsubs' ); ?>
						</label>
					</td>
					<td style="padding: 5px 0; vertical-align: top;">
						<span style="color: #777; font-size: 13px; display: inline-block;">
							<?php esc_html_e( 'Use current price instead of original subscription price when resubscribing', 'resubscribe-controls-for-wcsubs' ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<td style="padding: 5px 0; width: 200px; vertical-align: top;">
						<label style="margin-bottom: 0; display: inline-block;">
							<input type="checkbox" 
								class="checkbox" 
								name="_rcwcs_check_stock[<?php echo esc_attr( $loop ); ?>]" 
								id="_rcwcs_check_stock_<?php echo esc_attr( $loop ); ?>" 
								value="yes" 
								<?php checked( 'yes', $check_stock ); ?> 
								style="margin-right: 5px;">
							<?php esc_html_e( 'Check Stock', 'resubscribe-controls-for-wcsubs' ); ?>
						</label>
					</td>
					<td style="padding: 5px 0; vertical-align: top;">
						<span style="color: #777; font-size: 13px; display: inline-block;">
							<?php esc_html_e( 'Check product stock status before allowing resubscription', 'resubscribe-controls-for-wcsubs' ); ?>
						</span>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
		
		echo '</div>';
	}

	/**
	 * Save product data.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_data( $post_id ) {
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		// Check if this is a simple subscription product
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

		// Sanitize and save data
		$allow_resubscribe = isset( $_POST['_rcwcs_allow_resubscribe'] ) ? 'yes' : 'no';
		$product->update_meta_data( '_rcwcs_allow_resubscribe', $allow_resubscribe );

		$enforce_current_price = isset( $_POST['_rcwcs_enforce_current_price'] ) ? 'yes' : 'no';
		$product->update_meta_data( '_rcwcs_enforce_current_price', $enforce_current_price );

		$check_stock = isset( $_POST['_rcwcs_check_stock'] ) ? 'yes' : 'no';
		$product->update_meta_data( '_rcwcs_check_stock', $check_stock );
		
		// Save time limitation settings
		$override_time_limitation = isset( $_POST['_rcwcs_override_time_limitation'] ) ? 'yes' : 'no';
		$product->update_meta_data( '_rcwcs_override_time_limitation', $override_time_limitation );
		
		if ( isset( $_POST['_rcwcs_time_limitation_days'] ) ) {
			$time_limitation_days = absint( $_POST['_rcwcs_time_limitation_days'] );
			if ( $time_limitation_days > 365 ) {
				$time_limitation_days = 365;
			}
			$product->update_meta_data( '_rcwcs_time_limitation_days', $time_limitation_days );
		}

		// Save product.
		$product->save();
	}

	/**
	 * Save product variation data.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop Position in the loop.
	 */
	public function save_product_variation( $variation_id, $loop ) {
		// Get the variation product
		$variation_product = wc_get_product( $variation_id );

		if ( ! $variation_product ) {
			return;
		}

		// Get the parent product
		$parent_id = $variation_product->get_parent_id();
		$parent_product = wc_get_product( $parent_id );

		// Only process for subscription variations
		if ( ! $parent_product || 'variable-subscription' !== $parent_product->get_type() ) {
			return;
		}

		// Try/catch to prevent critical errors
		try {
			// Safely get posted values with sanitization
			$allow_resubscribe = isset( $_POST['_rcwcs_allow_resubscribe'][$loop] ) ? 'yes' : 'no';
			$enforce_current_price = isset( $_POST['_rcwcs_enforce_current_price'][$loop] ) ? 'yes' : 'no';
			$check_stock = isset( $_POST['_rcwcs_check_stock'][$loop] ) ? 'yes' : 'no';

			// Update meta data
			$variation_product->update_meta_data( '_rcwcs_allow_resubscribe', sanitize_text_field( $allow_resubscribe ) );
			$variation_product->update_meta_data( '_rcwcs_enforce_current_price', sanitize_text_field( $enforce_current_price ) );
			$variation_product->update_meta_data( '_rcwcs_check_stock', sanitize_text_field( $check_stock ) );

			// Save product.
			$variation_product->save();
		} catch ( Exception $e ) {
			// Log error but don't break the save process
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error( 'Error saving resubscribe controls: ' . $e->getMessage(), array( 'source' => 'rcwcs' ) );
			}
		}
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Resubscribe Controls', 'resubscribe-controls-for-wcsubs' ),
			__( 'Resubscribe Controls', 'resubscribe-controls-for-wcsubs' ),
			'manage_woocommerce',
			'rcwcs-admin',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our admin page.
		if ( 'woocommerce_page_rcwcs-admin' !== $hook ) {
			return;
		}

		// Enqueue admin styles.
			wp_enqueue_style(
				'rcwcs-admin',
				RCWCS_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				RCWCS_VERSION
			);

		// Enqueue admin scripts.
			wp_enqueue_script(
				'rcwcs-admin',
				RCWCS_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				RCWCS_VERSION,
				true
			);

		// Add admin data for JS
			wp_localize_script(
				'rcwcs-admin',
				'rcwcs_admin',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'rcwcs-admin' ),
			)
		);

		// If this is our settings page, add rich editor support
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
		
		if ( 'settings' === $current_tab ) {
			// WordPress editor scripts and styles
			wp_enqueue_editor();
			wp_enqueue_media();
			
			// Add custom styling for the editor
			wp_add_inline_style( 'rcwcs-admin', '
				.wp-editor-container {
					border: 1px solid #ddd;
					border-radius: 4px;
					margin-bottom: 10px;
				}
				.wp-editor-area {
					min-height: 200px;
				}
				.mce-toolbar .mce-btn-group .mce-btn.mce-listbox {
					border-radius: 3px;
				}
				/* Ensure editor width matches other fields */
				div.wp-editor-wrap {
					max-width: 95%;
				}
				/* Format email editor section */
				#rcwcs_notification_section .form-table th {
					padding: 20px 10px 20px 0;
				}
				/* Give more room for the editor */
				#rcwcs_price_email_content_ifr {
					min-height: 250px !important;
				}
				/* Fix editor height */
				.wp-editor-container textarea.wp-editor-area {
					min-height: 250px;
				}
			');
			
			// Add inline script to improve editor experience
			wp_add_inline_script( 'rcwcs-admin', '
				jQuery(document).ready(function($) {
					// Add image button functionality if needed
					$(document).on("click", ".rcwcs-add-media-button", function(e) {
						e.preventDefault();
						var editor_id = $(this).data("editor");
						
						// Open the media library
						var mediaUploader = wp.media({
							title: "Select or Upload an Image",
							button: {
								text: "Use this image"
							},
							multiple: false
						});
						
						// When an image is selected
						mediaUploader.on("select", function() {
							var attachment = mediaUploader.state().get("selection").first().toJSON();
							var img_url = attachment.url;
							var img_html = "<img src=\"" + img_url + "\" alt=\"" + attachment.title + "\" />";
							
							// Insert into editor
							if (typeof tinymce !== "undefined" && tinymce.get(editor_id)) {
								tinymce.get(editor_id).execCommand("mceInsertContent", false, img_html);
							} else {
								// Fallback to direct textarea manipulation if TinyMCE is not initialized
								var $textarea = $("#" + editor_id);
								$textarea.val($textarea.val() + img_html);
							}
						});
						
						// Open the uploader dialog
						mediaUploader.open();
					});
				});
			', 'after' );
		}
		
		// Get plugin settings
		$settings = get_option( 'rcwcs_settings', array() );
		
		// Check if analytics is enabled
		$analytics_enabled = isset( $settings['analytics_enabled'] ) && 'yes' === $settings['analytics_enabled'];
		
		// Only load analytics assets if we're on the analytics tab AND analytics is enabled
		if ( 'analytics' === $current_tab && $analytics_enabled ) {
			// Make sure the analytics module is initialized
			require_once RCWCS_PLUGIN_DIR . 'includes/modules/class-rcwcs-analytics.php';
			
			// Load Chart.js first and separately
			wp_enqueue_script(
				'chart-js',
				'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js',
				array( 'jquery' ),
				'3.7.1',
				true
			);
			
			// Enqueue our analytics CSS
			wp_enqueue_style(
				'rcwcs-analytics',
				RCWCS_PLUGIN_URL . 'assets/css/analytics.css',
				array(),
				RCWCS_VERSION
			);
			
			// Make sure Chart.js is fully loaded before our script
			wp_enqueue_script(
				'rcwcs-analytics',
				RCWCS_PLUGIN_URL . 'assets/js/analytics.js',
				array( 'jquery', 'chart-js' ),
				RCWCS_VERSION,
				true
			);

			// Properly localize the script with all needed data
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
			
			// Add translations
			wp_localize_script(
				'rcwcs-analytics',
				'rcwcs_analytics_i18n',
				array(
					'refresh'                   => __( 'Refresh Data', 'resubscribe-controls-for-wcsubs' ),
					'error'                     => __( 'Error loading data. Please try again.', 'resubscribe-controls-for-wcsubs' ),
					'price_differences'         => __( 'Price Differences', 'resubscribe-controls-for-wcsubs' ),
					'price_difference'          => __( 'Price Difference', 'resubscribe-controls-for-wcsubs' ),
					'date'                      => __( 'Date', 'resubscribe-controls-for-wcsubs' ),
					'resubscriptions_by_product' => __( 'Resubscriptions by Product', 'resubscribe-controls-for-wcsubs' ),
				)
			);
		}
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		// Get current tab.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';

		// Get plugin settings
		$settings = get_option( 'rcwcs_settings', array() );
		
		// Check if analytics is enabled
		$analytics_enabled = isset( $settings['analytics_enabled'] ) && 'yes' === $settings['analytics_enabled'];

		// Define tabs - only show analytics tab if enabled
		$tabs = array(
			'settings' => __( 'Settings', 'resubscribe-controls-for-wcsubs' ),
		);
		
		if ( $analytics_enabled ) {
			$tabs['analytics'] = __( 'Analytics', 'resubscribe-controls-for-wcsubs' );
		}
		
		// If analytics is not enabled but the current tab is analytics, redirect to settings
		if ( !$analytics_enabled && 'analytics' === $current_tab ) {
			$current_tab = 'settings';
		}
		?>
		<div class="wrap rcwcs-admin-wrap">
			<h1><?php echo esc_html__( 'Resubscribe Controls for WCSubs', 'resubscribe-controls-for-wcsubs' ); ?></h1>

			<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rcwcs-admin&tab=' . $tab_id ) ); ?>" class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_name ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="rcwcs-admin-content">
				<?php
				if ( 'settings' === $current_tab ) {
					$this->render_settings_tab();
				} elseif ( 'analytics' === $current_tab && $analytics_enabled ) {
					$this->render_analytics_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings tab.
	 */
	private function render_settings_tab() {
		// Use WordPress Settings API for settings.
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'rcwcs_settings' );
			// Display settings errors/notices
			settings_errors( 'rcwcs_settings' );
			do_settings_sections( 'rcwcs_settings' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render analytics tab.
	 */
	private function render_analytics_tab() {
		// Include the RCWCS_Analytics class if not already included
		if ( ! class_exists( 'RCWCS_Analytics' ) ) {
			require_once RCWCS_PLUGIN_DIR . 'includes/modules/class-rcwcs-analytics.php';
		}

		// Get analytics data.
		try {
			// Use the get_instance method instead of direct instantiation
			$analytics = RCWCS_Analytics::get_instance();
			$analytics->render_analytics_page();
		} catch ( Exception $e ) {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Error loading analytics module:', 'resubscribe-controls-for-wcsubs' ); ?> <?php echo esc_html( $e->getMessage() ); ?></p>
			</div>
			<?php
			// Log the error
			error_log( 'RCWCS Analytics Error: ' . $e->getMessage() );
		} catch ( Error $e ) {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Critical error loading analytics module:', 'resubscribe-controls-for-wcsubs' ); ?> <?php echo esc_html( $e->getMessage() ); ?></p>
			</div>
			<?php
			// Log the error
			error_log( 'RCWCS Analytics Critical Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Process product meta for variable subscription.
	 * 
	 * @param int $post_id Post ID.
	 */
	public function process_product_meta_variable_subscription( $post_id ) {
		// This method is now just used to make sure we have nonce verification
		// The actual variation saving is handled in save_product_variation
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'update-post_' . $post_id ) ) {
			return;
		}
	}
}

// Initialize admin.
new RCWCS_Admin(); 