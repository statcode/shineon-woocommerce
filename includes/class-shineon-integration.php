<?php

class ShineOn_Integration {

	private $api_key;
	private $api_base_url = 'https://api.shineon.com'; // Update with actual ShineOn API endpoint

	public function __construct() {
		$this->api_key = get_option( 'shineon_api_key' );
		$this->init();
	}

	public function init() {
		// Add admin menu for settings
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		
		// Register settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// Send order to ShineOn API when order is placed
		add_action( 'woocommerce_order_status_completed', array( $this, 'send_order_to_shineon' ) );
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
			'dashicons-admin-tools',
			65
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'shineon_settings', 'shineon_api_key' );

		add_settings_section(
			'shineon_main',
			'ShineOn API Configuration',
			array( $this, 'render_settings_section' ),
			'shineon_settings'
		);

		add_settings_field(
			'shineon_api_key',
			'API Key',
			array( $this, 'render_api_key_field' ),
			'shineon_settings',
			'shineon_main'
		);
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
		$api_key = get_option( 'shineon_api_key' );
		echo '<input type="text" name="shineon_api_key" value="' . esc_attr( $api_key ) . '" size="50" />';
		echo '<p class="description">Your unique ShineOn API Key for authentication</p>';
	}

	/**
	 * Render tabbed settings page
	 */
	public function render_tabbed_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'api-key';
		?>
		<div class="wrap">
			<h1>ShineOn</h1>

			<!-- Tab Navigation -->
			<div class="nav-tab-wrapper" style="border-bottom: 2px solid #ccc; padding-bottom: 0; margin: 20px 0 0 0;">
				<a href="?page=shineon-settings&tab=api-key" class="nav-tab <?php echo $tab === 'api-key' ? 'nav-tab-active' : ''; ?>" style="<?php echo $tab === 'api-key' ? 'border-top: 3px solid #0073aa; border-bottom: none; background: #f1f1f1;' : 'border-top: 3px solid transparent;'; ?>">
					API Key
				</a>
				<a href="?page=shineon-settings&tab=products" class="nav-tab <?php echo $tab === 'products' ? 'nav-tab-active' : ''; ?>" style="<?php echo $tab === 'products' ? 'border-top: 3px solid #0073aa; border-bottom: none; background: #f1f1f1;' : 'border-top: 3px solid transparent;'; ?>">
					My Products
				</a>
			</div>

			<!-- Tab Content -->
			<div class="shineon-tab-content" style="background: #fff; padding: 20px; margin-top: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<?php
				if ( $tab === 'products' ) {
					$this->render_products_tab();
				} else {
					$this->render_api_key_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render API Key Tab
	 */
	public function render_api_key_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'shineon_settings' );
			do_settings_sections( 'shineon_settings' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render Products Tab
	 */
	public function render_products_tab() {
		$api_key = get_option( 'shineon_api_key' );
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
				<?php $this->render_products_table(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Fetch products from ShineOn API
	 */
	private function fetch_shineon_products() {
		$api_key = get_option( 'shineon_api_key' );
		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', 'API Key not configured' );
		}

		$url = 'https://api.shineon.com/v1/skus';

		$args = array(
			'method'  => 'GET',
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 15,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'ShineOn Products API Error: ' . $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code >= 400 ) {
			error_log( 'ShineOn Products API Error (' . $response_code . '): ' . $response_body );
			return new WP_Error( 'api_error', 'ShineOn API returned error code: ' . $response_code );
		}

		$data = json_decode( $response_body, true );
		return $data;
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
			.shineon-accordion-table tbody tr.accordion-variation.expanded {
				display: table-row;
			}
			.shineon-accordion-table tbody tr.accordion-variation td {
				padding-left: 40px !important;
				border-left: 3px solid #0073aa;
			}
			.accordion-toggle-icon {
				display: inline-block;
				margin-right: 8px;
				font-weight: bold;
				width: 20px;
			}
		</style>

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
					<th style="width: 150px;">Mask Image</th>
					<th style="width: 150px; cursor: pointer;">
						<a href="<?php echo esc_url( $created_sort_url ); ?>" style="color: inherit; text-decoration: none;">
							Created
							<?php 
							if ( $orderby === 'created_at' ) {
								echo $order === 'asc' ? ' ▲' : ' ▼';
							}
							?>
						</a>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php $group_index = 0; foreach ( $grouped_products as $product_title => $variations ) : $group_index++; ?>
					<!-- Product Group Header Row -->
					<tr class="accordion-header" data-group="<?php echo $group_index; ?>" onclick="toggleAccordion(this, '<?php echo $group_index; ?>')">
						<td>
							<input type="checkbox" class="group-checkbox" data-group="<?php echo $group_index; ?>" onclick="event.stopPropagation(); toggleGroupCheckbox(this, '<?php echo $group_index; ?>')">
						</td>
						<td colspan="8">
							<span class="accordion-toggle-icon">▶</span>
							<strong><?php echo esc_html( $product_title ); ?></strong>
							<span style="font-size: 12px; color: #666; margin-left: 10px;">
								(<?php echo count( $variations ); ?> variation<?php echo count( $variations ) !== 1 ? 's' : ''; ?>)
							</span>
						</td>
					</tr>

					<!-- Variation Rows -->
					<?php foreach ( $variations as $product ) : ?>
						<tr class="accordion-variation" data-group="<?php echo $group_index; ?>">
							<td>
								<input type="checkbox" class="variation-checkbox" data-group="<?php echo $group_index; ?>" onclick="event.stopPropagation()">
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
								$mask_url = $product['artwork']['mask_src_url'] ?? null;
								if ( $mask_url ) : 
									?>
									<a href="<?php echo esc_url( $mask_url ); ?>" target="_blank" style="color: #0073aa; text-decoration: none;">
										View Image
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
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

		<p style="color: #666; font-size: 13px; margin-top: 20px;">
			<strong>Total Product Groups:</strong> <?php echo count( $grouped_products ); ?> | 
			<strong>Total Variations:</strong> <?php echo count( $products_list ); ?>
		</p>

		<script>
			function toggleAccordion(headerElement, groupId) {
				headerElement.classList.toggle('active');
				const variations = document.querySelectorAll('.accordion-variation[data-group="' + groupId + '"]');
				variations.forEach(row => row.classList.toggle('expanded'));
				
				const icon = headerElement.querySelector('.accordion-toggle-icon');
				if (headerElement.classList.contains('active')) {
					icon.textContent = '▼';
				} else {
					icon.textContent = '▶';
				}
			}

			function toggleGroupCheckbox(checkbox, groupId) {
				const isChecked = checkbox.checked;
				const variationCheckboxes = document.querySelectorAll('.variation-checkbox[data-group="' + groupId + '"]');
				variationCheckboxes.forEach(cb => cb.checked = isChecked);
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
		if ( ! $this->api_key ) {
			error_log( 'ShineOn: API Key not configured' );
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$order_data = array(
			'order_id'       => $order->get_id(),
			'customer_email' => $order->get_billing_email(),
			'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'items'          => $this->get_order_items( $order ),
			'shipping_address' => array(
				'street'  => $order->get_shipping_address_1(),
				'city'    => $order->get_shipping_city(),
				'state'   => $order->get_shipping_state(),
				'zip'     => $order->get_shipping_postcode(),
				'country' => $order->get_shipping_country(),
			),
		);

		$this->call_shineon_api( '/orders', 'POST', $order_data );
	}

	/**
	 * Get order items in the format expected by ShineOn API
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	private function get_order_items( $order ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'product_id' => $item->get_product_id(),
				'quantity'   => $item->get_quantity(),
				'price'      => $item->get_total(),
			);
		}
		return $items;
	}

	/**
	 * Call ShineOn API
	 *
	 * @param string $endpoint API endpoint path
	 * @param string $method HTTP method (GET, POST, etc.)
	 * @param array  $data Request body data
	 * @return array|WP_Error
	 */
	private function call_shineon_api( $endpoint, $method = 'GET', $data = array() ) {
		$url = $this->api_base_url . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
		);

		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'ShineOn API Error: ' . $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code >= 400 ) {
			error_log( 'ShineOn API Error (' . $response_code . '): ' . $response_body );
			return new WP_Error( 'api_error', 'ShineOn API returned error code: ' . $response_code );
		}

		return json_decode( $response_body, true );
	}
}
