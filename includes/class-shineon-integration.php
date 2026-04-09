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
			'ShineOn Settings',
			'ShineOn',
			'manage_options',
			'shineon-settings',
			array( $this, 'render_settings_page' ),
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
		echo 'Enter your ShineOn API credentials below. Documentation can be found at <a href="https://github.com/ShineOnCom/api/wiki/How-to-make-an-API-Request" target="_blank">ShineOn API Documentation</a>';
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
	 * Render settings page
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1>ShineOn Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'shineon_settings' );
				do_settings_sections( 'shineon_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
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
