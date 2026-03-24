<?php
/**
 * WooCommerce Integration for OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DSN_WooCommerce {


	public function __construct() {
		// Checkout consent (Native Block & Shortcode 8.6+)
		add_action( 'woocommerce_init', array( $this, 'register_checkout_fields' ) );

		// Legacy Checkout
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'legacy_add_checkout_consent_checkbox' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'legacy_checkout_process' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'legacy_save_checkout_consent' ), 10, 2 );

		// Blocks Checkout Save Hook for User Meta & Twilio Lookup
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'block_save_checkout_consent' ), 10, 2 );

		// Order Statuses for SMS
		add_action( 'woocommerce_order_status_processing', array( $this, 'trigger_order_placed' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'trigger_order_completed' ), 10, 2 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'trigger_order_refunded' ), 10, 2 );
	}

	public function register_checkout_fields() {
		if ( ! get_option( 'dsn_consent_checkout', 0 ) ) {
			return; // Disabled in settings
		}

		if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			$is_required = get_option( 'dsn_consent_required', 0 ) ? true : false;
			woocommerce_register_additional_checkout_field(
				array(
					'id'       => 'dsn/sms_consent',
					'label'    => esc_html__( 'I want to receive SMS notifications about my order.', 'desishad-sms-notifier' ),
					'location' => 'contact',
					'type'     => 'checkbox',
					'required' => $is_required,
				)
			);
		}
	}

	public function legacy_add_checkout_consent_checkbox() {
		if ( ! get_option( 'dsn_consent_checkout', 0 ) || function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		$is_required = get_option( 'dsn_consent_required', 0 ) ? true : false;

		woocommerce_form_field(
			'dsn_sms_consent',
			array(
				'type'     => 'checkbox',
				'class'    => array( 'form-row dsn-sms-consent' ),
				'required' => $is_required,
				'label'    => esc_html__( 'I want to receive SMS notifications about my order.', 'desishad-sms-notifier' ),
			),
			WC()->checkout->get_value( 'dsn_sms_consent' )
		);
	}

	public function legacy_checkout_process() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) && get_option( 'dsn_consent_checkout', 0 ) && get_option( 'dsn_consent_required', 0 ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( empty( $_POST['dsn_sms_consent'] ) ) {
				wc_add_notice( esc_html__( 'Please check the SMS consent box to proceed.', 'desishad-sms-notifier' ), 'error' );
			}
		}
	}

	public function block_save_checkout_consent( $order, $request ) {
		if ( ! get_option( 'dsn_consent_checkout', 0 ) ) {
			return;
		}
		$this->run_twilio_and_user_meta( $order );
	}

	public function legacy_save_checkout_consent( $order_id, $data ) {
		if ( ! get_option( 'dsn_consent_checkout', 0 ) ) {
			return;
		}

		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$consent = isset( $_POST['dsn_sms_consent'] ) && ! empty( $_POST['dsn_sms_consent'] ) ? 'yes' : 'no';
			update_post_meta( $order_id, '_dsn_sms_consent', $consent );
		}

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->run_twilio_and_user_meta( $order );
		}
	}

	private function run_twilio_and_user_meta( $order ) {
		$user_id = $order->get_customer_id();

		if ( $user_id ) {
			$consent = $this->has_consent( $order ) ? '1' : '0';
			update_user_meta( $user_id, 'desishad/sms-consent', $consent );
		}

		$phone = $order->get_billing_phone();
		if ( ! empty( $phone ) ) {
			$country = $order->get_billing_country();
			$lookup  = DSN_Twilio_API::lookup_phone( $phone, $country );
			if ( is_array( $lookup ) ) {
				if ( $lookup['valid'] ) {
					$order->set_billing_phone( $lookup['formatted'] );
					$order->update_meta_data( '_dsn_phone_valid', '1' );
					$order->save();

					if ( $user_id ) {
						update_user_meta( $user_id, 'billing_phone', $lookup['formatted'] );
						update_user_meta( $user_id, '_dsn_phone_valid', '1' );
					}
				} else {
					$order->update_meta_data( '_dsn_phone_valid', '-1' );
					$order->save();

					if ( $user_id ) {
						update_user_meta( $user_id, '_dsn_phone_valid', '-1' );
					}
				}
			}
		}
	}

	/**
	 * Check if customer consented for this specific order or via their profile
	 */
	private function has_consent( $order ) {
		if ( get_option( 'dsn_send_no_consent', 0 ) ) {
			return true;
		}

		$order_consent = $order->get_meta( '_wc_other/dsn/sms_consent' );
		if ( $order_consent === '' || $order_consent === null ) {
			$order_consent = $order->get_meta( 'dsn/sms_consent' );
		}
		if ( $order_consent === '' || $order_consent === null ) {
			$order_consent = $order->get_meta( '_dsn_sms_consent' );
		}

		if ( $order_consent === true || $order_consent === '1' || $order_consent === 'yes' ) {
			return true;
		}
		if ( $order_consent === false || $order_consent === '0' || $order_consent === 'no' ) {
			return false;
		}

		// Fallback to user meta
		$user_id = $order->get_user_id();
		if ( $user_id ) {
			$user_consent = get_user_meta( $user_id, 'desishad/sms-consent', true );
			return ( $user_consent == '1' || strtolower( $user_consent ) === 'yes' || strtolower( $user_consent ) === 'on' || strtolower( $user_consent ) === 'true' );
		}

		return false;
	}

	public function trigger_order_placed( $order_id, $order ) {
		if ( ! $this->has_consent( $order ) ) {
			return;
		}

		$template = get_option( 'dsn_tpl_placed' );
		$this->send_notification( $order, $template );
	}

	public function trigger_order_completed( $order_id, $order ) {
		if ( ! $this->has_consent( $order ) ) {
			return;
		}

		$template = get_option( 'dsn_tpl_completed' );
		$this->send_notification( $order, $template );
	}

	public function trigger_order_refunded( $order_id, $order ) {
		if ( ! $this->has_consent( $order ) ) {
			return;
		}

		$template = get_option( 'dsn_tpl_refunded' );
		$this->send_notification( $order, $template );
	}

	private function send_notification( $order, $template ) {
		if ( ! get_option( 'dsn_wc_sms_enabled', 1 ) ) {
			return;
		}

		if ( empty( $template ) ) {
			return;
		}

		$phone = $order->get_billing_phone();
		if ( empty( $phone ) ) {
			return;
		}

		$message = $this->parse_template( $template, $order );
		DSN_Twilio_API::send_sms( $phone, $message, $order->get_user_id(), $order->get_id() );
	}

	private function parse_template( $template, $order ) {
		$tracking_num_key = get_option( 'dsn_track_number_key', '_tracking_number' );
		$tracking_url_key = get_option( 'dsn_track_url_key', '_tracking_url' );

		$track_num = $order->get_meta( $tracking_num_key, true );
		$track_url = $order->get_meta( $tracking_url_key, true );
		$provider  = '';

		// Fallback: Parse PirateShip Order Notes
		if ( empty( $track_num ) || empty( $track_url ) ) {
			$notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
			foreach ( $notes as $note ) {
				// Example format: "Order shipped via UPS with tracking number <a href="...">1ZC...</a>"
				if ( preg_match( '/shipped via (.*?) with tracking number.*?href=[\'"](.*?)[\'"].*?>(.*?)<\/a>/is', $note->content, $matches ) ) {
					$provider  = trim( $matches[1] );
					$track_url = trim( $matches[2] );
					$track_num = trim( $matches[3] );
					break;
				}
			}
		}

		$replacements = array(
			'{order_id}'          => $order->get_id(),
			'{first_name}'        => $order->get_billing_first_name(),
			'{last_name}'         => $order->get_billing_last_name(),
			'{total}'             => html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ),
			'{tracking_number}'   => $track_num,
			'{tracking_url}'      => $track_url,
			'{shipping_provider}' => $provider,
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}
}

new DSN_WooCommerce();
