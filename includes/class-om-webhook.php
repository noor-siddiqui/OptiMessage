<?php
/**
 * Webhook Handler for Twilio Delivery Status
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OM_Webhook
 * Handles incoming webhooks.
 */
class OM_Webhook {


	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'om/v1',
			'/twilio-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'verify_twilio_signature' ),
			)
		);
	}

	/**
	 * Verify the incoming request is genuinely from Twilio using X-Twilio-Signature.
	 *
	 * @param  WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function verify_twilio_signature( WP_REST_Request $request ) {
		$token = get_option( 'om_twilio_token' );
		if ( empty( $token ) ) {
			return new WP_Error( 'om_webhook_no_token', 'Twilio Auth Token not configured.', array( 'status' => 403 ) );
		}

		$signature = $request->get_header( 'X-Twilio-Signature' );
		if ( empty( $signature ) ) {
			return new WP_Error( 'om_webhook_no_sig', 'Missing Twilio signature.', array( 'status' => 403 ) );
		}

		$params = $request->get_body_params();

		// Twilio validation: sort POST params by key, concatenate key+value, HMAC-SHA1 with Auth Token.
		ksort( $params );
		$post_data = '';
		foreach ( $params as $key => $value ) {
			$post_data .= $key . $value;
		}

		// ⚡ The Fix: Handle Live Server Proxies & URL Re-writes
		// Often rest_url() differs from the actual external URL Twilio hits due to load balancers (HTTP vs HTTPS).
		$urls_to_test = array(
			rest_url( 'om/v1/twilio-webhook' ),
		);

		// Reconstruct the exact external requested URL to bypass proxy issues.
		$protocol = ( isset( $_SERVER['HTTPS'] ) && 'on' === strtolower( $_SERVER['HTTPS'] ) ) || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ? 'https' : 'http';
		$host     = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
		$uri      = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

		if ( $host && $uri ) {
			$reconstructed_url = $protocol . '://' . $host . $uri;
			$urls_to_test[]    = $reconstructed_url;
			// Also account for potential trailing slash differences caused by server redirects.
			$urls_to_test[] = rtrim( $reconstructed_url, '/' );
			$urls_to_test[] = rtrim( $reconstructed_url, '/' ) . '/';
		}

		$urls_to_test = array_unique( $urls_to_test );
		$is_valid     = false;

		foreach ( $urls_to_test as $url ) {
			$data = $url . $post_data;
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$expected = base64_encode( hash_hmac( 'sha1', $data, $token, true ) );

			if ( hash_equals( $expected, $signature ) ) {
				$is_valid = true;
				break;
			}
		}

		if ( ! $is_valid ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( 'OptiMessage Webhook Error: Invalid Twilio signature. Request URLs tested: ' . print_r( $urls_to_test, true ) );
			return new WP_Error( 'om_webhook_invalid_sig', 'Invalid Twilio signature.', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Handle webhook request.
	 *
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		global $wpdb;

		// Twilio sends data as form-urlencoded POST body.
		$params = $request->get_body_params();

		$message_sid = isset( $params['MessageSid'] ) ? sanitize_text_field( $params['MessageSid'] ) : '';
		$status      = isset( $params['MessageStatus'] ) ? sanitize_text_field( $params['MessageStatus'] ) : '';

		if ( ! empty( $message_sid ) && ! empty( $status ) ) {
			$table_name = $wpdb->prefix . 'om_sms_history';

			// Update the status in our history table.
			$wpdb->update(
				$table_name,
				array( 'status' => $status ), // Twilio statuses: queued, failed, sent, delivered, undelivered.
				array( 'message_sid' => $message_sid ),
				array( '%s' ),
				array( '%s' )
			);

			// If the message has reached a final state, enqueue a job to fetch its price from Twilio.
			if ( in_array( $status, array( 'delivered', 'undelivered', 'failed' ), true ) ) {
				if ( function_exists( 'as_enqueue_async_action' ) ) {
					as_enqueue_async_action( 'om_async_fetch_sms_price_job', array( $message_sid ) );
				} else {
					wp_schedule_single_event( time(), 'om_async_fetch_sms_price_job', array( $message_sid ) );
				}
			}
		}

		// Twilio expects a 200 OK or XML response.
		// Returning a simple 200 OK via WP REST API is sufficient.
		return new WP_REST_Response( 'OK', 200 );
	}
}

new OM_Webhook();
