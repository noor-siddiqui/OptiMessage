<?php
/**
 * User Profile Consent for OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSN_User_Profile {

	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'add_sms_consent_field' ) );
		add_action( 'edit_user_profile', array( $this, 'add_sms_consent_field' ) );

		add_action( 'personal_options_update', array( $this, 'save_sms_consent_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_sms_consent_field' ) );
		
		// Validate phone immediately when profile saves
		add_action( 'profile_update', array( $this, 'validate_phone_on_profile_save' ), 10, 2 );
	}

	public function validate_phone_on_profile_save( $user_id, $old_user_data ) {
		if ( isset( $_POST['billing_phone'] ) ) {
			$phone = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) );
			if ( empty( $phone ) ) {
				delete_user_meta( $user_id, '_dsn_phone_valid' );
				return;
			}
			
			$current_valid = get_user_meta( $user_id, '_dsn_phone_valid', true );
			$old_phone     = get_user_meta( $user_id, 'billing_phone', true );
			
			if ( $phone !== $old_phone || empty( $current_valid ) ) {
				$lookup = DSN_Twilio_API::lookup_phone( $phone );
				if ( is_array( $lookup ) ) {
					if ( $lookup['valid'] ) {
						update_user_meta( $user_id, 'billing_phone', $lookup['formatted'] );
						update_user_meta( $user_id, '_dsn_phone_valid', '1' );
					} else {
						update_user_meta( $user_id, '_dsn_phone_valid', '-1' );
					}
				}
			}
		}
	}

	public function add_sms_consent_field( $user ) {
		if ( ! get_option( 'dsn_consent_profile', 0 ) ) {
			return; // disabled in settings
		}
		
		$consent = get_user_meta( $user->ID, 'desishad/sms-consent', true );
		?>
		<h3><?php _e( 'SMS Notification Preferences', 'desishad-sms-notifier' ); ?></h3>

		<table class="form-table">
			<tr>
				<th><label for="dsn_sms_consent"><?php _e( 'Receive SMS', 'desishad-sms-notifier' ); ?></label></th>
				<td>
					<input type="checkbox" name="dsn_sms_consent" id="dsn_sms_consent" value="1" <?php checked( $consent, '1' ); ?> />
					<span class="description"><?php _e( 'Check to opt-in to SMS updates for orders and marketing based on store settings.', 'desishad-sms-notifier' ); ?></span>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_sms_consent_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( ! get_option( 'dsn_consent_profile', 0 ) ) {
			return false; // disabled in settings
		}

		$consent = isset( $_POST['dsn_sms_consent'] ) ? '1' : '0';
		update_user_meta( $user_id, 'desishad/sms-consent', $consent );
	}
}

new DSN_User_Profile();
