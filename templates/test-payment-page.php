<?php
/**
 * eCash Test Payment Page
 * Simulates the real eCash payment flow for testing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once dirname(__FILE__) . '/../../../wp-load.php';
}

// Get payment parameters
$transaction_id = sanitize_text_field($_GET['transaction_id'] ?? '');
$order_id = intval($_GET['order_id'] ?? 0);
$amount = floatval($_GET['amount'] ?? 0);
$currency = sanitize_text_field($_GET['currency'] ?? 'SYP');
$customer_name = sanitize_text_field($_GET['customer_name'] ?? '');
$customer_email = sanitize_email($_GET['customer_email'] ?? '');
$customer_phone = sanitize_text_field($_GET['customer_phone'] ?? '');
$return_url = esc_url_raw($_GET['return_url'] ?? '');
$cancel_url = esc_url_raw($_GET['cancel_url'] ?? '');
$shop_url = esc_url(home_url('/'));

// Validate required parameters
if (empty($transaction_id) || empty($order_id) || empty($amount)) {
    wp_die('Invalid payment parameters');
}

// Get order
$order = wc_get_order($order_id);
if (!$order) {
    wp_die('Order not found');
}

// Handle form submission
$payment_step = $_POST['payment_step'] ?? 'card_details';
$error_message = '';
$success_message = '';

if ($_POST && isset($_POST['ecash_payment_submit'])) {
    if ($payment_step === 'card_details') {
        // Validate card details
        $card_number = sanitize_text_field($_POST['card_number'] ?? '');
        $card_expiry = sanitize_text_field($_POST['card_expiry'] ?? '');
        $card_cvv = sanitize_text_field($_POST['card_cvv'] ?? '');
        $card_name = sanitize_text_field($_POST['card_name'] ?? '');
        
        if (empty($card_number) || empty($card_expiry) || empty($card_cvv) || empty($card_name)) {
            $error_message = 'Please fill in all card details.';
        } elseif (!preg_match('/^\d{16}$/', $card_number)) {
            $error_message = 'Invalid card number format.';
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
            $error_message = 'Invalid expiry date format. Use MM/YY.';
        } elseif (!preg_match('/^\d{3,4}$/', $card_cvv)) {
            $error_message = 'Invalid CVV format.';
        } else {
            // Store card details in session for OTP step
            WC()->session->set('ecash_card_details', array(
                'number' => $card_number,
                'expiry' => $card_expiry,
                'cvv' => $card_cvv,
                'name' => $card_name
            ));
            $payment_step = 'otp_verification';
        }
    } elseif ($payment_step === 'otp_verification') {
        $otp_code = sanitize_text_field($_POST['otp_code'] ?? '');
        
        if (empty($otp_code)) {
            $error_message = 'Please enter the OTP code.';
        } elseif ($otp_code === '123456') { // Test OTP code
            // Process successful payment
            $order->payment_complete($transaction_id);
            $order->add_order_note(
                sprintf(
                    __('eCash test payment completed successfully. Transaction ID: %s. OTP verified.', 'ecash-gateway'),
                    $transaction_id
                )
            );
            
            // Store payment data
            $order->update_meta_data('_ecash_transaction_id', $transaction_id);
            $order->update_meta_data('_ecash_payment_status', 'completed');
            $order->update_meta_data('_ecash_test_mode', true);
            $order->save();
            
            $success_message = 'Payment completed successfully!';
            $payment_step = 'success';
        } else {
            $error_message = 'Invalid OTP code. Please try again.';
        }
    }
}

// No get_header() or get_footer() here for a standalone look
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eCash Payment - Test Mode</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            position: relative;
        }
        /* Focused overlay effect */
        .background-blur {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 0;
            background: rgba(80, 80, 160, 0.18);
            backdrop-filter: blur(6px);
        }
        .payment-container {
            position: relative;
            background: white;
            border-radius: 26px;
            box-shadow: 0 12px 40px 0 rgba(44,62,80,0.22), 0 2px 8px rgba(44,62,80,0.10);
            max-width: 430px;
            width: 100%;
            overflow: hidden;
            margin: 48px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 32px 28px 32px;
            z-index: 1;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .payment-container:focus-within, .payment-container:hover {
            box-shadow: 0 20px 60px 0 rgba(44,62,80,0.28), 0 4px 16px rgba(44,62,80,0.13);
            transform: scale(1.012);
        }
        .logo {
            margin-top: 38px;
            margin-bottom: 10px;
            display: flex;
            justify-content: center;
        }
        .logo img {
            width: 110px;
            height: auto;
            display: block;
        }
        .powered-by {
            text-align: center;
            color: #888;
            font-size: 13px;
            margin-bottom: 12px;
            letter-spacing: 1px;
        }
        .secure-row {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }
        .secure-row .lock {
            width: 18px;
            height: 18px;
            margin-right: 7px;
            color: #4CAF50;
            display: inline-block;
        }
        .secure-row span {
            color: #4CAF50;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header {
            background: none;
            color: #222;
            padding: 0 0 14px 0;
            text-align: center;
        }
        .header h1 {
            font-size: 25px;
            margin-bottom: 0;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .header p {
            opacity: 0.9;
            font-size: 15px;
            margin-top: 4px;
        }
        .content {
            padding: 0 0 0 0;
            width: 100%;
        }
        .test-notice {
            background: #fffbe6;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 26px;
            font-size: 15px;
            display: flex;
            align-items: center;
        }
        .test-notice strong {
            margin-right: 6px;
        }
        .payment-info {
            background: linear-gradient(135deg, #f8f9fa 80%, #e3eafc 100%);
            border: 1.5px solid #e3eafc;
            border-radius: 12px;
            padding: 20px 18px 20px 18px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(44,62,80,0.04);
        }
        .payment-info h3 {
            color: #495057;
            margin-bottom: 13px;
            font-size: 18px;
            font-weight: 700;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 7px 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 15px;
        }
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .info-label {
            font-weight: 700;
            color: #6c757d;
        }
        .info-value {
            color: #495057;
        }
        .form-group {
            margin-bottom: 22px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: #495057;
        }
        .form-group input {
            width: 100%;
            padding: 13px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .btn {
            width: 100%;
            padding: 17px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.18s cubic-bezier(.4,0,.2,1);
            box-shadow: 0 2px 8px rgba(44,62,80,0.06);
            position: relative;
            overflow: hidden;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            margin-bottom: 10px;
        }
        .btn-primary:hover, .btn-primary:focus {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px) scale(1.025);
            box-shadow: 0 8px 24px rgba(76, 175, 80, 0.18);
        }
        .btn-primary:active {
            transform: scale(0.97);
        }
        .btn-primary:after {
            content: '';
            display: block;
            position: absolute;
            left: 50%;
            top: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.3);
            border-radius: 100%;
            transform: translate(-50%, -50%);
            transition: width 0.3s ease, height 0.3s ease;
            z-index: 1;
        }
        .btn-primary:active:after {
            width: 180%;
            height: 500%;
        }
        .btn-secondary {
            background: transparent;
            color: #4CAF50;
            border: 2px solid #4CAF50;
            margin-bottom: 0;
        }
        .btn-secondary:hover, .btn-secondary:focus {
            background: #f8f9fa;
            color: #388e3c;
            border-color: #388e3c;
        }
        .back-link {
            display: block;
            text-align: center;
            margin: 22px 0 0 0;
            color: #4CAF50;
            text-decoration: underline;
            font-size: 16px;
            font-weight: 600;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            border: 1px solid #f5c6cb;
            font-size: 15px;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
            border: 1px solid #c3e6cb;
            font-size: 15px;
        }
        .otp-container {
            text-align: center;
            padding: 18px 0 0 0;
        }
        .otp-input {
            font-size: 22px;
            text-align: center;
            letter-spacing: 5px;
            font-weight: bold;
        }
        .loading {
            text-align: center;
            padding: 30px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            animation: spin 1s linear infinite;
            margin: 0 auto 18px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @media (max-width: 600px) {
            .payment-container {
                margin: 0;
                border-radius: 0;
                max-width: 100vw;
                padding: 0 8vw 18px 8vw;
            }
            .content {
                padding: 0 0 0 0;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .btn {
                font-size: 16px;
                padding: 14px;
            }
        }
        @media (max-width: 400px) {
            .payment-container {
                padding: 0 2vw 10px 2vw;
            }
        }
    </style>
</head>
<body>
    <div class="background-blur"></div>
    <div class="payment-container" role="main" aria-label="eCash Payment Form">
        <div class="logo">
            <img src="https://lecadeaupk.com/wp-content/uploads/2025/07/download.svg" alt="eCash Logo" width="110" height="auto" />
        </div>
        <div class="powered-by">Powered by eCash</div>
        <div class="secure-row">
            <span class="lock" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="8" width="12" height="8" rx="2" fill="#4CAF50" fill-opacity="0.12"/><rect x="7" y="4" width="6" height="6" rx="3" stroke="#4CAF50" stroke-width="2" fill="none"/><rect x="4" y="8" width="12" height="8" rx="2" stroke="#4CAF50" stroke-width="2" fill="none"/></svg>
            </span>
            <span>Secure Payment</span>
        </div>
        <div class="header">
            <h1>eCash Payment Gateway</h1>
            <p>Secure Payment Processing</p>
        </div>
        <div class="content">
            <?php if ($payment_step === 'success'): ?>
                <div class="success-message" role="alert">
                    <h3>✅ Payment Successful!</h3>
                    <p>Your payment has been processed successfully. You will be redirected to your order confirmation page.</p>
                </div>
                <div class="payment-info">
                    <h3>Payment Details</h3>
                    <div class="info-row">
                        <span class="info-label">Transaction ID:</span>
                        <span class="info-value"><?php echo esc_html($transaction_id); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Order ID:</span>
                        <span class="info-value">#<?php echo esc_html($order_id); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Amount:</span>
                        <span class="info-value"><?php echo esc_html(number_format($amount, 2)); ?> SYP</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value">✅ Completed</span>
                    </div>
                </div>
                <a href="<?php echo esc_url($return_url); ?>" class="btn btn-primary">Continue to Order</a>
                <a href="<?php echo $shop_url; ?>" class="back-link">Back to Shop</a>
                <script>
                    setTimeout(function() {
                        window.location.href = '<?php echo esc_url($return_url); ?>';
                    }, 3000);
                </script>
            <?php elseif ($payment_step === 'otp_verification'): ?>
                <div class="test-notice" role="alert">
                    <strong>🧪 Test Mode:</strong> Use OTP code: <strong>123456</strong>
                </div>
                <?php if ($error_message): ?>
                    <div class="error-message" role="alert"><?php echo esc_html($error_message); ?></div>
                <?php endif; ?>
                <div class="payment-info">
                    <h3>Payment Verification</h3>
                    <div class="info-row">
                        <span class="info-label">Amount:</span>
                        <span class="info-value"><?php echo esc_html(number_format($amount, 2)); ?> SYP</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Card:</span>
                        <span class="info-value">**** **** **** <?php echo esc_html(substr(WC()->session->get('ecash_card_details')['number'], -4)); ?></span>
                    </div>
                </div>
                <form method="post" class="otp-container" aria-label="OTP Verification">
                    <input type="hidden" name="payment_step" value="otp_verification">
                    <input type="hidden" name="ecash_payment_submit" value="1">
                    <div class="form-group">
                        <label for="otp_code">Enter OTP Code</label>
                        <input type="text" id="otp_code" name="otp_code" maxlength="6" pattern="\d{6}" 
                               class="otp-input" placeholder="123456" required autocomplete="off" aria-label="OTP Code">
                        <small style="color: #6c757d; margin-top: 5px; display: block;">
                            A 6-digit code has been sent to your phone number
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary" id="otp-submit-btn">Verify & Complete Payment</button>
                </form>
                <a href="<?php echo esc_url($cancel_url); ?>" class="btn btn-secondary" style="margin-top: 15px;">Cancel Payment</a>
                <a href="<?php echo $shop_url; ?>" class="back-link">Back to Shop</a>
            <?php else: ?>
                <div class="test-notice" role="alert">
                    <strong>🧪 Test Mode:</strong> This is a simulation of the eCash payment process. Use any valid card details.
                </div>
                <?php if ($error_message): ?>
                    <div class="error-message" role="alert"><?php echo esc_html($error_message); ?></div>
                <?php endif; ?>
                <div class="payment-info">
                    <h3>Order Summary</h3>
                    <div class="info-row">
                        <span class="info-label">Order ID:</span>
                        <span class="info-value">#<?php echo esc_html($order_id); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Customer:</span>
                        <span class="info-value"><?php echo esc_html($customer_name); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo esc_html($customer_email); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Amount:</span>
                        <span class="info-value"><strong><?php echo esc_html(number_format($amount, 2)); ?> SYP</strong></span>
                    </div>
                </div>
                <form method="post" aria-label="Card Details">
                    <input type="hidden" name="payment_step" value="card_details">
                    <input type="hidden" name="ecash_payment_submit" value="1">
                    <div class="form-group">
                        <label for="card_name">Cardholder Name</label>
                        <input type="text" id="card_name" name="card_name" required 
                               placeholder="John Doe" autocomplete="cc-name" aria-label="Cardholder Name">
                    </div>
                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" name="card_number" maxlength="16" 
                               pattern="\d{16}" required placeholder="1234567812345678" 
                               autocomplete="cc-number" aria-label="Card Number">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="card_expiry">Expiry Date</label>
                            <input type="text" id="card_expiry" name="card_expiry" 
                                   pattern="(0[1-9]|1[0-2])/\d{2}" required 
                                   placeholder="MM/YY" autocomplete="cc-exp" aria-label="Expiry Date">
                        </div>
                        <div class="form-group">
                            <label for="card_cvv">CVV</label>
                            <input type="text" id="card_cvv" name="card_cvv" maxlength="4" 
                                   pattern="\d{3,4}" required placeholder="123" 
                                   autocomplete="cc-csc" aria-label="CVV">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" id="pay-btn">PROCEED TO PAYMENT</button>
                </form>
                <a href="<?php echo esc_url($cancel_url); ?>" class="btn btn-secondary" style="margin-top: 15px;">Cancel Payment</a>
                <a href="<?php echo $shop_url; ?>" class="back-link">Back to Shop</a>
            <?php endif; ?>
        </div>
    </div>
    <div id="loading-overlay" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(255,255,255,0.7);z-index:9999;align-items:center;justify-content:center;">
        <div class="spinner"></div>
        <div style="text-align:center;color:#333;font-size:18px;">Processing...</div>
    </div>
    <script>
        // Auto-format card number
        document.getElementById('card_number')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            if (value.length > 16) {
                value = value.substr(0, 16);
            }
            e.target.value = value;
        });
        // Auto-format expiry date
        document.getElementById('card_expiry')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substr(0, 2) + '/' + value.substr(2, 2);
            }
            e.target.value = value;
        });
        // Auto-format CVV
        document.getElementById('card_cvv')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 4) {
                value = value.substr(0, 4);
            }
            e.target.value = value;
        });
        // Auto-format OTP
        document.getElementById('otp_code')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 6) {
                value = value.substr(0, 6);
            }
            e.target.value = value;
        });
        // Show loading spinner on submit
        function showLoading() {
            document.getElementById('loading-overlay').style.display = 'flex';
        }
        document.getElementById('pay-btn')?.addEventListener('click', function() {
            showLoading();
        });
        document.getElementById('otp-submit-btn')?.addEventListener('click', function() {
            showLoading();
        });
    </script>
</body>
</html>

<?php
// No rewrite rules needed for the new approach
?> 