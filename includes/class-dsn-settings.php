<?php
/**
 * Settings class for OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSN_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_settings_page() {
		add_menu_page(
			__( 'OptiMessage', 'desishad-sms-notifier' ),
			__( 'OptiMessage', 'desishad-sms-notifier' ),
			'manage_options',
			'dsn-settings',
			array( $this, 'render_settings_page' ),
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( DSN_PLUGIN_DIR . '/includes/icon.svg' ) ),
			56
		);
	}

	public function register_settings() {
		// Twilio API Settings
		register_setting( 'dsn_api_group', 'dsn_twilio_sid', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'dsn_api_group', 'dsn_twilio_token', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'dsn_api_group', 'dsn_twilio_from', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// General/Consent Settings
		register_setting( 'dsn_general_group', 'dsn_wc_sms_enabled', array( 'type' => 'boolean', 'default' => 1 ) );
		register_setting( 'dsn_general_group', 'dsn_send_no_consent', array( 'type' => 'boolean', 'default' => 0 ) );
		register_setting( 'dsn_general_group', 'dsn_consent_checkout', array( 'type' => 'boolean', 'default' => 0 ) );
		register_setting( 'dsn_general_group', 'dsn_consent_required', array( 'type' => 'boolean', 'default' => 0 ) );
		register_setting( 'dsn_general_group', 'dsn_consent_profile', array( 'type' => 'boolean', 'default' => 0 ) );
		register_setting( 'dsn_general_group', 'dsn_track_number_key', array( 'type' => 'string', 'default' => '_tracking_number' ) );
		register_setting( 'dsn_general_group', 'dsn_track_url_key', array( 'type' => 'string', 'default' => '_tracking_url' ) );

		// Template Settings
		register_setting( 'dsn_templates_group', 'dsn_tpl_placed', array( 'sanitize_callback' => 'wp_kses_post' ) );
		register_setting( 'dsn_templates_group', 'dsn_tpl_completed', array( 'sanitize_callback' => 'wp_kses_post' ) );
		register_setting( 'dsn_templates_group', 'dsn_tpl_refunded', array( 'sanitize_callback' => 'wp_kses_post' ) );
	}

	public function render_settings_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'send';
		?>
		<div class="wrap">
			<h1><?php _e( 'OptiMessage, Powerful SMS Notifier', 'desishad-sms-notifier' ); ?></h1>
			<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=dsn-settings&tab=send" class="nav-tab <?php echo $active_tab == 'send' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Send SMS', 'desishad-sms-notifier' ); ?></a>
				<a href="?page=dsn-settings&tab=history" class="nav-tab <?php echo $active_tab == 'history' ? 'nav-tab-active' : ''; ?>"><?php _e( 'History', 'desishad-sms-notifier' ); ?></a>
				<a href="?page=dsn-settings&tab=subscribers" class="nav-tab <?php echo $active_tab == 'subscribers' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Subscribers', 'desishad-sms-notifier' ); ?></a>
				<a href="?page=dsn-settings&tab=templates" class="nav-tab <?php echo $active_tab == 'templates' ? 'nav-tab-active' : ''; ?>"><?php _e( 'SMS Templates', 'desishad-sms-notifier' ); ?></a>
				<a href="?page=dsn-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e( 'General Settings', 'desishad-sms-notifier' ); ?></a>
				<a href="?page=dsn-settings&tab=api" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Twilio API', 'desishad-sms-notifier' ); ?></a>
			</h2>

			<?php
			$options_tabs = array( 'api', 'general', 'templates' );
			if ( in_array( $active_tab, $options_tabs ) ) :
			?>
			<form method="post" action="options.php">
			<?php endif; ?>
				<?php
				if ( $active_tab == 'api' ) {
					settings_fields( 'dsn_api_group' );
					$this->render_api_tab();
					submit_button();
				} elseif ( $active_tab == 'general' ) {
					settings_fields( 'dsn_general_group' );
					$this->render_general_tab();
					submit_button();
				} elseif ( $active_tab == 'templates' ) {
					settings_fields( 'dsn_templates_group' );
					$this->render_templates_tab();
					submit_button();
				} elseif ( $active_tab == 'send' ) {
					// We will render the send SMS interface here, no standard submit button
					do_action( 'dsn_render_send_sms_tab' );
				} elseif ( $active_tab == 'subscribers' ) {
					// Render subscribers table
					do_action( 'dsn_render_subscribers_tab' );
				} elseif ( $active_tab == 'history' ) {
					// Render history table
					do_action( 'dsn_render_history_tab' );
				}
				?>
			<?php if ( in_array( $active_tab, $options_tabs ) ) : ?>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_api_tab() {
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'Twilio Account SID', 'desishad-sms-notifier' ); ?></th>
				<td><input type="text" name="dsn_twilio_sid" value="<?php echo esc_attr( get_option( 'dsn_twilio_sid' ) ); ?>" class="regular-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Twilio Auth Token', 'desishad-sms-notifier' ); ?></th>
				<td><input type="password" name="dsn_twilio_token" value="<?php echo esc_attr( get_option( 'dsn_twilio_token' ) ); ?>" class="regular-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Twilio From Number / Messaging Service SID', 'desishad-sms-notifier' ); ?></th>
				<td><input type="text" name="dsn_twilio_from" value="<?php echo esc_attr( get_option( 'dsn_twilio_from' ) ); ?>" class="regular-text" />
				<p class="description"><?php _e( 'Enter a valid Twilio phone number, alphanumeric sender ID, or a Messaging Service SID (starts with MG...).', 'desishad-sms-notifier' ); ?></p></td>
			</tr>
		</table>
		<?php
	}

	private function render_general_tab() {
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'Enable WooCommerce SMS', 'desishad-sms-notifier' ); ?></th>
				<td>
					<input type="checkbox" name="dsn_wc_sms_enabled" value="1" <?php checked( 1, get_option( 'dsn_wc_sms_enabled', 1 ), true ); ?> />
					<label for="dsn_wc_sms_enabled"><?php _e( 'Enable SMS notifications for WooCommerce orders.', 'desishad-sms-notifier' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Send SMS to Users Without Consent', 'desishad-sms-notifier' ); ?></th>
				<td>
					<input type="checkbox" name="dsn_send_no_consent" value="1" <?php checked( 1, get_option( 'dsn_send_no_consent', 0 ), true ); ?> />
					<label for="dsn_send_no_consent"><?php _e( 'Send SMS even if the user has not explicitly consented.', 'desishad-sms-notifier' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Show Consent on Checkout', 'desishad-sms-notifier' ); ?></th>
				<td>
					<input type="checkbox" name="dsn_consent_checkout" value="1" <?php checked( 1, get_option( 'dsn_consent_checkout', 0 ), true ); ?> />
					<label for="dsn_consent_checkout"><?php _e( 'Add "Receive SMS notifications" checkbox to WooCommerce checkout.', 'desishad-sms-notifier' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Make Consent Required', 'desishad-sms-notifier' ); ?></th>
				<td>
					<input type="checkbox" name="dsn_consent_required" value="1" <?php checked( 1, get_option( 'dsn_consent_required', 0 ), true ); ?> />
					<label for="dsn_consent_required"><?php _e( 'Force users to check the SMS consent box during checkout.', 'desishad-sms-notifier' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Show Consent on Profile', 'desishad-sms-notifier' ); ?></th>
				<td>
					<input type="checkbox" name="dsn_consent_profile" value="1" <?php checked( 1, get_option( 'dsn_consent_profile', 0 ), true ); ?> />
					<label for="dsn_consent_profile"><?php _e( 'Add Consent field to WordPress User Profile page.', 'desishad-sms-notifier' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Tracking Number Meta Key', 'desishad-sms-notifier' ); ?></th>
				<td>
					<input type="text" name="dsn_track_number_key" value="<?php echo esc_attr( get_option( 'dsn_track_number_key', '_tracking_number' ) ); ?>" class="regular-text" />
					<p class="description"><?php _e( 'The post meta key where tracking numbers are saved.', 'desishad-sms-notifier' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Tracking URL Meta Key', 'desishad-sms-notifier' ); ?></th>
				<td>
					<input type="text" name="dsn_track_url_key" value="<?php echo esc_attr( get_option( 'dsn_track_url_key', '_tracking_url' ) ); ?>" class="regular-text" />
					<p class="description"><?php _e( 'The post meta key where tracking URLs are saved.', 'desishad-sms-notifier' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_templates_tab() {
		?>
		<p class="description">
			<?php _e( 'Available tags: {order_id}, {first_name}, {last_name}, {total}, {tracking_number}, {tracking_url}, {shipping_provider}', 'desishad-sms-notifier' ); ?>
		</p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'Order Placed Template', 'desishad-sms-notifier' ); ?></th>
				<td>
					<textarea name="dsn_tpl_placed" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'dsn_tpl_placed', 'Hi {first_name}, thanks for your order #{order_id}! We will let you know when it ships.' ) ); ?></textarea>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Order Completed (Shipment) Template', 'desishad-sms-notifier' ); ?></th>
				<td>
					<textarea name="dsn_tpl_completed" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'dsn_tpl_completed', 'Good news {first_name}! Your order #{order_id} has been shipped. Track here: {tracking_url}' ) ); ?></textarea>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Order Refunded Template', 'desishad-sms-notifier' ); ?></th>
				<td>
					<textarea name="dsn_tpl_refunded" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'dsn_tpl_refunded', 'Hi {first_name}, your order #{order_id} has been refunded.' ) ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php
	}
}

new DSN_Settings();
