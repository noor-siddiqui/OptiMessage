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
 * Class DSN_Send_SMS
 * Handles sending single and bulk SMS messages.
 */
class DSN_Send_SMS {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'dsn_render_send_sms_tab', array( $this, 'render' ) );
		add_action( 'admin_init', array( $this, 'process_send_sms_actions' ) );
	}

	/**
	 * Render the send SMS page.
	 */
	public function render() {
		$send_type = isset( $_GET['send_type'] ) ? sanitize_text_field( wp_unslash( $_GET['send_type'] ) ) : 'single';

		?>
		<h2><?php esc_html_e( 'Send SMS', 'desishad-sms-notifier' ); ?></h2>

		<h3 class="nav-tab-wrapper">
			<a href="?page=dsn-settings&tab=send&send_type=single" class="nav-tab <?php echo 'single' === $send_type ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Single SMS', 'desishad-sms-notifier' ); ?></a>
			<a href="?page=dsn-settings&tab=send&send_type=bulk_csv" class="nav-tab <?php echo 'bulk_csv' === $send_type ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Bulk (CSV Upload)', 'desishad-sms-notifier' ); ?></a>
			<a href="?page=dsn-settings&tab=send&send_type=bulk_filter" class="nav-tab <?php echo 'bulk_filter' === $send_type ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Bulk (Product/Role Filter)', 'desishad-sms-notifier' ); ?></a>
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
		<?php wp_nonce_field( 'dsn_send_single_sms', 'dsn_nonce' ); ?>
			<input type="hidden" name="dsn_action" value="send_single" />
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Phone Number (With Country Code)', 'desishad-sms-notifier' ); ?></th>
					<td><input type="text" name="phone_number" class="regular-text" required placeholder="+1234567890" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Message', 'desishad-sms-notifier' ); ?></th>
					<td>
						<textarea name="message" rows="5" class="large-text" required></textarea>
					</td>
				</tr>
			</table>
		<?php submit_button( esc_html__( 'Send Single SMS', 'desishad-sms-notifier' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render bulk CSV upload form.
	 */
	private function render_bulk_csv_form() {
		?>
		<p><?php esc_html_e( 'Upload a CSV file containing phone numbers in the first column.', 'desishad-sms-notifier' ); ?></p>
		<form method="post" action="" enctype="multipart/form-data">
		<?php wp_nonce_field( 'dsn_send_bulk_csv', 'dsn_nonce' ); ?>
			<input type="hidden" name="dsn_action" value="send_bulk_csv" />
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'CSV File', 'desishad-sms-notifier' ); ?></th>
					<td><input type="file" name="csv_file" accept=".csv" required /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Message', 'desishad-sms-notifier' ); ?></th>
					<td>
						<textarea name="message" rows="5" class="large-text" required></textarea>
					</td>
				</tr>
			</table>
		<?php submit_button( esc_html__( 'Send to CSV Numbers', 'desishad-sms-notifier' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render bulk filter form.
	 */
	private function render_bulk_filter_form() {
		// Fetch simple product list for dropdown (this can be optimized for larger stores).
		$products = wc_get_products(
			array(
				'limit'  => -1,
				'status' => 'publish',
			)
		);

		// Calculate unique customers natively. Cached to prevent slowdowns.
		$is_no_consent_allowed = get_option( 'dsn_send_no_consent', 0 );
		$cache_key             = 'dsn_bulk_counts_' . ( $is_no_consent_allowed ? 'all' : 'consent' );
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
				// Pre-load user metadata for all customers to avoid N+1 queries.
				$customer_ids = array_unique(
					array_filter(
						array_map(
							function ( $order ) {
								return $order->get_customer_id();
							},
							$orders
						)
					)
				);
				if ( ! empty( $customer_ids ) ) {
					update_meta_cache( 'user', $customer_ids );
				}

				foreach ( $orders as $order ) {
					$phone = $order->get_billing_phone();
					if ( empty( $phone ) ) {
						continue;
					}

					// Skip invalid phone numbers.
					if ( '-1' === $order->get_meta( '_dsn_phone_valid' ) ) {
						continue;
					}

					// Check consent logic to accurately predict who gets the SMS.
					if ( ! $is_no_consent_allowed ) {
						$has_consent   = false;
						$order_consent = $order->get_meta( '_wc_other/dsn/sms_consent' );
						if ( '' === $order_consent || null === $order_consent ) {
							$order_consent = $order->get_meta( 'dsn/sms_consent' );
						}
						if ( '' === $order_consent || null === $order_consent ) {
							$order_consent = $order->get_meta( '_dsn_sms_consent' );
						}

						if ( true === $order_consent || '1' === $order_consent || 'yes' === $order_consent ) {
							$has_consent = true;
						} elseif ( false === $order_consent || '0' === $order_consent || 'no' === $order_consent ) {
							$has_consent = false;
						} else {
							$customer_id = $order->get_customer_id();
							if ( $customer_id ) {
								$user_consent = get_user_meta( $customer_id, 'desishad/sms-consent', true );
								if ( ! empty( $user_consent ) && ( '1' == $user_consent || 'yes' === strtolower( $user_consent ) || 'on' === strtolower( $user_consent ) || 'true' === strtolower( $user_consent ) ) ) {
									$has_consent = true;
								}
							}
						}

						if ( ! $has_consent ) {
								continue;
						}
					}

					// Track unique phone numbers.
					$map['all'][ $phone ] = true;

					foreach ( $order->get_items() as $item ) {
						$pid = $item->get_product_id();
						if ( ! isset( $map['products'][ $pid ] ) ) {
							$map['products'][ $pid ] = array();
						}
						$map['products'][ $pid ][ $phone ] = true;
					}
				}
			}
			set_transient( $cache_key, $map, HOUR_IN_SECONDS );
		}

		$all_count = isset( $map['all'] ) ? count( $map['all'] ) : 0;
		?>
		<p><?php esc_html_e( 'Send a message to customers based on past purchases.', 'desishad-sms-notifier' ); ?></p>
		<p class="description"><?php esc_html_e( 'Only sends to users who have given consent via the desishad/sms-consent field (unless the "Send SMS to Users Without Consent" option is enabled in General Settings) AND have a valid billing phone number.', 'desishad-sms-notifier' ); ?></p>
		<form method="post" action="">
		<?php wp_nonce_field( 'dsn_send_bulk_filter', 'dsn_nonce' ); ?>
			<input type="hidden" name="dsn_action" value="send_bulk_filter" />
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Purchased Product', 'desishad-sms-notifier' ); ?></th>
					<td>
						<select name="product_id">
							<option value="all">
								<?php
								printf(
									/* translators: %d: number of customers */
									esc_html__( 'Any Product (%d customers)', 'desishad-sms-notifier' ),
									(int) $all_count
								);
								?>
							</option>
		<?php
		foreach ( $products as $product ) :
			$pid     = $product->get_id();
			$p_count = isset( $map['products'][ $pid ] ) ? count( $map['products'][ $pid ] ) : 0;
			?>
								<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $product->get_name() ) . sprintf( ' (%d customers)', (int) $p_count ); ?></option>
		<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Message', 'desishad-sms-notifier' ); ?></th>
					<td>
						<textarea name="message" rows="5" class="large-text" required></textarea>
					</td>
				</tr>
			</table>
		<?php submit_button( esc_html__( 'Send Filtered SMS', 'desishad-sms-notifier' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Process form submissions.
	 */
	public function process_send_sms_actions() {
		if ( ! isset( $_POST['dsn_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['dsn_action'] ) );

		if ( ! isset( $_POST['dsn_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['dsn_nonce'] ) );

		if ( 'send_single' === $action && wp_verify_nonce( $nonce, 'dsn_send_single_sms' ) ) {
			$this->handle_single_sms();
		} elseif ( 'send_bulk_csv' === $action && wp_verify_nonce( $nonce, 'dsn_send_bulk_csv' ) ) {
			$this->handle_bulk_csv();
		} elseif ( 'send_bulk_filter' === $action && wp_verify_nonce( $nonce, 'dsn_send_bulk_filter' ) ) {
			$this->handle_bulk_filter();
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'dsn-settings',
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

		if ( ! isset( $_POST['dsn_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsn_nonce'] ) ), 'dsn_send_single_sms' ) ) {
			// Nonce verification failed. Stop execution.
			return;
		}

		$to      = isset( $_POST['phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( $to && $message ) {
			DSN_Twilio_API::send_sms( $to, $message );
		}
	}

	/**
	 * Handle bulk CSV upload submission.
	 */
	private function handle_bulk_csv() {

		if ( ! isset( $_POST['dsn_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsn_nonce'] ) ), 'dsn_send_bulk_csv' ) ) {
			// Nonce verification failed. Stop execution.
			return;
		}

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		// Safely extract and sanitize the temporary file path.
		$csv_tmp_name = isset( $_FILES['csv_file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['csv_file']['tmp_name'] ) ) : '';

		// Ensure the path isn't empty AND that it is a legitimate uploaded file.
		if ( ! empty( $csv_tmp_name ) && is_uploaded_file( $csv_tmp_name ) && $message ) {
			$file = fopen( $csv_tmp_name, 'r' );
			if ( $file ) {
				while ( ( $row = fgetcsv( $file ) ) !== false ) {
					$to = sanitize_text_field( $row[0] );
					if ( ! empty( $to ) ) {
						DSN_Twilio_API::send_sms( $to, $message );
					}
				}
				fclose( $file );
			}
		}
	}

	/**
	 * Handle bulk filter submission.
	 */
	private function handle_bulk_filter() {

		if ( ! isset( $_POST['dsn_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsn_nonce'] ) ), 'dsn_send_bulk_filter' ) ) {
			// Nonce verification failed. Stop execution.
			return;
		}

		$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : 'all';
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( empty( $message ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'status' => array( 'wc-processing', 'wc-completed' ),
				'limit'  => -1,
			)
		);

		if ( ! $orders ) {
			return;
		}

		// Pre-load user metadata for all customers to avoid N+1 queries.
		$customer_ids = array_unique(
			array_filter(
				array_map(
					function ( $order ) {
						return $order->get_customer_id();
					},
					$orders
				)
			)
		);
		if ( ! empty( $customer_ids ) ) {
			update_meta_cache( 'user', $customer_ids );
		}

		$sent_phones = array();

		// Iterate over native orders rather than users to ensure Guest orders are captured.
		foreach ( $orders as $order ) {
			$phone = $order->get_billing_phone();
			if ( empty( $phone ) ) {
				continue;
			}

			// Skip invalid phone numbers.
			if ( '-1' === $order->get_meta( '_dsn_phone_valid' ) ) {
				continue;
			}

			// Prevent sending multiple SMS to the same phone number.
			if ( isset( $sent_phones[ $phone ] ) ) {
				continue;
			}

			// Check if this order has the selected product.
			if ( 'all' !== $product_id ) {
				$found_product = false;
				foreach ( $order->get_items() as $item ) {
					if ( (int) $item->get_product_id() === (int) $product_id ) {
						$found_product = true;
						break;
					}
				}
				if ( ! $found_product ) {
					continue;
				}
			}

			// Check consent (handles both registered users and guest orders).
			$has_consent   = false;
			$order_consent = $order->get_meta( '_wc_other/dsn/sms_consent' );
			if ( '' === $order_consent || null === $order_consent ) {
				$order_consent = $order->get_meta( 'dsn/sms_consent' );
			}
			if ( '' === $order_consent || null === $order_consent ) {
				$order_consent = $order->get_meta( '_dsn_sms_consent' );
			}

			if ( true === $order_consent || '1' === $order_consent || 'yes' === $order_consent ) {
				$has_consent = true;
			} elseif ( false === $order_consent || '0' === $order_consent || 'no' === $order_consent ) {
				$has_consent = false;
			} else {
				$customer_id = $order->get_customer_id();
				if ( $customer_id ) {
					$user_consent = get_user_meta( $customer_id, 'desishad/sms-consent', true );
					if ( ! empty( $user_consent ) && ( '1' == $user_consent || 'yes' === strtolower( $user_consent ) || 'on' === strtolower( $user_consent ) || 'true' === strtolower( $user_consent ) ) ) {
						$has_consent = true;
					}
				}
			}

			// If settings allow sending to no-consent users, bypass the consent check.
			if ( get_option( 'dsn_send_no_consent', 0 ) || $has_consent ) {
				$sent_phones[ $phone ] = true;
				DSN_Twilio_API::send_sms( $phone, $message, $order->get_customer_id() );
			}
		}
	}
}

new DSN_Send_SMS();
