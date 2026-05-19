<?php
/**
 * Short Links Manager for OptiMessage
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OM_Short_Links
 */
class OM_Short_Links {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'om_render_short_links_tab', array( $this, 'render_tab' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
		add_action( 'init', array( $this, 'handle_redirect' ) );
	}

	/**
	 * Handle redirect and click tracking.
	 */
	public function handle_redirect() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['omsl'] ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'om_short_links';

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$hash = sanitize_text_field( wp_unslash( $_GET['omsl'] ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE hash = %s", $hash ) );

			if ( $link ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "UPDATE {$table_name} SET clicks = clicks + 1 WHERE id = %d", $link->id ) );
				// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
				wp_redirect( esc_url_raw( $link->url ) );
				exit;
			}

			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Handle creation and deletion.
	 */
	public function handle_form_submission() {
		if ( ! isset( $_GET['page'] ) || 'om-settings' !== $_GET['page'] || ! isset( $_GET['tab'] ) || 'short_links' !== $_GET['tab'] ) {
			return;
		}

		// Handle adding short link.
		if ( isset( $_POST['om_add_short_link'] ) && isset( $_POST['om_short_link_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['om_short_link_nonce'] ) ), 'om_add_short_link_action' ) ) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$url = isset( $_POST['long_url'] ) ? esc_url_raw( wp_unslash( $_POST['long_url'] ) ) : '';
			if ( ! empty( $url ) ) {
				global $wpdb;
				$table_name = $wpdb->prefix . 'om_short_links';

				// Generate unique 6-char hash.
				$hash = substr( md5( uniqid( wp_rand(), true ) ), 0, 6 );

				$wpdb->insert(
					$table_name,
					array(
						'hash'       => $hash,
						'url'        => $url,
						'clicks'     => 0,
						'created_at' => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%d', '%s' )
				);

				add_settings_error( 'om_messages', 'om_short_link_added', esc_html__( 'Short link created successfully.', 'optimessage' ), 'updated' );
			} else {
				add_settings_error( 'om_messages', 'om_short_link_error', esc_html__( 'Please enter a valid URL.', 'optimessage' ), 'error' );
			}
		}

		// Handle deletion.
		if ( isset( $_GET['action'] ) && 'delete_link' === $_GET['action'] && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_link_' . intval( $_GET['id'] ) ) ) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'om_short_links';
			$wpdb->delete( $table_name, array( 'id' => intval( $_GET['id'] ) ), array( '%d' ) );

			wp_safe_redirect( admin_url( 'admin.php?page=om-settings&tab=short_links' ) );
			exit;
		}
	}

	/**
	 * Render the Short Links tab.
	 */
	public function render_tab() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'om_short_links';

		// Pagination logic.
		$per_page = 20;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_name}" );
		$total_pages = ceil( $total_items / $per_page );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$links = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		?>
		<h2><?php esc_html_e( 'URL Shortener & Analytics', 'optimessage' ); ?></h2>
		
		<div class="card" style="max-width: 100%; margin-top: 20px;">
			<h3><?php esc_html_e( 'Create New Short Link', 'optimessage' ); ?></h3>
			<form method="post" action="">
				<?php wp_nonce_field( 'om_add_short_link_action', 'om_short_link_nonce' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="long_url"><?php esc_html_e( 'Original URL', 'optimessage' ); ?></label></th>
						<td>
							<input type="url" name="long_url" id="long_url" class="regular-text" required placeholder="https://example.com/some/long/page" style="width: 100%; max-width: 600px;" />
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="om_add_short_link" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Create Short Link', 'optimessage' ); ?>" />
				</p>
			</form>
		</div>

		<div class="tablenav top" style="margin-top: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
			<h3 style="margin: 0;"><?php esc_html_e( 'Existing Short Links', 'optimessage' ); ?></h3>
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
					$base_url   = add_query_arg(
						array(
							'page' => 'om-settings',
							'tab'  => 'short_links',
						),
						admin_url( 'admin.php' )
					);
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
					<th width="20%"><?php esc_html_e( 'Short URL', 'optimessage' ); ?></th>
					<th width="40%"><?php esc_html_e( 'Original URL', 'optimessage' ); ?></th>
					<th width="10%"><?php esc_html_e( 'Clicks', 'optimessage' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Created At', 'optimessage' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Actions', 'optimessage' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $links ) : ?>
					<?php
					foreach ( $links as $link ) :
						$short_url = home_url( '/?omsl=' . $link->hash );
						?>
						<tr>
							<td>
								<a href="<?php echo esc_url( $short_url ); ?>" target="_blank"><?php echo esc_html( $short_url ); ?></a>
								<button type="button" class="button button-small copy-short-link" data-url="<?php echo esc_attr( $short_url ); ?>" style="margin-left: 8px;"><?php esc_html_e( 'Copy', 'optimessage' ); ?></button>
							</td>
							<td><a href="<?php echo esc_url( $link->url ); ?>" target="_blank"><?php echo esc_html( wp_trim_words( $link->url, 10, '...' ) ); ?></a></td>
							<td><strong><?php echo esc_html( $link->clicks ); ?></strong></td>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $link->created_at ) ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=om-settings&tab=short_links&action=delete_link&id=' . $link->id ), 'delete_link_' . $link->id ) ); ?>" class="button button-link-delete" style="color: #a00;" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this short link?', 'optimessage' ); ?>');"><?php esc_html_e( 'Delete', 'optimessage' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No short links found.', 'optimessage' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('.copy-short-link').on('click', function(e) {
					e.preventDefault();
					var url = $(this).data('url');
					var $btn = $(this);
					navigator.clipboard.writeText(url).then(function() {
						var originalText = $btn.text();
						$btn.text('<?php esc_attr_e( 'Copied!', 'optimessage' ); ?>');
						setTimeout(function() {
							$btn.text(originalText);
						}, 2000);
					});
				});
			});
		</script>
		<?php
	}
}

new OM_Short_Links();
