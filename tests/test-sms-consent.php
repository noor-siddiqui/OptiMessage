<?php
// phpcs:ignoreFile
<?php
// Mock WordPress global functions to prevent fatal errors in standalone script.
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e($text) { echo $text; }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url($text) { return $text; }
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url() { return ''; }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field() {}
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button() {}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $opt ) {
        if ( 'om_send_no_consent' === $opt ) {
            return false;
        }
        return false;
    }
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) { return false; }
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient() {}
}
if ( ! function_exists( 'update_meta_cache' ) ) {
	function update_meta_cache() {
        echo "[ERROR] update_meta_cache was called, but should have been replaced.\n";
    }
}
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta() {
        echo "[ERROR] get_user_meta was called, but should have been replaced.\n";
    }
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e($text) { echo $text; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode($data) { return json_encode($data); }
}

// Array Type Mock
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

// Mock WordPress db
class MockWPDB {
    public $usermeta = 'wp_usermeta';
    public function get_results($query, $output_type) {
        echo "Running query: $query\n";
        if ( strpos($query, 'optimessage/sms-consent') !== false ) {
            return array(
                array('user_id' => 123, 'meta_value' => '1'),
                array('user_id' => 456, 'meta_value' => '0'),
                array('user_id' => 789, 'meta_value' => 'yes'),
            );
        }
        return array();
    }
}
$wpdb = new MockWPDB();
$GLOBALS['wpdb'] = $wpdb;

// Mock order class
class MockOrder {
    public $id;
    public $customer_id;
    public $phone;
    public $meta = array();

    public function __construct($id, $customer_id, $phone, $sms_consent = null) {
        $this->id = $id;
        $this->customer_id = $customer_id;
        $this->phone = $phone;
    }

    public function get_customer_id() { return $this->customer_id; }
    public function get_billing_phone() { return $this->phone; }
    public function get_meta($key) { return isset($this->meta[$key]) ? $this->meta[$key] : ''; }
    public function get_items() { return array(); }
}

if ( ! function_exists( 'wc_get_orders' ) ) {
	function wc_get_orders() {
        return array(
            new MockOrder(1, 123, '+11234567890'),
            new MockOrder(2, 456, '+19876543210'),
            new MockOrder(3, 789, '+15555555555'),
            new MockOrder(4, 999, '+14444444444'), // no consent
        );
    }
}

// Ensure the class loads.
define( 'ABSPATH', __DIR__ . '/' );
require_once __DIR__ . '/../includes/class-om-send-sms.php';

// We need to capture output since render_bulk_filter_form echoes it.
ob_start();
$sms = new OM_Send_SMS();

// Make the private method accessible for testing.
$reflection = new ReflectionClass($sms);
$method = $reflection->getMethod('render_bulk_filter_form');
$method->setAccessible(true);
$method->invoke($sms);

$output = ob_get_clean();

// Basic assertions
if ( strpos( $output, 'Total Reachable Customers' ) !== false ) {
    echo "SUCCESS: render_bulk_filter_form executed without fatal errors.\n";
} else {
    echo "FAILED: Expected output not found.\n";
    echo $output;
}
