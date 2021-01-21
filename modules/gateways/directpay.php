<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

require 'directpay/helpers.php';

function directpay_MetaData()
{
    return array(
        'DisplayName' => 'DirectPay',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function directpay_config()
{
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
        'sandBox' => array(
            'FriendlyName' => 'SandBox Mode',
            'Type' => 'yesno',
            'Description' => 'Enable debug mode',
        ),
    );
}

function directpay_link($params)
{
    // Gateway Configuration Parameters
    $secret = $params['secret'];
    $merchantId = $params['merchantId'];
    $testMode = $params['sandBox'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstName = $params['clientdetails']['firstname'];
    $lastName = $params['clientdetails']['lastname'];
    $fullName = $firstName . " " . $lastName;
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

    $orderId = 'WH' . $invoiceId . 'D' . date("ymdhis");

//    $responseUrl = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php?invoice=' . $invoiceId;
    $responseUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/modules/gateways/callback/' . $moduleName . '.php?invoice=' . $invoiceId;

    printToLog($responseUrl);

    // API Connection Details
    if ($testMode == 'on') {
        $gatewayUrl = "https://test-gateway.directpay.lk/api/v3/create-session";
    } else {
        $gatewayUrl = "https://gateway.directpay.lk/api/v3/create-session";
    }

    printToLog("Test Mode: " . ($testMode ? "yes" : "no"));
    printToLog($gatewayUrl);

    $mainProductOfRecurring = getRecurringItem($invoiceId);

    // Set post values
    if ($mainProductOfRecurring != null) {

        $priceResult = getPriceDetails($invoiceId, $mainProductOfRecurring);

        $requestData = [
            "merchant_id" => $merchantId,
            "amount" => $amount ? (string)$amount : "0.00",
            "source" => "WHMCS_v1.1",
            "payment_category" => "PAYMENT_LINK",
            "type" => "RECURRING",
            "order_id" => (string)$orderId,
            "currency" => $currencyCode,
            "return_url" => $returnUrl,
            "response_url" => $responseUrl,
            "first_name" => $firstName,
            "last_name" => $lastName,
            "email" => $email,
            "phone" => $phone,
            "start_date" => date("Y-m-d"),
            "end_date" => $mainProductOfRecurring->_endDate,
            "do_initial_payment" => true,
            "initial_amount" => $priceResult->_startupTotal,
            "interval" => convertInterval($mainProductOfRecurring->_interval),
            "description" => $description,
        ];
    } else {
        $requestData = [
            "merchant_id" => $merchantId,
            "amount" => $amount ? (string)$amount : "0.00",
            "source" => "WHMCS_v1.1",
            "type" => "ONE_TIME",
            "order_id" => (string)$orderId,
            "currency" => $currencyCode,
            "response_url" => $responseUrl,
            "return_url" => $returnUrl,
            "first_name" => $firstName,
            "last_name" => $lastName,
            "email" => $email,
            "phone" => $phone,
            "logo" => '',
            "description" => $description,
        ];
    }

    $dataString = base64_encode(json_encode($requestData));
    $signature = 'hmac ' . hash_hmac('sha256', $dataString, $secret);

    // Call API and get payment session URL
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
    if (curl_error($ch)) {
        printToLog('Unable to fetch payment link: ' . curl_errno($ch) . ' - ' . curl_error($ch));
    }
    curl_close($ch);

    $getSession = json_decode($response);

    if ($getSession->status == 200) {
        $link = $getSession->data->link;
        $paymentRedirect = $link;
    } else {
        $paymentRedirect = $returnUrl;
    }

    // Redirect to Payment Gateway
    return '<form id="directpay_payment_form" method="GET" action="' . $paymentRedirect . '">
                <img src="https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png" alt="DirectPay_payment" width="20%" min-width="200px" onclick="document.getElementById(\'directpay_payment_form\').submit();" />
                <input type="submit" value="' . $langPayNow . '">
            </form>';

}

function convertInterval($interval)
{
    switch ($interval) {
        case 'MONTHLY':
            return 1;
        case 'BIANNUAL':
            return 2;
        case 'YEARLY':
            return 3;
        case 'QUARTERLY':
            return 4;
        case 'BIENNIALLY':
            return 5;
        case 'TRIENNIALLY':
            return 6;
        default:
            return $interval;
    }
}