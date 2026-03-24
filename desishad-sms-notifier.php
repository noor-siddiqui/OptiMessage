<?php
/**
 * Plugin Name: OptiMessage
 * Description: A powerful, open source SMS notification and bulk messaging framework scaling Twilio for WooCommerce.
 * Version: 1.0.0
 * Author: Noor Nabiul Alam Siddiqui
 * GitHub Plugin URI: https://github.com/NoorNabiul/desishad-sms-notifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'DSN_VERSION', '1.0.0' );
define( 'DSN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DSN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Setup plugin class
class Desishad_SMS_Notifier {

	/**
	 * Single instance of the class
	 */
	private static $instance = null;

	/**
	 * Main Instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files
	 */
	private function includes() {
		require_once DSN_PLUGIN_DIR . 'includes/class-dsn-settings.php';
		require_once DSN_PLUGIN_DIR . 'includes/class-dsn-send-sms.php';
		require_once DSN_PLUGIN_DIR . 'includes/class-dsn-twilio-api.php';
		require_once DSN_PLUGIN_DIR . 'includes/class-dsn-history.php';
		require_once DSN_PLUGIN_DIR . 'includes/class-dsn-woocommerce.php';
		require_once DSN_PLUGIN_DIR . 'includes/class-dsn-user-profile.php';
		require_once DSN_PLUGIN_DIR . 'includes/class-dsn-webhook.php';
		require_once DSN_PLUGIN_DIR . 'includes/class-dsn-subscribers-table.php';
	}

	/**
	 * Hook into actions and filters
	 */
	private function init_hooks() {
		// Run DB creation automatically if version is mismatched (fixes live server updates without reactivation)
		add_action( 'admin_init', array( $this, 'check_db_update' ) );
	}

	public function check_db_update() {
		if ( get_option( 'dsn_db_version' ) !== DSN_VERSION ) {
			self::install();
			update_option( 'dsn_db_version', DSN_VERSION );
		}
	}

	/**
	 * Plugin installation (create DB tables)
	 */
	public static function install() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dsn_sms_history';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			phone_number varchar(20) NOT NULL,
			message_sid varchar(50) DEFAULT '' NOT NULL,
			message text NOT NULL,
			status varchar(50) NOT NULL,
			sent_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			user_id bigint(20) DEFAULT 0,
			order_id bigint(20) DEFAULT 0,
			error_message text,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
}

// Hook activation outside the instance class appropriately
register_activation_hook( __FILE__, array( 'Desishad_SMS_Notifier', 'install' ) );

// Initialize the plugin
function dsn_init() {
	return Desishad_SMS_Notifier::instance();
}
add_action( 'plugins_loaded', 'dsn_init' );

// Setup automatic plugin updates using Plugin Update Checker (PUC)
if ( file_exists( DSN_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once DSN_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
	
	try {
		// NOTE: Change 'your-repo-name' to the exact name of your GitHub repository.
		$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/NoorNabiul/your-repo-name/',
			__FILE__,
			'desishad-sms-notifier'
		);

		// Specify the branch to pull the stable release from
		$myUpdateChecker->setBranch( 'main' );
		
		// If you make the repository Private, uncomment the line below and add your GitHub Personal Access Token
		// $myUpdateChecker->setAuthentication('YOUR_GITHUB_PERSONAL_ACCESS_TOKEN');
	} catch ( \Exception $e ) {
		// Log gracefully without breaking the site if PUC fails
	}
}
