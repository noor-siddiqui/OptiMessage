<?php
/**
 * OptiMessage Uninstall
 *
 * Fired when the plugin is deleted.
 * Clean up the database table and settings to prevent orphaned data.
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * CAUTION: This deletes all core plugin data permanently.
 */

// Drop the SMS history table
$table_name = $wpdb->prefix . 'dsn_sms_history';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Delete plugin options from the wp_options table
delete_option( 'dsn_db_version' );
delete_option( 'dsn_twilio_sid' );
delete_option( 'dsn_twilio_token' );
delete_option( 'dsn_twilio_from' );
delete_option( 'dsn_wc_sms_enabled' );
delete_option( 'dsn_send_no_consent' );
delete_option( 'dsn_consent_checkout' );
delete_option( 'dsn_consent_required' );
delete_option( 'dsn_consent_profile' );
delete_option( 'dsn_track_number_key' );
delete_option( 'dsn_track_url_key' );
delete_option( 'dsn_tpl_placed' );
delete_option( 'dsn_tpl_completed' );
delete_option( 'dsn_tpl_refunded' );
