<?php

/*
 * Plugin Name: ShineOn for WooCommerce
 * Plugin URI: https://github.com/statcode/shineon-woocommerce
 * Description: Integrate WooCommerce orders with ShineOn API for order fulfillment.
 * Version: 1.0
 * Author: AuditAct
 * Author URI: https://auditact.ai
 */

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	function shineon_init() {
		if ( ! class_exists( 'ShineOn_Integration' ) ) {
			require_once 'includes/class-shineon-integration.php';

			new ShineOn_Integration();
		}
	}

	add_action( 'plugins_loaded', 'shineon_init' );
}
