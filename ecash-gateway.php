<?php
/*
Plugin Name: eCash Gateway for WooCommerce
Description: Custom eCash payment gateway for WooCommerce with full API integration.
Version: 2.5
Author: Obada Tarazi - CupCoding
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) exit;

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('ECASH_PLUGIN_VERSION', '2.0');
define('ECASH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ECASH_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Add gateway to WooCommerce
add_filter('woocommerce_payment_gateways', 'ecash_add_gateway_class');
function ecash_add_gateway_class($gateways) {
    $gateways[] = 'WC_Gateway_ECash';
    return $gateways;
}

// Initialize gateway class
add_action('plugins_loaded', 'ecash_init_gateway_class');

// No rewrite rules needed for the new approach

// Handle test payment page
add_action('template_redirect', 'ecash_handle_test_payment');
function ecash_handle_test_payment() {
    if (isset($_GET['ecash_payment']) && $_GET['ecash_payment'] === '1') {
        include plugin_dir_path(__FILE__) . 'templates/test-payment-page.php';
        exit;
    }
}
function ecash_init_gateway_class() {
    
    // Check if WooCommerce is active
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_ECash extends WC_Payment_Gateway {
        
        private $api_base_url;
        private $api_key;
        private $merchant_id;
        private $webhook_secret;
        private $debug_mode;
        
        public function __construct() {
            $this->id = 'ecash_gateway';
            $this->icon = 'https://lecadeaupk.com/wp-content/uploads/2025/07/download.svg';
            $this->method_title = 'eCash Gateway';
            $this->method_description = 'Accept payments through eCash with optional test mode.';
            $this->has_fields = true;
            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->test_mode = 'yes' === $this->get_option('test_mode');
            
            // API Configuration (Test mode only for now)
            $this->api_base_url = $this->get_option('test_api_url');
            $this->api_key = $this->get_option('test_api_key');
            $this->merchant_id = $this->get_option('test_merchant_id');
            $this->webhook_secret = $this->get_option('webhook_secret');
            $this->debug_mode = 'yes' === $this->get_option('debug_mode');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_ecash_webhook', array($this, 'handle_webhook'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            
            // Add admin notices
            add_action('admin_notices', array($this, 'admin_notices'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable eCash Gateway',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'Payment method title that the customer sees during checkout.',
                    'default' => 'eCash - Pay securely with your card',
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'Payment method description that the customer sees during checkout.',
                    'default' => 'Pay securely using your eCash card.',
                    'desc_tip' => true
                ),
                'test_mode' => array(
                    'title' => 'Test Mode',
                    'type' => 'checkbox',
                    'label' => 'Enable test mode (Currently always enabled)',
                    'description' => 'Test mode is currently always enabled since no live APIs are available.',
                    'default' => 'yes',
                    'custom_attributes' => array(
                        'checked' => 'checked',
                        'disabled' => 'disabled'
                    ),
                    'desc_tip' => true
                ),
                'debug_mode' => array(
                    'title' => 'Debug Mode',
                    'type' => 'checkbox',
                    'label' => 'Enable debug logging',
                    'description' => 'Log eCash events, such as API requests, inside this file. By default, this option is disabled. To help with debugging, uncomment the line that says "define" in the main plugin file.',
                    'default' => 'no',
                    'desc_tip' => true
                ),
                'test_api_url' => array(
                    'title' => 'Test API URL',
                    'type' => 'text',
                    'description' => 'The test eCash API endpoint URL (for future use).',
                    'default' => 'https://test-api.ecash.sy/v1',
                    'desc_tip' => true
                ),
                'test_api_key' => array(
                    'title' => 'Test API Key',
                    'type' => 'password',
                    'description' => 'Your test eCash API key (for future use).',
                    'desc_tip' => true
                ),
                'test_merchant_id' => array(
                    'title' => 'Test Merchant ID',
                    'type' => 'text',
                    'description' => 'Your test eCash merchant ID (for future use).',
                    'desc_tip' => true
                ),
                'webhook_secret' => array(
                    'title' => 'Webhook Secret',
                    'type' => 'password',
                    'description' => 'Webhook secret for verifying webhook authenticity.',
                    'desc_tip' => true
                ),
                'auto_capture' => array(
                    'title' => 'Auto Capture',
                    'type' => 'checkbox',
                    'label' => 'Automatically capture payments',
                    'description' => 'Automatically capture payments when they are authorized.',
                    'default' => 'yes',
                    'desc_tip' => true
                ),
                'currency' => array(
                    'title' => 'Currency',
                    'type' => 'text',
                    'description' => 'Currency for transactions (SYP only).',
                    'default' => 'SYP',
                    'custom_attributes' => array(
                        'readonly' => 'readonly'
                    ),
                    'desc_tip' => true
                )
            );
        }

        public function admin_notices() {
            if (!$this->is_available()) {
                echo '<div class="notice notice-error"><p>' . 
                     __('eCash Gateway requires WooCommerce to be installed and active.', 'ecash-gateway') . 
                     '</p></div>';
            }
        }

        public function enqueue_scripts() {
            if (is_checkout()) {
                wp_enqueue_script(
                    'ecash-gateway',
                    ECASH_PLUGIN_URL . 'assets/js/ecash-gateway.js',
                    array('jquery'),
                    ECASH_PLUGIN_VERSION,
                    true
                );
                
                wp_localize_script('ecash-gateway', 'ecash_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('ecash_nonce'),
                    'test_mode' => $this->test_mode
                ));
            }
        }

        public function payment_fields() {
            echo '<div id="ecash-payment-form">';
            echo '<p>' . esc_html($this->description) . '</p>';
            
            echo '<div class="ecash-test-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px;">
                <strong>🧪 Test Mode Active:</strong> You will be redirected to eCash test payment page.
                <ul style="margin: 5px 0 0 20px;">
                    <li>Use any valid card details on the eCash page</li>
                    <li>OTP code for test: <strong>123456</strong></li>
                    <li>Test phone number: <strong>+963123456789</strong></li>
                    <li>This is a simulation - no real payments will be processed</li>
                </ul>
            </div>';
            
            echo '<div class="ecash-payment-info" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 10px 0; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #495057;">' . __('Payment Process', 'ecash-gateway') . '</h4>
                <p style="margin: 0; color: #6c757d;">' . __('You will be redirected to eCash secure payment page to complete your transaction. Your payment will be processed in Syrian Pounds (SYP).', 'ecash-gateway') . '</p>
            </div>';
            
            echo '<div id="ecash-payment-errors" style="display: none; color: #dc3232; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0; border-radius: 4px;"></div>';
            echo '</div>';
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            
            try {
                // Generate unique transaction ID
                $transaction_id = 'ECASH_' . time() . '_' . $order_id;
                
                // Store transaction data in session for webhook processing
                WC()->session->set('ecash_transaction_id', $transaction_id);
                WC()->session->set('ecash_order_id', $order_id);
                
                // Always redirect to test payment page for now
                $redirect_url = $this->get_test_payment_url($order, $transaction_id);
                
                // Add order note
                $order->add_order_note(
                    sprintf(
                        __('eCash test payment initiated. Transaction ID: %s. Redirecting to test payment page.', 'ecash-gateway'),
                        $transaction_id
                    )
                );
                
                return array(
                    'result' => 'success',
                    'redirect' => $redirect_url
                );

            } catch (Exception $e) {
                $this->log_error('Payment processing error: ' . $e->getMessage());
                $order->add_order_note('Payment processing error: ' . $e->getMessage());
                return array(
                    'result' => 'failure',
                    'messages' => array(__('An unexpected error occurred. Please try again.', 'ecash-gateway'))
                );
            }
        }

        private function validate_payment_data() {
            $card_name = sanitize_text_field($_POST['ecash_card_name']);
            $card_number = sanitize_text_field($_POST['ecash_card_number']);
            $card_expiry = sanitize_text_field($_POST['ecash_card_expiry']);
            $card_cvv = sanitize_text_field($_POST['ecash_card_cvv']);

            if (empty($card_name) || empty($card_number) || empty($card_expiry) || empty($card_cvv)) {
                return array(
                    'result' => 'failure',
                    'messages' => array(__('Please fill in all card information.', 'ecash-gateway'))
                );
            }

            if (!preg_match('/^\\d{16}$/', $card_number)) {
                return array(
                    'result' => 'failure',
                    'messages' => array(__('Invalid card number format.', 'ecash-gateway'))
                );
            }

            if (!preg_match('/^(0[1-9]|1[0-2])\\/\\d{2}$/', $card_expiry)) {
                return array(
                    'result' => 'failure',
                    'messages' => array(__('Invalid expiration date format. Use MM/YY.', 'ecash-gateway'))
                );
            }

            if (!preg_match('/^\\d{3,4}$/', $card_cvv)) {
                return array(
                    'result' => 'failure',
                    'messages' => array(__('Invalid CVV format.', 'ecash-gateway'))
                );
            }

            // Check if card is expired
            $expiry_parts = explode('/', $card_expiry);
            $expiry_month = intval($expiry_parts[0]);
            $expiry_year = 2000 + intval($expiry_parts[1]);
            
            if ($expiry_year < date('Y') || ($expiry_year == date('Y') && $expiry_month < date('n'))) {
                return array(
                    'result' => 'failure',
                    'messages' => array(__('Card has expired.', 'ecash-gateway'))
                );
            }

            if ($this->test_mode) {
                $valid_cards = array(
                    '1234567812345678',
                    '1111222233334444',
                    '5555444433332222'
                );
                if (!in_array($card_number, $valid_cards)) {
                    return array(
                        'result' => 'failure',
                        'messages' => array(__('This card is not accepted in test mode.', 'ecash-gateway'))
                    );
                }
            }

            return true;
        }

        private function prepare_payment_data($order) {
            $card_name = sanitize_text_field($_POST['ecash_card_name']);
            $card_number = sanitize_text_field($_POST['ecash_card_number']);
            $card_expiry = sanitize_text_field($_POST['ecash_card_expiry']);
            $card_cvv = sanitize_text_field($_POST['ecash_card_cvv']);

            $expiry_parts = explode('/', $card_expiry);
            
            return array(
                'merchant_id' => $this->merchant_id,
                'amount' => $order->get_total(),
                'currency' => $this->get_option('currency', 'SYP'),
                'order_id' => $order->get_id(),
                'customer_email' => $order->get_billing_email(),
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_phone' => $order->get_billing_phone(),
                'billing_address' => array(
                    'line1' => $order->get_billing_address_1(),
                    'line2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'postal_code' => $order->get_billing_postcode(),
                    'country' => $order->get_billing_country()
                ),
                'card' => array(
                    'number' => $card_number,
                    'expiry_month' => intval($expiry_parts[0]),
                    'expiry_year' => 2000 + intval($expiry_parts[1]),
                    'cvv' => $card_cvv,
                    'name' => $card_name
                ),
                'auto_capture' => $this->get_option('auto_capture', 'yes') === 'yes',
                'webhook_url' => home_url('/wc-api/ecash_webhook'),
                'return_url' => $this->get_return_url($order),
                'cancel_url' => $order->get_cancel_order_url()
            );
        }

        private function make_api_request($endpoint, $data) {
            $url = $this->api_base_url . '/' . $endpoint;
            
            $headers = array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'X-Merchant-ID' => $this->merchant_id,
                'User-Agent' => 'eCash-WooCommerce/' . ECASH_PLUGIN_VERSION
            );

            $args = array(
                'method' => 'POST',
                'headers' => $headers,
                'body' => json_encode($data),
                'timeout' => 30,
                'sslverify' => true
            );

            $this->log_info('API Request to ' . $url . ': ' . json_encode($data));

            $response = wp_remote_post($url, $args);

            if (is_wp_error($response)) {
                $this->log_error('API Request failed: ' . $response->get_error_message());
                return array(
                    'success' => false,
                    'error' => 'Network error: ' . $response->get_error_message()
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            $this->log_info('API Response (' . $response_code . '): ' . $response_body);

            if ($response_code >= 200 && $response_code < 300) {
                return array(
                    'success' => true,
                    'data' => $response_data
                );
            } else {
                $error_message = isset($response_data['message']) ? $response_data['message'] : 'API request failed';
                return array(
                    'success' => false,
                    'error' => $error_message
                );
            }
        }

        private function process_successful_payment($order, $payment_data) {
            $transaction_id = isset($payment_data['transaction_id']) ? $payment_data['transaction_id'] : '';
            $status = isset($payment_data['status']) ? $payment_data['status'] : 'completed';
            
            $order->payment_complete($transaction_id);
            $order->add_order_note(
                sprintf(
                    __('eCash payment processed successfully. Transaction ID: %s. Status: %s (%s)', 'ecash-gateway'),
                    $transaction_id,
                    $status,
                    $this->test_mode ? 'TEST MODE' : 'LIVE'
                )
            );
            
            // Store additional payment data
            $order->update_meta_data('_ecash_transaction_id', $transaction_id);
            $order->update_meta_data('_ecash_payment_status', $status);
            $order->update_meta_data('_ecash_test_mode', $this->test_mode);
            $order->save();
        }

        private function process_failed_payment($order, $error_message) {
            $order->update_status('failed', __('eCash payment failed: ' . $error_message, 'ecash-gateway'));
            $order->add_order_note('eCash payment failed: ' . $error_message);
        }

        public function handle_webhook() {
            $payload = file_get_contents('php://input');
            $signature = isset($_SERVER['HTTP_X_ECASH_SIGNATURE']) ? $_SERVER['HTTP_X_ECASH_SIGNATURE'] : '';
            
            $this->log_info('Webhook received: ' . $payload);
            
            // Verify webhook signature
            if (!$this->verify_webhook_signature($payload, $signature)) {
                $this->log_error('Webhook signature verification failed');
                http_response_code(400);
                exit;
            }
            
            $data = json_decode($payload, true);
            
            if (!$data) {
                $this->log_error('Invalid webhook payload');
                http_response_code(400);
                exit;
            }
            
            $this->process_webhook($data);
            
            http_response_code(200);
            exit;
        }

        private function verify_webhook_signature($payload, $signature) {
            if (empty($this->webhook_secret)) {
                return true; // Skip verification if no secret is set
            }
            
            $expected_signature = hash_hmac('sha256', $payload, $this->webhook_secret);
            return hash_equals($expected_signature, $signature);
        }

        private function process_webhook($data) {
            $order_id = isset($data['order_id']) ? $data['order_id'] : null;
            $transaction_id = isset($data['transaction_id']) ? $data['transaction_id'] : null;
            $status = isset($data['status']) ? $data['status'] : null;
            
            if (!$order_id) {
                $this->log_error('Webhook missing order_id');
                return;
            }
            
            $order = wc_get_order($order_id);
            if (!$order) {
                $this->log_error('Order not found: ' . $order_id);
                return;
            }
            
            switch ($status) {
                case 'completed':
                case 'captured':
                    $order->payment_complete($transaction_id);
                    $order->add_order_note(
                        sprintf(__('eCash payment completed via webhook. Transaction ID: %s', 'ecash-gateway'), $transaction_id)
                    );
                    break;
                    
                case 'failed':
                case 'declined':
                    $order->update_status('failed', __('eCash payment failed via webhook', 'ecash-gateway'));
                    break;
                    
                case 'refunded':
                    $order->update_status('refunded', __('eCash payment refunded via webhook', 'ecash-gateway'));
                    break;
                    
                default:
                    $this->log_info('Unhandled webhook status: ' . $status);
                    break;
            }
        }

        public function process_refund($order_id, $amount = null, $reason = '') {
            $order = wc_get_order($order_id);
            
            if (!$amount) {
                $amount = $order->get_total();
            }
            
            $refund_data = array(
                'merchant_id' => $this->merchant_id,
                'transaction_id' => $order->get_meta('_ecash_transaction_id'),
                'amount' => $amount,
                'reason' => $reason
            );
            
            $api_response = $this->make_api_request('refunds', $refund_data);
            
            if ($api_response['success']) {
                $order->add_order_note(
                    sprintf(__('eCash refund processed. Amount: %s. Reason: %s', 'ecash-gateway'), 
                        wc_price($amount), 
                        $reason
                    )
                );
                return true;
            } else {
                $order->add_order_note(
                    sprintf(__('eCash refund failed: %s', 'ecash-gateway'), $api_response['error'])
                );
                return false;
            }
        }

        private function log_info($message) {
            if ($this->debug_mode) {
                $this->log($message, 'INFO');
            }
        }

        private function log_error($message) {
            $this->log($message, 'ERROR');
        }

        private function log($message, $level = 'INFO') {
            if ($this->debug_mode || $level === 'ERROR') {
                $log_entry = sprintf(
                    '[%s] [%s] %s',
                    date('Y-m-d H:i:s'),
                    $level,
                    $message
                );
                
                $log_file = WC_LOG_DIR . 'ecash-gateway-' . date('Y-m-d') . '.log';
                error_log($log_entry . PHP_EOL, 3, $log_file);
            }
        }

        private function get_test_payment_url($order, $transaction_id) {
            $params = array(
                'transaction_id' => $transaction_id,
                'order_id' => $order->get_id(),
                'amount' => $order->get_total(),
                'currency' => 'SYP',
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email(),
                'customer_phone' => $order->get_billing_phone(),
                'return_url' => $this->get_return_url($order),
                'cancel_url' => $order->get_cancel_order_url(),
                'webhook_url' => home_url('/wc-api/ecash_webhook'),
                'mode' => 'test'
            );
            
            // Use a more reliable URL approach
            return add_query_arg($params, home_url('/?ecash_payment=1'));
        }

        private function get_live_payment_url($order, $transaction_id) {
            // For future live mode implementation
            // This will redirect to actual eCash API when available
            return $this->get_test_payment_url($order, $transaction_id);
        }

        public function is_available() {
            if (!parent::is_available()) {
                return false;
            }
            
            // For test mode, always available
            // For future live mode, check if required settings are configured
            if (!$this->test_mode && (empty($this->api_key) || empty($this->merchant_id) || empty($this->api_base_url))) {
                return false;
            }
            
            return true;
        }
    }
}

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ecash_settings_link');
function ecash_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=ecash_gateway') . '">' . __('Settings', 'ecash-gateway') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Add AJAX handler for card validation
add_action('wp_ajax_ecash_validate_card', 'ecash_validate_card_ajax');
add_action('wp_ajax_nopriv_ecash_validate_card', 'ecash_validate_card_ajax');
function ecash_validate_card_ajax() {
    check_ajax_referer('ecash_nonce', 'nonce');
    
    $card_number = sanitize_text_field($_POST['card_number']);
    $card_expiry = sanitize_text_field($_POST['card_expiry']);
    $card_cvv = sanitize_text_field($_POST['card_cvv']);
    
    $errors = array();
    
    if (!preg_match('/^\\d{16}$/', $card_number)) {
        $errors[] = __('Invalid card number format.', 'ecash-gateway');
    }
    
    if (!preg_match('/^(0[1-9]|1[0-2])\\/\\d{2}$/', $card_expiry)) {
        $errors[] = __('Invalid expiration date format. Use MM/YY.', 'ecash-gateway');
    }
    
    if (!preg_match('/^\\d{3,4}$/', $card_cvv)) {
        $errors[] = __('Invalid CVV format.', 'ecash-gateway');
    }
    
    wp_send_json(array(
        'valid' => empty($errors),
        'errors' => $errors
    ));
}

// Activation hook
register_activation_hook(__FILE__, 'ecash_activate');
function ecash_activate() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('This plugin requires WooCommerce to be installed and active.', 'ecash-gateway'));
    }
    
    // Create log directory if it doesn't exist
    $log_dir = WC_LOG_DIR . 'ecash-gateway/';
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'ecash_deactivate');
function ecash_deactivate() {
    // Clean up any scheduled events or temporary data
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'ecash_uninstall');
function ecash_uninstall() {
    // Remove plugin options and data
    delete_option('woocommerce_ecash_gateway_settings');
    // Clean up any test data
}

// No rewrite rules needed for the new approach
