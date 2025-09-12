<?php

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

$gatewayResult = [];

function saveSubscriptionForInvoice($invoiceId, $scheduleId)
{
    $hostingTotal = updateHostingItems($invoiceId, $scheduleId);
    $domainTotal = updateDomainItems($invoiceId, $scheduleId);
    $invoiceDetails = updateInvoiceItems($invoiceId, $scheduleId);

    return [
        'hosting' => $hostingTotal,
        'domain' => $domainTotal,
        'invoice' => $invoiceDetails
    ];

}

function updateHostingItems($invoiceId, $scheduleId)
{

    $itemCount = 0;

    $hostingItems = Capsule::table('tblinvoiceitems')
        ->where([
            ['invoiceid', '=', $invoiceId],
            ['type', '=', 'Hosting']
        ])
        ->get();

    foreach ($hostingItems as $item) {
        $itemCount++;

        Capsule::table('tblhosting')
            ->where([
                ['id', '=', $item->relid],
                ['billingcycle', '!=', 'One Time'],
            ])
            ->update(['subscriptionid' => $scheduleId]);

        try {
            Capsule::table('tblhostingaddons')
                ->where('hostingid', '=', $item->relid)
                ->update(['subscriptionid' => $scheduleId]);
        } catch (Exception $exception) {
            echo " Exception in addon subId update. ";
            debugLog('[tblhostingaddons] | EXCEPTION: ' . $exception->getMessage(), 'EXCEPTION');
            debugLog('[tblhostingaddons] | EXCEPTION: ' . $exception->getLine(), 'EXCEPTION');
        }
    }

    return $itemCount;
}

function updateDomainItems($invoiceId, $scheduleId)
{
    $itemCount = 0;

    $domainItems = Capsule::table('tblinvoiceitems')
        ->where('invoiceid', '=', $invoiceId)
        ->whereIn('type', ['Domain', 'DomainRegister', 'DomainTransfer'])
        ->get();

    foreach ($domainItems as $item) {
        $itemCount++;

        Capsule::table('tbldomains')
            ->where('id', '=', $item->relid)
            ->update(['subscriptionid' => $scheduleId]);
    }

    return $itemCount;
}

function updateInvoiceItems($invoiceId, $scheduleId)
{
    $invoiceDetails = [];
    $invoiceItems = Capsule::table('tblinvoiceitems')
        ->where([
            ['invoiceid', '=', $invoiceId],
            ['type', '=', 'Invoice']
        ])
        ->get();

    foreach ($invoiceItems as $item) {
        $invoiceDetails[$item->relid] = saveSubscriptionForInvoice($item->relid, $scheduleId);
    }

    return $invoiceDetails;
}

function getLatestInvoiceId($scheduleId, $invoiceId)
{
    $newInvoiceId = $invoiceId;

    $hostingItem = Capsule::table('tblhosting')
        ->where('subscriptionid', '=', $scheduleId)
        ->orderBy('id', 'DESC')
        ->first();

    if ($hostingItem) {
        $invoiceItem = Capsule::table('tblinvoiceitems')
            ->where('relid', '=', $hostingItem->id)
            ->where('type', '=', 'Hosting')
            ->first();

        if ($invoiceItem) {
            $newInvoiceId = $invoiceItem->invoiceid;
        } else {
            echo " Invoice item not found for schedule: $scheduleId. ";
        }
    } else {
        echo " Hosting item not found for schedule: $scheduleId. ";

        $domainItem = Capsule::table('tbldomains')
            ->where('subscriptionid', '=', $scheduleId)
            ->orderBy('id', 'DESC')
            ->first();

        if ($domainItem) {
            $invoiceItem = Capsule::table('tblinvoiceitems')
                ->where('relid', '=', $domainItem->id)
                ->whereIn('type', ['Domain', 'DomainRegister', 'DomainTransfer'])
                ->first();

            if ($invoiceItem) {
                $newInvoiceId = $invoiceItem->invoiceid;
            } else {
                echo " Invoice item not found for schedule: $scheduleId. ";
            }
        } else {
            echo " Domain item not found for schedule: $scheduleId. ";
        }
    }

    echo " Latest invoice: $newInvoiceId. ";

    return $newInvoiceId;
}


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

// Set global gateway params for logging
global $gatewayParams;

logActivity('PAYMENT RESPONSE - invoice_id: ' . $_GET['invoice']);
logActivity('PAYMENT RESPONSE - body: ' . $postBody_raw);

// Log detailed callback data
logPaymentProcess('CALLBACK_RECEIVED', [
    'raw_body' => $postBody_raw,
    'decoded_body' => $postBody,
    'invoice_id' => $_GET['invoice'] ?? 'unknown',
    'server_data' => $_SERVER
], $_GET['invoice'] ?? null);

$headers = array();
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) <> 'HTTP_') {
        continue;
    }
    $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
    $headers[$header] = $value;
}

logActivity('PAYMENT RESPONSE - headers: ' . json_encode($headers));

const RECURRING = 'RECURRING';

$transactionType = $postBody["type"];
$orderId = $postBody["order_id"];
$transactionId = $postBody["transaction_id"];
$transactionType = $postBody["type"];
$transactionStatus = isset($postBody["transaction"]) ? $postBody["transaction"]["status"] : "-";
$transactionDesc = isset($postBody["transaction"]) ? $postBody["transaction"]["description"] : "-";
$paymentAmount = isset($postBody["transaction"]) ? $postBody["transaction"]["amount"] : "0.00";
$paymentCurrency = isset($postBody["transaction"]) ? $postBody["transaction"]["currency"] : "LKR";
$scheduleId = isset($postBody["recurring"]) ? $postBody["recurring"]["id"] : "0";
$invoiceId = $_GET['invoice'];

$success = false;
$responseValidation = '';
$zeroFee = 0.00;

$authHeaders = explode(' ', $headers['Authorization']);

logPaymentProcess('SIGNATURE_VERIFICATION_START', [
    'auth_headers' => $authHeaders,
    'headers_count' => count($authHeaders),
    'invoice_id' => $invoiceId
], $invoiceId);

if (count($authHeaders) == 2) {
    $hash = hash_hmac('sha256', $postBody_raw, $gatewayParams['secret']);
    if (strcmp($authHeaders[1], $hash) == 0) {
        $success = true;
        echo " Signature Verified. ";
        
        logPaymentProcess('SIGNATURE_VERIFIED', [
            'signature_match' => true,
            'invoice_id' => $invoiceId
        ], $invoiceId);
    } else {
        $responseValidation = ' - Signature Verification Failed';
        echo " Signature Verification Failed. ";
        
        logDirectPayError('Signature Verification Failed', [
            'expected_hash' => $hash,
            'received_hash' => $authHeaders[1],
            'invoice_id' => $invoiceId
        ]);
    }
} else {
    $responseValidation = ' - Invalid Signature';
    echo " Invalid Signature. Headers: " . json_encode($headers) . " | Raw Headers: " . json_encode($_SERVER);
    
    logDirectPayError('Invalid Signature Format', [
        'auth_headers' => $authHeaders,
        'headers_count' => count($authHeaders),
        'all_headers' => $headers,
        'invoice_id' => $invoiceId
    ]);
}

if ($success) {
    if ($transactionType == RECURRING) {
        $itemExists = Capsule::table('tblhosting')
            ->where('subscriptionid', '=', $scheduleId)
            ->get();

        echo " ScheduleId: $scheduleId. ";

        if (sizeof($itemExists) > 0) {
//            logActivity('Recurring Subscription exists. Invoice ID: ' . $invoiceId);
            echo " Existing subscription. ";
            $invoiceId = getLatestInvoiceId($scheduleId, $invoiceId);
        } else {
            logActivity('New Recurring Subscription. Invoice ID: ' . $invoiceId);
            echo " New subscription. ";
            $gatewayResult['item_data'] = saveSubscriptionForInvoice($invoiceId, $scheduleId);
        }
    }
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
    logPaymentProcess('CALLBACK_SUCCESS', [
        'transaction_status' => $transactionStatus,
        'transaction_id' => $transactionId,
        'payment_amount' => $paymentAmount,
        'invoice_id' => $invoiceId
    ], $invoiceId);
    
    if ($transactionStatus == 'SUCCESS') {
        logPaymentProcess('PAYMENT_SUCCESS', [
            'transaction_id' => $transactionId,
            'payment_amount' => $paymentAmount,
            'currency' => $paymentCurrency,
            'invoice_id' => $invoiceId
        ], $invoiceId);
        
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
            $gatewayParams['name']
        );

        echo " Invoice added successfully. InvoiceId: $invoiceId. ";
        
        logPaymentProcess('INVOICE_PAYMENT_ADDED', [
            'invoice_id' => $invoiceId,
            'transaction_id' => $transactionId,
            'amount' => $paymentAmount
        ], $invoiceId);
    } else {
        logPaymentProcess('PAYMENT_FAILED', [
            'transaction_status' => $transactionStatus,
            'transaction_id' => $transactionId,
            'invoice_id' => $invoiceId
        ], $invoiceId);
    }
} else {
    logPaymentProcess('CALLBACK_FAILED', [
        'reason' => 'Signature verification failed',
        'validation_error' => $responseValidation,
        'invoice_id' => $invoiceId
    ], $invoiceId);
}

echo json_encode($gatewayResult);

//header("Location: ".$gatewayParams['systemurl'].'viewinvoice.php?id='.$invoiceId);