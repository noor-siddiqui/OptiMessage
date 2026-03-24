<?php
/**
 * Webhook Handler for Twilio Delivery Status
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSN_Webhook {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'dsn/v1', '/twilio-webhook', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_webhook' ),
			'permission_callback' => '__return_true', // Twilio hits this publicly
		) );
	}

	public function handle_webhook( WP_REST_Request $request ) {
		global $wpdb;

		// Twilio sends data as form-urlencoded POST body
		$params = $request->get_body_params();

		$message_sid = isset( $params['MessageSid'] ) ? sanitize_text_field( $params['MessageSid'] ) : '';
		$status      = isset( $params['MessageStatus'] ) ? sanitize_text_field( $params['MessageStatus'] ) : '';

		if ( ! empty( $message_sid ) && ! empty( $status ) ) {
			$table_name = $wpdb->prefix . 'dsn_sms_history';

			// Update the status in our history table
			$wpdb->update(
				$table_name,
				array( 'status' => $status ), // Twilio statuses: queued, failed, sent, delivered, undelivered
				array( 'message_sid' => $message_sid ),
				array( '%s' ),
				array( '%s' )
			);
		}

		// Twilio expects a 200 OK or XML response.
		// Returning a simple 200 OK via WP REST API is sufficient.
		return new WP_REST_Response( 'OK', 200 );
	}
}

new DSN_Webhook();
