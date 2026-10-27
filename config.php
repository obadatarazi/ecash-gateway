<?php
/**
 * eCash Gateway Configuration Template
 * 
 * Copy this file to your plugin directory and customize the settings
 * for your specific eCash API integration.
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// eCash API Configuration
define('ECASH_CONFIG', array(
    
    // API Endpoints (Test only for now)
    'test_api_url' => 'https://test-api.ecash.sy/v1',
    
    // Default Settings (Test Mode)
    'default_title' => 'eCash - Pay securely with your card (Test Mode)',
    'default_description' => 'Pay securely using your eCash card. Currently in test mode.',
    'default_currency' => 'SYP',
    
    // Test Cards (for testing only)
    'test_cards' => array(
        array(
            'number' => '1234567812345678',
            'expiry' => '01/26',
            'cvv' => '123',
            'name' => 'Test User'
        ),
        array(
            'number' => '1111222233334444',
            'expiry' => '09/25',
            'cvv' => '456',
            'name' => 'Demo User'
        ),
        array(
            'number' => '5555444433332222',
            'expiry' => '12/24',
            'cvv' => '789',
            'name' => 'Sandbox'
        )
    ),
    
    // Supported Currency (SYP only)
    'supported_currencies' => array(
        'SYP' => 'Syrian Pound'
    ),
    
    // API Request Timeout (seconds)
    'timeout' => 30,
    
    // Webhook Settings
    'webhook_events' => array(
        'payment.completed',
        'payment.failed',
        'payment.refunded',
        'payment.cancelled'
    ),
    
    // Logging Configuration
    'log_levels' => array(
        'INFO' => 'Information messages',
        'ERROR' => 'Error messages',
        'DEBUG' => 'Debug messages (only in debug mode)'
    ),
    
    // Security Settings
    'security' => array(
        'require_ssl' => true,
        'webhook_verification' => true,
        'input_sanitization' => true,
        'nonce_verification' => true
    ),
    
    // Error Messages
    'error_messages' => array(
        'invalid_card' => 'Invalid card number format.',
        'expired_card' => 'Card has expired.',
        'invalid_cvv' => 'Invalid CVV format.',
        'invalid_expiry' => 'Invalid expiration date format. Use MM/YY.',
        'missing_fields' => 'Please fill in all card information.',
        'network_error' => 'Network error occurred. Please try again.',
        'api_error' => 'Payment processing error. Please try again.',
        'test_mode_only' => 'This card is not accepted in test mode.'
    ),
    
    // Success Messages
    'success_messages' => array(
        'payment_completed' => 'Payment completed successfully.',
        'refund_processed' => 'Refund processed successfully.'
    ),
    
    // CSS Classes for Styling
    'css_classes' => array(
        'form_container' => 'ecash-payment-form',
        'test_notice' => 'ecash-test-notice',
        'error_container' => 'ecash-payment-errors',
        'card_fields' => 'ecash-card-fields',
        'test_banner' => 'ecash-test-banner'
    ),
    
    // JavaScript Configuration
    'js_config' => array(
        'auto_format' => true,
        'real_time_validation' => true,
        'card_type_detection' => true,
        'clear_on_unload' => true
    ),
    
    // Admin Settings
    'admin_settings' => array(
        'show_test_banner' => true,
        'enable_debug_logging' => true,
        'auto_capture_payments' => true,
        'webhook_retry_attempts' => 3
    )
));

// Helper function to get configuration
function ecash_get_config($key = null) {
    if ($key === null) {
        return ECASH_CONFIG;
    }
    
    $keys = explode('.', $key);
    $config = ECASH_CONFIG;
    
    foreach ($keys as $k) {
        if (isset($config[$k])) {
            $config = $config[$k];
        } else {
            return null;
        }
    }
    
    return $config;
}

// Helper function to get error message
function ecash_get_error_message($key) {
    $messages = ecash_get_config('error_messages');
    return isset($messages[$key]) ? $messages[$key] : 'An error occurred.';
}

// Helper function to get success message
function ecash_get_success_message($key) {
    $messages = ecash_get_config('success_messages');
    return isset($messages[$key]) ? $messages[$key] : 'Operation completed successfully.';
}

// Helper function to validate API credentials
function ecash_validate_api_credentials($api_key, $merchant_id, $api_url) {
    if (empty($api_key) || empty($merchant_id) || empty($api_url)) {
        return false;
    }
    
    // Basic URL validation
    if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    return true;
}

// Helper function to get supported currencies
function ecash_get_supported_currencies() {
    return ecash_get_config('supported_currencies');
}

// Helper function to get test cards
function ecash_get_test_cards() {
    return ecash_get_config('test_cards');
}

// Helper function to check if SSL is required
function ecash_requires_ssl() {
    return ecash_get_config('security.require_ssl');
}

// Helper function to get timeout setting
function ecash_get_timeout() {
    return ecash_get_config('timeout');
}

// Helper function to get CSS class
function ecash_get_css_class($element) {
    $classes = ecash_get_config('css_classes');
    return isset($classes[$element]) ? $classes[$element] : '';
}

// Helper function to get JavaScript config
function ecash_get_js_config() {
    return ecash_get_config('js_config');
}

// Helper function to get admin settings
function ecash_get_admin_setting($key) {
    $settings = ecash_get_config('admin_settings');
    return isset($settings[$key]) ? $settings[$key] : false;
}
?> 