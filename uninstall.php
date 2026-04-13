<?php
/**
 * ShineOn for WooCommerce Uninstall
 *
 * This file is executed when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Delete plugin settings
delete_option( 'shineon_api_key' );
delete_option( 'shineon_settings' );

// 2. Delete product metadata created by the plugin
global $wpdb;

$meta_keys = array(
	'_shineon_artwork_url',
	'_shineon_product_template_id',
	'_shineon_all_render_ids',
);

foreach ( $meta_keys as $key ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $key ) );
}

// 3. Clear any transient data if applicable
delete_transient( 'shineon_products_cache' );
