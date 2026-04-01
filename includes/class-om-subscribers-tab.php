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
	}

	/**
	 * Render the view.
	 */
	public function render() {

		$sub_tab = isset( $_GET['sub_tab'] ) ? sanitize_text_field( wp_unslash( $_GET['sub_tab'] ) ) : 'registered';

		echo '<h2>' . esc_html__( 'Subscribers & User Consent', 'optimessage' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Manage all SMS subscribers natively without crashing high-performance databases. Guests who do not create accounts are stored in Guest Orders.', 'optimessage' ) . '</p>';

		echo '<h3 class="nav-tab-wrapper" style="margin-bottom: 20px;">';
		echo '<a href="?page=om-settings&tab=subscribers&sub_tab=registered" class="nav-tab ' . ( 'registered' === $sub_tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Registered Users', 'optimessage' ) . '</a>';
		echo '<a href="?page=om-settings&tab=subscribers&sub_tab=guests" class="nav-tab ' . ( 'guests' === $sub_tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Guest Orders', 'optimessage' ) . '</a>';
		echo '</h3>';

		echo '<form method="post" action="?page=om-settings&tab=subscribers&sub_tab=' . esc_attr( $sub_tab ) . '">';

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
}

// Instantiate the tab so the hook is registered.
new OM_Subscribers_Tab();
