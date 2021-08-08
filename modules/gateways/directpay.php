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
    // Gateway Configuration Parameters
    $secret = $params['secret'];
    $merchantId = $params['merchantId'];
    $testMode = $params['sandBox'];
    $notifyUrl = $params['notifyUrl'];
    $logoUrl = $params['logoUrl'];

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

    // API Connection Details
    if ($testMode == 'on') {
        $gatewayUrl = "https://test-gateway.directpay.lk/api/v3/create-session";
    } else {
        $gatewayUrl = "https://gateway.directpay.lk/api/v3/create-session";
    }

    $recurringItem = getRecurringInfoByInvoiceId($invoiceId);

    debugLog(json_encode($recurringItem), '$recurringItem');

    $htmlOutput = '';

    if ($recurringItem['invalid']) {
        $htmlOutput = "<img src='https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png' alt='DirectPay_payment' max-width='20%' /><br><p>{$recurringItem['details']} <span style='color:red;'>*</span></p>";
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

        if ($recurringItem['recurring']) {
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
        if (curl_error($ch)) {
            debugLog('Unable to fetch payment link: ' . curl_errno($ch) . ' - ' . curl_error($ch));
        }
        curl_close($ch);

        $getSession = json_decode($response);

        if ($getSession->status == 200) {
            $htmlOutput = '<form id="directpay_payment_form" method="GET" action="' . $getSession->data->link . '">
                <img style="cursor: pointer;" src="https://cdn.directpay.lk/live/gateway/dp_visa_master_logo.png" alt="DirectPay_payment" onclick="document.getElementById(\'directpay_payment_form\').submit();" max-width="20%" />
                <input type="submit" value="' . $langPayNow . '">
            </form>';
        } else {
            $htmlOutput = "Could not proceed the payment. Please try again. If this problem persists, please contact the merchant.<br>(ErrorCode WHM". date('ymdHis') . ")";
        }
    }

    return $htmlOutput;
}