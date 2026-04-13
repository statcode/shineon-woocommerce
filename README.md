# ShineOn for WooCommerce

A WordPress plugin that integrates WooCommerce with the ShineOn API for automated order fulfillment. This is the unofficial plugin for ShineOn.

## Features

- Automatic order synchronization to ShineOn
- Easy API key configuration via WordPress admin
- Real-time order transmission on completion
- Support for order items, customer details, and shipping information

## Installation

1. Clone or download this plugin to `/wp-content/plugins/shineon-for-woocommerce/`
2. Activate the plugin from WordPress admin
3. Go to **ShineOn** menu in WordPress admin
4. Enter your ShineOn API Key
5. Save settings

## Configuration

### Getting Your API Key

1. Log in to your ShineOn account at https://shineon.com
2. Navigate to API settings
3. Generate or copy your API key
4. Paste it into the ShineOn settings page in WordPress

## Usage

Once activated and configured:

1. Create and process orders in WooCommerce normally
2. When an order status is changed to **Completed**, the plugin automatically sends the order details to ShineOn
3. ShineOn receives the order and begins fulfillment
4. Check ShineOn dashboard for order status

## Requirements

- WordPress 5.0+
- WooCommerce 2.1+
- Valid ShineOn account and API key

## Version

1.0 - Initial release