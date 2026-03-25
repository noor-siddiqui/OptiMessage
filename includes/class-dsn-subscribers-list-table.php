<?php
/**
 * Subscribers Table class for OptiMessage
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
 * Class DSN_Subscribers_List_Table
 * Table class for subscribers.
 */
class DSN_Subscribers_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Subscriber', 'desishad-sms-notifier' ),
				'plural'   => __( 'Subscribers', 'desishad-sms-notifier' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Message when no items found.
	 */
	public function no_items() {
		esc_html_e( 'No subscribers found.', 'desishad-sms-notifier' );
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'name'        => esc_html__( 'Full Name', 'desishad-sms-notifier' ),
			'email'       => esc_html__( 'Email', 'desishad-sms-notifier' ),
			'phone'       => esc_html__( 'Phone', 'desishad-sms-notifier' ),
			'valid_phone' => esc_html__( 'Valid Phone?', 'desishad-sms-notifier' ),
			'address'     => esc_html__( 'Address', 'desishad-sms-notifier' ),
			'state'       => esc_html__( 'State', 'desishad-sms-notifier' ),
			'consent'     => esc_html__( 'SMS Consent', 'desishad-sms-notifier' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'name'        => array( 'first_name', false ),
			'email'       => array( 'email', false ),
			'phone'       => array( 'billing_phone', false ),
			'valid_phone' => array( '_dsn_phone_valid', false ),
			'address'     => array( 'billing_address_1', false ),
			'state'       => array( 'billing_state', false ),
			'consent'     => array( 'desishad/sms-consent', false ),
		);
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();
		$offset   = ( $paged - 1 ) * $per_page;

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Setup WP_User_Query arguments.
		$args = array(
			'number' => $per_page,
			'offset' => $offset,
		);

		// Handle Search natively across Core and Meta.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is not required for read-only search operations.
		if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is not required for read-only search operations.
			$search = sanitize_text_field( wp_unslash( $_POST['s'] ) );

			if ( is_email( $search ) ) {
				$args['search']         = '*' . $search . '*';
				$args['search_columns'] = array( 'user_email', 'user_login' );
			} elseif ( preg_match( '/^[0-9\+\-\s\(\)]+$/', $search ) ) {
				$clean              = preg_replace( '/[^0-9]/', '', $search );
				$args['meta_query'] = array(
					'relation' => 'OR',
					array(
						'key'     => 'billing_phone',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'billing_phone',
						'value'   => $clean,
						'compare' => 'LIKE',
					),
				);
			} else {
				$terms      = explode( ' ', $search );
				$meta_query = array( 'relation' => 'OR' );
				foreach ( $terms as $term ) {
					$term = trim( $term );
					if ( empty( $term ) ) {
						continue;
					}
					$meta_query[] = array(
						'key'     => 'first_name',
						'value'   => $term,
						'compare' => 'LIKE',
					);
					$meta_query[] = array(
						'key'     => 'last_name',
						'value'   => $term,
						'compare' => 'LIKE',
					);
					$meta_query[] = array(
						'key'     => 'billing_first_name',
						'value'   => $term,
						'compare' => 'LIKE',
					);
					$meta_query[] = array(
						'key'     => 'billing_last_name',
						'value'   => $term,
						'compare' => 'LIKE',
					);
				}
				if ( count( $meta_query ) > 1 ) {
					$args['meta_query'] = $meta_query;
				}
			}
		}

		// Handle Sorting.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'ID';
		$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

		$args['order'] = $order;

		// If sorting by a user meta field.
		$meta_keys = array( 'first_name', 'billing_phone', '_dsn_phone_valid', 'billing_address_1', 'billing_state', 'desishad/sms-consent' );
		if ( in_array( $orderby, $meta_keys ) ) {
			$args['meta_key'] = $orderby;
			$args['orderby']  = 'meta_value';
		} else {
			$args['orderby'] = $orderby; // ID, login, email, etc.
		}

		// Run native WP_User_Query.
		$user_query = new WP_User_Query( $args );

		$this->items = $user_query->get_results();
		$total_items = $user_query->get_total();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Column default rendering.
	 *
	 * @param object $item        The item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		// $item is a WP_User object natively returned by WP_User_Query.
		switch ( $column_name ) {
			case 'name':
				$first = get_user_meta( $item->ID, 'first_name', true );
				$last  = get_user_meta( $item->ID, 'last_name', true );
				$name  = trim( $first . ' ' . $last );
				return esc_html( $name ? $name : $item->display_name );

			case 'email':
				return '<a href="' . esc_url( get_edit_user_link( $item->ID ) ) . '">' . esc_html( $item->user_email ) . '</a>';

			case 'phone':
				return esc_html( get_user_meta( $item->ID, 'billing_phone', true ) );

			case 'valid_phone':
				$phone = get_user_meta( $item->ID, 'billing_phone', true );
				if ( empty( $phone ) ) {
					return '<span style="color:grey;">' . __( 'No Number', 'desishad-sms-notifier' ) . '</span>';
				}

				$status = get_user_meta( $item->ID, '_dsn_phone_valid', true );
				if ( '1' === $status ) {
					return '<span style="color:green;font-weight:bold;">' . __( 'Yes', 'desishad-sms-notifier' ) . '</span>';
				} elseif ( '-1' === $status ) {
					return '<span style="color:red;font-weight:bold;">' . __( 'No', 'desishad-sms-notifier' ) . '</span>';
				}

				// If not validated yet, do it on the fly.
				$country = get_user_meta( $item->ID, 'billing_country', true );
				$lookup  = DSN_Twilio_API::lookup_phone( $phone, $country );
				if ( is_array( $lookup ) ) {
					if ( $lookup['valid'] ) {
						update_user_meta( $item->ID, 'billing_phone', $lookup['formatted'] );
						update_user_meta( $item->ID, '_dsn_phone_valid', '1' );
						return '<span style="color:green;font-weight:bold;">' . __( 'Yes', 'desishad-sms-notifier' ) . '</span>';
					} else {
						update_user_meta( $item->ID, '_dsn_phone_valid', '-1' );
						return '<span style="color:red;font-weight:bold;">' . __( 'No', 'desishad-sms-notifier' ) . '</span>';
					}
				}

				return '<span style="color:orange;">' . __( 'Pending', 'desishad-sms-notifier' ) . '</span>';

			case 'address':
				return esc_html( get_user_meta( $item->ID, 'billing_address_1', true ) );

			case 'state':
				return esc_html( get_user_meta( $item->ID, 'billing_state', true ) );

			case 'consent':
				$consent      = get_user_meta( $item->ID, 'desishad/sms-consent', true );
				$is_consented = ( '1' === $consent || 'yes' === strtolower( $consent ) || 'on' === strtolower( $consent ) || 'true' === strtolower( $consent ) );

				if ( $is_consented ) {
					return '<span style="color:green;font-weight:bold;">' . __( 'Opted In', 'desishad-sms-notifier' ) . '</span>';
				} else {
					return '<span style="color:grey;">' . __( 'No Consent', 'desishad-sms-notifier' ) . '</span>';
				}

			default:
				return '';
		}
	}
}
