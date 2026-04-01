<?php
/**
 * Guest Orders Table class for OptiMessage
 *
 * @package OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class OM_Guest_Customers_List_Table
 * Customer table for guest orders.
 */
class OM_Guest_Customers_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Guest Order', 'optimessage' ),
				'plural'   => __( 'Guest Orders', 'optimessage' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Message when no items found.
	 */
	public function no_items() {
		esc_html_e( 'No guest orders found.', 'optimessage' );
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'order_id'    => esc_html__( 'Order ID', 'optimessage' ),
			'name'        => esc_html__( 'Billing Name', 'optimessage' ),
			'email'       => esc_html__( 'Email', 'optimessage' ),
			'phone'       => esc_html__( 'Phone', 'optimessage' ),
			'valid_phone' => esc_html__( 'Valid Phone?', 'optimessage' ),
			'address'     => esc_html__( 'Address', 'optimessage' ),
			'state'       => esc_html__( 'State', 'optimessage' ),
			'consent'     => esc_html__( 'SMS Consent', 'optimessage' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'order_id' => array( 'ID', false ),
		);
	}

	/**
	 * Render dropdown filters above the table.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters.
		$filter_valid   = isset( $_REQUEST['om_valid'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['om_valid'] ) ) : '';
		$filter_consent = isset( $_REQUEST['om_consent'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['om_consent'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<select name="om_valid">
				<option value=""><?php esc_html_e( 'All Phone Status', 'optimessage' ); ?></option>
				<option value="valid" <?php selected( $filter_valid, 'valid' ); ?>><?php esc_html_e( 'Valid', 'optimessage' ); ?></option>
				<option value="invalid" <?php selected( $filter_valid, 'invalid' ); ?>><?php esc_html_e( 'Invalid', 'optimessage' ); ?></option>
				<option value="unchecked" <?php selected( $filter_valid, 'unchecked' ); ?>><?php esc_html_e( 'Not Checked', 'optimessage' ); ?></option>
			</select>

			<select name="om_consent">
				<option value=""><?php esc_html_e( 'All Consent', 'optimessage' ); ?></option>
				<option value="yes" <?php selected( $filter_consent, 'yes' ); ?>><?php esc_html_e( 'Opted In', 'optimessage' ); ?></option>
				<option value="no" <?php selected( $filter_consent, 'no' ); ?>><?php esc_html_e( 'No Consent', 'optimessage' ); ?></option>
			</select>

			<?php submit_button( esc_html__( 'Filter', 'optimessage' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$args = array(
			'customer_id' => 0,
			'limit'       => $per_page,
			'page'        => $paged,
			'paginate'    => true,
		);

		// Handle Search — use WooCommerce's native search which works with both HPOS and legacy.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search parameter for WP_List_Table.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		if ( ! empty( $search ) ) {
			if ( is_email( $search ) ) {
				$args['billing_email'] = $search;
			} else {
				// WooCommerce 's' parameter searches across billing name, email, phone, and order ID.
				$args['s'] = $search;
			}
		}

		// Handle dropdown filters.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters.
		$filter_valid   = isset( $_REQUEST['om_valid'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['om_valid'] ) ) : '';
		$filter_consent = isset( $_REQUEST['om_consent'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['om_consent'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$meta_query = array();

		if ( 'valid' === $filter_valid ) {
			$meta_query[] = array(
				'key'   => '_om_phone_valid',
				'value' => '1',
			);
		} elseif ( 'invalid' === $filter_valid ) {
			$meta_query[] = array(
				'key'   => '_om_phone_valid',
				'value' => '-1',
			);
		} elseif ( 'unchecked' === $filter_valid ) {
			$meta_query[] = array(
				'key'     => '_om_phone_valid',
				'compare' => 'NOT EXISTS',
			);
		}

		if ( 'yes' === $filter_consent ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'   => '_om_sms_consent',
					'value' => '1',
				),
				array(
					'key'   => '_wc_other/om/sms_consent',
					'value' => '1',
				),
				array(
					'key'   => 'om/sms_consent',
					'value' => '1',
				),
			);
		} elseif ( 'no' === $filter_consent ) {
			$meta_query[] = array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_om_sms_consent',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_om_sms_consent',
						'value'   => array( '0', 'no', '' ),
						'compare' => 'IN',
					),
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_wc_other/om/sms_consent',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_wc_other/om/sms_consent',
						'value'   => array( '0', 'no', '' ),
						'compare' => 'IN',
					),
				),
			);
		}

		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for phone/consent filtering.
			$args['meta_query'] = $meta_query;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only sort parameters, standard WP_List_Table pattern.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$args['orderby'] = $orderby;
		$args['order']   = $order;

		$results = wc_get_orders( $args );

		$this->items = $results->orders;
		$this->set_pagination_args(
			array(
				'total_items' => $results->total,
				'per_page'    => $per_page,
				'total_pages' => $results->max_num_pages,
			)
		);
	}

	/**
	 * Column default rendering.
	 *
	 * @param  object $item        The item.
	 * @param  string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'order_id':
				return '<a href="' . esc_url( $item->get_edit_order_url() ) . '">#' . esc_html( $item->get_order_number() ) . '</a>';

			case 'name':
				return esc_html( trim( $item->get_billing_first_name() . ' ' . $item->get_billing_last_name() ) );

			case 'email':
				return esc_html( $item->get_billing_email() );

			case 'phone':
				return esc_html( $item->get_billing_phone() );

			case 'valid_phone':
				$phone = $item->get_billing_phone();
				if ( empty( $phone ) ) {
					return '<span style="color:grey;">' . esc_html__( 'No Number', 'optimessage' ) . '</span>';
				}

				$status = $item->get_meta( '_om_phone_valid', true );
				if ( '1' === $status ) {
					return '<span style="color:green;font-weight:bold;">' . esc_html__( 'Yes', 'optimessage' ) . '</span>';
				} elseif ( '-1' === $status ) {
					return '<span style="color:red;font-weight:bold;">' . esc_html__( 'No', 'optimessage' ) . '</span>';
				}

				return '<span style="color:orange;">' . esc_html__( 'Not Checked', 'optimessage' ) . '</span>';

			case 'address':
				return esc_html( $item->get_billing_address_1() );

			case 'state':
				return esc_html( $item->get_billing_state() );

			case 'consent':
				$consent = $item->get_meta( '_wc_other/om/sms_consent', true );
				if ( '' === $consent || null === $consent ) {
					$consent = $item->get_meta( 'om/sms_consent', true );
				}
				if ( '' === $consent || null === $consent ) {
					$consent = $item->get_meta( '_om_sms_consent', true );
				}
				$consent_str  = strtolower( (string) $consent );
				$is_consented = ( true === $consent || '1' === $consent || 'yes' === $consent_str || 'on' === $consent_str || 'true' === $consent_str );

				if ( $is_consented ) {
					return '<span style="color:green;font-weight:bold;">' . esc_html__( 'Opted In', 'optimessage' ) . '</span>';
				} else {
					return '<span style="color:grey;">' . esc_html__( 'No Consent', 'optimessage' ) . '</span>';
				}

			default:
				return '';
		}
	}
}
