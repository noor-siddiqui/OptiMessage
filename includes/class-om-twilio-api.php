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
 * Class OM_Twilio_API
 * Handles SMS sending and Twilio API communication.
 */
class OM_Twilio_API {

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
		$sid   = get_option( 'om_twilio_sid' );
		$token = get_option( 'om_twilio_token' );
		$from  = get_option( 'om_twilio_from' );

		if ( empty( $sid ) || empty( $token ) || empty( $from ) || empty( $to ) || empty( $message ) ) {
			self::log_sms( $to, '', $message, 'failed', $user_id, $order_id, 'Missing API credentials or empty To/Message' );
			return false;
		}

		// ⚡ The Fix: Intelligent Phone Number Formatting
		$to_clean = trim( $to );
		$has_plus = str_starts_with( $to_clean, '+' );
		$to_clean = preg_replace( '/[^0-9+]/', '', $to_clean );

		if ( ! $has_plus ) {
			// Do not blindly prepend '+'. Use Twilio Lookup to properly format local numbers based on the store's home country.
			$base_country = class_exists( 'WooCommerce' ) ? WC()->countries->get_base_country() : '';
			$lookup       = self::lookup_phone( $to_clean, $base_country );

			if ( is_array( $lookup ) && $lookup['valid'] ) {
				$to_clean = $lookup['formatted'];
			} else {
				// If lookup fails, leave it without the '+'. Let Twilio attempt to parse it or reject it cleanly.
				$to_clean = preg_replace( '/[^0-9]/', '', $to_clean );
			}
		}

		$url         = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
		$webhook_url = rest_url( 'om/v1/twilio-webhook' );

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

		$table_name = $wpdb->prefix . 'om_sms_history';

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
	 * Get the Twilio Lookup v2 URL for a phone number.
	 *
	 * @param  string $phone        Phone number.
	 * @param  string $country_code Country code.
	 * @return string
	 */
	private static function get_lookup_request_url( $phone, $country_code = '' ) {
		$clean_phone = trim( $phone );
		$has_plus    = str_starts_with( $clean_phone, '+' );

		// Remove all non-numeric characters.
		$numbers_only = preg_replace( '/[^0-9]/', '', $clean_phone );

		if ( $has_plus ) {
			$lookup_number = '+' . $numbers_only;
			$url           = 'https://lookups.twilio.com/v2/PhoneNumbers/' . urlencode( $lookup_number );
		} else {
			$lookup_number = $numbers_only; // National format.

			// ⚡ Fallback to the WooCommerce store's base country if none is provided.
			if ( empty( $country_code ) && class_exists( 'WooCommerce' ) ) {
				$country_code = WC()->countries->get_base_country();
			}

			$url = 'https://lookups.twilio.com/v2/PhoneNumbers/' . urlencode( $lookup_number );

			if ( ! empty( $country_code ) ) {
				$url = add_query_arg( 'CountryCode', strtoupper( sanitize_text_field( $country_code ) ), $url );
			} else {
				// Ultimate fallback if absolutely no country code exists.
				$lookup_number = '+' . $numbers_only;
				$url           = 'https://lookups.twilio.com/v2/PhoneNumbers/' . urlencode( $lookup_number );
			}
		}

		// Request line type intelligence to identify landlines.
		$url = add_query_arg( 'Fields', 'line_type_intelligence', $url );

		return $url;
	}

	/**
	 * Verify phone number via Twilio Lookup v2
	 *
	 * @param  string $phone        Phone number to lookup.
	 * @param  string $country_code (Optional) ISO Country Code (e.g. US, BD).
	 * @return array|bool Array with 'formatted' and 'valid', or false on API error.
	 */
	public static function lookup_phone( $phone, $country_code = '' ) {
		$sid   = get_option( 'om_twilio_sid' );
		$token = get_option( 'om_twilio_token' );

		if ( empty( $sid ) || empty( $token ) || empty( $phone ) ) {
			return false;
		}

		$url = self::get_lookup_request_url( $phone, $country_code );

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
		return self::parse_lookup_response( $body, $phone );
	}

	/**
	 * Perform multiple phone lookups in parallel.
	 *
	 * @param array $requests Array of requests, each being ['phone' => ..., 'country' => ...].
	 * @return array Array of results, indexed by the same keys as $requests.
	 */
	public static function lookup_phone_batch( array $requests ) {
		$sid   = get_option( 'om_twilio_sid' );
		$token = get_option( 'om_twilio_token' );

		if ( empty( $sid ) || empty( $token ) || empty( $requests ) ) {
			return array();
		}

		if ( ! function_exists( 'curl_multi_init' ) ) {
			// Fallback to synchronous if curl_multi is not available.
			$results = array();
			foreach ( $requests as $key => $req ) {
				$results[ $key ] = self::lookup_phone( $req['phone'], isset( $req['country'] ) ? $req['country'] : '' );
			}
			return $results;
		}

		$mh      = curl_multi_init();
		$curls   = array();
		$results = array();
		$auth    = base64_encode( "$sid:$token" );

		foreach ( $requests as $key => $req ) {
			$url = self::get_lookup_request_url( $req['phone'], isset( $req['country'] ) ? $req['country'] : '' );

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Authorization: Basic $auth" ) );

			curl_multi_add_handle( $mh, $ch );
			$curls[ $key ] = $ch;
		}

		$active = null;
		do {
			$status = curl_multi_exec( $mh, $active );
			if ( $active ) {
				curl_multi_select( $mh );
			}
		} while ( $active && CURLM_OK === $status );

		foreach ( $curls as $key => $ch ) {
			$body            = curl_multi_getcontent( $ch );
			$results[ $key ] = self::parse_lookup_response( $body, $requests[ $key ]['phone'] );
			curl_multi_remove_handle( $mh, $ch );
			curl_close( $ch );
		}

		curl_multi_close( $mh );

		return $results;
	}

	/**
	 * Parse the JSON response from Twilio Lookup API.
	 *
	 * @param string $body  JSON response body.
	 * @param string $phone Original phone number for fallback.
	 * @return array|bool
	 */
	private static function parse_lookup_response( $body, $phone ) {
		$data = json_decode( $body );

		if ( isset( $data->valid ) ) {
			// Check if the number is a landline, which cannot receive SMS.
			if ( isset( $data->line_type_intelligence->type ) && 'landline' === $data->line_type_intelligence->type ) {
				return false;
			}

			return array(
				'valid'     => (bool) $data->valid,
				'formatted' => isset( $data->phone_number ) ? $data->phone_number : $phone,
			);
		}

		// If returning an error object (like 404 Not Found due to invalid format).
		if ( isset( $data->code ) ) {
			return array(
				'valid'     => false,
				'formatted' => $phone,
			);
		}

		return false;
	}

	/**
	 * Fetch message price from Twilio API.
	 *
	 * @param string $message_sid The Twilio Message SID.
	 * @return float|bool The price of the message or false on failure.
	 */
	public static function fetch_message_price( $message_sid ) {
		$sid   = get_option( 'om_twilio_sid' );
		$token = get_option( 'om_twilio_token' );

		if ( empty( $sid ) || empty( $token ) || empty( $message_sid ) ) {
			return false;
		}

		$url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages/$message_sid.json";

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

		if ( isset( $data->price ) && is_numeric( $data->price ) ) {
			// Twilio returns negative prices (e.g., -0.0075) to indicate cost.
			// We store it as a positive absolute value.
			return abs( (float) $data->price );
		}

		return false;
	}
}
