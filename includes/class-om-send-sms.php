<?php
/**
 * Send SMS Interface for OptiMessage SMS Notifier
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OM_Send_SMS
 * Handles sending single and bulk SMS messages.
 */
class OM_Send_SMS {


	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'om_render_send_sms_tab', array( $this, 'render' ) );
		add_action( 'admin_init', array( $this, 'process_send_sms_actions' ) );
		add_action( 'wp_ajax_om_setup_sms_queue', array( $this, 'ajax_setup_sms_queue' ) );
		add_action( 'wp_ajax_om_setup_csv_queue', array( $this, 'ajax_setup_csv_queue' ) );
		add_action( 'wp_ajax_om_process_sms_batch', array( $this, 'ajax_process_sms_batch' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Render the send SMS page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$send_type = isset( $_GET['send_type'] ) ? sanitize_text_field( wp_unslash( $_GET['send_type'] ) ) : 'single';

		?>
		<h2><?php esc_html_e( 'Send SMS', 'optimessage' ); ?></h2>

		<h3 class="nav-tab-wrapper">
			<a href="?page=om-settings&tab=send&send_type=single" class="nav-tab <?php echo 'single' === $send_type ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Single SMS', 'optimessage' ); ?></a>
			<a href="?page=om-settings&tab=send&send_type=bulk_csv" class="nav-tab <?php echo 'bulk_csv' === $send_type ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Bulk (CSV Upload)', 'optimessage' ); ?></a>
			<a href="?page=om-settings&tab=send&send_type=bulk_filter" class="nav-tab <?php echo 'bulk_filter' === $send_type ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Bulk (Product Filter)', 'optimessage' ); ?></a>
		</h3>

		<div class="wrap" style="margin-top: 20px;">
		<?php
		if ( 'single' === $send_type ) {
			$this->render_single_sms_form();
		} elseif ( 'bulk_csv' === $send_type ) {
			$this->render_bulk_csv_form();
		} elseif ( 'bulk_filter' === $send_type ) {
			$this->render_bulk_filter_form();
		}
		?>
		</div>

		<?php
	}

	/**
	 * Render single SMS form.
	 */
	private function render_single_sms_form() {
		?>
		<form method="post" action="">
		<?php wp_nonce_field( 'om_send_single_sms', 'om_nonce' ); ?>
			<input type="hidden" name="om_action" value="send_single" />
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Phone Number (With Country Code)', 'optimessage' ); ?></th>
					<td><input type="text" name="phone_number" class="regular-text" required placeholder="+1234567890" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Message', 'optimessage' ); ?></th>
					<td>
						<textarea name="message" rows="5" class="large-text" required></textarea>
					</td>
				</tr>
			</table>
		<?php submit_button( esc_html__( 'Send Single SMS', 'optimessage' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render bulk CSV upload form.
	 */
	private function render_bulk_csv_form() {
		?>
		<p><?php esc_html_e( 'Upload a CSV file containing phone numbers (with country code) in the first column. One number per row.', 'optimessage' ); ?></p>
		<p>
			<a href="<?php echo esc_url( plugin_dir_url( __DIR__ ) . 'assets/templates/sms-template.csv' ); ?>" download class="button button-secondary">
				<span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Download Template CSV', 'optimessage' ); ?>
			</a>
		</p>
		<form method="post" action="" enctype="multipart/form-data" id="om-bulk-csv-form">
		<?php wp_nonce_field( 'om_send_bulk_csv', 'om_nonce' ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'CSV File', 'optimessage' ); ?></th>
					<td>
						<input type="file" name="csv_file" accept=".csv" required />
						<p class="description"><?php esc_html_e( 'First column must contain phone numbers with country code (e.g. 8801712345678). The "+" prefix is optional — it will be added automatically if missing.', 'optimessage' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Message', 'optimessage' ); ?></th>
					<td>
						<textarea name="message" rows="5" class="large-text" required></textarea>
					</td>
				</tr>
			</table>
		<p class="submit">
			<button type="submit" class="button button-primary" id="om-csv-send-btn"><?php esc_html_e( 'Send to CSV Numbers', 'optimessage' ); ?></button>
		</p>

		<div id="om-csv-progress-wrapper" style="display:none; margin-top: 20px; max-width: 600px;">
			<p><strong id="om-csv-progress-text"><?php esc_html_e( 'Preparing messages...', 'optimessage' ); ?></strong></p>
			<div style="background: #e0e0e0; border-radius: 4px; height: 24px; width: 100%; overflow: hidden;">
				<div id="om-csv-progress-bar" style="background: #2271b1; width: 0%; height: 100%; transition: width 0.3s ease;"></div>
			</div>
		</div>
		</form>
		<?php
	}

	/**
	 * Render bulk filter form.
	 */
	private function render_bulk_filter_form() {

		// Calculate unique customers natively. Cached to prevent slowdowns.
		$is_no_consent_allowed = get_option( 'om_send_no_consent', 0 );
		$cache_key             = 'om_bulk_counts_' . ( $is_no_consent_allowed ? 'all' : 'consent' );
		$map                   = get_transient( $cache_key );

		if ( false === $map ) {
			$map    = array(
				'all'      => array(),
				'products' => array(),
			);
			$orders = wc_get_orders(
				array(
					'status' => array( 'wc-processing', 'wc-completed' ),
					'limit'  => -1,
				)
			);

			if ( $orders ) {
				// Pre-load user meta cache to avoid N+1 queries during the loop.
				$customer_ids = array();
				foreach ( $orders as $order ) {
					if ( $order->get_customer_id() ) {
						$customer_ids[] = $order->get_customer_id();
					}
				}
				$user_consents = array();
				if ( ! empty( $customer_ids ) ) {
					global $wpdb;
					$unique_ids = array_unique( $customer_ids );
					// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$ids_string = implode( ',', array_map( 'intval', $unique_ids ) );
					$results    = $wpdb->get_results(
						"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'optimessage/sms-consent' AND user_id IN ({$ids_string})"
					);
					// phpcs:enable
					if ( $results ) {
						foreach ( $results as $row ) {
							$user_consents[ $row->user_id ] = $row->meta_value;
						}
					}
				}

				foreach ( $orders as $order ) {
								$phone = $order->get_billing_phone();
					if ( empty( $phone ) ) {
						continue;
					}

					// Skip invalid phone numbers.
					if ( '-1' === $order->get_meta( '_om_phone_valid' ) ) {
						continue;
					}

					// Check consent logic to accurately predict who gets the SMS.
					if ( ! $is_no_consent_allowed ) {
						$has_consent   = false;
						$order_consent = $order->get_meta( '_wc_other/om/sms_consent' );
						if ( '' === $order_consent || null === $order_consent ) {
							$order_consent = $order->get_meta( 'om/sms_consent' );
						}
						if ( '' === $order_consent || null === $order_consent ) {
							$order_consent = $order->get_meta( '_om_sms_consent' );
						}

						if ( true === $order_consent || '1' === $order_consent || 'yes' === $order_consent ) {
							$has_consent = true;
						} elseif ( false === $order_consent || '0' === $order_consent || 'no' === $order_consent ) {
							$has_consent = false;
						} else {
							$customer_id = $order->get_customer_id();
							if ( $customer_id ) {
								$user_consent = isset( $user_consents[ $customer_id ] ) ? $user_consents[ $customer_id ] : '';
								if ( ! empty( $user_consent ) && ( '1' === $user_consent || 'yes' === strtolower( $user_consent ) || 'on' === strtolower( $user_consent ) || 'true' === strtolower( $user_consent ) ) ) {
										$has_consent = true;
								}
							}
						}

						if ( ! $has_consent ) {
							continue;
						}
					}

					// Track unique phone numbers.
					$map['all'][ $phone ] = $order->get_customer_id();

					foreach ( $order->get_items() as $item ) {
						$pid = $item->get_product_id();
						if ( ! isset( $map['products'][ $pid ] ) ) {
							$map['products'][ $pid ] = array();
						}
						$map['products'][ $pid ][ $phone ] = $order->get_customer_id();
					}
				}
			}
			set_transient( $cache_key, $map, HOUR_IN_SECONDS );
		}

		$all_count      = isset( $map['all'] ) ? count( $map['all'] ) : 0;
		$product_counts = array();
		if ( isset( $map['products'] ) ) {
			foreach ( $map['products'] as $pid => $phones ) {
				$product_counts[ $pid ] = count( $phones );
			}
		}
		?>
		<p><?php esc_html_e( 'Send a message to customers based on past purchases.', 'optimessage' ); ?></p>
		<p class="description"><?php esc_html_e( 'Only sends to users who have given consent via the sms-consent field (unless the "Send SMS to Users Without Consent" option is enabled in General Settings) AND have a valid billing phone number.', 'optimessage' ); ?></p>
		<div class="notice notice-info inline" style="margin: 15px 0;">
			<p>
				<strong>
					<span id="om-reachable-label"><?php esc_html_e( 'Total Reachable Customers (All Products)', 'optimessage' ); ?></span>:
					<span id="om-reachable-count"><?php echo (int) $all_count; ?></span>
				</strong>
			</p>
		</div>
		<form method="post" action="" id="om-bulk-filter-form">
		<?php wp_nonce_field( 'om_send_bulk_filter', 'om_nonce' ); ?>
			<input type="hidden" name="om_action" value="send_bulk_filter" />
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Filter by Product', 'optimessage' ); ?></th>
					<td>
						<select class="wc-product-search" style="width: 50%; max-width: 400px;" name="product_id" data-placeholder="<?php esc_attr_e( 'Search for a product... (Leave blank for all)', 'optimessage' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">
							</select>
						<p class="description"><?php esc_html_e( 'Start typing a product name to narrow down recipients. If you leave this blank, the message will be sent to ALL reachable customers.', 'optimessage' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Message', 'optimessage' ); ?></th>
					<td>
						<textarea name="message" rows="5" class="large-text" required></textarea>
					</td>
				</tr>
			</table>
		<p class="submit">
				<button type="submit" class="button button-primary" id="om-send-btn"><?php esc_html_e( 'Send SMS', 'optimessage' ); ?></button>
			</p>

			<div id="om-progress-wrapper" style="display:none; margin-top: 20px; max-width: 600px;">
				<p><strong id="om-progress-text"><?php esc_html_e( 'Preparing messages...', 'optimessage' ); ?></strong></p>
				<div style="background: #e0e0e0; border-radius: 4px; height: 24px; width: 100%; overflow: hidden;">
					<div id="om-progress-bar" style="background: #2271b1; width: 0%; height: 100%; transition: width 0.3s ease;"></div>
				</div>
			</div>
		</form>
		<script>
			var omProductCounts = <?php echo wp_json_encode( $product_counts ); ?>;
			var omAllCount = <?php echo (int) $all_count; ?>;
		</script>
		<?php
	}

	/**
	 * Process form submissions.
	 */
	public function process_send_sms_actions() {
		if ( ! isset( $_POST['om_action'] ) || ! isset( $_POST['om_nonce'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['om_action'] ) );
		$nonce  = sanitize_text_field( wp_unslash( $_POST['om_nonce'] ) );

		if ( 'send_single' === $action && wp_verify_nonce( $nonce, 'om_send_single_sms' ) ) {
			$this->handle_single_sms();
		} else {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'om-settings',
					'tab'  => 'history',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle single SMS submission.
	 */
	private function handle_single_sms() {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is explicitly verified in the calling method.
		$to = isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is explicitly verified in the calling method.
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( $to && $message ) {
			OM_Twilio_API::send_sms( $to, $message );
		}
	}

	/**
	 * AJAX Action: Setup the Product Filter Queue.
	 */
	public function ajax_setup_sms_queue() {
		check_ajax_referer( 'om_send_bulk_filter', 'om_nonce' );

		$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : '';
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( empty( $message ) ) {
			wp_send_json_error( 'Message is empty.' );
		}

		// Grab your map from the transient cache just like before.
		$is_no_consent_allowed = get_option( 'om_send_no_consent', 0 );
		$cache_key             = 'om_bulk_counts_' . ( $is_no_consent_allowed ? 'all' : 'consent' );
		$map                   = get_transient( $cache_key );

		if ( false === $map ) {
			wp_send_json_error( 'Cache expired. Please refresh the page.' );
		}

		$target_phones = array();
		if ( ( empty( $product_id ) || 'all' === $product_id ) && isset( $map['all'] ) ) {
			$target_phones = $map['all'];
		} elseif ( isset( $map['products'][ $product_id ] ) ) {
			$target_phones = $map['products'][ $product_id ];
		}

		// Save the queue for the batch processor.
		$queue_data = array(
			'message' => $message,
			'phones'  => $target_phones,
		);
		set_transient( 'om_sms_queue_' . get_current_user_id(), $queue_data, HOUR_IN_SECONDS );

		wp_send_json_success( array( 'total' => count( $target_phones ) ) );
	}

	/**
	 * AJAX Action: Setup the CSV Queue.
	 * Reads the uploaded CSV file and stores phone numbers in a transient queue.
	 */
	public function ajax_setup_csv_queue() {
		check_ajax_referer( 'om_send_bulk_csv', 'om_nonce' );

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( empty( $message ) ) {
			wp_send_json_error( 'Message is empty.' );
		}

		// Extract the temporary file path — this is a PHP system value, not user input.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tmp_name is a server-provided filesystem path; sanitization would corrupt it.
		$csv_tmp_name = isset( $_FILES['csv_file']['tmp_name'] ) ? $_FILES['csv_file']['tmp_name'] : '';

		if ( empty( $csv_tmp_name ) || ! is_uploaded_file( $csv_tmp_name ) ) {
			wp_send_json_error( 'Invalid or missing CSV file.' );
		}

		// Read phone numbers from CSV.
		$raw_phones = array();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reading a temporary uploaded file.
		$file = fopen( $csv_tmp_name, 'r' );
		if ( $file ) {
			$is_first_row = true;
			while ( ( $row = fgetcsv( $file ) ) !== false ) {
				$phone = isset( $row[0] ) ? sanitize_text_field( $row[0] ) : '';

				// Skip header row if it doesn't look like a phone number.
				if ( $is_first_row ) {
					$is_first_row = false;
					if ( ! empty( $phone ) && ! preg_match( '/^[+\d]/', $phone ) ) {
						continue;
					}
				}

				if ( ! empty( $phone ) ) {
					$raw_phones[ $phone ] = 0;
				}
			}
			fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}

		// Validate each number via Twilio Lookup API.
		$valid_phones   = array();
		$invalid_phones = array();

		foreach ( $raw_phones as $phone => $user_id ) {
			$result = OM_Twilio_API::lookup_phone( $phone );

			if ( is_array( $result ) && ! empty( $result['valid'] ) ) {
				// Use the E.164 formatted number from Twilio.
				$valid_phones[ $result['formatted'] ] = $user_id;
			} else {
				$invalid_phones[] = $phone;
			}
		}

		// Store only valid numbers in the queue.
		$queue_data = array(
			'message' => $message,
			'phones'  => $valid_phones,
		);
		set_transient( 'om_sms_queue_' . get_current_user_id(), $queue_data, HOUR_IN_SECONDS );

		wp_send_json_success(
			array(
				'total'          => count( $valid_phones ),
				'invalid_count'  => count( $invalid_phones ),
				'invalid_phones' => $invalid_phones,
			)
		);
	}

	/**
	 * AJAX Action 2: Process a Batch
	 */
	public function ajax_process_sms_batch() {
		check_ajax_referer( 'om_process_batch', 'om_nonce' );

		$transient_name = 'om_sms_queue_' . get_current_user_id();
		$queue_data     = get_transient( $transient_name );

		if ( false === $queue_data || empty( $queue_data['phones'] ) ) {
			wp_send_json_success(
				array(
					'is_done'   => true,
					'processed' => 0,
				)
			);
			return;
		}

		$message = $queue_data['message'];
		$phones  = $queue_data['phones'];

		// Slicing: Take the first 10 items off the array to process now.
		$batch_size = 10;
		$batch      = array_slice( $phones, 0, $batch_size, true );

		// Remove those 10 items from the main queue array.
		$phones = array_diff_key( $phones, $batch );

		// Fire off the 10 messages.
		$processed_count = 0;
		foreach ( $batch as $phone => $customer_id ) {
			OM_Twilio_API::send_sms( $phone, $message, $customer_id );
			++$processed_count;
		}

		// Are we completely done?
		if ( empty( $phones ) ) {
			delete_transient( $transient_name );
			wp_send_json_success(
				array(
					'is_done'   => true,
					'processed' => $processed_count,
				)
			);
		} else {
			// Save the remaining phones back to the transient for the next AJAX call.
			$queue_data['phones'] = $phones;
			set_transient( $transient_name, $queue_data, HOUR_IN_SECONDS );

			wp_send_json_success(
				array(
					'is_done'   => false,
					'processed' => $processed_count,
				)
			);
		}
	}

	/**
	 * Enqueue admin scripts and pass translatable strings to JS.
	 */
	public function enqueue_admin_scripts() {
		// Only load this script on the OptiMessage settings page to save resources.
		if ( ! isset( $_GET['page'] ) || 'om-settings' !== $_GET['page'] ) {
			return;
		}

		// Register and enqueue the script.
		wp_enqueue_script(
			'optimessage-admin-js',
			plugin_dir_url( __DIR__ ) . 'assets/js/optimessage-admin.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		wp_localize_script(
			'optimessage-admin-js',
			'omData',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'batch_nonce' => wp_create_nonce( 'om_process_batch' ),
				'strings'     => array(
					'processing'         => __( 'Processing...', 'optimessage' ),
					'no_customers'       => __( 'No eligible customers found.', 'optimessage' ),
					'send_sms'           => __( 'Send SMS', 'optimessage' ),
					'send_csv'           => __( 'Send to CSV Numbers', 'optimessage' ),
					'error'              => __( 'Error', 'optimessage' ),
					'sent'               => __( 'Sent', 'optimessage' ),
					'of'                 => __( 'of', 'optimessage' ),
					'success'            => __( 'Success!', 'optimessage' ),
					'messages_sent'      => __( 'messages sent.', 'optimessage' ),
					'finished'           => __( 'Finished', 'optimessage' ),
					'error_processing'   => __( 'Error during processing', 'optimessage' ),
					'server_lost'        => __( 'Server connection lost. Check history to see progress.', 'optimessage' ),
					'reachable_all'      => __( 'Total Reachable Customers (All Products)', 'optimessage' ),
					'reachable_filtered' => __( 'Total Reachable Customers (Filtered)', 'optimessage' ),
					'send_another'       => __( 'Send Another', 'optimessage' ),
					'invalid_numbers'    => __( 'Invalid Numbers Skipped', 'optimessage' ),
					// translators: %d is the number of reachable customers.
					'validated'          => __( 'Validated! Sending to %d numbers...', 'optimessage' ),
				),
			)
		);
	}
}

new OM_Send_SMS();
