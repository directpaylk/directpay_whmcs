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

// Retrieve data returned in payment gateway callback
$postBody = (array)json_decode(file_get_contents('php://input'));

//logActivity('Message goes here', 0);
$transactionType = $postBody["type"];
$orderId = $postBody["orderId"];
$transactionId = $postBody["trnId"];
$transactionStatus = $postBody["status"];
$transactionDesc = $postBody["desc"];
$signature = $postBody["signature"];

logActivity('response: ' . json_encode($postBody));
logActivity('response: ' . json_encode($_POST));
logActivity('response: ' . json_encode($HTTP_RAW_POST_DATA));

$resA = json_decode($_POST, true);
foreach ($resA as $k => $v) {
    logActivity('response: ' . $k . ' => ' . $v);
}
logActivity($transactionType);
logActivity($orderId);
logActivity($transactionId);
logActivity($transactionStatus);
logActivity($transactionDesc);
logActivity($signature);

$dataString = $transactionType .
    $orderId .
    $transactionId .
    $transactionStatus .
    $transactionDesc;

$pubKeyid = openssl_get_publickey($gatewayParams['publicKey']);
$signatureVerify = openssl_verify($dataString, base64_decode($signature), $pubKeyid, OPENSSL_ALGO_SHA256);

if ($signatureVerify == 1) {
    $success = true;
} else {
    $success = false;
}

$invoiceId = $_GET['invoice'];
$paymentAmount = $_GET['amount'];
logActivity($invoiceId);
logActivity($paymentAmount);

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
// checkCbTransID($transactionId);



/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array. In the case of an
 * array it will be
 *
 * @param string $gatewayName        Display label
 * @param string|array $debugData    Data to log
 * @param string $transactionStatus  Status
 */
logTransaction($gatewayParams['name'], $_POST, $transactionStatus);

if ($success) {
    if($transactionStatus == 'SUCCESS') {
        /**
         * Add Invoice Payment.
         *
         * Applies a payment transaction entry to the given invoice ID.
         *
         * @param int $invoiceId         Invoice ID
         * @param string $transactionId  Transaction ID
         * @param float $paymentAmount   Amount paid (defaults to full balance)
         * @param float $paymentFee      Payment fee (optional)
         * @param string $gatewayModule  Gateway module name
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
