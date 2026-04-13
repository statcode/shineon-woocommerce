<?php
/**
 * ShineOn Settings
 *
 * Centralized configuration for the ShineOn plugin.
 * All classes should reference this instead of duplicating API config.
 *
 * @package ShineOn_For_WooCommerce
 */

class ShineOn_Settings {

	/**
	 * @var string ShineOn API base URL.
	 */
	const API_BASE_URL = 'https://api.shineon.com/v2';

	/**
	 * @var string WordPress option key for the API key.
	 */
	const API_KEY_OPTION = 'shineon_api_key';

	/**
	 * @var string WordPress option key for test mode.
	 */
	const TEST_MODE_OPTION = 'shineon_test_mode';

	/**
	 * @var string Product tag used to identify ShineOn-imported products.
	 */
	const PRODUCT_TAG = 'shineon';

	/**
	 * Checks if test mode is enabled.
	 *
	 * @return boolean
	 */
	public static function is_test_mode() {
		return get_option( self::TEST_MODE_OPTION, 'no' ) === 'yes';
	}

	/**
	 * Get the stored API key.
	 *
	 * @return string|false The API key or false if not set.
	 */
	public static function get_api_key() {
		return get_option( self::API_KEY_OPTION );
	}

	/**
	 * Get the API base URL.
	 *
	 * @return string
	 */
	public static function get_api_base_url() {
		return self::API_BASE_URL;
	}

	/**
	 * Make a request to the ShineOn API.
	 *
	 * @param string $endpoint API endpoint (e.g., '/skus').
	 * @param string $method   HTTP method.
	 * @param array  $data     Request body.
	 * @return array|WP_Error
	 */
	public static function request( $endpoint, $method = 'GET', $data = array() ) {
		$api_key = self::get_api_key();
		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', 'API Key not configured' );
		}

		$url = self::get_api_base_url() . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code >= 400 ) {
			return new WP_Error( 'api_error', 'ShineOn API error (' . $response_code . '): ' . $response_body );
		}

		return json_decode( $response_body, true );
	}
}
