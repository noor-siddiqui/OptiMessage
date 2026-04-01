<?php
/**
 * Settings class for OptiMessage
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OM_Settings
 * Registers and renders the plugin settings.
 */
class OM_Settings {


	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_footer', array( $this, 'render_sms_character_counter_js' ) );
	}

	/**
	 * Add settings page to the admin menu.
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'OptiMessage', 'optimessage' ),
			__( 'OptiMessage', 'optimessage' ),
			'manage_options',
			'om-settings',
			array( $this, 'render_settings_page' ),
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( OM_PLUGIN_DIR . '/includes/icon.svg' ) ),
			56
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		// Twilio API Settings.
		register_setting( 'om_api_group', 'om_twilio_sid', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'om_api_group', 'om_twilio_token', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'om_api_group', 'om_twilio_from', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// General/Consent Settings.
		register_setting(
			'om_general_group',
			'om_wc_sms_enabled',
			array(
				'type'    => 'boolean',
				'default' => 1,
			)
		);
		register_setting(
			'om_general_group',
			'om_send_no_consent',
			array(
				'type'    => 'boolean',
				'default' => 0,
			)
		);
		register_setting(
			'om_general_group',
			'om_consent_checkout',
			array(
				'type'    => 'boolean',
				'default' => 0,
			)
		);
		register_setting(
			'om_general_group',
			'om_consent_required',
			array(
				'type'    => 'boolean',
				'default' => 0,
			)
		);
		register_setting(
			'om_general_group',
			'om_consent_profile',
			array(
				'type'    => 'boolean',
				'default' => 0,
			)
		);
		register_setting(
			'om_general_group',
			'om_track_number_key',
			array(
				'type'    => 'string',
				'default' => '_tracking_number',
			)
		);
		register_setting(
			'om_general_group',
			'om_track_url_key',
			array(
				'type'    => 'string',
				'default' => '_tracking_url',
			)
		);

		// Template Settings.
		register_setting( 'om_templates_group', 'om_tpl_placed', array( 'sanitize_callback' => 'wp_kses_post' ) );
		register_setting( 'om_templates_group', 'om_tpl_completed', array( 'sanitize_callback' => 'wp_kses_post' ) );
		register_setting( 'om_templates_group', 'om_tpl_refunded', array( 'sanitize_callback' => 'wp_kses_post' ) );
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'send';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OptiMessage, Powerful SMS Notifier', 'optimessage' ); ?></h1>
		<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=om-settings&tab=send" class="nav-tab <?php echo 'send' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Send SMS', 'optimessage' ); ?></a>
				<a href="?page=om-settings&tab=history" class="nav-tab <?php echo 'history' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'History', 'optimessage' ); ?></a>
				<a href="?page=om-settings&tab=subscribers" class="nav-tab <?php echo 'subscribers' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Subscribers', 'optimessage' ); ?></a>
				<a href="?page=om-settings&tab=templates" class="nav-tab <?php echo 'templates' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'SMS Templates', 'optimessage' ); ?></a>
				<a href="?page=om-settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General Settings', 'optimessage' ); ?></a>
				<a href="?page=om-settings&tab=api" class="nav-tab <?php echo 'api' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Twilio API', 'optimessage' ); ?></a>
			</h2>

		<?php
		$options_tabs = array( 'api', 'general', 'templates' );
		if ( in_array( $active_tab, $options_tabs, true ) ) :
			?>
			<form method="post" action="options.php">
		<?php endif; ?>
		<?php
		if ( 'api' === $active_tab ) {
			settings_fields( 'om_api_group' );
			$this->render_api_tab();
			submit_button();
		} elseif ( 'general' === $active_tab ) {
			settings_fields( 'om_general_group' );
			$this->render_general_tab();
			submit_button();
		} elseif ( 'templates' === $active_tab ) {
			settings_fields( 'om_templates_group' );
			$this->render_templates_tab();
			submit_button();
		} elseif ( 'send' === $active_tab ) {
			// We will render the send SMS interface here, no standard submit button.
			do_action( 'om_render_send_sms_tab' );
		} elseif ( 'subscribers' === $active_tab ) {
			// Render subscribers table.
			do_action( 'om_render_subscribers_tab' );
		} elseif ( 'history' === $active_tab ) {
			// Render history table.
			do_action( 'om_render_history_tab' );
		}
		?>
		<?php if ( in_array( $active_tab, $options_tabs, true ) ) : ?>
			</form>
		<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the API settings tab.
	 */
	private function render_api_tab() {
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Twilio Account SID', 'optimessage' ); ?></th>
				<td><input type="text" name="om_twilio_sid" value="<?php echo esc_attr( get_option( 'om_twilio_sid' ) ); ?>" class="regular-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Twilio Auth Token', 'optimessage' ); ?></th>
				<td><input type="password" name="om_twilio_token" value="<?php echo esc_attr( get_option( 'om_twilio_token' ) ); ?>" class="regular-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Twilio From Number / Messaging Service SID', 'optimessage' ); ?></th>
				<td><input type="text" name="om_twilio_from" value="<?php echo esc_attr( get_option( 'om_twilio_from' ) ); ?>" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Enter a valid Twilio phone number, alphanumeric sender ID, or a Messaging Service SID (starts with MG...).', 'optimessage' ); ?></p></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the General settings tab.
	 */
	private function render_general_tab() {
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Enable WooCommerce SMS', 'optimessage' ); ?></th>
				<td>
					<input type="checkbox" name="om_wc_sms_enabled" value="1" <?php checked( 1, get_option( 'om_wc_sms_enabled', 1 ), true ); ?> />
					<label for="om_wc_sms_enabled"><?php esc_html_e( 'Enable SMS notifications for WooCommerce orders.', 'optimessage' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Send SMS to Users Without Consent', 'optimessage' ); ?></th>
				<td>
					<input type="checkbox" name="om_send_no_consent" value="1" <?php checked( 1, get_option( 'om_send_no_consent', 0 ), true ); ?> />
					<label for="om_send_no_consent"><?php esc_html_e( 'Send SMS even if the user has not explicitly consented.', 'optimessage' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Show Consent on Checkout', 'optimessage' ); ?></th>
				<td>
					<input type="checkbox" name="om_consent_checkout" value="1" <?php checked( 1, get_option( 'om_consent_checkout', 0 ), true ); ?> />
					<label for="om_consent_checkout"><?php esc_html_e( 'Add "Receive SMS notifications" checkbox to WooCommerce checkout.', 'optimessage' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Make Consent Required', 'optimessage' ); ?></th>
				<td>
					<input type="checkbox" name="om_consent_required" value="1" <?php checked( 1, get_option( 'om_consent_required', 0 ), true ); ?> />
					<label for="om_consent_required"><?php esc_html_e( 'Force users to check the SMS consent box during checkout.', 'optimessage' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Show Consent on Profile', 'optimessage' ); ?></th>
				<td>
					<input type="checkbox" name="om_consent_profile" value="1" <?php checked( 1, get_option( 'om_consent_profile', 0 ), true ); ?> />
					<label for="om_consent_profile"><?php esc_html_e( 'Add Consent field to WordPress User Profile page.', 'optimessage' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Tracking Number Meta Key', 'optimessage' ); ?></th>
				<td>
					<input type="text" name="om_track_number_key" value="<?php echo esc_attr( get_option( 'om_track_number_key', '_tracking_number' ) ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'The post meta key where tracking numbers are saved.', 'optimessage' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Tracking URL Meta Key', 'optimessage' ); ?></th>
				<td>
					<input type="text" name="om_track_url_key" value="<?php echo esc_attr( get_option( 'om_track_url_key', '_tracking_url' ) ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'The post meta key where tracking URLs are saved.', 'optimessage' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the Templates settings tab.
	 */
	private function render_templates_tab() {
		?>
		<p class="description">
		<?php esc_html_e( 'Available tags: {order_id}, {first_name}, {last_name}, {total}, {tracking_number}, {tracking_url}, {shipping_provider}', 'optimessage' ); ?>
		</p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Order Placed Template', 'optimessage' ); ?></th>
				<td>
					<textarea name="om_tpl_placed" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'om_tpl_placed', 'Hi {first_name}, thanks for your order #{order_id}! We will let you know when it ships.' ) ); ?></textarea>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Order Completed (Shipment) Template', 'optimessage' ); ?></th>
				<td>
					<textarea name="om_tpl_completed" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'om_tpl_completed', 'Good news {first_name}! Your order #{order_id} has been shipped. Track here: {tracking_url}' ) ); ?></textarea>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Order Refunded Template', 'optimessage' ); ?></th>
				<td>
					<textarea name="om_tpl_refunded" rows="5" class="large-text"><?php echo esc_textarea( get_option( 'om_tpl_refunded', 'Hi {first_name}, your order #{order_id} has been refunded.' ) ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Inject JavaScript for SMS Character & Segment Counting.
	 */
	public function render_sms_character_counter_js() {
		// Only run on our settings page.
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'om-settings' ) === false ) {
			return;
		}
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Target all textareas used for SMS messages
				var $textareas = $('textarea[name="message"], textarea[name="om_tpl_placed"], textarea[name="om_tpl_completed"], textarea[name="om_tpl_refunded"]');

				if ($textareas.length === 0) {
					return;
				}

				// Append counter elements dynamically
				$textareas.each(function() {
					var $counter = $('<div class="om-sms-counter" style="margin-top:8px;font-size:13px;color:#555;"></div>');
					$(this).after($counter);
					updateCounter($(this), $counter);
				});

				$textareas.on('input keyup change', function() {
					updateCounter($(this), $(this).next('.om-sms-counter'));
				});

				function updateCounter($textarea, $counter) {
					var text = $textarea.val();
					var length = text.length;

					// Basic approximation: Check for characters outside standard printable ASCII.
					// Technically GSM-7 allows some extended characters, but this securely catches Bangla/Unicode.
					var isUnicode = /[^\u0000-\u007F]+/.test(text);

					var limit = isUnicode ? 70 : 160;
					var splitLimit = isUnicode ? 67 : 153;

					var segments = 1;
					if (length > limit) {
						segments = Math.ceil(length / splitLimit);
					}

					var encodingText = isUnicode ? '<span style="color:#d63638;font-weight:bold;">Unicode (UCS-2)</span>' : '<span style="color:#007cba;font-weight:bold;">GSM-7</span>';

					$counter.html('Characters: <strong>' + length + '</strong> &nbsp;|&nbsp; SMS Segments: <strong>' + segments + '</strong> <span style="font-size:11px;color:#888;">(Max ' + limit + ' per segment)</span> &nbsp;|&nbsp; Encoding: ' + encodingText);
				}
			});
		</script>
		<?php
	}
}

new OM_Settings();
