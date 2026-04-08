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
		global $wpdb;
		$table_name = $wpdb->prefix . 'om_sms_history';

		// Handle pagination.
		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be prepared.
		$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$table_name}" );
		$total_pages = ceil( $total_items / $per_page );

     // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be prepared.
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY sent_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );

		if ( $results ) {
			// Pre-load user cache to avoid N+1 queries during the loop when calling get_edit_user_link.
			$user_ids = array();
			foreach ( $results as $row ) {
				if ( ! empty( $row->user_id ) ) {
					$user_ids[] = (int) $row->user_id;
				}
			}
			if ( ! empty( $user_ids ) ) {
				cache_users( array_unique( $user_ids ) );
			}
		}
		?>
		<h2><?php esc_html_e( 'SMS Sending History', 'optimessage' ); ?></h2>
		
		<div class="tablenav top">
			<div class="tablenav-pages">
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
					'base'      => add_query_arg( 'paged', '%#%' ),
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
			$date_format     = get_option( 'date_format' );
			$time_format     = get_option( 'time_format' );
			$datetime_format = $date_format . ' ' . $time_format;
			?>
			<?php foreach ( $results as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->id ); ?></td>
							<td><?php echo esc_html( wp_date( $datetime_format, strtotime( $row->sent_at ) ) ); ?></td>
							<td>
				<?php echo esc_html( $row->phone_number ); ?>
				<?php if ( $row->user_id ) : ?>
									<br><small><a href="<?php echo esc_url( get_edit_user_link( $row->user_id ) ); ?>"><?php esc_html_e( 'User', 'optimessage' ); ?> #<?php echo esc_html( $row->user_id ); ?></a></small>
				<?php endif; ?>
				<?php if ( $row->order_id ) : ?>
									<br><small><a href="<?php echo esc_url( get_edit_post_link( $row->order_id ) ); ?>"><?php esc_html_e( 'Order', 'optimessage' ); ?> #<?php echo esc_html( $row->order_id ); ?></a></small>
				<?php endif; ?>
							</td>
							<td><?php echo nl2br( esc_html( wp_trim_words( $row->message, 20, '...' ) ) ); ?></td>
							<td>
				<?php
				$color = 'sent' === $row->status ? 'green' : ( 'failed' === $row->status ? 'red' : 'gray' );
				echo '<span style="color:' . esc_attr( $color ) . ';font-weight:bold;">' . esc_html( ucfirst( $row->status ) ) . '</span>';
				?>
							</td>
							<td><?php echo esc_html( $row->error_message ); ?></td>
						</tr>
			<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'No sms history found.', 'optimessage' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}
}

new OM_History();
