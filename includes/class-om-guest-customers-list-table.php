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

		// Handle Search.
     // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is not required for read-only search operations.
		if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {

         // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is not required for read-only search operations.
			$search = sanitize_text_field( wp_unslash( $_POST['s'] ) );

			if ( is_email( $search ) ) {
				$args['billing_email'] = $search;
			} elseif ( preg_match( '/^[0-9\+\-\s\(\)]+$/', $search ) ) {
				$clean               = preg_replace( '/[^0-9]/', '', $search );
				$args['field_query'] = array(
					'relation' => 'OR',
					array(
						'field'   => 'billing_phone',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'field'   => 'billing_phone',
						'value'   => $clean,
						'compare' => 'LIKE',
					),
				);
			} else {
				$terms       = explode( ' ', $search );
				$field_query = array( 'relation' => 'OR' );
				foreach ( $terms as $term ) {
					$term = trim( $term );
					if ( empty( $term ) ) {
						continue;
					}
					$field_query[] = array(
						'field'   => 'billing_first_name',
						'value'   => $term,
						'compare' => 'LIKE',
					);
					$field_query[] = array(
						'field'   => 'billing_last_name',
						'value'   => $term,
						'compare' => 'LIKE',
					);
				}
				if ( count( $field_query ) > 1 ) {
					$args['field_query'] = $field_query;
				}
			}
		}

		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';

		$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

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
					return '<span style="color:grey;">' . __( 'No Number', 'optimessage' ) . '</span>';
				}

				$status = $item->get_meta( '_om_phone_valid', true );
				if ( '1' === $status ) {
					return '<span style="color:green;font-weight:bold;">' . __( 'Yes', 'optimessage' ) . '</span>';
				} elseif ( '-1' === $status ) {
					return '<span style="color:red;font-weight:bold;">' . __( 'No', 'optimessage' ) . '</span>';
				}

				// Offload Twilio validation to background job to prevent N+1 API bottlenecks.
				if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'om_async_twilio_lookup_job', array( $item->get_id() ) ) ) {
					as_enqueue_async_action( 'om_async_twilio_lookup_job', array( $item->get_id() ) );
				} elseif ( ! function_exists( 'as_has_scheduled_action' ) && ! wp_next_scheduled( 'om_async_twilio_lookup_job', array( $item->get_id() ) ) ) {
					wp_schedule_single_event( time(), 'om_async_twilio_lookup_job', array( $item->get_id() ) );
				}

				return '<span style="color:orange;">' . __( 'Pending Background Validation', 'optimessage' ) . '</span>';

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
				$is_consented = ( true === $consent || '1' === $consent || 'yes' === strtolower( $consent ) || 'on' === strtolower( $consent ) || 'true' === strtolower( $consent ) );

				if ( $is_consented ) {
					return '<span style="color:green;font-weight:bold;">' . __( 'Opted In', 'optimessage' ) . '</span>';
				} else {
					return '<span style="color:grey;">' . __( 'No Consent', 'optimessage' ) . '</span>';
				}

			default:
				return '';
		}
	}
}
