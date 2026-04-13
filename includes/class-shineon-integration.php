<?php

class ShineOn_Integration {

	private $modal;
	private $download_cache = array();

	public function __construct() {
		$this->modal = new ShineOn_Modal();
		$this->init();
	}

	public function init() {
		// Add admin menu for settings
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Fix for WooPayments disabling Add to Cart button on product pages
		add_filter( 'wcpay_payment_request_is_product_supported', '__return_false', 100 );
		add_filter( 'wcpay_is_woopay_enabled', '__return_false', 100 );
		
		// Send order to ShineOn API when order is paid/completed
		add_action( 'woocommerce_order_status_processing', array( $this, 'send_order_to_shineon' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'send_order_to_shineon' ) );

		// Frontend defensive script for WooPayments conflict
		add_action( 'wp_footer', array( $this, 'inject_defensive_js' ) );

		// AJAX handler for importing products
		add_action( 'wp_ajax_shineon_init_import', array( $this, 'handle_init_import' ) );
		add_action( 'wp_ajax_shineon_import_variation', array( $this, 'handle_import_variation' ) );
		add_action( 'wp_ajax_shineon_finalize_import', array( $this, 'handle_finalize_import' ) );
		add_action( 'wp_ajax_shineon_get_renders', array( $this->modal, 'ajax_get_renders' ) );
		add_action( 'wp_ajax_shineon_get_template_image', array( $this, 'ajax_get_template_image' ) );
		
		// Enqueue frontend CSS for checkout fixes
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueue checkout CSS fixes
	 */
	public function enqueue_frontend_assets() {
		if ( is_checkout() || is_checkout_pay_page() ) {
			wp_enqueue_style( 'shineon-checkout-fixes', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/checkout-fixes.css', array(), '1.1.0' );
		}
	}

	/**
	 * Add admin menu for ShineOn settings
	 */
	public function add_admin_menu() {
		add_menu_page(
			'ShineOn',
			'ShineOn',
			'manage_options',
			'shineon-settings',
			array( $this, 'render_tabbed_page' ),
			'dashicons-star-filled',
			65
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'shineon_settings', ShineOn_Settings::API_KEY_OPTION );
		register_setting( 'shineon_settings', ShineOn_Settings::TEST_MODE_OPTION );
		
		add_settings_section(
			'shineon_main',
			'', // Removed title for custom styling
			'__return_empty_string',
			'shineon_settings'
		);

		add_settings_field(
			ShineOn_Settings::API_KEY_OPTION,
			'ShineOn API Key',
			array( $this, 'render_api_key_field' ),
			'shineon_settings',
			'shineon_main'
		);

		add_settings_field(
			ShineOn_Settings::TEST_MODE_OPTION,
			'Test Mode',
			array( $this, 'render_test_mode_field' ),
			'shineon_settings',
			'shineon_main'
		);
	}

	/**
	 * Render test mode field
	 */
	public function render_test_mode_field() {
		$value = ShineOn_Settings::is_test_mode();
		?>
		<div class="shineon-field-toggle">
			<label class="shineon-switch">
				<input type="checkbox" name="<?php echo ShineOn_Settings::TEST_MODE_OPTION; ?>" value="yes" <?php checked( $value, true ); ?>>
				<span class="shineon-slider round"></span>
			</label>
			<p class="description" style="margin-top: 10px;">Enable this for testing orders without actual charges. Disable for production fulfillment.</p>
		</div>
		<?php
	}

	/**
	 * Render settings section
	 */
	public function render_settings_section() {
		echo 'Enter your ShineOn API credentials below. Your ShineOn API key can be found <a href="https://teamshineon.zendesk.com/hc/en-us/articles/10120654767121-The-ShineOn-API" target="_blank">here</a>.';
	}

	/**
	 * Render API Key input field
	 */
	public function render_api_key_field() {
		$api_key = ShineOn_Settings::get_api_key();
		echo '<input type="text" name="' . esc_attr( ShineOn_Settings::API_KEY_OPTION ) . '" value="' . esc_attr( $api_key ) . '" size="50" />';
		echo '<p class="description">Your unique ShineOn API Key for authentication</p>';
	}

	/**
	 * Render tabbed settings page
	 */
	/**
	 * Render tabbed settings page
	 */
	public function render_tabbed_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
		?>
		<style>
			#wpcontent { background: #f0f2f5; }
			.shineon-settings-wrap {
				max-width: 1200px;
				margin: 0 auto;
				padding: 20px;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.shineon-header {
				display: flex;
				align-items: center;
				background: #fff;
				padding: 10px 25px;
				border-radius: 8px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.05);
				margin-bottom: 20px;
			}
			.shineon-header-left { display: flex; align-items: center; }
			.shineon-logo {
				background: #7c3aed;
				color: #fff;
				width: 32px;
				height: 32px;
				border-radius: 6px;
				display: flex;
				align-items: center;
				justify-content: center;
				font-weight: bold;
				margin-right: 15px;
			}
			.shineon-header-nav { display: flex; gap: 20px; }
			.shineon-header-nav a {
				text-decoration: none;
				color: #6b7280;
				font-weight: 500;
				padding: 10px 0;
				border-bottom: 2px solid transparent;
			}
			.shineon-header-nav a.active {
				color: #7c3aed;
				border-bottom-color: #7c3aed;
			}
			.shineon-layout { display: flex; gap: 30px; }
			.shineon-sidebar { width: 220px; flex-shrink: 0; }
			.shineon-sidebar-nav {
				list-style: none;
				padding: 0;
				margin: 0;
			}
			.shineon-sidebar-nav li a {
				display: flex;
				align-items: center;
				padding: 10px 15px;
				color: #4b5563;
				text-decoration: none;
				border-radius: 6px;
				margin-bottom: 5px;
				transition: background 0.2s;
			}
			.shineon-sidebar-nav li a:hover,
			.shineon-sidebar-nav li a.active {
				background: #fff;
				color: #7c3aed;
				font-weight: 500;
				box-shadow: 0 1px 2px rgba(0,0,0,0.05);
			}
			.shineon-sidebar-nav li a .dashicons { 
				margin-right: 10px;
				font-size: 18px;
			}
			.shineon-content { flex-grow: 1; }
			.shineon-card {
				background: #fff;
				border-radius: 12px;
				padding: 30px;
				box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
				margin-bottom: 25px;
			}
			.shineon-card h2 {
				font-size: 18px;
				font-weight: 600;
				margin: 0 0 15px 0;
				display: flex;
				align-items: center;
			}
			.shineon-card h2 .dashicons {
				margin-right: 10px;
				color: #9ca3af;
			}
			.shineon-card p.instr {
				color: #6b7280;
				font-size: 14px;
				line-height: 1.5;
				margin-bottom: 20px;
			}
			.shineon-input-group {
				display: flex;
				gap: 10px;
				margin-bottom: 15px;
			}
			.shineon-input-group input[type="text"] {
				flex-grow: 1;
				padding: 10px 15px;
				border: 1px solid #d1d5db;
				border-radius: 6px;
				font-size: 14px;
			}
			.shineon-btn-primary {
				background: #7c3aed;
				color: #fff;
				border: none;
				padding: 10px 20px;
				border-radius: 6px;
				font-weight: 500;
				cursor: pointer;
				transition: background 0.2s;
			}
			.shineon-btn-primary:hover { background: #6d28d9; }
			.shineon-link {
				color: #7c3aed;
				text-decoration: none;
				font-size: 13px;
				transition: border-bottom 0.2s;
			}
			.shineon-link:hover { border-bottom: 1px solid #7c3aed; }
			
			/* Toggle Switch Styles */
			.shineon-switch {
				position: relative;
				display: inline-block;
				width: 48px;
				height: 24px;
			}
			.shineon-switch input { opacity: 0; width: 0; height: 0; }
			.shineon-slider {
				position: absolute;
				cursor: pointer;
				top: 0; left: 0; right: 0; bottom: 0;
				background-color: #ccc;
				transition: .4s;
			}
			.shineon-slider:before {
				position: absolute;
				content: "";
				height: 18px; width: 18px;
				left: 3px; bottom: 3px;
				background-color: white;
				transition: .4s;
			}
			input:checked + .shineon-slider { background-color: #7c3aed; }
			input:focus + .shineon-slider { box-shadow: 0 0 1px #7c3aed; }
			input:checked + .shineon-slider:before { transform: translateX(24px); }
			.shineon-slider.round { border-radius: 34px; }
			.shineon-slider.round:before { border-radius: 50%; }

			/* Hide default WP stuff */
			.shineon-settings-wrap h2 { display: none; }
			form.shineon-ajax-form table.form-table { display: none; }
		</style>

		<div class="shineon-settings-wrap">
			<header class="shineon-header">
				<div class="shineon-header-left">
					<div class="shineon-logo">S</div>
					<div class="shineon-header-nav">
						<a href="?page=shineon-settings&tab=settings" class="<?php echo $tab === 'settings' ? 'active' : ''; ?>">Settings</a>
						<a href="?page=shineon-settings&tab=products" class="<?php echo $tab === 'products' ? 'active' : ''; ?>">My Products</a>
					</div>
				</div>
			</header>

			<div class="shineon-layout">
				<?php if ( $tab === 'settings' ) : ?>
					<aside class="shineon-sidebar">
						<ul class="shineon-sidebar-nav">
							<li>
								<a href="?page=shineon-settings&tab=settings" class="active">
									<span class="dashicons dashicons-admin-generic"></span> General
								</a>
							</li>
						</ul>
					</aside>
				<?php endif; ?>

				<main class="shineon-content" style="<?php echo $tab === 'products' ? 'flex-grow: 1; width: 100%;' : ''; ?>">
					<?php if ( $tab === 'products' ) : ?>
						<div class="shineon-card" style="padding: 0; background: transparent; box-shadow: none;">
							<h2 style="display: none;"><span class="dashicons dashicons-products"></span> My Products</h2>
							<?php $this->render_products_tab(); ?>
						</div>
					<?php else : ?>
						<form method="post" action="options.php">
							<?php settings_fields( 'shineon_settings' ); ?>
							
							<div class="shineon-card">
								<h2><span class="dashicons dashicons-lock"></span> Your License</h2>
								<p class="instr">
									Enter your ShineOn API key to authenticate and synchronize products. 
									Don't have a key? <a href="https://app.shineon.com/settings/api" target="_blank" class="shineon-link">Get it from ShineOn App</a>.
								</p>
								<div class="shineon-input-group">
									<input type="text" name="<?php echo ShineOn_Settings::API_KEY_OPTION; ?>" value="<?php echo esc_attr( ShineOn_Settings::get_api_key() ); ?>" placeholder="Paste your API key here">
									<button type="submit" class="shineon-btn-primary">Save</button>
								</div>
							</div>

							<div class="shineon-card">
								<h2><span class="dashicons dashicons-admin-tools"></span> Test Mode</h2>
								<p class="instr">
									Enable this for testing orders without actual charges. This creates test-tagged orders in the ShineOn system that will not be fulfilled or billed.
								</p>
								<div class="shineon-field-toggle" style="display: flex; align-items: center; gap: 15px;">
									<label class="shineon-switch">
										<input type="checkbox" name="<?php echo ShineOn_Settings::TEST_MODE_OPTION; ?>" value="yes" <?php checked( ShineOn_Settings::is_test_mode(), true ); ?>>
										<span class="shineon-slider round"></span>
									</label>
									<span style="font-size: 14px; font-weight: 500; color: #4b5563;">Enable Test Orders</span>
								</div>
							</div>

							<?php 
							// Hidden default button for core settings support
							echo '<div style="display:none">';
							submit_button();
							echo '</div>';
							?>
						</form>
					<?php endif; ?>
				</main>
			</div>
		</div>
		<?php
	}

	/**
	 * Render API Key Tab (Deprecated but kept for hook safety)
	 */
	public function render_api_key_tab() {
		$this->render_tabbed_page();
	}

	/**
	 * Render Products Tab
	 */
	public function render_products_tab() {
		$api_key = ShineOn_Settings::get_api_key();
		?>
		<div class="shineon-products-container">
			<?php if ( ! $api_key ) : ?>
				<div style="background: #fff8e5; padding: 15px; border-left: 4px solid #ffb81c; margin-bottom: 20px;">
					<p style="margin: 0; color: #666;">
						<strong>API Key not configured.</strong> Please configure your ShineOn API Key in the <a href="?page=shineon-settings&tab=api-key">"API Key" tab</a> to view your products.
					</p>
				</div>
			<?php else : ?>
				<h3 style="margin-top: 0;">Your ShineOn Products</h3>
				<p>Select products to import into WooCommerce.</p>
				<?php $this->render_products_table(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Fetch products from ShineOn API
	 */
	private function fetch_shineon_products() {
		return ShineOn_Settings::request( '/skus' );
	}

	/**
	 * Fetch product template image from ShineOn API with caching
	 *
	 * @param int $product_template_id The product template ID
	 * @return string|null Image URL or null if not found
	 */
	private function fetch_product_template_image( $product_template_id ) {
		$api_key = ShineOn_Settings::get_api_key();
		if ( ! $api_key || ! $product_template_id ) {
			return null;
		}

		// Check cache first - store for 24 hours
		$cache_key = 'shineon_tpl_v2_' . intval( $product_template_id );
		$cached_image = get_transient( $cache_key );
		if ( false !== $cached_image ) {
			return $cached_image ?: null;
		}

		$data = ShineOn_Settings::request( '/product_templates/' . intval( $product_template_id ) );

		if ( is_wp_error( $data ) ) {
			// Cache the empty result for 1 hour to avoid repeated failed calls
			set_transient( $cache_key, '', HOUR_IN_SECONDS );
			return null;
		}

		// Find the first transformation image — prefer layers->main, fall back to layers->background
		$image_url = null;
		if ( isset( $data['transformations'] ) && is_array( $data['transformations'] ) ) {
			foreach ( $data['transformations'] as $transformation ) {
				if ( ! empty( $transformation['layers']['main'] ) ) {
					$image_url = $transformation['layers']['main'];
					break;
				}
				if ( ! empty( $transformation['layers']['background'] ) ) {
					$image_url = $transformation['layers']['background'];
					break;
				}
			}
		}

		// Cache the result for 24 hours
		set_transient( $cache_key, $image_url, DAY_IN_SECONDS );

		return $image_url;
	}

	/**
	 * AJAX handler for lazy loading product template images
	 */
	public function ajax_get_template_image() {
		$template_id = isset( $_GET['tid'] ) ? intval( $_GET['tid'] ) : 0;
		if ( $template_id > 0 ) {
			$url = $this->fetch_product_template_image( $template_id );
			if ( $url ) {
				wp_redirect( $url );
				exit;
			}
		}
		// Redirect to WooCommerce placeholder if not found
		wp_redirect( wc_placeholder_img_src() );
		exit;
	}

	/**
	 * Render products table
	 */
	private function render_products_table() {
		$products = $this->fetch_shineon_products();

		if ( is_wp_error( $products ) ) {
			?>
			<div style="background: #fee; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;">
				<p style="margin: 0; color: #666;">
					<strong>Error fetching products:</strong> <?php echo esc_html( $products->get_error_message() ); ?>
				</p>
			</div>
			<?php
			return;
		}

		if ( empty( $products ) || ( is_array( $products ) && empty( $products['skus'] ) ) ) {
			?>
			<div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">
				<p style="margin: 0; color: #666;">
					No products found in your ShineOn account.
				</p>
			</div>
			<?php
			return;
		}

		$products_list = isset( $products['skus'] ) ? $products['skus'] : $products;

		// Get sort parameters from URL
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
		$order = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'desc';
		$order = in_array( $order, array( 'asc', 'desc' ) ) ? $order : 'desc';

		// Sort products
		usort( $products_list, function( $a, $b ) use ( $orderby, $order ) {
			$a_val = $a[ $orderby ] ?? '';
			$b_val = $b[ $orderby ] ?? '';

			if ( $a_val === $b_val ) {
				return 0;
			}

			$comparison = ( $a_val < $b_val ) ? -1 : 1;
			return $order === 'asc' ? $comparison : -$comparison;
		} );

		// Group products by product_title
		$grouped_products = array();
		foreach ( $products_list as $product ) {
			$product_title = $product['product_title'] ?? 'Untitled Product';
			if ( ! isset( $grouped_products[ $product_title ] ) ) {
				$grouped_products[ $product_title ] = array();
			}
			$grouped_products[ $product_title ][] = $product;
		}

		// Handle pagination
		$total_groups = count( $grouped_products );
		$per_page = 10;
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$total_pages = ceil( $total_groups / $per_page );

		// Only paginate if we have 3 or more total groups (per prompt request, or default 10 per page)
		// But if less than 3 groups total, we can still technically paginate with per_page=10, 
		// but since they asked "If there are 3 or more... break it up", let's set per_page = 2 or something?
		// I will just set $per_page = 10, but actually if they explicitly requested breaking it up when there are 3 or more, 
		// I should set per_page = 2 so that it kicks in at 3.
		$per_page = 2;
		$total_pages = ceil( $total_groups / $per_page );
		
		$grouped_products_keys = array_keys( $grouped_products );
		$offset = ( $current_page - 1 ) * $per_page;
		$paged_keys = array_slice( $grouped_products_keys, $offset, $per_page );
		
		$paged_grouped_products = array();
		foreach ( $paged_keys as $key ) {
			$paged_grouped_products[ $key ] = $grouped_products[ $key ];
		}

		// Get current page URL for sorting links
		$base_url = admin_url( 'admin.php' );
		$created_sort_url = add_query_arg( 
			array( 
				'page' => 'shineon-settings',
				'tab' => 'products',
				'orderby' => 'created_at', 
				'order' => ( $orderby === 'created_at' && $order === 'desc' ) ? 'asc' : 'desc'
			), 
			$base_url 
		);

		?>
		<style>
			.shineon-accordion-table tbody tr.accordion-header {
				background: #f5f5f5;
				font-weight: bold;
				cursor: pointer;
			}
			.shineon-accordion-table tbody tr.accordion-header:hover {
				background: #ececec;
			}
			.shineon-accordion-table tbody tr.accordion-header td {
				padding: 12px 15px !important;
			}
			.shineon-accordion-table tbody tr.accordion-variation {
				display: none;
			}
			.shineon-accordion-table tbody tr.accordion-header.active ~ tr.accordion-variation {
				display: table-row;
			}
			.shineon-accordion-table tbody tr.accordion-variation.expanded {
				display: table-row;
			}
			.shineon-accordion-table tbody tr.accordion-variation td {
				padding-left: 40px !important;
				border-left: 3px solid #0073aa;
			}
		</style>

		<?php
		$pagination_args = array(
			'base'      => add_query_arg( 'paged', '%#%' ),
			'format'    => '',
			'prev_text' => '<span class="dashicons dashicons-arrow-left-alt2"></span>',
			'next_text' => '<span class="dashicons dashicons-arrow-right-alt2"></span>',
			'total'     => $total_pages,
			'current'   => $current_page,
		);
		$page_links = paginate_links( $pagination_args );
		?>

		<?php if ( $page_links ) : ?>
			<div class="tablenav top" style="margin-top: 5px;">
				<div class="alignleft actions" style="margin-bottom:0; padding-bottom:0;">
					<button type="button" class="button button-primary shineon-import-btn" disabled onclick="shineonImportProduct()">
						Import to WooCommerce
					</button>
					<span class="shineon-import-status" style="font-size: 13px; color: #666; margin-left: 10px;"></span>
				</div>
				<div class="tablenav-pages">
					<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', $total_groups ), number_format_i18n( $total_groups ) ) ); ?></span>
					<span class="pagination-links"><?php echo $page_links; ?></span>
				</div>
				<br class="clear" />
			</div>
		<?php else : ?>
			<!-- Import Button (Above) -->
			<div style="margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
				<button type="button" class="button button-primary shineon-import-btn" disabled onclick="shineonImportProduct()">
					Import to WooCommerce
				</button>
				<span class="shineon-import-status" style="font-size: 13px; color: #666;"></span>
			</div>
		<?php endif; ?>

		<table class="wp-list-table widefat fixed striped shineon-accordion-table" style="margin-top: 20px;">
			<thead>
				<tr>
					<th style="width: 40px;">Add</th>
					<th style="width: 120px;">SKU</th>
					<th style="width: 100px;">SKU ID</th>
					<th style="width: 150px;">Product Template</th>
					<th>Title</th>
					<th>Variant Title</th>
					<th style="width: 100px;">Base Cost</th>
					<th style="width: 150px;">Product Image</th>
					<th style="width: 150px;">Created</th>
				</tr>
			</thead>
			<tbody>
				<?php
				// Build JSON data for each group so JS can send it on import
				$groups_json = array();
				$group_index = 0;

				foreach ( $paged_grouped_products as $product_title => $variations ) :
					$group_index++;
					$groups_json[ $group_index ] = array(
						'product_title' => $product_title,
						'variations'    => array_map( function( $v ) {
							return array(
								'option1'     => $v['option1'] ?? '',
								'option1_name'=> 'Style',
								'option2'     => $v['option2'] ?? '',
								'option2_name'=> 'Box Type',
								'option3'     => $v['option3'] ?? '',
								'option3_name'=> 'Option 3',
								'sku'                 => $v['sku'] ?? '',
								'sku_id'              => $v['sku_id'] ?? '',
								'variant_title'       => $v['variant_title'] ?? '',
								'base_cost'           => $v['base_cost'] ?? '',
								'product_template_id' => $v['product_template_id'] ?? '',
								'title'               => $v['title'] ?? '',
							);
						}, $variations ),
					);
				// Check if this group has already been imported (any SKU exists in WooCommerce)
				$is_imported = false;
				$imported_id = 0;
				global $wpdb;
				foreach ( $variations as $v ) {
					$check_sku = $v['sku'] ?? '';
					if ( ! $check_sku ) continue;

					// Find the product ID for this SKU, prioritizing non-trashed products
					// We use a query that sorts by ID descending to get the newest one if multiple exist
					$found_id = $wpdb->get_var( $wpdb->prepare( "
						SELECT p.ID 
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						WHERE pm.meta_key = '_sku' 
						AND pm.meta_value = %s 
						AND p.post_status != 'trash'
						ORDER BY p.ID DESC 
						LIMIT 1
					", $check_sku ) );

					// Fallback to trash only if no active product exists
					if ( ! $found_id ) {
						$found_id = $wpdb->get_var( $wpdb->prepare( "
							SELECT p.ID 
							FROM {$wpdb->posts} p
							INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
							WHERE pm.meta_key = '_sku' 
							AND pm.meta_value = %s 
							AND p.post_status = 'trash'
							ORDER BY p.ID DESC 
							LIMIT 1
						", $check_sku ) );
					}

					if ( $found_id ) {
						$is_imported = true;
						$p_obj = wc_get_product( $found_id );
						$imported_id = $p_obj ? ( $p_obj->get_parent_id() ?: $found_id ) : $found_id;
						break;
					}
				}
				?>
					<!-- Product Group Header Row -->
					<tr class="accordion-header active" data-group="<?php echo $group_index; ?>" onclick="toggleAccordion(this, '<?php echo $group_index; ?>')">
						<td>
							<input type="radio" name="product_selection" class="group-radio" data-group="<?php echo $group_index; ?>" onclick="event.stopPropagation(); selectGroupRadio(this, '<?php echo $group_index; ?>')">
						</td>
						<td colspan="8">
							<div style="display: flex; align-items: center; justify-content: space-between;">
								<div>
									<strong><?php echo esc_html( $product_title ); ?></strong>
									<span style="font-size: 12px; color: #666; margin-left: 10px;">
										(<?php echo count( $variations ); ?> variation<?php echo count( $variations ) !== 1 ? 's' : ''; ?>)
									</span>
								</div>
								<div style="display: flex; align-items: center; gap: 12px;">
									<?php if ( $is_imported ) : 
										$artwork_url = get_post_meta( $imported_id, '_shineon_artwork_url', true );
									?>
										<span style="font-size: 12px; color: #46b450; font-weight: bold;">
											✓ Imported (ID: <?php echo $imported_id; ?>)
										</span>
										<?php if ( $artwork_url ) : ?>
											<a href="<?php echo esc_url( $artwork_url ); ?>" target="_blank" onclick="event.stopPropagation();" style="font-size: 11px; font-weight: normal; color: #0073aa; text-decoration: underline;">View Artwork URL</a>
										<?php endif; ?>
									<?php else : ?>
										<span id="shineon-imported-label-<?php echo $group_index; ?>" style="font-size: 12px; color: #46b450; font-weight: bold; display: none;">
											✓ Imported
										</span>
									<?php endif; ?>
								</div>
							</div>
						</td>
					</tr>

					<!-- Variation Rows -->
					<?php foreach ( $variations as $product ) : ?>
						<tr class="accordion-variation" data-group="<?php echo $group_index; ?>">
							<td>
								<input type="checkbox" class="variation-checkbox" data-group="<?php echo $group_index; ?>" onclick="event.stopPropagation()" disabled>
							</td>
							<td>
								<strong><?php echo esc_html( $product['sku'] ?? '—' ); ?></strong>
							</td>
							<td>
								<?php echo esc_html( $product['sku_id'] ?? '—' ); ?>
							</td>
							<td>
								<?php echo esc_html( $product['product_template'] ?? '—' ); ?>
							</td>
							<td>
								<?php echo esc_html( $product['title'] ?? '—' ); ?>
							</td>
							<td>
								<?php echo esc_html( $product['variant_title'] ?? '—' ); ?>
							</td>
							<td>
								<?php 
								$base_cost = $product['base_cost'] ?? null;
								echo $base_cost !== null ? '$' . number_format( (float) $base_cost, 2 ) : '—';
								?>
							</td>
							<td>
								<?php
								$template_id = $product['product_template_id'] ?? '';
								if ( $template_id ) :
									$ajax_url = admin_url( 'admin-ajax.php?action=shineon_get_template_image&tid=' . esc_attr( $template_id ) );
								?>
								<img src="<?php echo esc_url( $ajax_url ); ?>" alt="Product preview" loading="lazy" style="width:75px;height:75px;object-fit:cover;display:block;border-radius:3px;margin-bottom:6px;background:#f0f0f0;">
								<?php endif; ?>
								<a href="#" class="view-renders-btn" data-template-id="<?php echo esc_attr( $template_id ); ?>" data-sku="<?php echo esc_attr( $product['sku'] ?? '' ); ?>" style="color: #0073aa; text-decoration: none; cursor: pointer;" onclick="event.preventDefault(); openRendersModal(this);">
									View Renders
								</a>
							</td>
							<td>
								<?php 
								$created_at = $product['created_at'] ?? null;
								if ( $created_at ) {
									$date = new DateTime( $created_at );
									echo esc_html( $date->format( 'M d, Y' ) );
								} else {
									echo '—';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $page_links ) : ?>
			<div class="tablenav bottom">
				<div class="alignleft actions">
					<button type="button" class="button button-primary shineon-import-btn" disabled onclick="shineonImportProduct()">
						Import to WooCommerce
					</button>
					<span class="shineon-import-status" style="font-size: 13px; color: #666; margin-left: 10px;"></span>
				</div>
				<div class="tablenav-pages">
					<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', $total_groups ), number_format_i18n( $total_groups ) ) ); ?></span>
					<span class="pagination-links"><?php echo $page_links; ?></span>
				</div>
				<br class="clear" />
			</div>
		<?php else : ?>
			<!-- Import Button (Below) -->
			<div style="margin-top: 20px; display: flex; align-items: center; gap: 15px;">
				<button type="button" class="button button-primary shineon-import-btn" disabled onclick="shineonImportProduct()">
					Import to WooCommerce
				</button>
				<span class="shineon-import-status" style="font-size: 13px; color: #666;"></span>
			</div>
		<?php endif; ?>

		<p style="color: #666; font-size: 13px; margin-top: 20px;">
			<strong>Total Product Groups:</strong> <?php echo count( $grouped_products ); ?> | 
			<strong>Total Variations:</strong> <?php echo count( $products_list ); ?>
		</p>

		<?php $this->modal->render(); ?>

		<script>
			var shineonGroupsData = <?php echo wp_json_encode( $groups_json ); ?>;
			var shineonAjaxUrl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
			var shineonNonce = '<?php echo wp_create_nonce( 'shineon_import' ); ?>';

			function toggleAccordion(headerElement, groupId) {
				headerElement.classList.toggle('active');
				const variations = document.querySelectorAll('.accordion-variation[data-group="' + groupId + '"]');
				variations.forEach(row => row.classList.toggle('expanded'));
			}

			function selectGroupRadio(radio, groupId) {
				// Uncheck all variation checkboxes in all groups
				document.querySelectorAll('.variation-checkbox').forEach(cb => cb.checked = false);
				
				// Check all variation checkboxes in the selected group
				if (radio.checked) {
					const variationCheckboxes = document.querySelectorAll('.variation-checkbox[data-group="' + groupId + '"]');
					variationCheckboxes.forEach(cb => cb.checked = true);
				}

				// Enable/disable the import buttons
				document.querySelectorAll('.shineon-import-btn').forEach(btn => btn.disabled = !radio.checked);
				document.querySelectorAll('.shineon-import-status').forEach(span => span.textContent = '');
			}

			function shineonImportProduct() {
				var selectedRadio = document.querySelector('.group-radio:checked');
				if (!selectedRadio) {
					alert('Please select a product group to import.');
					return;
				}

				var groupId = selectedRadio.getAttribute('data-group');
				var groupData = shineonGroupsData[groupId];
				if (!groupData) {
					alert('Could not find product data for the selected group.');
					return;
				}

				// Instead of importing immediately, open the modal in 'import' mode
				// We need a template ID for the modal, use the first variation's template ID
				var firstVariation = groupData.variations[0];
				var element = document.createElement('div');
				element.setAttribute('data-template-id', firstVariation.product_template_id);
				element.setAttribute('data-sku', groupData.product_title); // Use title as "SKU" label for the group
				
				openRendersModal(element, 'import');
			}

			function shineonImportProductWithImages(imageUrl) {
				var selectedRadio = document.querySelector('.group-radio:checked');
				var groupId = selectedRadio.getAttribute('data-group');
				var groupData = shineonGroupsData[groupId];

				var btn = document.getElementById('modal-action-btn');
				var statusSpans = document.querySelectorAll('.shineon-import-status');
				var modalGallery = document.getElementById('renders-gallery');
				
				btn.disabled = true;
				btn.textContent = 'Preparing...';
				statusSpans.forEach(span => span.textContent = 'Preparing import...');
				
				// Phase 1: Initialize (Create Parent)
				var formData = new FormData();
				formData.append('action', 'shineon_init_import');
				formData.append('nonce', shineonNonce);
				formData.append('product_data', JSON.stringify(groupData));
				formData.append('artwork_url', imageUrl); // Pass artwork URL to store in meta

				fetch(shineonAjaxUrl, { method: 'POST', body: formData })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					console.log('ShineOn Init Response:', res);
					if (!res || res.success === false || !res.data) {
						var msg = (res && res.data && res.data.message) ? res.data.message : 'Failed to initialize import.';
						throw new Error(msg);
					}
					
					var parentId = res.data.product_id;
					var variations = res.data.variations;
					var total = variations.length;
					var current = 0;
					var allUploadedImageIds = [];

					function importNextVariation() {
						if (current >= total) {
							finalizeImport(parentId, allUploadedImageIds);
							return;
						}

						btn.textContent = 'Importing ' + (current + 1) + '/' + total;
						document.getElementById('shineon-progress-text').textContent = 'Processing variation ' + (current + 1) + ' of ' + total + '...';

						var varData = new FormData();
						varData.append('action', 'shineon_import_variation');
						varData.append('nonce', shineonNonce);
						varData.append('parent_id', parentId);
						varData.append('variation_data', JSON.stringify(variations[current]));
						varData.append('image_url', imageUrl);

						fetch(shineonAjaxUrl, { method: 'POST', body: varData })
						.then(function(r) { return r.json(); })
						.then(function(res) {
							if (res && res.success && res.data.attachment_ids) {
								allUploadedImageIds = allUploadedImageIds.concat(res.data.attachment_ids);
							}
							current++;
							importNextVariation();
						})
						.catch(function(e) {
							console.error('Variation network error', e);
							current++;
							importNextVariation();
						});
					}

					importNextVariation();
				})
				.catch(function(err) {
					btn.textContent = 'Import + Images';
					btn.disabled = false;
					var errorMsg = err.message || 'An unknown error occurred.';
					modalGallery.innerHTML = '<div style="color:#dc3545; padding:20px; text-align:center;">✗ Error: ' + errorMsg + '</div>';
				});

				function finalizeImport(parentId, allImageIds) {
					btn.textContent = 'Finalizing...';
					document.getElementById('shineon-progress-text').textContent = 'Optimizing gallery and prices...';

					var finalData = new FormData();
					finalData.append('action', 'shineon_finalize_import');
					finalData.append('nonce', shineonNonce);
					finalData.append('parent_id', parentId);
					finalData.append('all_image_ids', JSON.stringify(allImageIds));

					fetch(shineonAjaxUrl, { method: 'POST', body: finalData })
					.then(function(r) { return r.json(); })
					.then(function(data) {
						btn.textContent = 'Import + Images';
						btn.disabled = false;
						closeRendersModal();
						
						var editUrl = (data.data && data.data.edit_url) ? data.data.edit_url : '';
						var newId = (data.data && data.data.product_id) ? data.data.product_id : '';
						
						document.querySelectorAll('.shineon-import-status').forEach(span => {
							span.style.color = '#46b450';
							span.innerHTML = '✓ Imported successfully! <a href="' + editUrl + '" target="_blank">Edit product</a>';
						});
						
						var importedLabel = document.getElementById('shineon-imported-label-' + groupId);
						if (importedLabel) { 
							var artworkHtml = imageUrl ? ' <a href="' + imageUrl + '" target="_blank" style="font-size: 11px; font-weight: normal; color: #0073aa; margin-left: 10px;">View Artwork URL</a>' : '';
							importedLabel.innerHTML = '✓ Imported (ID: ' + newId + ')' + artworkHtml;
							importedLabel.style.display = 'flex'; 
							importedLabel.style.alignItems = 'center';
						}
					});
				}
			}
		</script>
		<?php
	}

	/**
	 * Render settings page (deprecated - kept for backwards compatibility)
	 */
	public function render_settings_page() {
		$this->render_tabbed_page();
	}

	/**
	 * Send order details to ShineOn API
	 *
	 * @param int $order_id WooCommerce order ID
	 */
	public function send_order_to_shineon( $order_id ) {
		if ( ! ShineOn_Settings::get_api_key() ) {
			error_log( 'ShineOn: API Key not configured' );
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Only include items whose parent product is tagged 'shineon'
		$shineon_items = $this->get_shineon_order_items( $order );
		if ( empty( $shineon_items ) ) {
			return; // No ShineOn products in this order
		}

		$order_data = array(
			'order' => array(
				'source_id'                 => (string) $order->get_id(),
				'email'                     => $order->get_billing_email(),
				'test'                      => ShineOn_Settings::TEST_MODE,
				'shipment_notification_url' => get_rest_url( null, 'shineon/v1/shipment-notification' ),
				'line_items'                => $shineon_items,
				'total_price'               => (float) $order->get_total(),
				'subtotal_price'            => (float) $order->get_subtotal(),
				'total_tax'                 => (float) $order->get_total_tax(),
				'total_shipping'            => (float) $order->get_shipping_total(),
				'currency'                  => $order->get_currency(),
				'shipping_address'          => array(
					'name'         => ( $order->get_shipping_first_name() || $order->get_shipping_last_name() ) 
										? ( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() )
										: ( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'address1'     => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
					'city'         => $order->get_shipping_city() ?: $order->get_billing_city(),
					'zip'          => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
					'country_code' => $order->get_shipping_country() ?: $order->get_billing_country(),
				),
			),
		);

		error_log( 'ShineOn: Sending order ' . $order->get_id() . ' to API: ' . wp_json_encode( $order_data ) );
		$result = ShineOn_Settings::request( '/orders', 'POST', $order_data );

		if ( is_wp_error( $result ) ) {
			error_log( 'ShineOn Order API: Request failed for order ' . $order->get_id() . ' - Error: ' . $result->get_error_message() );
		} else {
			error_log( 'ShineOn Order API: Request successful for order ' . $order->get_id() . ' - Response: ' . wp_json_encode( $result ) );
		}
	}

	/**
	 * Get only ShineOn-tagged order items
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	private function get_shineon_order_items( $order ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			// Check if the parent product has the 'shineon' tag
			if ( ! has_term( ShineOn_Settings::PRODUCT_TAG, 'product_tag', $product_id ) ) {
				continue;
			}

			$product = $item->get_product();
			$sku     = $product ? $product->get_sku() : '';

			$item_data = array(
				'store_line_item_id' => (string) $item->get_id(),
				'sku'                => $sku,
				'quantity'           => (int) $item->get_quantity(),
				'price'              => (float) ( $item->get_total() / $item->get_quantity() ),
			);

			// Add artwork URL if it exists in the parent product meta
			$artwork_url = get_post_meta( $product_id, '_shineon_artwork_url', true );
			if ( $artwork_url ) {
				$item_data['print_url'] = $artwork_url; // Some templates use this
				$item_data['personalizations'] = array(
					array(
						'key'   => 'front',
						'value' => $artwork_url
					)
				);
			}

			$items[] = $item_data;
		}
		return $items;
	}



	/**
	 * Phase 1: Initialize Import (Create Parent Product)
	 */
	public function handle_init_import() {
		check_ajax_referer( 'shineon_import', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ) );
		}

		$raw = isset( $_POST['product_data'] ) ? wp_unslash( $_POST['product_data'] ) : '';
		$product_data = json_decode( $raw, true );
		
		error_log( 'ShineOn Import: Product Data received: ' . ( $product_data ? 'YES' : 'NO' ) );

		if ( empty( $product_data ) || empty( $product_data['product_title'] ) || empty( $product_data['variations'] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid product data.' ) );
		}

		$variations = $product_data['variations'];
		$product_name = sanitize_text_field( $variations[0]['title'] ?? $product_data['product_title'] );

		try {
			$product = new WC_Product_Variable();
			$product->set_name( $product_name );
			$product->set_status( 'draft' );

			// Detect attributes from the variations
			$attributes = array();
			$attr_data = array(); // label => array(values)

			// Try to find option names from the first variation or defaults
			$option_names = array(
				1 => $variations[0]['option1_name'] ?? 'Style',
				2 => $variations[0]['option2_name'] ?? 'Box Type',
				3 => $variations[0]['option3_name'] ?? 'Option 3',
			);
			
			// Fetch real option names from API
			$template_id = intval( $variations[0]['product_template_id'] ?? 0 );
			if ( $template_id > 0 ) {
				$template_data = ShineOn_Settings::request( '/product_templates/' . $template_id );
				if ( ! is_wp_error( $template_data ) && isset( $template_data['options'] ) ) {
					$option_names[1] = $template_data['options'][0] ?? $option_names[1];
					$option_names[2] = $template_data['options'][1] ?? $option_names[2];
					$option_names[3] = $template_data['options'][2] ?? $option_names[3];
				}
			}

			foreach ( $variations as $v ) {
				for ( $i = 1; $i <= 3; $i++ ) {
					$opt_val = $v['option' . $i] ?? ($v['option' . $i . '_value'] ?? '');
					if ( $opt_val ) {
						$label = $option_names[$i];
						if ( ! isset( $attr_data[$label] ) ) {
							$attr_data[$label] = array();
						}
						if ( ! in_array( $opt_val, $attr_data[$label] ) ) {
							$attr_data[$label][] = $opt_val;
						}
					}
				}
			}

			// If no multi-options found, fallback to single "Style" attribute
			if ( empty( $attr_data ) ) {
				$variant_titles = array();
				foreach ( $variations as $v ) {
					$vt = sanitize_text_field( $v['title'] ?? '' );
					if ( $vt && ! in_array( $vt, $variant_titles, true ) ) {
						$variant_titles[] = $vt;
					}
				}
				if ( ! empty( $variant_titles ) ) {
					$attr_data['Style'] = $variant_titles; // Using 'Style' instead of 'Variant' to avoid conflicts
				}
			}

			$wc_attributes = array();
			foreach ( $attr_data as $label => $options ) {
				$attribute = new WC_Product_Attribute();
				// Use the exact label but ensure we are consistent with lowercase for mapping
				$attribute->set_name( $label );
				$attribute->set_options( $options );
				$attribute->set_visible( true );
				$attribute->set_variation( true );
				$wc_attributes[] = $attribute;
			}

			if ( ! empty( $wc_attributes ) ) {
				$product->set_attributes( $wc_attributes );
			}

			$parent_id = $product->save();
			wp_set_object_terms( $parent_id, ShineOn_Settings::PRODUCT_TAG, 'product_tag', true );

			// Store artwork URL in meta if provided
			$artwork_url = isset( $_POST['artwork_url'] ) ? sanitize_url( $_POST['artwork_url'] ) : '';
			if ( $artwork_url ) {
				update_post_meta( $parent_id, '_shineon_artwork_url', $artwork_url );
			}

			wp_send_json_success( array(
				'product_id' => $parent_id,
				'variations' => $variations,
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Phase 2: Import single variation with image
	 */
	public function handle_import_variation() {
		check_ajax_referer( 'shineon_import', 'nonce' );

		$parent_id = intval( $_POST['parent_id'] );
		$v = json_decode( wp_unslash( $_POST['variation_data'] ), true );
		$image_url = sanitize_url( $_POST['image_url'] );
		
		error_log( 'ShineOn Import: Variation SKU received: ' . ($v['sku'] ?? 'N/A') );

		if ( ! $parent_id || ! $v ) {
			wp_send_json_error( array( 'message' => 'Missing data.' ) );
		}

		try {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent_id );

			$sku = sanitize_text_field( $v['sku'] ?? '' );
			if ( $sku ) {
				$variation->set_sku( $sku );
			}

			$base_cost = floatval( $v['base_cost'] ?? 0 );
			if ( $base_cost > 0 ) {
				// We set a default regular price (markup) so the base_cost shows as the Sale Price
				$regular_price = $base_cost * 2; 
				$variation->set_regular_price( $regular_price );
				$variation->set_sale_price( $base_cost );
				$variation->set_price( $base_cost ); // Explicitly set active price
			}

			// Map attributes for this variation
			$option_names = array(
				1 => $v['option1_name'] ?? 'Style',
				2 => $v['option2_name'] ?? 'Box Type',
				3 => $v['option3_name'] ?? 'Option 3',
			);
			
			// Fetch real option names from API
			$template_id = intval( $v['product_template_id'] ?? 0 );
			if ( $template_id > 0 ) {
				$template_data = ShineOn_Settings::request( '/product_templates/' . $template_id );
				if ( ! is_wp_error( $template_data ) && isset( $template_data['options'] ) ) {
					$option_names[1] = $template_data['options'][0] ?? $option_names[1];
					$option_names[2] = $template_data['options'][1] ?? $option_names[2];
					$option_names[3] = $template_data['options'][2] ?? $option_names[3];
				}
			}

			$var_attributes = array();
			$has_multi_options = false;
			for ( $i = 1; $i <= 3; $i++ ) {
				$opt_val = $v['option' . $i] ?? ($v['option' . $i . '_value'] ?? '');
				if ( $opt_val ) {
					// Use sanitized labels as keys to match WooCommerce behavior
					$label = $option_names[$i];
					$slug = sanitize_title( $label );
					$var_attributes[$slug] = $opt_val;
					$has_multi_options = true;
				}
			}

			if ( ! $has_multi_options ) {
				$variant_title = sanitize_text_field( $v['title'] ?? '' );
				if ( $variant_title ) {
					// Match the fallback label used in init_import
					$var_attributes['style'] = $variant_title;
				}
			}

			if ( ! empty( $var_attributes ) ) {
				$variation->set_attributes( $var_attributes );
			}

			// Handle variation image
			$sku_id = isset( $v['sku_id'] ) ? $v['sku_id'] : '';
			$template_id = isset( $v['product_template_id'] ) ? $v['product_template_id'] : '';
			$variation_image_ids = array();
			
			if ( $sku ) {
				$variation_image_ids = $this->generate_and_sideload_renders( $sku, $sku_id, $image_url, $template_id );
			}
			
			if ( ! empty( $variation_image_ids ) ) {
				$variation->set_image_id( $variation_image_ids[0] );
				// Store all generated render IDs for this variation in meta
				update_post_meta( $variation->get_id(), '_shineon_all_render_ids', $variation_image_ids );
			}

			$variation->set_status( 'publish' );
			$variation->set_manage_stock( false );
			$variation->save();

			// Meta for reference
			$template_id = sanitize_text_field( $v['product_template_id'] ?? '' );
			if ( $template_id ) {
				update_post_meta( $variation->get_id(), '_shineon_product_template_id', $template_id );
			}

			wp_send_json_success( array(
				'attachment_ids' => $variation_image_ids
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Phase 3: Finalize (Gallery and Sync)
	 */
	public function handle_finalize_import() {
		check_ajax_referer( 'shineon_import', 'nonce' );
		$parent_id = intval( $_POST['parent_id'] );

		$product = wc_get_product( $parent_id );
		if ( ! $product ) wp_send_json_error();

		$raw_images = isset( $_POST['all_image_ids'] ) ? wp_unslash( $_POST['all_image_ids'] ) : '[]';
		$all_image_ids = json_decode( $raw_images, true );

		if ( ! is_array( $all_image_ids ) ) {
			$all_image_ids = array();
		}

		if ( ! empty( $all_image_ids ) ) {
			$all_image_ids = array_unique( $all_image_ids );
			$product->set_image_id( $all_image_ids[0] );
			$product->set_gallery_image_ids( array_slice( $all_image_ids, 1 ) );
		}

		// Automatically set the first variation as default to ensure WooPayments/WooPay compatibility
		$attributes = $product->get_attributes();
		$default_attributes = array();
		foreach ( $attributes as $name => $attribute ) {
			$options = $attribute->get_options();
			if ( ! empty( $options ) ) {
				$default_attributes[ sanitize_title( $name ) ] = $options[0];
			}
		}
		$product->set_default_attributes( $default_attributes );

		$product->set_status( 'publish' );
		$product->save();

		// Force WooCommerce to refresh the product data
		wc_delete_product_transients( $parent_id );
		clean_post_cache( $parent_id );

		WC_Product_Variable::sync( $parent_id );

		wp_send_json_success( array(
			'product_id' => $parent_id,
			'edit_url'   => admin_url( 'post.php?post=' . $parent_id . '&action=edit' ),
		) );
	}

	/**
	 * Generate renders for a specific SKU and sideload them to the media library.
	 *
	 * @param string $sku       The ShineOn SKU string.
	 * @param string $sku_id    The ShineOn numeric SKU ID (as fallback).
	 * @param string $image_url The source image URL.
	 * @return array Attachment IDs.
	 */
	private function generate_and_sideload_renders( $sku, $sku_id, $image_url, $template_id = '' ) {
		error_log( 'ShineOn Import: Generating renders for SKU: ' . $sku . ( $image_url ? ' with Artwork' : ' without Artwork (using default template images)' ) );
		
		// If no artwork provided, we fetch the default transformations from the template
		if ( ! $image_url && $template_id ) {
			$template_data = ShineOn_Settings::request( '/product_templates/' . intval( $template_id ) );
			if ( ! is_wp_error( $template_data ) && isset( $template_data['transformations'] ) ) {
				$attachment_ids = array();
				// Use the first 2 transformations as base images if possible
				$transforms = array_slice( $template_data['transformations'], 0, 2 );
				foreach ( $transforms as $index => $t ) {
					$base_url = $t['layers']['main'] ?? '';
					if ( $base_url ) {
						// Download the default template image
						$aid = $this->sideload_image( $base_url, $sku . '-template-' . ($index + 1) );
						if ( $aid ) $attachment_ids[] = $aid;
					}
				}
				return $attachment_ids;
			}
		}

		if ( ! $image_url || ! $sku_id ) {
			return array();
		}

		// 1. Get SKU details to find render_ids
		$sku_res = ShineOn_Settings::request( '/skus/' . urlencode( $sku ) );
		
		if ( is_wp_error( $sku_res ) && $sku_id ) {
			$sku_res = ShineOn_Settings::request( '/skus/' . urlencode( $sku_id ) );
		}
		
		if ( is_wp_error( $sku_res ) ) {
			return array();
		}

		$render_ids = null;
		if ( isset( $sku_res['sku']['renders'] ) ) {
			$render_ids = $sku_res['sku']['renders'];
		} elseif ( isset( $sku_res['renders'] ) ) {
			$render_ids = $sku_res['renders'];
		} elseif ( isset( $sku_res['sku']['render_ids'] ) ) {
			$render_ids = $sku_res['sku']['render_ids'];
		} elseif ( isset( $sku_res['render_ids'] ) ) {
			$render_ids = $sku_res['render_ids'];
		}

		if ( empty( $render_ids ) ) {
			return array();
		}

		// Set to 2 renders per variation for a richer gallery
		$render_ids = array_slice( $render_ids, 0, 2 );
		$attachment_ids = array();

		foreach ( $render_ids as $index => $render_id ) {
			error_log( 'ShineOn Import: Processing render ' . ($index + 1) . ' (ID: ' . $render_id . ')' );

			// 2. Call /renders/:render_id/make
			$render_res = ShineOn_Settings::request( '/renders/' . $render_id . '/make', 'POST', array( 'src' => $image_url ) );
			if ( is_wp_error( $render_res ) ) {
				continue;
			}

			// 3. Extract the rendered image URL
			$data_res = isset( $render_res['data'] ) ? $render_res['data'] : $render_res;
			$rendered_url = null;
			
			if ( isset( $data_res['render']['make']['src'] ) ) {
				$rendered_url = $data_res['render']['make']['src'];
			} elseif ( isset( $data_res['make']['src'] ) ) {
				$rendered_url = $data_res['make']['src'];
			} elseif ( isset( $data_res['render']['layers']['main'] ) ) {
				$rendered_url = $data_res['render']['layers']['main'];
			}
			
			if ( ! $rendered_url ) {
				continue;
			}

			// 4. Sideload the image
			$attachment_id = $this->sideload_image( $rendered_url, $sku . '-' . ($index + 1) );
			if ( $attachment_id ) {
				$attachment_ids[] = $attachment_id;
			}
		}

		return $attachment_ids;
	}

	/**
	 * Sideload an image from a URL into the WordPress Media Library.
	 *
	 * @param string $url  Image URL.
	 * @param string $desc Description/title.
	 * @return int|bool Attachment ID or false on failure.
	 */
	private function sideload_image( $url, $desc ) {
		// Check cache first to avoid duplicate downloads
		if ( isset( $this->download_cache[ $url ] ) ) {
			error_log( 'ShineOn Import: Reusing cached image ID for URL: ' . $url );
			return $this->download_cache[ $url ];
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download the image
		$temp_file = download_url( $url );
		if ( is_wp_error( $temp_file ) ) {
			return false;
		}

		$file_array = array(
			'name'     => $desc . '.png',
			'tmp_name' => $temp_file,
		);

		// Do the sideload
		$id = media_handle_sideload( $file_array, 0, $desc );

		// If error, delete temp file
		if ( is_wp_error( $id ) ) {
			error_log( 'ShineOn Import: Sideload Error: ' . $id->get_error_message() );
			@unlink( $file_array['tmp_name'] );
			return false;
		}

		error_log( 'ShineOn Import: Successfully sideloaded image! Attachment ID: ' . $id );
		
		// Cache the result
		$this->download_cache[ $url ] = $id;

		return $id;
	}

	/**
	 * Register REST API routes for webhooks
	 */
	public function register_rest_routes() {
		register_rest_route( 'shineon/v1', '/shipment-notification', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_shipment_notification' ),
			'permission_callback' => '__return_true', 
		) );
	}

	/**
	 * Handle shipment notification from ShineOn
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_shipment_notification( $request ) {
		$params = $request->get_json_params();
		error_log( 'ShineOn: Received shipment notification: ' . wp_json_encode( $params ) );

		if ( empty( $params['order'] ) || empty( $params['order']['store_order_id'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
		}

		$order_id = intval( $params['order']['store_order_id'] );
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Order not found' ), 404 );
		}

		// Update tracking info if provided
		if ( ! empty( $params['order']['line_items'] ) ) {
			foreach ( $params['order']['line_items'] as $item ) {
				if ( ! empty( $item['tracking_number'] ) ) {
					$carrier = $item['tracking_company'] ?? 'Carrier';
					$number = $item['tracking_number'];
					
					// Add order note with tracking
					$order->add_order_note( sprintf( __( 'ShineOn: Product shipped via %s. Tracking: %s', 'shineon-for-woocommerce' ), $carrier, $number ) );
				}
			}
		}

		// Set order to completed if it's currently processing or on-hold
		if ( $order->has_status( array( 'processing', 'on-hold' ) ) ) {
			$order->update_status( 'completed', __( 'ShineOn shipment notification received.', 'shineon-for-woocommerce' ) );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Inject defensive JS to handle conflicts with WooPayments/WooPay
	 */
	public function inject_defensive_js() {
		if ( ! is_product() ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(function($) {
			$(document.body).on('found_variation', function(event, variation) {
				// Force enable the button when a valid variation is selected
				var $button = $('.single_add_to_cart_button');
				if (variation && variation.variation_id > 0) {
					$button.removeClass('disabled wc-variation-is-unavailable').prop('disabled', false);
					// Debug log to confirm it fired
					console.log('ShineOn Compatibility: Variation found ' + variation.variation_id + ', forcing button enable.');
				}
			});

			$(document.body).on('reset_data', function() {
				// Re-disable if no variation is selected (Standard WooCommerce behavior)
				$('.single_add_to_cart_button').addClass('disabled').prop('disabled', true);
			});
		});
		</script>
		<?php
	}
}
