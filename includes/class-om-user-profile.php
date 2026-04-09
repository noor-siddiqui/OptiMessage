<?php
/**
 * User Profile Consent for OptiMessage
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OM_User_Profile
 * Handles user profile fields and updates.
 */
class OM_User_Profile {


	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'add_sms_consent_field' ) );
		add_action( 'edit_user_profile', array( $this, 'add_sms_consent_field' ) );

		add_action( 'personal_options_update', array( $this, 'save_sms_consent_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_sms_consent_field' ) );

		// Validate phone immediately when profile saves.
		add_action( 'profile_update', array( $this, 'validate_phone_on_profile_save' ), 10, 2 );

		// Async Twilio Lookup for users.
		add_action( 'om_async_twilio_user_lookup_job', array( $this, 'process_async_twilio_user_lookup' ) );
	}

	/**
	 * Process async Twilio lookup for a user.
	 *
	 * @param int $user_id The user ID.
	 */
	public function process_async_twilio_user_lookup( $user_id ) {
		$phone = get_user_meta( $user_id, 'billing_phone', true );
		if ( empty( $phone ) ) {
			return;
		}

		$country = get_user_meta( $user_id, 'billing_country', true );
		$lookup  = OM_Twilio_API::lookup_phone( $phone, $country );
		if ( is_array( $lookup ) ) {
			if ( $lookup['valid'] ) {
				update_user_meta( $user_id, 'billing_phone', $lookup['formatted'] );
				update_user_meta( $user_id, '_om_phone_valid', '1' );
			} else {
				update_user_meta( $user_id, '_om_phone_valid', '-1' );
			}
		}
	}

	/**
	 * Validate and update phone on profile save.
	 *
	 * @param int   $user_id       User ID.
	 * @param array $old_user_data Old user data.
	 */
	public function validate_phone_on_profile_save( $user_id, $old_user_data ) {
     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WordPress/WooCommerce core during profile update.
		if ( isset( $_POST['billing_phone'] ) ) {

         // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WordPress/WooCommerce core during profile update.
			$phone = sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) );
			if ( empty( $phone ) ) {
				delete_user_meta( $user_id, '_om_phone_valid' );
				return;
			}

			$current_valid = get_user_meta( $user_id, '_om_phone_valid', true );
			$old_phone     = get_user_meta( $user_id, 'billing_phone', true );

			if ( $phone !== $old_phone || empty( $current_valid ) ) {
				$lookup = OM_Twilio_API::lookup_phone( $phone );
				if ( is_array( $lookup ) ) {
					if ( $lookup['valid'] ) {
						update_user_meta( $user_id, 'billing_phone', $lookup['formatted'] );
						update_user_meta( $user_id, '_om_phone_valid', '1' );
					} else {
						update_user_meta( $user_id, '_om_phone_valid', '-1' );
					}
				}
			}
		}
	}

	/**
	 * Add SMS consent field to user profile.
	 *
	 * @param WP_User $user The user object.
	 */
	public function add_sms_consent_field( $user ) {
		if ( ! get_option( 'om_consent_profile', 0 ) ) {
			return; // Disabled in settings.
		}

		$consent = get_user_meta( $user->ID, 'optimessage/sms-consent', true );
		?>
		<h3><?php esc_html_e( 'SMS Notification Preferences', 'optimessage' ); ?></h3>

		<table class="form-table">
			<tr>
				<th><label for="om_sms_consent"><?php esc_html_e( 'Receive SMS', 'optimessage' ); ?></label></th>
				<td>
					<input type="checkbox" name="om_sms_consent" id="om_sms_consent" value="1" <?php checked( $consent, '1' ); ?> />
					<span class="description"><?php esc_html_e( 'Check to opt-in to SMS updates for orders and marketing based on store settings.', 'optimessage' ); ?></span>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save SMS consent field value.
	 *
	 * @param  int $user_id The user ID.
	 * @return bool|void
	 */
	public function save_sms_consent_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( ! get_option( 'om_consent_profile', 0 ) ) {
			return false; // Disabled in settings.
		}

     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WordPress/WooCommerce core during profile update.
		$consent = isset( $_POST['om_sms_consent'] ) ? '1' : '0';
		update_user_meta( $user_id, 'optimessage/sms-consent', $consent );
	}
}

new OM_User_Profile();
