<?php
/**
 * OptiMessage Uninstall
 *
 * Fired when the plugin is deleted.
 * Clean up the database table and settings to prevent orphaned data.
 *
 * @package OptiMessage
 * @author Noor Nabiul Alam Siddiqui <siddiqui.sazal@gmail.com>
 * @license https://github.com/noor-siddiqui/OptiMessage/blob/main/LICENSE GNU General Public License v3.0
 * @link https://github.com/noor-siddiqui/OptiMessage
 * @since 0.1.0_beta
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * CAUTION: This deletes all core plugin data permanently.
 */

// Drop the SMS history table.
$table_name = $wpdb->prefix . 'om_sms_history';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be prepared.
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Delete plugin options from the wp_options table.
delete_option( 'om_db_version' );
delete_option( 'om_twilio_sid' );
delete_option( 'om_twilio_token' );
delete_option( 'om_twilio_from' );
delete_option( 'om_wc_sms_enabled' );
delete_option( 'om_wc_event_placed' );
delete_option( 'om_wc_event_completed' );
delete_option( 'om_wc_event_on_hold' );
delete_option( 'om_wc_event_cancelled' );
delete_option( 'om_wc_event_refunded' );
delete_option( 'om_send_no_consent' );
delete_option( 'om_consent_checkout' );
delete_option( 'om_consent_required' );
delete_option( 'om_consent_profile' );
delete_option( 'om_track_number_key' );
delete_option( 'om_track_url_key' );
delete_option( 'om_tpl_placed' );
delete_option( 'om_tpl_completed' );
delete_option( 'om_tpl_on_hold' );
delete_option( 'om_tpl_cancelled' );
delete_option( 'om_tpl_refunded' );

// Clean up user meta left by the plugin.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup during uninstall.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('optimessage/sms-consent', '_om_phone_valid')" );

// Clean up order meta (HPOS-compatible via wc_orders_meta if table exists, plus legacy postmeta).
$hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup during uninstall.
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_meta_table ) ) === $hpos_meta_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cleanup during uninstall; table name from $wpdb->prefix.
	$wpdb->query( "DELETE FROM {$hpos_meta_table} WHERE meta_key IN ('_om_phone_valid', '_om_sms_consent', '_wc_other/om/sms_consent', 'om/sms_consent')" );
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup during uninstall.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_om_phone_valid', '_om_sms_consent')" );

// Clean up transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup during uninstall.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_om_%' OR option_name LIKE '_transient_timeout_om_%'" );
