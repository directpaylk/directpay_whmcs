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

    $pluginName = "WHMCS";
    $pluginVersion = "_v1.0";

    $orderId = 'WH' . $invoiceId . date("ymdHis");

    $reference = $orderId;

//    $responseUrl = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php?invoice=' . $invoiceId;
//    $responseUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/modules/gateways/callback/' . $moduleName . '.php?invoice=' . $invoiceId;
    $responseUrl = $notifyUrl . '?invoice=' . $invoiceId . '&amount=' . $amount;

    printToLog($responseUrl);

    // API Connection Details
    if ($testMode == 'on') {
        $gatewayUrl = "https://testpay.directpay.lk/";
    } else {
        $gatewayUrl = "https://pay.directpay.lk/";
    }

    printToLog("Test Mode: " . ($testMode ? "yes" : "no"));
    printToLog($gatewayUrl);

    $redirectForm = '';
    $signature = '';

    $mainProductOfRecurring = getRecurringItem($invoiceId);

    $redirectFormData = array();
    $redirectFormData["_mId"] = $merchantId;
    $redirectFormData["_firstName"] = $firstName;
    $redirectFormData["_lastName"] = $lastName;
    $redirectFormData["_email"] = $email;
    $redirectFormData["_reference"] = $reference;
    $redirectFormData["_description"] = $description;
    $redirectFormData["_returnUrl"] = $returnUrl;
    $redirectFormData["_cancelUrl"] = $cancelUrl;
    $redirectFormData["_responseUrl"] = $responseUrl;
    $redirectFormData["_currency"] = $currencyCode;
    $redirectFormData["_orderId"] = $orderId;
    $redirectFormData["_pluginVersion"] = $pluginVersion;
    $redirectFormData["_pluginName"] = $pluginName;
    $redirectFormData["api_key"] = $apiKey;

    // Set post values
    if ($mainProductOfRecurring != null) {

        $priceResult = getPriceDetails($invoiceId, $mainProductOfRecurring);

        $endDate = $mainProductOfRecurring->_endDate;
        $interval = convertInterval($mainProductOfRecurring->_interval);
        $initialAmount = $priceResult->_startupTotal ? $priceResult->_startupTotal : $amount;

        $dataString = $merchantId . $initialAmount . $currencyCode . $pluginName . $pluginVersion . $returnUrl . $cancelUrl . $orderId .
            $reference . $firstName . $lastName . $email . $description . $apiKey . $responseUrl . date("Y-m-d") . $endDate .
            $interval . "1";

        $redirectFormData["_type"] = "RECURRING";
        $redirectFormData["_startDate"] = date("Y-m-d");
        $redirectFormData["_endDate"] = $endDate;
        $redirectFormData["_interval"] = $interval;
        $redirectFormData["_doFirstPayment"] = "1";
        $redirectFormData["_recurringAmount"] = $amount;
        $redirectFormData["_amount"] = $initialAmount;

    } else {

        $dataString = $merchantId . $amount . $currencyCode . $pluginName . $pluginVersion . $returnUrl . $cancelUrl . $orderId .
            $reference . $firstName . $lastName . $email . $description . $apiKey . $responseUrl;

        $redirectFormData["_type"] = "ONE_TIME";
        $redirectFormData["_amount"] = $amount;

    }

    $pkeyid = openssl_get_privatekey($privateKey);
    $signResult = openssl_sign($dataString, $signature, $pkeyid, OPENSSL_ALGO_SHA256);
    $signatureEncoded = base64_encode($signature);

    $redirectFormData["signature"] = $signatureEncoded;

    $redirectForm .= '<form id="directpay_payment_form" method="POST" action="' . $gatewayUrl . '">';
    foreach ($redirectFormData as $key => $value) {
        $redirectForm .= '<input type="hidden" name="' . $key . '" id="' . $key . '" value="' . $value . '">';
    }
    $redirectForm .= '<img style="cursor: pointer;" src="https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png" alt="DirectPay_payment" onclick="document.getElementById(\'directpay_payment_form\').submit();" max-width="20%" />
            <input type="submit" value="' . $langPayNow . '">
            </form>';

    return $redirectForm;
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