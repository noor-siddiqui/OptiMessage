<?php
/**
 * Twilio API Handler for OptiMessage
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DSN_Twilio_API
 * Handles SMS sending and Twilio API communication.
 */
class DSN_Twilio_API {


	/**
	 * Send SMS via Twilio and log to DB
	 *
	 * @param  string $to       The recipient phone number.
	 * @param  string $message  The message text to send.
	 * @param  int    $user_id  Optional user ID associated with SMS.
	 * @param  int    $order_id Optional WooCommerce order ID.
	 * @return bool True on success, false on failure
	 */
	public static function send_sms( $to, $message, $user_id = 0, $order_id = 0 ) {
		$sid   = get_option( 'dsn_twilio_sid' );
		$token = get_option( 'dsn_twilio_token' );
		$from  = get_option( 'dsn_twilio_from' );

		if ( empty( $sid ) || empty( $token ) || empty( $from ) || empty( $to ) || empty( $message ) ) {
			self::log_sms( $to, '', $message, 'failed', $user_id, $order_id, 'Missing API credentials or empty To/Message' );
			return false;
		}

		// Basic formatting: ensure $to has a '+' sign if it's purely numerical and longer than 10 digits.
		$to_clean = preg_replace( '/[^0-9+]/', '', $to );
		if ( ! str_starts_with( $to_clean, '+' ) ) {
			$to_clean = '+' . $to_clean;
		}

		$url         = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
		$webhook_url = rest_url( 'dsn/v1/twilio-webhook' );

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "$sid:$token" ),
			),
			'body'    => array(
				'To'   => $to_clean,
				'Body' => $message,
			),
			'timeout' => 15,
		);

		// Only attach webhook if it's not a local unroutable domain (which Twilio rejects with 400).
		if ( false === strpos( $webhook_url, 'localhost' ) && false === strpos( $webhook_url, '.local' ) ) {
			$args['body']['StatusCallback'] = $webhook_url;
		}

		if ( str_starts_with( trim( $from ), 'MG' ) ) {
			$args['body']['MessagingServiceSid'] = trim( $from );
		} else {
			$args['body']['From'] = trim( $from );
		}

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			self::log_sms( $to_clean, '', $message, 'failed', $user_id, $order_id, $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		$twilio_err = '';
		if ( isset( $data->message ) && ! empty( $data->message ) && ( ! isset( $data->status ) || in_array( $data->status, array( 400, 401, 403, 404, 500 ) ) ) ) {
			$twilio_err = $data->message;
		} elseif ( isset( $data->error_message ) && ! empty( $data->error_message ) ) {
			$twilio_err = $data->error_message;
		}

		if ( $twilio_err ) {
			self::log_sms( $to_clean, '', $message, 'failed', $user_id, $order_id, sanitize_text_field( $twilio_err ) );
			return false;
		}

		$message_sid = isset( $data->sid ) ? sanitize_text_field( $data->sid ) : '';
		$status      = isset( $data->status ) ? sanitize_text_field( $data->status ) : 'unknown';

		if ( in_array( $status, array( 'queued', 'sent', 'delivered' ) ) ) {
			self::log_sms( $to_clean, $message_sid, $message, $status, $user_id, $order_id, '' );
			return true;
		} else {
			self::log_sms( $to_clean, $message_sid, $message, 'failed', $user_id, $order_id, 'API returned status: ' . $status );
			return false;
		}
	}

	/**
	 * Log SMS to Database
	 *
	 * @param string $phone_number  The destination phone number.
	 * @param string $message_sid   The Twilio message SID.
	 * @param string $message       The message body.
	 * @param string $status        The delivery status.
	 * @param int    $user_id       The user ID.
	 * @param int    $order_id      The order ID.
	 * @param string $error_message Any API error message.
	 */
	private static function log_sms( $phone_number, $message_sid, $message, $status, $user_id, $order_id, $error_message ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dsn_sms_history';

		$wpdb->insert(
			$table_name,
			array(
				'phone_number'  => sanitize_text_field( $phone_number ),
				'message_sid'   => sanitize_text_field( $message_sid ),
				'message'       => sanitize_textarea_field( $message ),
				'status'        => sanitize_text_field( $status ),
				'sent_at'       => current_time( 'mysql' ),
				'user_id'       => intval( $user_id ),
				'order_id'      => intval( $order_id ),
				'error_message' => sanitize_textarea_field( $error_message ),
			)
		);
	}

	/**
	 * Verify phone number via Twilio Lookup v2
	 *
	 * @param  string $phone        Phone number to lookup.
	 * @param  string $country_code (Optional) ISO Country Code (e.g. US, BD).
	 * @return array|bool Array with 'formatted' and 'valid', or false on API error.
	 */
	public static function lookup_phone( $phone, $country_code = '' ) {
		$sid   = get_option( 'dsn_twilio_sid' );
		$token = get_option( 'dsn_twilio_token' );

		if ( empty( $sid ) || empty( $token ) || empty( $phone ) ) {
			return false;
		}

		$clean_phone = trim( $phone );
		$has_plus    = str_starts_with( $clean_phone, '+' );

		// Remove all non-numeric characters.
		$numbers_only = preg_replace( '/[^0-9]/', '', $clean_phone );

		if ( $has_plus ) {
			$lookup_number = '+' . $numbers_only;
			$url           = 'https://lookups.twilio.com/v2/PhoneNumbers/' . urlencode( $lookup_number );
		} else {
			$lookup_number = $numbers_only; // National format.
			$url           = 'https://lookups.twilio.com/v2/PhoneNumbers/' . urlencode( $lookup_number );

			if ( ! empty( $country_code ) ) {
				$url = add_query_arg( 'CountryCode', strtoupper( sanitize_text_field( $country_code ) ), $url );
			} else {
				// Fallback to legacy behavior if country code is completely missing.
				$lookup_number = '+' . $numbers_only;
				$url           = 'https://lookups.twilio.com/v2/PhoneNumbers/' . urlencode( $lookup_number );
			}
		}

		$args = array(
			'method'  => 'GET',
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "$sid:$token" ),
			),
			'timeout' => 15,
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( isset( $data->valid ) ) {
			return array(
				'valid'     => (bool) $data->valid,
				'formatted' => isset( $data->phone_number ) ? $data->phone_number : $lookup_number,
			);
		}

		// If returning an error object (like 404 Not Found due to invalid format).
		if ( isset( $data->code ) ) {
			return array(
				'valid'     => false,
				'formatted' => $lookup_number,
			);
		}

		return false;
	}
}
