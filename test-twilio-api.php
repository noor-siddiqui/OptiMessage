<?php
// Mock get_option
if (!function_exists('get_option')) {
    function get_option($key) {
        if ($key === 'om_twilio_sid') return 'test_sid';
        if ($key === 'om_twilio_token') return 'test_token';
        return false;
    }
}
if (!function_exists('add_action')) {
    function add_action() {}
}

require_once 'includes/class-om-twilio-api.php';

echo "Method exists: " . (method_exists('OM_Twilio_API', 'lookup_phone_batch') ? 'Yes' : 'No') . "\n";
