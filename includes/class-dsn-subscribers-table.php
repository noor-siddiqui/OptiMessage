<?php
/**
 * Subscribers Table tab for OptiMessage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class DSN_Subscribers_List_Table extends WP_List_Table {


	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Subscriber', 'desishad-sms-notifier' ),
				'plural'   => __( 'Subscribers', 'desishad-sms-notifier' ),
				'ajax'     => false,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No subscribers found.', 'desishad-sms-notifier' );
	}

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

	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();
		$offset   = ( $paged - 1 ) * $per_page;

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Setup WP_User_Query arguments
		$args = array(
			'number' => $per_page,
			'offset' => $offset,
		);

		// Handle Search natively across Core and Meta
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$search = sanitize_text_field( wp_unslash( trim( $_POST['s'] ) ) );

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

		// Handle Sorting
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'ID';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

		$args['order'] = $order;

		// If sorting by a user meta field
		$meta_keys = array( 'first_name', 'billing_phone', '_dsn_phone_valid', 'billing_address_1', 'billing_state', 'desishad/sms-consent' );
		if ( in_array( $orderby, $meta_keys ) ) {
			$args['meta_key'] = $orderby;
			$args['orderby']  = 'meta_value';
		} else {
			$args['orderby'] = $orderby; // ID, login, email, etc.
		}

		// Run native WP_User_Query
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

	public function column_default( $item, $column_name ) {
		// $item is a WP_User object natively returned by WP_User_Query
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
				if ( $status === '1' ) {
					return '<span style="color:green;font-weight:bold;">' . __( 'Yes', 'desishad-sms-notifier' ) . '</span>';
				} elseif ( $status === '-1' ) {
					return '<span style="color:red;font-weight:bold;">' . __( 'No', 'desishad-sms-notifier' ) . '</span>';
				}

				// If not validated yet, do it on the fly
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
				$is_consented = ( $consent == '1' || strtolower( $consent ) === 'yes' || strtolower( $consent ) === 'on' || strtolower( $consent ) === 'true' );

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

class DSN_Guest_Customers_List_Table extends WP_List_Table {


	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'Guest Order', 'desishad-sms-notifier' ),
				'plural'   => __( 'Guest Orders', 'desishad-sms-notifier' ),
				'ajax'     => false,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No guest orders found.', 'desishad-sms-notifier' );
	}

	public function get_columns() {
		return array(
			'order_id'    => esc_html__( 'Order ID', 'desishad-sms-notifier' ),
			'name'        => esc_html__( 'Billing Name', 'desishad-sms-notifier' ),
			'email'       => esc_html__( 'Email', 'desishad-sms-notifier' ),
			'phone'       => esc_html__( 'Phone', 'desishad-sms-notifier' ),
			'valid_phone' => esc_html__( 'Valid Phone?', 'desishad-sms-notifier' ),
			'address'     => esc_html__( 'Address', 'desishad-sms-notifier' ),
			'state'       => esc_html__( 'State', 'desishad-sms-notifier' ),
			'consent'     => esc_html__( 'SMS Consent', 'desishad-sms-notifier' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'order_id' => array( 'ID', false ),
		);
	}

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

		// Handle Search
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$search = sanitize_text_field( wp_unslash( trim( $_POST['s'] ) ) );

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

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'date';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
					return '<span style="color:grey;">' . __( 'No Number', 'desishad-sms-notifier' ) . '</span>';
				}

				$status = $item->get_meta( '_dsn_phone_valid', true );
				if ( $status === '1' ) {
					return '<span style="color:green;font-weight:bold;">' . __( 'Yes', 'desishad-sms-notifier' ) . '</span>';
				} elseif ( $status === '-1' ) {
					return '<span style="color:red;font-weight:bold;">' . __( 'No', 'desishad-sms-notifier' ) . '</span>';
				}

				$country = $item->get_billing_country();
				$lookup  = DSN_Twilio_API::lookup_phone( $phone, $country );
				if ( is_array( $lookup ) ) {
					if ( $lookup['valid'] ) {
						$item->update_meta_data( '_dsn_phone_valid', '1' );
						$item->set_billing_phone( $lookup['formatted'] );
						$item->save();
						return '<span style="color:green;font-weight:bold;">' . __( 'Yes', 'desishad-sms-notifier' ) . '</span>';
					} else {
						$item->update_meta_data( '_dsn_phone_valid', '-1' );
						$item->save();
						return '<span style="color:red;font-weight:bold;">' . __( 'No', 'desishad-sms-notifier' ) . '</span>';
					}
				}

				return '<span style="color:orange;">' . __( 'Pending', 'desishad-sms-notifier' ) . '</span>';

			case 'address':
				return esc_html( $item->get_billing_address_1() );

			case 'state':
				return esc_html( $item->get_billing_state() );

			case 'consent':
				$consent = $item->get_meta( '_wc_other/dsn/sms_consent', true );
				if ( $consent === '' || $consent === null ) {
					$consent = $item->get_meta( 'dsn/sms_consent', true );
				}
				if ( $consent === '' || $consent === null ) {
					$consent = $item->get_meta( '_dsn_sms_consent', true );
				}
				$is_consented = ( $consent === true || $consent == '1' || strtolower( $consent ) === 'yes' || strtolower( $consent ) === 'on' || strtolower( $consent ) === 'true' );

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

class DSN_Subscribers_Tab {


	public function __construct() {
		add_action( 'dsn_render_subscribers_tab', array( $this, 'render' ) );
	}

	public function render() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sub_tab = isset( $_GET['sub_tab'] ) ? sanitize_text_field( wp_unslash( $_GET['sub_tab'] ) ) : 'registered';

		echo '<h2>' . esc_html__( 'Subscribers & User Consent', 'desishad-sms-notifier' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Manage all SMS subscribers natively without crashing high-performance databases. Guests who do not create accounts are stored in Guest Orders.', 'desishad-sms-notifier' ) . '</p>';

		echo '<h3 class="nav-tab-wrapper" style="margin-bottom: 20px;">';
		echo '<a href="?page=dsn-settings&tab=subscribers&sub_tab=registered" class="nav-tab ' . ( $sub_tab === 'registered' ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Registered Users', 'desishad-sms-notifier' ) . '</a>';
		echo '<a href="?page=dsn-settings&tab=subscribers&sub_tab=guests" class="nav-tab ' . ( $sub_tab === 'guests' ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Guest Orders', 'desishad-sms-notifier' ) . '</a>';
		echo '</h3>';

		echo '<form method="post" action="?page=dsn-settings&tab=subscribers&sub_tab=' . esc_attr( $sub_tab ) . '">';

		if ( $sub_tab === 'guests' ) {
			$table = new DSN_Guest_Customers_List_Table();
			$table->prepare_items();
			$table->search_box( esc_html__( 'Search Guests', 'desishad-sms-notifier' ), 'search_id' );
			$table->display();
		} else {
			$table = new DSN_Subscribers_List_Table();
			$table->prepare_items();
			$table->search_box( esc_html__( 'Search Users', 'desishad-sms-notifier' ), 'search_id' );
			$table->display();
		}

		echo '</form>';
	}
}

new DSN_Subscribers_Tab();
