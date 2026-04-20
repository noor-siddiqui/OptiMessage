<?php
/**
 * Settings tab renderer for Subscribers list.
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OM_Subscribers_Tab
 */
class OM_Subscribers_Tab {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'om_render_subscribers_tab', array( $this, 'render' ) );
		add_action( 'wp_ajax_om_validate_phones_batch', array( $this, 'ajax_validate_phones_batch' ) );
	}

	/**
	 * Render the view.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation parameter.
		$sub_tab = isset( $_GET['sub_tab'] ) ? sanitize_text_field( wp_unslash( $_GET['sub_tab'] ) ) : 'registered';

		echo '<h2>' . esc_html__( 'Subscribers & User Consent', 'optimessage' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Manage all SMS subscribers natively without crashing high-performance databases. Guests who do not create accounts are stored in Guest Orders.', 'optimessage' ) . '</p>';

		echo '<h3 class="nav-tab-wrapper" style="margin-bottom: 20px;">';
		echo '<a href="?page=om-settings&tab=subscribers&sub_tab=registered" class="nav-tab ' . ( 'registered' === $sub_tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Registered Users', 'optimessage' ) . '</a>';
		echo '<a href="?page=om-settings&tab=subscribers&sub_tab=guests" class="nav-tab ' . ( 'guests' === $sub_tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Guest Orders', 'optimessage' ) . '</a>';
		echo '</h3>';

		// Validate Numbers button + progress area.
		?>
		<div style="margin-bottom: 15px;">
			<button type="button" class="button button-secondary" id="om-validate-btn">
				<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-right: 4px;"></span>
				<?php esc_html_e( 'Validate All Unchecked Numbers', 'optimessage' ); ?>
			</button>
			<span id="om-validate-status" style="margin-left: 10px; font-style: italic;"></span>
		</div>
		<div id="om-validate-progress" style="display:none; margin-bottom: 20px; max-width: 500px;">
			<div style="background: #e0e0e0; border-radius: 4px; height: 20px; width: 100%; overflow: hidden;">
				<div id="om-validate-bar" style="background: #2271b1; width: 0%; height: 100%; transition: width 0.3s ease;"></div>
			</div>
		</div>
		<script>
		jQuery(document).ready(function($) {
			var subTab = '<?php echo esc_js( $sub_tab ); ?>';

			$('#om-validate-btn').on('click', function() {
				var $btn = $(this);
				var $status = $('#om-validate-status');
				var $progress = $('#om-validate-progress');
				var $bar = $('#om-validate-bar');

				$btn.prop('disabled', true);
				$status.text('<?php echo esc_js( __( 'Starting validation...', 'optimessage' ) ); ?>');
				$progress.show();
				$bar.css('width', '0%');

				function runBatch(validated, failed) {
					$.post(omData.ajax_url, {
						action: 'om_validate_phones_batch',
						om_nonce: omData.batch_nonce,
						sub_tab: subTab
					}, function(response) {
						if (response.success) {
							var d = response.data;
							validated += d.validated;
							failed += d.failed;

							if (d.remaining > 0) {
								var total = validated + failed + d.remaining;
								var pct = Math.round(((validated + failed) / total) * 100);
								$bar.css('width', pct + '%');
								$status.text('<?php echo esc_js( __( 'Checked', 'optimessage' ) ); ?> ' + (validated + failed) + ' / ' + total + '...');
								runBatch(validated, failed);
							} else {
								$bar.css('width', '100%');
								$status.text('<?php echo esc_js( __( 'Done!', 'optimessage' ) ); ?> ' + validated + ' <?php echo esc_js( __( 'valid', 'optimessage' ) ); ?>, ' + failed + ' <?php echo esc_js( __( 'invalid.', 'optimessage' ) ); ?> <?php echo esc_js( __( 'Refresh to see results.', 'optimessage' ) ); ?>');
								$btn.prop('disabled', false);
							}
						} else {
							$status.text('<?php echo esc_js( __( 'Error:', 'optimessage' ) ); ?> ' + response.data);
							$btn.prop('disabled', false);
						}
					}).fail(function() {
						$status.text('<?php echo esc_js( __( 'Connection lost.', 'optimessage' ) ); ?>');
						$btn.prop('disabled', false);
					});
				}

				runBatch(0, 0);
			});
		});
		</script>
		<?php

		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=om-settings&tab=subscribers&sub_tab=' . $sub_tab ) ) . '">';

		if ( 'guests' === $sub_tab ) {
			$table = new OM_Guest_Customers_List_Table();
			$table->prepare_items();
			$table->search_box( esc_html__( 'Search Guests', 'optimessage' ), 'search_id' );
			$table->display();
		} else {
			$table = new OM_Subscribers_List_Table();
			$table->prepare_items();
			$table->search_box( esc_html__( 'Search Users', 'optimessage' ), 'search_id' );
			$table->display();
		}

		echo '</form>';
	}

	/**
	 * AJAX: Validate a batch of unchecked phone numbers.
	 * Processes up to 10 numbers per call to avoid timeouts.
	 */
	public function ajax_validate_phones_batch() {
		check_ajax_referer( 'om_process_batch', 'om_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$sub_tab   = isset( $_POST['sub_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['sub_tab'] ) ) : 'registered';
		$batch     = 10;
		$validated = 0;
		$failed    = 0;
		$remaining = 0;

		if ( 'guests' === $sub_tab ) {
			// Validate guest order phone numbers.
			$orders = wc_get_orders(
				array(
					'customer_id' => 0,
					'limit'       => $batch,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_om_phone_valid',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);

			$lookup_requests = array();
			foreach ( $orders as $order ) {
				$phone   = $order->get_billing_phone();
				$country = $order->get_billing_country();

				if ( empty( $phone ) ) {
					$order->update_meta_data( '_om_phone_valid', '-1' );
					$order->save();
					++$failed;
					continue;
				}

				$lookup_requests[ $order->get_id() ] = array(
					'phone'   => $phone,
					'country' => $country,
				);
			}

			if ( ! empty( $lookup_requests ) ) {
				$results = OM_Twilio_API::lookup_phone_batch( $lookup_requests );

				foreach ( $orders as $order ) {
					$order_id = $order->get_id();
					if ( ! isset( $results[ $order_id ] ) ) {
						continue;
					}

					$lookup = $results[ $order_id ];
					if ( is_array( $lookup ) && ! empty( $lookup['valid'] ) ) {
						$order->update_meta_data( '_om_phone_valid', '1' );
						$order->set_billing_phone( $lookup['formatted'] );
						$order->save();
						++$validated;
					} else {
						$order->update_meta_data( '_om_phone_valid', '-1' );
						$order->save();
						++$failed;
					}
				}
			}

			// Count remaining.
			$remaining_orders = wc_get_orders(
				array(
					'customer_id' => 0,
					'limit'       => 1,
					'paginate'    => true,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => '_om_phone_valid',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
			$remaining        = $remaining_orders->total;
		} else {
			// Validate registered user phone numbers.
			$user_query = new WP_User_Query(
				array(
					'number'     => $batch,
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						array(
							'key'     => 'billing_phone',
							'value'   => '',
							'compare' => '!=',
						),
						array(
							'key'     => '_om_phone_valid',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);

			$users = $user_query->get_results();

			$lookup_requests = array();
			foreach ( $users as $user ) {
				$phone   = get_user_meta( $user->ID, 'billing_phone', true );
				$country = get_user_meta( $user->ID, 'billing_country', true );

				$lookup_requests[ $user->ID ] = array(
					'phone'   => $phone,
					'country' => $country,
				);
			}

			if ( ! empty( $lookup_requests ) ) {
				$results = OM_Twilio_API::lookup_phone_batch( $lookup_requests );

				foreach ( $users as $user ) {
					if ( ! isset( $results[ $user->ID ] ) ) {
						continue;
					}

					$lookup = $results[ $user->ID ];
					if ( is_array( $lookup ) && ! empty( $lookup['valid'] ) ) {
						update_user_meta( $user->ID, 'billing_phone', $lookup['formatted'] );
						update_user_meta( $user->ID, '_om_phone_valid', '1' );
						++$validated;
					} else {
						update_user_meta( $user->ID, '_om_phone_valid', '-1' );
						++$failed;
					}
				}
			}

			// Count remaining.
			$remaining_query = new WP_User_Query(
				array(
					'number'     => 1,
					'count_total' => true,
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						array(
							'key'     => 'billing_phone',
							'value'   => '',
							'compare' => '!=',
						),
						array(
							'key'     => '_om_phone_valid',
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);
			$remaining       = $remaining_query->get_total();
		}

		wp_send_json_success(
			array(
				'validated' => $validated,
				'failed'    => $failed,
				'remaining' => $remaining,
			)
		);
	}
}

// Instantiate the tab so the hook is registered.
new OM_Subscribers_Tab();
