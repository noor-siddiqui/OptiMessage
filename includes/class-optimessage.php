<?php
/**
 * Main plugin class.
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class OptiMessage
 */
class OptiMessage {


	/**
	 * Single instance of the class.
	 *
	 * @var OptiMessage|null
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
		include_once OM_PLUGIN_DIR . 'includes/class-om-settings.php';
		include_once OM_PLUGIN_DIR . 'includes/class-om-send-sms.php';
		include_once OM_PLUGIN_DIR . 'includes/class-om-twilio-api.php';
		include_once OM_PLUGIN_DIR . 'includes/class-om-history.php';
		include_once OM_PLUGIN_DIR . 'includes/class-om-woocommerce.php';
		include_once OM_PLUGIN_DIR . 'includes/class-om-user-profile.php';
		include_once OM_PLUGIN_DIR . 'includes/class-om-webhook.php';
		include_once OM_PLUGIN_DIR . 'includes/class-om-subscribers-list-table.php';
		include_once OM_PLUGIN_DIR . 'includes/class-om-guest-customers-list-table.php';
		include_once OM_PLUGIN_DIR . 'includes/class-om-subscribers-tab.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		add_action( 'admin_init', array( $this, 'check_db_update' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_notices', array( $this, 'admin_setup_notice' ) );
	}

	/**
	 * Load text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'optimessage', false, dirname( plugin_basename( OM_PLUGIN_DIR . 'optimessage.php' ) ) . '/languages' );
	}

	/**
	 * Admin setup notice
	 */
	public function admin_setup_notice() {
		if ( ! get_option( 'om_twilio_sid' ) || ! get_option( 'om_twilio_token' ) ) {
			$settings_url = admin_url( 'admin.php?page=om-settings&tab=api' );
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'OptiMessage is almost ready!', 'optimessage' ); ?></strong>
			<?php
			printf(
			/* translators: %s: Settings page URL */
				wp_kses_post( __( 'Please <a href="%s">configure your Twilio API credentials</a> to start sending SMS notifications.', 'optimessage' ) ),
				esc_url( $settings_url )
			);
			?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Check if database update is required.
	 */
	public function check_db_update() {
		if ( get_option( 'om_db_version' ) !== OM_VERSION ) {
			self::install();
			update_option( 'om_db_version', OM_VERSION );
		}
	}

	/**
	 * Plugin installation (create DB tables)
	 */
	public static function install() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'om_sms_history';
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

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}