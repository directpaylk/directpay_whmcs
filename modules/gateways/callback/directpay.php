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
$postBody_raw = file_get_contents('php://input');
$postBody = json_decode(base64_decode($postBody_raw), true);

logActivity('PAYMENT RESPONSE - invoice_id: ' . $_GET['invoice']);
logActivity('PAYMENT RESPONSE - body: ' . $postBody_raw);

$headers = array();
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) <> 'HTTP_') {
        continue;
    }
    $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
    $headers[$header] = $value;
}

logActivity('PAYMENT RESPONSE - headers: ' . json_encode($headers));

$transactionType = $postBody["type"];
$orderId = $postBody["order_id"];
$transactionId = $postBody["transaction_id"];
$transactionStatus = isset($postBody["transaction"]) ? $postBody["transaction"]["status"] : "-";
$transactionDesc = isset($postBody["transaction"]) ? $postBody["transaction"]["description"] : "-";
$paymentAmount = isset($postBody["transaction"]) ? $postBody["transaction"]["amount"] : "0.00";
$paymentCurrency = isset($postBody["transaction"]) ? $postBody["transaction"]["currency"] : "LKR";
$invoiceId = $_GET['invoice'];

$success = false;
$responseValidation = '';
$zeroFee = "0";

$authHeaders = explode(' ', $headers['Authorization']);

if (count($authHeaders) == 2) {
    $hash = hash_hmac('sha256', $postBody_raw, $gatewayParams['secret']);
    if (strcmp($authHeaders[1], $hash) == 0) {
        $success = true;
        echo " Signature Verified. ";
    } else {
        $responseValidation = ' - Signature Verification Failed';
        echo " Signature Verification Failed. ";
    }
} else {
    $responseValidation = ' - Invalid Signature';
    echo " Invalid Signature. Headers: " . json_encode($headers) . " | Raw Headers: " . json_encode($_SERVER);
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
logTransaction($gatewayParams['name'], json_encode($postBody), "Invoice: " . $invoiceId . " | Transaction Status: " . $transactionStatus . $responseValidation);

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
            $zeroFee,
            $gatewayModuleName
        );

        echo " Invoice added successfully. InvoiceId: $invoiceId ";
    }
}

//header("Location: ".$gatewayParams['systemurl'].'viewinvoice.php?id='.$invoiceId);