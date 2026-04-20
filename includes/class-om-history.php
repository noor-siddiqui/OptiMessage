<?php
/**
 * History Tab for OptiMessage
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OM_History
 * Handles the display of SMS sending history.
 */
class OM_History {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'om_render_history_tab', array( $this, 'render' ) );
	}

	/**
	 * Render the history tab page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'om_sms_history';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter/pagination parameters, standard WP admin pattern.
		$per_page      = 20;
		$paged         = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset        = ( $paged - 1 ) * $per_page;
		$filter_phone  = isset( $_GET['om_phone'] ) ? sanitize_text_field( wp_unslash( $_GET['om_phone'] ) ) : '';
		$filter_status = isset( $_GET['om_status'] ) ? sanitize_text_field( wp_unslash( $_GET['om_status'] ) ) : '';
		$filter_from   = isset( $_GET['om_from'] ) ? sanitize_text_field( wp_unslash( $_GET['om_from'] ) ) : '';
		$filter_to     = isset( $_GET['om_to'] ) ? sanitize_text_field( wp_unslash( $_GET['om_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Build dynamic WHERE clause.
		$where  = ' WHERE 1=1';
		$values = array();

		if ( ! empty( $filter_phone ) ) {
			$where   .= ' AND phone_number LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $filter_phone ) . '%';
		}

		if ( ! empty( $filter_status ) ) {
			$where   .= ' AND status = %s';
			$values[] = $filter_status;
		}

		if ( ! empty( $filter_from ) ) {
			$where   .= ' AND sent_at >= %s';
			$values[] = $filter_from . ' 00:00:00';
		}

		if ( ! empty( $filter_to ) ) {
			$where   .= ' AND sent_at <= %s';
			$values[] = $filter_to . ' 23:59:59';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be prepared; user values are parameterized.
		$count_query = "SELECT COUNT(id) FROM {$table_name}{$where}";
		$total_items = ! empty( $values )
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built with placeholders above.
			? (int) $wpdb->get_var( $wpdb->prepare( $count_query, $values ) )
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user values to prepare.
			: (int) $wpdb->get_var( $count_query );
		$total_pages = ceil( $total_items / $per_page );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and WHERE built with placeholders.
		$data_query = "SELECT * FROM {$table_name}{$where} ORDER BY sent_at DESC LIMIT %d OFFSET %d";
		$values[]   = $per_page;
		$values[]   = $offset;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built with placeholders above.
		$results = $wpdb->get_results( $wpdb->prepare( $data_query, $values ) );

		// Prime user cache to prevent N+1 queries when calling get_edit_user_link() in the loop.
		if ( ! empty( $results ) ) {
			$user_ids = array_filter( wp_list_pluck( $results, 'user_id' ) );
			if ( ! empty( $user_ids ) ) {
				cache_users( array_unique( $user_ids ) );
			}
		}

		// Build base URL for pagination links (preserve filters).
		$filter_args = array(
			'page'      => 'om-settings',
			'tab'       => 'history',
			'om_phone'  => $filter_phone,
			'om_status' => $filter_status,
			'om_from'   => $filter_from,
			'om_to'     => $filter_to,
		);
		$base_url    = add_query_arg( array_filter( $filter_args ), admin_url( 'admin.php' ) );
		?>
		<h2><?php esc_html_e( 'SMS Sending History', 'optimessage' ); ?></h2>

		<div class="tablenav top" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 8px 0;">
			<form method="get" action="" style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin: 0;">
				<input type="hidden" name="page" value="om-settings" />
				<input type="hidden" name="tab" value="history" />

				<input type="search" name="om_phone" value="<?php echo esc_attr( $filter_phone ); ?>" placeholder="<?php esc_attr_e( 'Search phone...', 'optimessage' ); ?>" class="regular-text" style="max-width: 180px;" />

				<select name="om_status">
					<option value=""><?php esc_html_e( 'All Statuses', 'optimessage' ); ?></option>
					<?php
					$statuses = array( 'queued', 'sent', 'delivered', 'failed', 'undelivered' );
					foreach ( $statuses as $status ) :
						?>
						<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filter_status, $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
					<?php endforeach; ?>
				</select>

				<label style="display:inline-flex; align-items:center; gap:4px;">
					<?php esc_html_e( 'From', 'optimessage' ); ?>
					<input type="date" name="om_from" value="<?php echo esc_attr( $filter_from ); ?>" />
				</label>

				<label style="display:inline-flex; align-items:center; gap:4px;">
					<?php esc_html_e( 'To', 'optimessage' ); ?>
					<input type="date" name="om_to" value="<?php echo esc_attr( $filter_to ); ?>" />
				</label>

				<?php submit_button( esc_html__( 'Filter', 'optimessage' ), 'secondary', 'submit', false ); ?>

				<?php if ( $filter_phone || $filter_status || $filter_from || $filter_to ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=om-settings&tab=history' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'optimessage' ); ?></a>
				<?php endif; ?>
			</form>

			<div class="tablenav-pages" style="margin-left: auto;">
				<span class="displaying-num">
				<?php
				printf(
					/* translators: %s: number of items */
					esc_html( _n( '%s item', '%s items', $total_items, 'optimessage' ) ),
					esc_html( number_format_i18n( $total_items ) )
				);
				?>
				</span>
				<?php
				if ( $total_pages > 1 ) {
					$page_links = paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%', $base_url ),
							'format'    => '',
							'prev_text' => esc_html__( '&laquo;', 'optimessage' ),
							'next_text' => esc_html__( '&raquo;', 'optimessage' ),
							'total'     => $total_pages,
							'current'   => $paged,
						)
					);
					echo wp_kses_post( '<span class="pagination-links">' . $page_links . '</span>' );
				}
				?>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-id" width="5%"><?php esc_html_e( 'ID', 'optimessage' ); ?></th>
					<th scope="col" class="manage-column" width="15%"><?php esc_html_e( 'Date', 'optimessage' ); ?></th>
					<th scope="col" class="manage-column" width="15%"><?php esc_html_e( 'To', 'optimessage' ); ?></th>
					<th scope="col" class="manage-column" width="35%"><?php esc_html_e( 'Message', 'optimessage' ); ?></th>
					<th scope="col" class="manage-column" width="10%"><?php esc_html_e( 'Status', 'optimessage' ); ?></th>
					<th scope="col" class="manage-column" width="20%"><?php esc_html_e( 'Error/Details', 'optimessage' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( $results ) : ?>
				<?php
				// Pre-load user cache to avoid N+1 queries during the loop.
				$user_ids = array();
				foreach ( $results as $row ) {
					if ( ! empty( $row->user_id ) ) {
						$user_ids[] = (int) $row->user_id;
					}
				}
				if ( ! empty( $user_ids ) ) {
					cache_users( array_unique( $user_ids ) );
				}

				$date_format     = get_option( 'date_format' );
				$time_format     = get_option( 'time_format' );
				$datetime_format = $date_format . ' ' . $time_format;

				// ⚡ Bolt: Prevent N+1 query bottleneck by priming the user cache.
				$user_ids = array_filter( wp_list_pluck( $results, 'user_id' ) );
				if ( ! empty( $user_ids ) ) {
					cache_users( array_unique( $user_ids ) );
				}
				?>
				<?php foreach ( $results as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->id ); ?></td>
						<td><?php echo esc_html( wp_date( $datetime_format, strtotime( $row->sent_at ), new DateTimeZone( 'UTC' ) ) ); ?></td>
						<td>
							<?php echo esc_html( $row->phone_number ); ?>
							<?php if ( $row->user_id ) : ?>
								<br><small><a href="<?php echo esc_url( get_edit_user_link( $row->user_id ) ); ?>"><?php esc_html_e( 'User', 'optimessage' ); ?> #<?php echo esc_html( $row->user_id ); ?></a></small>
							<?php endif; ?>
							<?php if ( $row->order_id ) : ?>
								<br><small><a href="<?php echo esc_url( $this->get_order_edit_url( $row->order_id ) ); ?>"><?php esc_html_e( 'Order', 'optimessage' ); ?> #<?php echo esc_html( $row->order_id ); ?></a></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( wp_trim_words( $row->message, 20, '...' ) ); ?></td>
						<td>
							<?php
							$success_statuses = array( 'sent', 'delivered', 'queued' );
							$color            = in_array( $row->status, $success_statuses, true ) ? 'green' : ( 'failed' === $row->status ? 'red' : 'gray' );
							echo '<span style="color:' . esc_attr( $color ) . ';font-weight:bold;">' . esc_html( ucfirst( $row->status ) ) . '</span>';
							?>
						</td>
						<td><?php echo esc_html( $row->error_message ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No SMS history found.', 'optimessage' ); ?></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get the edit URL for a WooCommerce order.
	 * Supports both HPOS (High-Performance Order Storage) and legacy post-based orders.
	 *
	 * @param int $order_id The order ID.
	 * @return string The edit URL.
	 */
	private function get_order_edit_url( $order_id ) {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
		}
		return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}
}

new OM_History();
