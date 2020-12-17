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
        'hashKey' => array(
            'FriendlyName' => 'URL hash key',
            'Type' => 'textarea',
            'Default' => '',
            'Description' => 'Strong random string',
        ),
        'testMode' => array(
            'FriendlyName' => 'SandBox Mode',
            'Type' => 'yesno',
            'Description' => 'Enable sand box mode',
        ),
    );
}

function directpay_link($params)
{
    // Gateway Configuration Parameters
    $hashKey = $params['hashKey'];
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

    $pluginName = "DirectPay_WHMCS";
    $pluginVersion = 'v1.1';

    $getSession = getPaymentSessionURL($params);

    if ($getSession->status == 200) {
        do_log($getSession->status);
        do_log($getSession->data->link);
        $link = $getSession->data->link;
        $paymentRedirect = $link;
//        header("Location: $link");
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




    
