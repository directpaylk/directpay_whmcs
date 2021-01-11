<?php

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}
logActivity("Got Response");
logActivity(json_encode($_SERVER));

$headers = array();
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) <> 'HTTP_') {
        continue;
    }
    $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
    $headers[$header] = $value;
}


// TODO Remove these ->> BEGIN
/*
 * HEADERS - Postman
 *
 * */

//Array
//(
//    [Content-Type] => text/plain
//    [User-Agent] => PostmanRuntime/7.26.8
//    [Host] => whmcstest.directpay.lk
//    [Cookie] => WHMCSy551iLvnhYt7=adcabb5949c5e2b9ab53ce3a82da4cef
//    [Content-Length] => 880
//    [Connection] => keep-alive
//    [Accept-Encoding] => gzip, deflate, br
//    [Postman-Token] => 05f18e7e-0525-421f-9cf2-d0e32ef93119
//    [Accept] => */*
//)

/*
 * Headers - Gateway
 *
 * */

//Array
//(
//    [Content-Type] => text/plain
//    [User-Agent] => GuzzleHttp/7
//    [Host] => whmcstest.directpay.lk
//    [Authorization] => hmac 10f40642743f115460b6c4afce9a44ae0c4915560b4d566a74a0b28f8ef5f861
//)

print_r($headers);

//{
//    "channel": "MASTERCARD",
//  "type": "ONE_TIME",
//  "order_id": "D0217212",
//  "transaction_id": "101036",
//  "status": "SUCCESS",
//  "card_id": null,
//  "description": null,
//  "card_mask": "511111xxxxxx1118",
//  "customer": {
//    "name": "testF testL",
//    "email": "test4@admin.com",
//    "mobile": "0767664928"
//  },
//  "transaction": {
//    "id": "101036",
//    "status": "SUCCESS",
//    "description": "Approved",
//    "message": "SUCCESS",
//    "amount": "3.00",
//    "currency": "LKR"
//  }
//}


// TODO Remove these ->> END


// Retrieve data returned in payment gateway callback
$postBody_raw = file_get_contents('php://input');
$postBody = json_decode(base64_decode($postBody_raw), true);

logActivity('PAYMENT RESPONSE - headers: ' . json_encode($headers));
logActivity('PAYMENT RESPONSE - body: ' . $postBody_raw);
logActivity('PAYMENT RESPONSE - invoice_id: ' . $_GET['invoice']);

//logActivity('Message goes here', 0);
$transactionType = $postBody["type"];
$orderId = $postBody["order_id"];
$transactionId = $postBody["transaction_id"];
$transactionStatus = isset($postBody["transaction"]) ? $postBody["transaction"]["status"] : "-";
$transactionDesc = isset($postBody["transaction"]) ? $postBody["transaction"]["description"] : "-";
$paymentAmount = isset($postBody["transaction"]) ? $postBody["transaction"]["amount"] : "0.00";
$paymentCurrency = isset($postBody["transaction"]) ? $postBody["transaction"]["currency"] : "LKR";
$invoiceId = $_GET['invoice'];

logActivity($invoiceId);
logActivity($transactionType);
logActivity($orderId);
logActivity($transactionId);
logActivity($transactionStatus);
logActivity($transactionDesc);
logActivity($paymentAmount);
logActivity($paymentCurrency);

$success = false;
$authHeaders = explode(' ', $headers['Authorization']);

if (count($authHeaders) == 2) {
    $hash = hash_hmac('sha256', $postBody, $gatewayParams['secret']);
    if (strcmp($authHeaders[1], $hash) == 0) {
        $success = true;
        logTransaction(
            $gatewayParams['name'],
            'Invoice ID: ' . $invoiceId,
            'Signature Verification Successful'
        );

    } else {
        logTransaction(
            $gatewayParams['name'],
            'Invoice ID: ' . $invoiceId,
            'Signature Verification Failed'
        );
    }
} else {
    logTransaction(
        $gatewayParams['name'],
        'Invoice ID: ' . $invoiceId,
        'Invalid Signature'
    );
}

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

/**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */
checkCbTransID($transactionId);


/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName Display label
 * @param string|array $debugData Data to log
 * @param string $transactionStatus Status
 */
logTransaction($gatewayParams['name'], json_encode($postBody), $transactionStatus);

if ($success) {
    if ($transactionStatus == 'SUCCESS') {
        /**
         * Add Invoice Payment.
         *
         * Applies a payment transaction entry to the given invoice ID.
         *
         * @param int $invoiceId Invoice ID
         * @param string $transactionId Transaction ID
         * @param float $paymentAmount Amount paid (defaults to full balance)
         * @param float $paymentFee Payment fee (optional)
         * @param string $gatewayModule Gateway module name
         */
        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $paymentAmount,
            0.00,
            $gatewayModuleName
        );
    }
}

//header("Location: ".$gatewayParams['systemurl'].'viewinvoice.php?id='.$invoiceId);
