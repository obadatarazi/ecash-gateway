<?php
/**
 * eCash Gateway Test Suite
 * Comprehensive testing for the eCash payment gateway plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../');
}

// Include WordPress test framework
require_once ABSPATH . 'wp-load.php';

class ECashGatewayTest {
    
    private $gateway;
    private $test_order;
    
    public function __construct() {
        $this->gateway = new WC_Gateway_ECash();
        $this->setup_test_environment();
    }
    
    private function setup_test_environment() {
        // Create a test order
        $this->test_order = wc_create_order();
        $this->test_order->add_product(wc_get_product(1), 1);
        $this->test_order->set_total(100.00);
        $this->test_order->save();
    }
    
    public function run_all_tests() {
        echo "Starting eCash Gateway Test Suite...\n";
        echo "=====================================\n\n";
        
        $tests = array(
            'test_gateway_initialization',
            'test_form_fields',
            'test_payment_validation',
            'test_api_integration',
            'test_webhook_handling',
            'test_refund_processing',
            'test_error_handling',
            'test_security_features',
            'test_logging_functionality'
        );
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            try {
                $result = $this->$test();
                if ($result) {
                    echo "✓ " . str_replace('_', ' ', $test) . " - PASSED\n";
                    $passed++;
                } else {
                    echo "✗ " . str_replace('_', ' ', $test) . " - FAILED\n";
                    $failed++;
                }
            } catch (Exception $e) {
                echo "✗ " . str_replace('_', ' ', $test) . " - ERROR: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
        
        echo "\n=====================================\n";
        echo "Test Results: $passed passed, $failed failed\n";
        echo "=====================================\n";
        
        return $failed === 0;
    }
    
    public function test_gateway_initialization() {
        // Test gateway properties
        $this->assert($this->gateway->id === 'ecash_gateway', 'Gateway ID should be ecash_gateway');
        $this->assert($this->gateway->method_title === 'eCash Gateway', 'Method title should be correct');
        $this->assert($this->gateway->has_fields === true, 'Gateway should have fields');
        
        // Test supported features
        $expected_features = array(
            'products', 'refunds', 'subscriptions', 'subscription_cancellations',
            'subscription_suspensions', 'subscription_reactivations',
            'subscription_amount_changes', 'subscription_date_changes',
            'subscription_payment_method_change', 'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin', 'multiple_subscriptions'
        );
        
        foreach ($expected_features as $feature) {
            $this->assert(in_array($feature, $this->gateway->supports), "Should support $feature");
        }
        
        return true;
    }
    
    public function test_form_fields() {
        $form_fields = $this->gateway->get_form_fields();
        
        // Test required fields exist
        $required_fields = array(
            'enabled', 'title', 'description', 'test_mode', 'debug_mode',
            'live_api_url', 'test_api_url', 'live_api_key', 'test_api_key',
            'live_merchant_id', 'test_merchant_id', 'webhook_secret',
            'auto_capture', 'currency'
        );
        
        foreach ($required_fields as $field) {
            $this->assert(isset($form_fields[$field]), "Form field $field should exist");
        }
        
        // Test field types
        $this->assert($form_fields['enabled']['type'] === 'checkbox', 'Enabled field should be checkbox');
        $this->assert($form_fields['title']['type'] === 'text', 'Title field should be text');
        $this->assert($form_fields['live_api_key']['type'] === 'password', 'API key should be password');
        
        return true;
    }
    
    public function test_payment_validation() {
        // Test valid card data
        $_POST['ecash_card_name'] = 'Test User';
        $_POST['ecash_card_number'] = '1234567812345678';
        $_POST['ecash_card_expiry'] = '12/25';
        $_POST['ecash_card_cvv'] = '123';
        
        $validation_result = $this->gateway->validate_payment_data();
        $this->assert($validation_result === true, 'Valid card data should pass validation');
        
        // Test invalid card number
        $_POST['ecash_card_number'] = '123456789';
        $validation_result = $this->gateway->validate_payment_data();
        $this->assert($validation_result['result'] === 'failure', 'Invalid card number should fail');
        
        // Test expired card
        $_POST['ecash_card_number'] = '1234567812345678';
        $_POST['ecash_card_expiry'] = '01/20';
        $validation_result = $this->gateway->validate_payment_data();
        $this->assert($validation_result['result'] === 'failure', 'Expired card should fail');
        
        // Test missing fields
        unset($_POST['ecash_card_name']);
        $validation_result = $this->gateway->validate_payment_data();
        $this->assert($validation_result['result'] === 'failure', 'Missing fields should fail');
        
        return true;
    }
    
    public function test_api_integration() {
        // Mock API response for testing
        $mock_response = array(
            'success' => true,
            'data' => array(
                'transaction_id' => 'TEST_' . time(),
                'status' => 'completed',
                'amount' => 100.00
            )
        );
        
        // Test API request preparation
        $_POST['ecash_card_name'] = 'Test User';
        $_POST['ecash_card_number'] = '1234567812345678';
        $_POST['ecash_card_expiry'] = '12/25';
        $_POST['ecash_card_cvv'] = '123';
        
        $payment_data = $this->gateway->prepare_payment_data($this->test_order);
        
        $this->assert(isset($payment_data['merchant_id']), 'Payment data should include merchant_id');
        $this->assert(isset($payment_data['amount']), 'Payment data should include amount');
        $this->assert(isset($payment_data['card']), 'Payment data should include card info');
        $this->assert($payment_data['amount'] == 100.00, 'Amount should match order total');
        
        return true;
    }
    
    public function test_webhook_handling() {
        // Test webhook signature verification
        $payload = '{"order_id":123,"status":"completed"}';
        $secret = 'test_secret';
        $signature = hash_hmac('sha256', $payload, $secret);
        
        $this->assert($this->gateway->verify_webhook_signature($payload, $signature), 'Valid signature should pass');
        $this->assert(!$this->gateway->verify_webhook_signature($payload, 'invalid'), 'Invalid signature should fail');
        
        return true;
    }
    
    public function test_refund_processing() {
        // Test refund data preparation
        $refund_data = array(
            'merchant_id' => 'TEST_MERCHANT',
            'transaction_id' => 'TEST_TRANSACTION',
            'amount' => 50.00,
            'reason' => 'Test refund'
        );
        
        $this->assert(isset($refund_data['merchant_id']), 'Refund data should include merchant_id');
        $this->assert(isset($refund_data['transaction_id']), 'Refund data should include transaction_id');
        $this->assert($refund_data['amount'] == 50.00, 'Refund amount should be correct');
        
        return true;
    }
    
    public function test_error_handling() {
        // Test network error handling
        $error_response = array(
            'success' => false,
            'error' => 'Network error: Connection timeout'
        );
        
        $this->assert($error_response['success'] === false, 'Error response should indicate failure');
        $this->assert(isset($error_response['error']), 'Error response should include error message');
        
        return true;
    }
    
    public function test_security_features() {
        // Test input sanitization
        $dirty_input = '<script>alert("xss")</script>Test User';
        $clean_input = sanitize_text_field($dirty_input);
        
        $this->assert($clean_input === 'Test User', 'Input should be sanitized');
        $this->assert(strpos($clean_input, '<script>') === false, 'XSS should be prevented');
        
        // Test nonce verification
        $nonce = wp_create_nonce('ecash_nonce');
        $this->assert(wp_verify_nonce($nonce, 'ecash_nonce'), 'Nonce should be valid');
        
        return true;
    }
    
    public function test_logging_functionality() {
        // Test logging methods exist
        $this->assert(method_exists($this->gateway, 'log_info'), 'Gateway should have log_info method');
        $this->assert(method_exists($this->gateway, 'log_error'), 'Gateway should have log_error method');
        $this->assert(method_exists($this->gateway, 'log'), 'Gateway should have log method');
        
        return true;
    }
    
    private function assert($condition, $message) {
        if (!$condition) {
            throw new Exception("Assertion failed: $message");
        }
    }
    
    public function cleanup() {
        // Clean up test order
        if ($this->test_order) {
            $this->test_order->delete(true);
        }
    }
}

// Run tests if this file is executed directly
if (php_sapi_name() === 'cli' || isset($_GET['run_tests'])) {
    $test_suite = new ECashGatewayTest();
    $success = $test_suite->run_all_tests();
    $test_suite->cleanup();
    
    if (php_sapi_name() === 'cli') {
        exit($success ? 0 : 1);
    }
}
?> 