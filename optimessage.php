<?php
/**
 * Plugin Name: OptiMessage
 * Description: A powerful, open source SMS notification and bulk messaging framework scaling Twilio for WooCommerce.
 * Version: 0.5.0_beta
 * Author: Noor Nabiul Alam Siddiqui
 * GitHub Plugin URI: https://github.com/noor-siddiqui/OptiMessage
 *
 * @package OptiMessage
 * @author  Noor Nabiul Alam Siddiqui <siddiqui.sazal@gmail.com>
 * @license https://github.com/noor-siddiqui/OptiMessage/blob/main/LICENSE GNU General Public License v3.0
 * @link    https://github.com/noor-siddiqui/OptiMessage
 * @since   0.1.0_beta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'OM_VERSION', '0.5.0_beta' );
define( 'OM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// 1. Require the newly named class file.
require_once OM_PLUGIN_DIR . 'includes/class-optimessage.php';

// 2. Hook activation using the new class name.
register_activation_hook( __FILE__, array( 'OptiMessage', 'install' ) );

/**
 * Initialize the plugin.
 */
function optimessage_init() {
	return OptiMessage::instance();
}
add_action( 'plugins_loaded', 'optimessage_init' );

// 3. Setup automatic plugin updates using Plugin Update Checker (PUC).
if ( file_exists( OM_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php' ) ) {
	include_once OM_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

	try {
		$my_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/noor-siddiqui/OptiMessage',
			__FILE__,
			'optiMessage'
		);
		$my_update_checker->setBranch( 'main' );
	} catch ( \Exception $e ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'OptiMessage: ' . $e->getMessage() );
	}
}
