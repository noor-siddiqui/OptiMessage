<?php
/**
 * WooCommerce Integration for OptiMessage
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OM_WooCommerce
 * Handles WooCommerce hooks for orders and checkout.
 */
class OM_WooCommerce {


	/**
	 * Constructor.
	 */
	public function __construct() {
		// Checkout consent (Native Block & Shortcode 8.6+).
		add_action( 'woocommerce_init', array( $this, 'register_checkout_fields' ) );

		// Legacy Checkout.
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'legacy_add_checkout_consent_checkbox' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'legacy_checkout_process' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'legacy_save_checkout_consent' ) );

		// Blocks Checkout Save Hook for User Meta & Twilio Lookup.
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'block_save_checkout_consent' ) );

		// Order Statuses for SMS.
		add_action( 'woocommerce_order_status_processing', array( $this, 'trigger_order_placed' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'trigger_order_completed' ), 10, 2 );
		add_action( 'woocommerce_order_status_on-hold', array( $this, 'trigger_order_on_hold' ), 10, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'trigger_order_cancelled' ), 10, 2 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'trigger_order_refunded' ), 10, 2 );

		// Async Twilio Lookup.
		add_action( 'om_async_twilio_lookup_job', array( $this, 'process_async_twilio_lookup' ) );

		// ⚡ NEW: Async SMS Sending.
		add_action( 'om_async_send_sms_job', array( $this, 'process_async_sms_job' ), 10, 4 );

		// Built-in short URL redirect listener.
		add_action( 'init', array( $this, 'handle_short_url_redirect' ) );
	}

	/**
	 * Register checkout fields.
	 */
	public function register_checkout_fields() {
		if ( ! get_option( 'om_consent_checkout', 0 ) ) {
			return; // Disabled in settings.
		}

		if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			$is_required = get_option( 'om_consent_required', 0 ) ? true : false;
			woocommerce_register_additional_checkout_field(
				array(
					'id'       => 'om/sms_consent',
					'label'    => esc_html__( 'I want to receive SMS notifications about my order and promotions.', 'optimessage' ),
					'location' => 'contact',
					'type'     => 'checkbox',
					'required' => $is_required,
				)
			);
		}
	}

	/**
	 * Legacy add checkout consent checkbox.
	 */
	public function legacy_add_checkout_consent_checkbox() {
		if ( ! get_option( 'om_consent_checkout', 0 ) || function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		$is_required = get_option( 'om_consent_required', 0 ) ? true : false;

		woocommerce_form_field(
			'om_sms_consent',
			array(
				'type'     => 'checkbox',
				'class'    => array( 'form-row om-sms-consent' ),
				'required' => $is_required,
				'label'    => esc_html__( 'I want to receive SMS notifications about my order.', 'optimessage' ),
			),
			WC()->checkout->get_value( 'om_sms_consent' )
		);
	}

	/**
	 * Legacy checkout process validation.
	 */
	public function legacy_checkout_process() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) && get_option( 'om_consent_checkout', 0 ) && get_option( 'om_consent_required', 0 ) ) {

			// Safely retrieve and sanitize the POST variable before checking it.
         // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WooCommerce core during the checkout process.
			$sms_consent = isset( $_POST['om_sms_consent'] ) ? sanitize_text_field( wp_unslash( $_POST['om_sms_consent'] ) ) : '';

			if ( empty( $sms_consent ) ) {
				wc_add_notice( esc_html__( 'Please check the SMS consent box to proceed.', 'optimessage' ), 'error' );
			}
		}
	}

	/**
	 * Block save checkout consent.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function block_save_checkout_consent( $order ) {
		if ( ! get_option( 'om_consent_checkout', 0 ) ) {
			return;
		}
		$this->run_twilio_and_user_meta( $order );
	}

	/**
	 * Legacy save checkout consent.
	 *
	 * @param int $order_id The order ID.
	 */
	public function legacy_save_checkout_consent( $order_id ) {
		if ( ! get_option( 'om_consent_checkout', 0 ) ) {
			return;
		}

		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by WooCommerce core during the checkout process.
			$sms_consent_raw = isset( $_POST['om_sms_consent'] ) ? sanitize_text_field( wp_unslash( $_POST['om_sms_consent'] ) ) : '';

			// Determine 'yes' or 'no' based on the sanitized input.
			$consent = ! empty( $sms_consent_raw ) ? 'yes' : 'no';

			update_post_meta( $order_id, '_om_sms_consent', $consent );
		}

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->run_twilio_and_user_meta( $order );
		}
	}

	/**
	 * Run Twilio validation and save user meta.
	 *
	 * @param WC_Order $order The order object.
	 */
	private function run_twilio_and_user_meta( $order ) {
		$user_id = $order->get_customer_id();

		if ( $user_id ) {
			$consent = $this->has_consent( $order ) ? '1' : '0';
			update_user_meta( $user_id, 'optimessage/sms-consent', $consent );
		}

		$phone = $order->get_billing_phone();
		if ( ! empty( $phone ) ) {
			// Decouple Twilio API lookup to prevent blocking checkout.
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( 'om_async_twilio_lookup_job', array( $order->get_id() ) );
			} else {
				wp_schedule_single_event( time(), 'om_async_twilio_lookup_job', array( $order->get_id() ) );
			}
		}
	}

	/**
	 * Process async Twilio lookup for an order.
	 *
	 * @param int $order_id The order ID.
	 */
	public function process_async_twilio_lookup( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// ⚡ The Fix: Prevent Double-Lookups
		// If our JIT validation already formatted this number, skip it to save Twilio API costs!
		if ( '' !== $order->get_meta( '_om_phone_valid' ) ) {
			return;
		}

		$phone = $order->get_billing_phone();
		if ( empty( $phone ) ) {
			return;
		}

		$country = $order->get_billing_country();
		$lookup  = OM_Twilio_API::lookup_phone( $phone, $country );
		if ( is_array( $lookup ) ) {
			$user_id = $order->get_customer_id();
			if ( $lookup['valid'] ) {
				$order->set_billing_phone( $lookup['formatted'] );
				$order->update_meta_data( '_om_phone_valid', '1' );
				$order->save();

				if ( $user_id ) {
					update_user_meta( $user_id, 'billing_phone', $lookup['formatted'] );
					update_user_meta( $user_id, '_om_phone_valid', '1' );
				}
			} else {
				$order->update_meta_data( '_om_phone_valid', '-1' );
				$order->save();

				if ( $user_id ) {
					update_user_meta( $user_id, '_om_phone_valid', '-1' );
				}
			}
		}
	}

	/**
	 * Check if customer consented for this specific order or via their profile.
	 *
	 * @param  WC_Order $order The order object.
	 * @return bool
	 */
	private function has_consent( $order ) {
		if ( get_option( 'om_send_no_consent', 0 ) ) {
			return true;
		}

		$order_consent = $order->get_meta( '_wc_other/om/sms_consent' );
		if ( '' === $order_consent || null === $order_consent ) {
			$order_consent = $order->get_meta( 'om/sms_consent' );
		}
		if ( '' === $order_consent || null === $order_consent ) {
			$order_consent = $order->get_meta( '_om_sms_consent' );
		}

		if ( true === $order_consent || '1' === $order_consent || 'yes' === $order_consent ) {
			return true;
		}
		if ( false === $order_consent || '0' === $order_consent || 'no' === $order_consent ) {
			return false;
		}

		// Fallback to user meta.
		$user_id = $order->get_user_id();
		if ( $user_id ) {
			$user_consent = get_user_meta( $user_id, 'optimessage/sms-consent', true );
			return ( '1' === $user_consent || 'yes' === strtolower( $user_consent ) || 'on' === strtolower( $user_consent ) || 'true' === strtolower( $user_consent ) );
		}

		return false;
	}

	/**
	 * Trigger SMS on order placed.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order Object.
	 */
	public function trigger_order_placed( $order_id, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->has_consent( $order ) ) {
			return;
		}

		$template = get_option( 'om_tpl_placed' );
		$this->send_notification( $order, $template, 'placed' );
	}

	/**
	 * Trigger SMS on order completed.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order Object.
	 */
	public function trigger_order_completed( $order_id, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->has_consent( $order ) ) {
			return;
		}

		$template = get_option( 'om_tpl_completed' );
		$this->send_notification( $order, $template, 'completed' );
	}

	/**
	 * Trigger SMS on order on hold.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order Object.
	 */
	public function trigger_order_on_hold( $order_id, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->has_consent( $order ) ) {
			return;
		}

		$template = get_option( 'om_tpl_on_hold' );
		$this->send_notification( $order, $template, 'on_hold' );
	}

	/**
	 * Trigger SMS on order cancelled.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order Object.
	 */
	public function trigger_order_cancelled( $order_id, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->has_consent( $order ) ) {
			return;
		}

		$template = get_option( 'om_tpl_cancelled' );
		$this->send_notification( $order, $template, 'cancelled' );
	}

	/**
	 * Trigger SMS on order refunded.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order Object.
	 */
	public function trigger_order_refunded( $order_id, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->has_consent( $order ) ) {
			return;
		}

		$template = get_option( 'om_tpl_refunded' );
		$this->send_notification( $order, $template, 'refunded' );
	}

	/**
	 * Send the SMS notification.
	 *
	 * @param WC_Order $order    Order Object.
	 * @param string   $template The SMS template.
	 * @param string   $event    The event key (placed, completed, refunded).
	 */
	private function send_notification( $order, $template, $event = '' ) {
		if ( ! get_option( 'om_wc_sms_enabled', 1 ) ) {
			return;
		}

		// Check per-event toggle if an event key is provided.
		if ( ! empty( $event ) && ! get_option( 'om_wc_event_' . $event, 1 ) ) {
			return;
		}

		if ( empty( $template ) ) {
			return;
		}

		// ⚡ The Fix: Just-In-Time (JIT) Validation
		// If the async formatting job hasn't run yet, force it to run right now.
		if ( '' === $order->get_meta( '_om_phone_valid' ) ) {
			$this->process_async_twilio_lookup( $order->get_id() );
			// Reload the order from the database to grab the newly formatted phone number.
			$order = wc_get_order( $order->get_id() );
		}

		// If Twilio explicitly flagged the number as invalid (-1), don't waste money trying to send an SMS.
		if ( '-1' === $order->get_meta( '_om_phone_valid' ) ) {
			return;
		}

		$phone = $order->get_billing_phone();
		if ( empty( $phone ) ) {
			return;
		}

		$message = $this->parse_template( $template, $order );

		// ⚡ The Upgrade: True Async SMS Sending
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			// Push it to the WooCommerce Action Scheduler queue.
			as_enqueue_async_action( 'om_async_send_sms_job', array( $phone, $message, $order->get_user_id(), $order->get_id() ) );
		} else {
			// Fallback to standard WP-Cron just in case.
			wp_schedule_single_event( time(), 'om_async_send_sms_job', array( $phone, $message, $order->get_user_id(), $order->get_id() ) );
		}
	}

	/**
	 * Parse template variables.
	 *
	 * @param  string   $template The SMS template.
	 * @param  WC_Order $order    Order Object.
	 * @return string
	 */
	private function parse_template( $template, $order ) {
		$tracking_num_key = get_option( 'om_track_number_key', '_tracking_number' );
		$tracking_url_key = get_option( 'om_track_url_key', '_tracking_url' );

		$track_num = $order->get_meta( $tracking_num_key, true );
		$track_url = $order->get_meta( $tracking_url_key, true );
		$provider  = '';

		// Fallback: Parse PirateShip Order Notes.
		if ( empty( $track_num ) || empty( $track_url ) ) {
			$notes = wc_get_order_notes( array( 'order_id' => $order->get_id() ) );
			foreach ( $notes as $note ) {
				// Example format: "Order shipped via UPS with tracking number <a href="...">1ZC...</a>".
				if ( preg_match( '/shipped via (.*?) with tracking number.*?href=[\'"](.*?)[\'"].*?>(.*?)<\/a>/is', $note->content, $matches ) ) {
					$provider  = trim( $matches[1] );
					$track_url = trim( $matches[2] );
					$track_num = trim( $matches[3] );
					break;
				}
			}
		}

		if ( get_option( 'om_shorten_tracking_url', 0 ) && ! empty( $track_url ) ) {
			$track_url = $this->shorten_url( $track_url, $order );
		}

		$replacements = array(
			'{order_id}'          => $order->get_id(),
			'{first_name}'        => $order->get_billing_first_name(),
			'{last_name}'         => $order->get_billing_last_name(),
			'{total}'             => html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ),
			'{order_status}'      => wc_get_order_status_name( $order->get_status() ),
			'{order_date}'        => $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
			'{payment_method}'    => $order->get_payment_method_title(),
			'{site_name}'         => get_bloginfo( 'name' ),
			'{tracking_number}'   => $track_num,
			'{tracking_url}'      => $track_url,
			'{shipping_provider}' => $provider,
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Shorten URL using the built-in OptiMessage shortener.
	 *
	 * @param string   $url   The URL to shorten.
	 * @param WC_Order $order The order object.
	 * @return string The shortened URL.
	 */
	private function shorten_url( $url, $order ) {
		if ( empty( $url ) || ! $order ) {
			return $url;
		}

		$order_id = $order->get_id();
		// Create a 5-character hash to prevent guessing order tracking URLs.
		$hash = substr( wp_hash( $order_id . $url ), 0, 5 );

		// Use a very short internal URL: e.g. site.com/?omt=123xABCDE.
		return home_url( '/?omt=' . $order_id . 'x' . $hash );
	}

	/**
	 * Handle redirection for the built-in short URLs.
	 */
	public function handle_short_url_redirect() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['omt'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$omt   = sanitize_text_field( wp_unslash( $_GET['omt'] ) );
			$parts = explode( 'x', $omt );

			if ( 2 === count( $parts ) ) {
				$order_id = intval( $parts[0] );
				$hash     = $parts[1];

				$order = wc_get_order( $order_id );
				if ( $order ) {
					$tracking_url_key = get_option( 'om_track_url_key', '_tracking_url' );
					$track_url        = $order->get_meta( $tracking_url_key, true );

					// Fallback: Parse PirateShip Order Notes.
					if ( empty( $track_url ) ) {
						$tracking_num_key = get_option( 'om_track_number_key', '_tracking_number' );
						$track_num        = $order->get_meta( $tracking_num_key, true );
						$notes            = wc_get_order_notes( array( 'order_id' => $order_id ) );
						foreach ( $notes as $note ) {
							if ( preg_match( '/shipped via (.*?) with tracking number.*?href=[\'"](.*?)[\'"].*?>(.*?)<\/a>/is', $note->content, $matches ) ) {
								$track_url = trim( $matches[2] );
								break;
							}
						}
					}

					if ( ! empty( $track_url ) ) {
						$expected_hash = substr( wp_hash( $order_id . $track_url ), 0, 5 );
						if ( hash_equals( $expected_hash, $hash ) ) {
							// Record click analytics.
							$clicks = (int) $order->get_meta( '_om_short_url_clicks', true );
							$order->update_meta_data( '_om_short_url_clicks', $clicks + 1 );
							$order->save_meta_data();

							// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
							wp_redirect( esc_url_raw( $track_url ) );
							exit;
						}
					}
				}
			}

			// If invalid or not found, safely redirect to the homepage.
			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Process async SMS sending job.
	 *
	 * @param string $phone    The recipient phone number.
	 * @param string $message  The message text.
	 * @param int    $user_id  The user ID.
	 * @param int    $order_id The order ID.
	 */
	public function process_async_sms_job( $phone, $message, $user_id, $order_id ) {
		OM_Twilio_API::send_sms( $phone, $message, $user_id, $order_id );
	}
}

new OM_WooCommerce();
