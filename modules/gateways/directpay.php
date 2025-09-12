<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require 'directpay/helper_methods.php';

function directpay_MetaData()
{
    return array(
        'DisplayName' => 'DirectPay',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function directpay_config()
{
    $responseUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/modules/gateways/callback/directpay.php';

    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'DirectPay',
        ),
        'merchantId' => array(
            'FriendlyName' => 'Merchant ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Your Merchant ID from DirectPay',
        ),
        'secret' => array(
            'FriendlyName' => 'Secret Key',
            'Type' => 'text',
            'Size' => '191',
            'Default' => '',
            'Description' => 'Secret Key string from DirectPay',
        ),
        'notifyUrl' => array(
            'FriendlyName' => 'Notify URL',
            'Type' => 'text',
            'Size' => '191',
            'Default' => $responseUrl,
            'Description' => 'Notification endpoint URL<br><small>Default Endpoint - </small> <p style="color: grey;">' . $responseUrl . '</p>',
        ),
        'logoUrl' => array(
            'FriendlyName' => 'Logo URL',
            'Type' => 'text',
            'Size' => '191',
            'Default' => '',
            'Description' => 'Your logo URL to display at payment page',
        ),
        'sandBox' => array(
            'FriendlyName' => 'SandBox Mode',
            'Type' => 'yesno',
            'Description' => 'Enable debug mode',
        ),
        'paymentMode' => array(
            'FriendlyName' => 'Payment Mode',
            'Type' => 'dropdown',
            'Options' => array(
                'both' => 'Both One-Time and Recurring Payments',
                'onetime' => 'One-Time Payments Only',
            ),
            'Default' => 'both',
            'Description' => 'Select the payment processing mode. Choose "One-Time Payments Only" to disable recurring payment functionality.',
        ),
        'debugLogging' => array(
            'FriendlyName' => 'Enable Debug Logging',
            'Type' => 'yesno',
            'Description' => 'Enable detailed logging for debugging purposes. Logs are saved to /modules/gateways/logs/directpay.log',
        ),
        'logRetention' => array(
            'FriendlyName' => 'Log Retention (days)',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '30',
            'Description' => 'Number of days to keep log files (0 = keep forever). Logs are automatically cleared after this period.',
        ),
    );
}

/**
 * Payment link.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return string
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 */
function directpay_link($params)
{
    // Set global gateway params for logging
    global $gatewayParams;
    $gatewayParams = $params;
    
    // Gateway Configuration Parameters
    $secret = $params['secret'];
    $merchantId = $params['merchantId'];
    $testMode = $params['sandBox'];
    $notifyUrl = $params['notifyUrl'];
    $logoUrl = $params['logoUrl'];
    $paymentMode = $params['paymentMode'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    $orderId = 'WH' . $invoiceId . date("ymdHis");

    $responseUrl = $notifyUrl . '?invoice=' . $invoiceId;
    
    // Log payment initiation
    logPaymentProcess('PAYMENT_INITIATED', [
        'invoice_id' => $invoiceId,
        'amount' => $amount,
        'currency' => $currencyCode,
        'client_email' => $email,
        'payment_mode' => $paymentMode,
        'test_mode' => $testMode,
        'order_id' => $orderId
    ], $invoiceId);

    // API Connection Details
    if ($testMode == 'on') {
        $gatewayUrl = "https://test-gateway.directpay.lk/api/v3/create-session";
    } else {
        $gatewayUrl = "https://gateway.directpay.lk/api/v3/create-session";
    }

    $recurringItem = getRecurringInfoByInvoiceId($invoiceId);

    debugLog(json_encode($recurringItem), '$recurringItem');
    
    // Log recurring item analysis
    logPaymentProcess('RECURRING_ANALYSIS', [
        'recurring_item' => $recurringItem,
        'payment_mode' => $paymentMode
    ], $invoiceId);

    $htmlOutput = '';

    // Check if merchant has selected one-time only mode but invoice has recurring items
    if ($paymentMode === 'onetime' && $recurringItem['recurring'] && $recurringItem['recurring_amount'] != 0.00) {
        $htmlOutput = "<img src='https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png' alt='DirectPay_payment' max-width='20%' /><br><p style='color:red;'>Recurring payments are disabled for this merchant. This invoice contains recurring items that cannot be processed with the current payment mode setting.</p>";
        
        logPaymentProcess('PAYMENT_BLOCKED', [
            'reason' => 'Recurring payments disabled',
            'recurring_amount' => $recurringItem['recurring_amount']
        ], $invoiceId);
    } elseif ($recurringItem['invalid']) {
        $htmlOutput = "<img src='https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png' alt='DirectPay_payment' max-width='20%' /><br><p>{$recurringItem['details']} <span style='color:red;'>*</span></p>";
        
        logPaymentProcess('PAYMENT_BLOCKED', [
            'reason' => 'Invalid recurring item',
            'details' => $recurringItem['details']
        ], $invoiceId);
    } else {
        $requestData = [
            "merchant_id" => $merchantId,
            "amount" => $amount ? number_format($amount, 2, '.', '') : "0.00",
            "source" => "WHMCS_v1.3.0",
            "type" => "ONE_TIME",
            "payment_category" => "PAYMENT_LINK",
            "order_id" => (string)$orderId,
            "currency" => $currencyCode,
            "return_url" => $returnUrl,
            "response_url" => $responseUrl,
            "first_name" => $firstname,
            "last_name" => $lastname,
            "email" => $email,
            "phone" => $phone,
            "logo" => $logoUrl,
            "description" => $description,
        ];

        // Only process recurring payments if merchant has enabled recurring mode
        if ($paymentMode === 'both' && $recurringItem['recurring'] && $recurringItem['recurring_amount'] != 0.00) {
            $totalTax = getTotalTaxAmount($invoiceId);

            debugLog($totalTax, '$totalTax');

            $requestData["type"] = "RECURRING";
            $requestData["initial_amount"] = $amount ? (string)$amount : "0.00";
            $requestData["recurring_amount"] = number_format($recurringItem['recurring_amount'] + $totalTax, 2, '.', '');
            $requestData["start_date"] = $recurringItem['start_date'];
            $requestData["end_date"] = $recurringItem['end_date'];
            $requestData["do_initial_payment"] = true;
            $requestData["interval"] = convertInterval($recurringItem['interval']);
        }

        debugLog(json_encode($requestData), 'Payment data');
        
        // Log API request details
        logApiCall($gatewayUrl, $requestData, null, 'REQUEST');

        $dataString = base64_encode(json_encode($requestData));
        $signature = 'hmac ' . hash_hmac('sha256', $dataString, $secret);

        debugLog($signature, 'Signature');

        /// Call API and get payment session URL
        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => $gatewayUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => base64_encode(json_encode($requestData)),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: $signature",
            ],
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        
        if ($curlError) {
            debugLog('Unable to fetch payment link: ' . $curlErrno . ' - ' . $curlError);
            logDirectPayError('API Call Failed', [
                'curl_error' => $curlError,
                'curl_errno' => $curlErrno,
                'endpoint' => $gatewayUrl,
                'invoice_id' => $invoiceId
            ]);
        }
        
        curl_close($ch);

        $getSession = json_decode($response);
        
        // Log API response
        logApiCall($gatewayUrl, $requestData, $response, $httpCode);

        if ($getSession->status == 200) {
            $htmlOutput = '<form id="directpay_payment_form" method="GET" action="' . $getSession->data->link . '">
                <img style="cursor: pointer;" src="https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png" alt="DirectPay_payment" onclick="document.getElementById(\'directpay_payment_form\').submit();" max-width="20%" />
                <input type="submit" value="' . $langPayNow . '">
            </form>';
            
            logPaymentProcess('PAYMENT_LINK_CREATED', [
                'payment_link' => $getSession->data->link,
                'session_id' => $getSession->data->id ?? 'unknown'
            ], $invoiceId);
        } else {
            $errorCode = 'WHM' . date('ymdHis');
            $htmlOutput = "Could not proceed the payment. Please try again. If this problem persists, please contact the merchant.<br>(ErrorCode $errorCode)";
            
            logDirectPayError('Payment Link Creation Failed', [
                'api_status' => $getSession->status ?? 'unknown',
                'api_response' => $response,
                'error_code' => $errorCode,
                'invoice_id' => $invoiceId
            ]);
        }
    }

    return $htmlOutput;
}