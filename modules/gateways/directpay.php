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
        'privateKey' => array(
            'FriendlyName' => 'Private Key',
            'Type' => 'textarea',
            'cols' => 3,
            'rows' => 5,
            'Default' => '',
            'Description' => 'Private Key string from DirectPay',
        ),
        'publicKey' => array(
            'FriendlyName' => 'Public Key',
            'Type' => 'textarea',
            'cols' => 3,
            'rows' => 5,
            'Default' => '',
            'Description' => 'Public Key string from DirectPay',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '255',
            'Default' => '',
            'Description' => 'API key string',
        ),
        'notifyUrl' => array(
            'FriendlyName' => 'Notify URL',
            'Type' => 'text',
            'Size' => '191',
            'Default' => $responseUrl,
            'Description' => 'Notification endpoint URL.<br><small>Default Endpoint - </small> <p style="color: grey;">' . $responseUrl . '</p>',
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
    $notifyUrl = $params['notifyUrl'];
    $privateKey = $params['privateKey'];
    $publicKey = $params['publicKey'];
    $apiKey = $params['apiKey'];

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

    $cancelUrl = $returnUrl;

    $pluginName = "WHMCS_";
    $pluginVersion = "v1.0";

    $orderId = 'WH' . $invoiceId . date("ymdHis");

    $reference = $orderId;

//    $responseUrl = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php?invoice=' . $invoiceId;
//    $responseUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/modules/gateways/callback/' . $moduleName . '.php?invoice=' . $invoiceId;
    $responseUrl = $notifyUrl . '?invoice=' . $invoiceId . '&amount=' . $amount;

    printToLog($responseUrl);

    // API Connection Details
//    if ($testMode == 'on') {
//        $gatewayUrl = "https://test-gateway.directpay.lk/api/v3/create-session";
//    } else {
//        $gatewayUrl = "https://gateway.directpay.lk/api/v3/create-session";
//    }
    if ($testMode == 'on') {
        $gatewayUrl = "https://testpay.directpay.lk/";
    } else {
        $gatewayUrl = "https://pay.directpay.lk/";
    }

    printToLog("Test Mode: " . ($testMode ? "yes" : "no"));
    printToLog($gatewayUrl);

    $mainProductOfRecurring = getRecurringItem($invoiceId);

    // Set post values
    if ($mainProductOfRecurring != null) {

        $priceResult = getPriceDetails($invoiceId, $mainProductOfRecurring);

        // TODO: Recurring product
        printToLog("recurring");

//        $requestData = [
//            "merchant_id" => $merchantId,
//            "amount" => $amount ? (string)$amount : "0.00",
//            "source" => "WHMCS_v1.1",
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
//            "end_date" => $mainProductOfRecurring->_endDate,
//            "do_initial_payment" => true,
//            "initial_amount" => $priceResult->_startupTotal,
//            "interval" => convertInterval($mainProductOfRecurring->_interval),
//            "description" => $description,
//        ];

        $endDate = $mainProductOfRecurring->_endDate;
        $interval = convertInterval($mainProductOfRecurring->_interval);

        $dataString = $merchantId . $amount . $currencyCode . $pluginName . $pluginVersion . $returnUrl . $cancelUrl . $orderId .
            $reference . $firstName . $lastName . $email . $description . $apiKey . $responseUrl . date("Y-m-d") . $endDate .
            $interval . "1";
        $pkeyid = openssl_get_privatekey($privateKey);
        $signResult = openssl_sign($dataString, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
        $signatureEncoded = base64_encode($signature);

        return '<form id="directpay_payment_form" method="POST" action="' . $gatewayUrl . '">
<input type="hidden" name="_type" id="_type" value="RECURRING" />
<input type="hidden" name="_mId" id="_mId" value="' . $merchantId . '" />
<input type="text" name="_amount" id="_amount" value="' . $amount . '" />
<input type="hidden" name="_firstName" id="_firstName" value="' . $firstName . '">
<input type="hidden" name="_lastName" id="_lastName" value="' . $lastName . '">
<input type="hidden" name="_email" id="_email" value="' . $email . '">
<input type="hidden" name="_reference" id="_reference" value="' . $reference . '">
<input type="hidden" name="_description" id="_description" value="' . $description . '">
<input type="hidden" name="_returnUrl" id="_returnUrl" value="' . $returnUrl . '">
<input type="hidden" name="_cancelUrl" id="_cancelUrl" value="' . $cancelUrl . '">
<input type="hidden" name="_responseUrl" id="_responseUrl" value="' . $responseUrl . '">
<input type="hidden" name="_currency" id="_currency" value="' . $currencyCode . '">
   
<input type="hidden" name="_orderId" id="_orderId" value="' . $orderId . '">
<input type="hidden" name="_pluginVersion" id="_pluginVersion" value="' . $pluginVersion . '">
<input type="hidden" name="_pluginName" id="_pluginName" value="' . $pluginName . '">
<input type="hidden" name="api_key" id="api_key" value="' . $apiKey . '">
<input type="hidden" name="signature" id="signature" value="' . $signatureEncoded . '">



<input type="hidden" name="_startDate" id="_startDate" value="' . date("Y-m-d") . '">
<input type="hidden" name="_endDate" id="_endDate" value="' . $endDate . '">
<input type="hidden" name="_interval" id="_interval" value="' . $interval . '">
<input type="hidden" name="_doFirstPayment" id="_doFirstPayment" value="1">
    
<img src="https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png" alt="DirectPay_payment" onclick="document.getElementById(\'directpay_payment_form\').submit();" max-width="20%" />

</form>
';


    } else {

        // TODO: Onetime Product

//        $requestData = [
//            "merchant_id" => $merchantId,
//            "amount" => $amount ? (string)$amount : "0.00",
//            "source" => "WHMCS_v1.1",
//            "type" => "ONE_TIME",
//            "order_id" => (string)$orderId,
//            "currency" => $currencyCode,
//            "response_url" => $responseUrl,
//            "return_url" => $returnUrl,
//            "first_name" => $firstName,
//            "last_name" => $lastName,
//            "email" => $email,
//            "phone" => $phone,
//            "logo" => '',
//            "description" => $description,
//        ];

        $dataString = $merchantId . $amount . $currencyCode . $pluginName . $pluginVersion . $returnUrl . $cancelUrl . $orderId .
            $reference . $firstName . $lastName . $email . $description . $apiKey . $responseUrl;
        $pkeyid = openssl_get_privatekey($privateKey);
        $signResult = openssl_sign($dataString, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
        $signatureEncoded = base64_encode($signature);


        return '<form id="directpay_payment_form" method="POST" action="' . $gatewayUrl . '">
                    <input type="hidden" name="_type" id="_type" value="ONE_TIME">
                    <input type="hidden" name="_mId" id="_mId" value="' . $merchantId . '">
                    <input type="hidden" name="_amount" id="_amount" value="' . $amount . '">
                    <input type="hidden" name="_firstName" id="_firstName" value="' . $firstName . '">
                    <input type="hidden" name="_lastName" id="_lastName" value="' . $lastName . '">
                    <input type="hidden" name="_email" id="_email" value="' . $email . '">
                    <input type="hidden" name="_reference" id="_reference" value="' . $reference . '">
                    <input type="hidden" name="_description" id="_description" value="' . $description . '">
                    <input type="hidden" name="_returnUrl" id="_returnUrl" value="' . $returnUrl . '">
                    <input type="hidden" name="_cancelUrl" id="_cancelUrl" value="' . $cancelUrl . '">
                    <input type="hidden" name="_responseUrl" id="_responseUrl" value="' . $responseUrl . '">
                    <input type="hidden" name="_currency" id="_currency" value="' . $currencyCode . '">
                    <input type="hidden" name="_orderId" id="_orderId" value="' . $orderId . '">
                    <input type="hidden" name="_pluginVersion" id="_pluginVersion" value="' . $pluginVersion . '">
                    <input type="hidden" name="_pluginName" id="_pluginName" value="' . $pluginName . '">
                    <input type="hidden" name="api_key" id="api_key" value="' . $apiKey . '">
                    <input type="hidden" name="signature" id="signature" value="' . $signatureEncoded . '">
                
                    <img src="https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png" alt="DirectPay_payment" onclick="document.getElementById(\'directpay_payment_form\').submit();" max-width="20%" />
                </form>
                ';

    }

//    $dataString = base64_encode(json_encode($requestData));
//    $signature = 'hmac ' . hash_hmac('sha256', $dataString, $secret);
//
//    // Call API and get payment session URL
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

//    $response = curl_exec($ch);
//    if (curl_error($ch)) {
//        printToLog('Unable to fetch payment link: ' . curl_errno($ch) . ' - ' . curl_error($ch));
//    }
//    curl_close($ch);
//
//    $getSession = json_decode($response);
//
//    if ($getSession->status == 200) {
//        $link = $getSession->data->link;
//        $paymentRedirect = $link;
//    } else {
//        $paymentRedirect = $returnUrl;
//    }
//
//    // Redirect to Payment Gateway
//    return '<form id="directpay_payment_form" method="GET" action="' . $paymentRedirect . '">
//                <img src="https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png" alt="DirectPay_payment" width="20%" min-width="200px" onclick="document.getElementById(\'directpay_payment_form\').submit();" />
//                <input type="submit" value="' . $langPayNow . '">
//            </form>';



}

function convertInterval($interval)
{
    switch ($interval) {
        case 'MONTHLY':
            return 3;
        case 'BIANNUAL':
            return 1;
        case 'YEARLY':
            return 0;
        case 'QUARTERLY':
            return 2;
        case 'WEEKLY':
            return 4;
        case 'DAILY':
            return 5;
        case 'BIENNIALLY':
            return 6;
        case 'TRIENNIALLY':
            return 7;
        default:
            return $interval;
    }
}