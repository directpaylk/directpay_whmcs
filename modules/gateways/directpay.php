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
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '255',
            'Default' => '',
            'Description' => 'API Key from DirectPay',
        ),
        'privateKey' => array(
            'FriendlyName' => 'Private Key String',
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '60',
            'Default' => '',
            'Description' => 'Paste your private key string here',
        ),
        'publicKey' => array(
            'FriendlyName' => 'Public Key String',
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '60',
            'Default' => '',
            'Description' => 'Paste your public key string here',
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
//function directpay_link($params) {
//    /* Gateway Configuration Parameters */
//    $merchantId = $params['merchantId'];
//    $privateKey = $params['secret'];
//    $sandBox = $params['sandBox'];
//
//    /* Invoice Parameters*/
//    $invoiceId = $params['invoiceid'];
//    $description = $params["description"];
//    $amount = $params['amount'];
//    $currencyCode = $params['currency'];
//
//    // Client Parameters
//    $firstName = $params['clientdetails']['firstname'];
//    $lastName = $params['clientdetails']['lastname'];
//    $fullName = $firstName . " " . $lastName;
//    $email = $params['clientdetails']['email'];
//    $address1 = $params['clientdetails']['address1'];
//    $address2 = $params['clientdetails']['address2'];
//    $city = $params['clientdetails']['city'];
//    $state = $params['clientdetails']['state'];
//    $postcode = $params['clientdetails']['postcode'];
//    $country = $params['clientdetails']['country'];
//    $phone = $params['clientdetails']['phonenumber'];
//
//    // System Parameters
//    $companyName = $params['companyname'];
//    $systemUrl = $params['systemurl'];
//    $returnUrl = $params['returnurl']; // http://localhost/whmcs/viewinvoice.php?id=4
//    $langPayNow = $params['langpaynow'];
//    $moduleDisplayName = $params['name'];
//    $moduleName = $params['paymentmethod'];
//    $whmcsVersion = $params['whmcsVersion'];
//
//    $orderId = substr($merchantId, 1) . $invoiceId;
//    $hmacSecret = $privateKey;
//
//    $responseUrl = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php?invoice=' . $invoiceId . '&amount=' . $amount;
//
//    // API Connection Details
//    $gatewayUrl = "https://test-gateway.directpay.lk/api/v3/create-session";
//    if ($sandBox == 'off') {
//        $gatewayUrl = "https://gateway.directpay.lk/api/v3/create-session";
//    }
//
//    $mainProductOfRecurring = getRecurringItem($invoiceId);
//
//    // Set post values
//    if ($mainProductOfRecurring != null) {
//        // TODO : Recurring Item
//
//        $priceResult = getPriceDetails($invoiceId, $mainProductOfRecurring);
//
//        $requestData = [
//            "merchant_id" => $merchantId,
//            "amount" => $amount ? (string)$amount : "0.00",
//            "source" => "DirectPay_WHMCS_v1.1",
//            "payment_category" => "PAYMENT_LINK",
//            "type" => "RECURRING",
//            "order_id" => (string)$orderId,
//            "currency" => $currencyCode,
//            "return_url" => $returnUrl,
//            "response_url" => $responseUrl,
//            "first_name" => $firstName,
//            "last_name" => $lastName,
//            "email" => $email,
//            "phone" => $phone,
//            "start_date" => date("Y-m-d"),
//            "end_date" => $mainProductOfRecurring->recurringDuration,
//            "do_initial_payment" => true,
//            "initial_amount" => $priceResult->startupTotal,
//            "interval" => $mainProductOfRecurring->recurringPeriod,
//        ];
//        do_log('got recurring');
//    } else {
//        $requestData = [
//            "merchant_id" => $merchantId,
//            "amount" => $amount ? (string)$amount : "0.00",
//            "type" => "ONE_TIME",
//            "order_id" => (string)$orderId,
//            "currency" => $currencyCode,
//            "response_url" => $responseUrl,
//            "return_url" => $returnUrl,
//            "logo" => ''
//        ];
//    }
//
//    $dataString = base64_encode(json_encode($requestData));
//    $signature = 'hmac ' . hash_hmac('sha256', $dataString, $hmacSecret);
//
//    // Call the API
//    $ch = curl_init();
//
//    curl_setopt_array($ch, array(
//        CURLOPT_URL => $gatewayUrl,
//        CURLOPT_RETURNTRANSFER => true,
//        CURLOPT_ENCODING => "",
//        CURLOPT_MAXREDIRS => 10,
//        CURLOPT_TIMEOUT => 30,
//        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//        CURLOPT_CUSTOMREQUEST => "POST",
//        CURLOPT_POSTFIELDS => base64_encode(json_encode($requestData)),
//        CURLOPT_HTTPHEADER => [
//            "Content-Type: application/json",
//            "Authorization: $signature",
//        ],
//    ));
//
//    $response = curl_exec($ch);
//    if (curl_error($ch)) {
//        do_log('Unable to connect: ' . curl_errno($ch) . ' - ' . curl_error($ch));
//    }
//    curl_close($ch);
//
//    // Decode response
//    //    $jsonData = json_decode($response, true);
//
//    // Dump array structure for inspection
//    return json_decode($response);
//}

function directpay_link($params)
{
    // Gateway Configuration Parameters
    $secret = $params['secret'];
    $merchantId = $params['merchantId'];
    $privateKey = $params['privateKey'];
    $testMode = $params['testMode'];
    $apiKey = $params['apiKey'];
//    $dropdownField = $params['dropdownField'];
//    $radioField = $params['radioField'];
//    $textareaField = $params['textareaField'];

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
    $returnUrl = $params['returnurl']; // http://localhost/whmcs/viewinvoice.php?id=4
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    $orderId = substr($merchantId, 1) . $invoiceId;

    $responseUrl = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php?invoice=' . $invoiceId . '&amount=' . $amount;

    // API Connection Details
    $gatewayUrl = "https://test-gateway.directpay.lk/api/v3/create-session";
    if ($testMode == 'off') {
        $gatewayUrl = "https://gateway.directpay.lk/api/v3/create-session";
    }

    $mainProductOfRecurring = getRecurringItem($invoiceId);

    // Set post values
    if ($mainProductOfRecurring != null) {
        // TODO : Recurring Item

        $priceResult = getPriceDetails($invoiceId, $mainProductOfRecurring);

        $requestData = [
            "merchant_id" => $merchantId,
            "amount" => $amount ? (string)$amount : "0.00",
            "source" => "DirectPay_WHMCS_v1.1",
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
            "end_date" => $mainProductOfRecurring->recurringDuration,
            "do_initial_payment" => true,
            "initial_amount" => $priceResult->startupTotal,
            "interval" => $mainProductOfRecurring->recurringPeriod,
        ];
        do_log('got recurring');
    } else {
        $requestData = [
            "merchant_id" => $merchantId,
            "amount" => $amount ? (string)$amount : "0.00",
            "source" => "DirectPay_WHMCS_v1.1",
            "type" => "ONE_TIME",
            "order_id" => (string)$orderId,
            "currency" => $currencyCode,
            "response_url" => $responseUrl,
            "return_url" => $returnUrl,
            "first_name" => $firstName,
            "last_name" => $lastName,
            "email" => $email,
            "phone" => $phone,
            "logo" => ''
        ];
    }

    $dataString = base64_encode(json_encode($requestData));
    $signature = 'hmac ' . hash_hmac('sha256', $dataString, $secret);

    // Call the API
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
        do_log('Unable to connect: ' . curl_errno($ch) . ' - ' . curl_error($ch));
    }
    curl_close($ch);

    $getSession = json_decode($response);

    if ($getSession->status == 200) {
        do_log($getSession->status);
        do_log($getSession->data->link);
        $link = $getSession->data->link;
        $paymentRedirect = $link;
    } else {
        //TODO getSession failed
        $paymentRedirect = '';
    }

    // REDIRECT TO GATEWAY
    return '<form method="GET" action="' . $paymentRedirect . '">
                <img src="https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png" alt="DirectPay Payment" width="20%" min-width="200px" />
            </form>';

}

//function directpay_refund($params)
//{
//    // Gateway Configuration Parameters
//    $merchantId = $params['merchantId'];
//    $privateKey = $params['privateKey'];
//    $testMode = $params['testMode'];
//    $dropdownField = $params['dropdownField'];
//    $radioField = $params['radioField'];
//    $textareaField = $params['textareaField'];
//
//    // Transaction Parameters
//    $transactionIdToRefund = $params['transid'];
//    $refundAmount = $params['amount'];
//    $currencyCode = $params['currency'];
//
//    // Client Parameters
//    $firstname = $params['clientdetails']['firstname'];
//    $lastname = $params['clientdetails']['lastname'];
//    $email = $params['clientdetails']['email'];
//    $address1 = $params['clientdetails']['address1'];
//    $address2 = $params['clientdetails']['address2'];
//    $city = $params['clientdetails']['city'];
//    $state = $params['clientdetails']['state'];
//    $postcode = $params['clientdetails']['postcode'];
//    $country = $params['clientdetails']['country'];
//    $phone = $params['clientdetails']['phonenumber'];
//
//    // System Parameters
//    $companyName = $params['companyname'];
//    $systemUrl = $params['systemurl'];
//    $langPayNow = $params['langpaynow'];
//    $moduleDisplayName = $params['name'];
//    $moduleName = $params['paymentmethod'];
//    $whmcsVersion = $params['whmcsVersion'];
//
//    // perform API call to initiate refund and interpret result
//
//    return array(
//        // 'success' if successful, otherwise 'declined', 'error' for failure
//        'status' => 'success',
//        // Data to be recorded in the gateway log - can be a string or array
//        'rawdata' => $responseData,
//        // Unique Transaction ID for the refund transaction
//        'transid' => $refundTransactionId,
//        // Optional fee amount for the fee value refunded
//        'fees' => $feeAmount,
//    );
//}

//function directpay_cancelSubscription($params)
//{
//    // Gateway Configuration Parameters
//    $merchantId = $params['merchantId'];
//    $privateKey = $params['privateKey'];
//    $testMode = $params['testMode'];
//    $dropdownField = $params['dropdownField'];
//    $radioField = $params['radioField'];
//    $textareaField = $params['textareaField'];
//
//    // Subscription Parameters
//    $subscriptionIdToCancel = $params['subscriptionID'];
//
//    // System Parameters
//    $companyName = $params['companyname'];
//    $systemUrl = $params['systemurl'];
//    $langPayNow = $params['langpaynow'];
//    $moduleDisplayName = $params['name'];
//    $moduleName = $params['paymentmethod'];
//    $whmcsVersion = $params['whmcsVersion'];
//
//    // perform API call to cancel subscription and interpret result
//
//    return array(
//        // 'success' if successful, any other value for failure
//        'status' => 'success',
//        // Data to be recorded in the gateway log - can be a string or array
//        'rawdata' => $responseData,
//    );
//}




    
