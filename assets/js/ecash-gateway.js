/**
 * eCash Gateway JavaScript
 * Handles client-side validation and form interactions
 */

jQuery(function($) {
    'use strict';

    // Initialize eCash gateway functionality
    function initECashGateway() {
        const $form = $('#ecash-payment-form');
        const $cardNumber = $('#ecash_card_number');
        const $cardExpiry = $('#ecash_card_expiry');
        const $cardCvv = $('#ecash_card_cvv');
        const $cardName = $('#ecash_card_name');
        const $errorDiv = $('#ecash-payment-errors');

        // Format card number with spaces
        $cardNumber.on('input', function() {
            let value = $(this).val().replace(/\s/g, '');
            if (value.length > 16) {
                value = value.substr(0, 16);
            }
            $(this).val(value);
        });

        // Format expiry date
        $cardExpiry.on('input', function() {
            let value = $(this).val().replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substr(0, 2) + '/' + value.substr(2, 2);
            }
            $(this).val(value);
        });

        // Format CVV
        $cardCvv.on('input', function() {
            let value = $(this).val().replace(/\D/g, '');
            if (value.length > 4) {
                value = value.substr(0, 4);
            }
            $(this).val(value);
        });

        // Real-time validation
        function validateCardNumber() {
            const value = $cardNumber.val();
            if (value.length !== 16) {
                return 'Card number must be 16 digits';
            }
            if (!/^\d{16}$/.test(value)) {
                return 'Card number must contain only digits';
            }
            return null;
        }

        function validateExpiry() {
            const value = $cardExpiry.val();
            if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(value)) {
                return 'Invalid expiry format. Use MM/YY';
            }
            
            const parts = value.split('/');
            const month = parseInt(parts[0]);
            const year = 2000 + parseInt(parts[1]);
            const currentDate = new Date();
            const currentYear = currentDate.getFullYear();
            const currentMonth = currentDate.getMonth() + 1;
            
            if (year < currentYear || (year === currentYear && month < currentMonth)) {
                return 'Card has expired';
            }
            
            return null;
        }

        function validateCvv() {
            const value = $cardCvv.val();
            if (!/^\d{3,4}$/.test(value)) {
                return 'CVV must be 3-4 digits';
            }
            return null;
        }

        function validateCardName() {
            const value = $cardName.val().trim();
            if (value.length < 2) {
                return 'Cardholder name is required';
            }
            return null;
        }

        // Show/hide error messages
        function showError(message) {
            $errorDiv.html('<p>' + message + '</p>').show();
        }

        function hideError() {
            $errorDiv.hide();
        }

        // Real-time validation on input
        $cardNumber.on('blur', function() {
            const error = validateCardNumber();
            if (error) {
                showError(error);
            } else {
                hideError();
            }
        });

        $cardExpiry.on('blur', function() {
            const error = validateExpiry();
            if (error) {
                showError(error);
            } else {
                hideError();
            }
        });

        $cardCvv.on('blur', function() {
            const error = validateCvv();
            if (error) {
                showError(error);
            } else {
                hideError();
            }
        });

        $cardName.on('blur', function() {
            const error = validateCardName();
            if (error) {
                showError(error);
            } else {
                hideError();
            }
        });

        // Form submission validation - simplified for redirect flow
        $('form.checkout').on('submit', function(e) {
            if ($('input[name="payment_method"]:checked').val() === 'ecash_gateway') {
                // Show loading state
                const $submitButton = $(this).find('button[type="submit"]');
                const originalText = $submitButton.text();
                $submitButton.text('Redirecting to eCash...').prop('disabled', true);
                
                // Hide any existing errors
                hideError();
            }
        });

        // Test mode indicator
        if (typeof ecash_ajax !== 'undefined' && ecash_ajax.test_mode) {
            $form.prepend('<div class="ecash-test-banner" style="background: #ff6b6b; color: white; padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center;"><strong>TEST MODE ACTIVE</strong> - This is a test environment</div>');
        }

        // Card type detection (basic)
        $cardNumber.on('input', function() {
            const number = $(this).val();
            let cardType = '';
            
            if (number.startsWith('4')) {
                cardType = 'Visa';
            } else if (number.startsWith('5')) {
                cardType = 'Mastercard';
            } else if (number.startsWith('3')) {
                cardType = 'American Express';
            } else if (number.startsWith('6')) {
                cardType = 'Discover';
            }
            
            if (cardType) {
                $(this).attr('data-card-type', cardType);
            }
        });

        // Enhanced security - prevent form data caching
        $form.find('input[type="text"], input[type="password"]').attr('autocomplete', 'off');
        
        // Clear sensitive data on page unload
        $(window).on('beforeunload', function() {
            $cardNumber.val('');
            $cardCvv.val('');
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        initECashGateway();
    });

    // Re-initialize on checkout form updates
    $(document.body).on('updated_checkout', function() {
        initECashGateway();
    });

    // AJAX card validation (if needed)
    function validateCardAjax(cardData) {
        return $.ajax({
            url: ecash_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ecash_validate_card',
                nonce: ecash_ajax.nonce,
                card_number: cardData.number,
                card_expiry: cardData.expiry,
                card_cvv: cardData.cvv
            }
        });
    }

    // Export functions for external use
    window.ECashGateway = {
        validateCard: validateCardAjax,
        init: initECashGateway
    };
}); 