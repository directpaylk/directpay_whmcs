<?php

use WHMCS\Database\Capsule;
use WHMCS\Carbon;

require 'helper_classes.php';

const INT_ONETIME = "ONE TIME";
const INT_MONTHLY = "MONTHLY";
const INT_QUARTERLY = "QUARTERLY";
const INT_BIANNUALLY = "SEMI-ANNUALLY";
const INT_ANNUALLY = "ANNUALLY";
const INT_BIENNIALLY = "BIENNIALLY";
const INT_TRIENNIALLY = "TRIENNIALLY";

function debugLog($message, $key = '')
{
    global $gatewayParams;
    
    // Check if debug logging is enabled
    $debugLoggingEnabled = isset($gatewayParams['debugLogging']) && $gatewayParams['debugLogging'] == 'on';
    $sandboxMode = isset($gatewayParams['sandBox']) && $gatewayParams['sandBox'] == 'on';
    
    // Log to file if debug logging is enabled OR in sandbox mode
    if ($debugLoggingEnabled || $sandboxMode) {
        writeDirectPayLog('DEBUG', $message, ['key' => $key]);
    }
    
    // Show on screen only in sandbox mode (compact display)
    if ($sandboxMode) {
        $escapedMessage = htmlspecialchars($message);
        $escapedKey = htmlspecialchars($key);
        echo "
            <div style='padding: 1px 3px; 
                          margin: 0.5px; 
                          display: inline-block; 
                          background-color: #f8f9fa; 
                          border: 1px solid #e9ecef;
                          font-size: 12px;
                          font-family: monospace;
                          border-radius: 1px;
                          max-width: 100%;
                          word-break: break-all;
                          line-height: 1.2;'
                          ><span style='color: #495057; font-weight: bold;'>[$escapedKey]:</span> $escapedMessage
            </div>
        ";
    }
}


/**
 * Write detailed logs to file for debugging and support
 */
function writeDirectPayLog($level, $message, $context = [])
{
    global $gatewayParams;
    
    // Check if logging should be enabled
    $debugLoggingEnabled = isset($gatewayParams['debugLogging']) && $gatewayParams['debugLogging'] == 'on';
    $sandboxMode = isset($gatewayParams['sandBox']) && $gatewayParams['sandBox'] == 'on';
    
    // Always log errors and payment events, but only log debug/info if enabled
    $shouldLog = in_array($level, ['ERROR', 'PAYMENT', 'API']) || $debugLoggingEnabled || $sandboxMode;
    
    if (!$shouldLog) {
        return;
    }
    
    $logFile = __DIR__ . '/../../logs/directpay.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_PRETTY_PRINT) : '';
    $logMessage = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Clear old logs if retention is configured
    if (isset($gatewayParams['logRetention']) && $gatewayParams['logRetention'] > 0) {
        clearOldLogs($gatewayParams['logRetention']);
    }
}

/**
 * Log payment processing details
 */
function logPaymentProcess($step, $data, $invoiceId = null)
{
    $context = [
        'step' => $step,
        'invoice_id' => $invoiceId,
        'timestamp' => time(),
        'data' => $data
    ];
    writeDirectPayLog('PAYMENT', "Payment Process: $step", $context);
}

/**
 * Log API requests and responses
 */
function logApiCall($endpoint, $requestData, $responseData, $status = null)
{
    $context = [
        'endpoint' => $endpoint,
        'request' => $requestData,
        'response' => $responseData,
        'status' => $status
    ];
    writeDirectPayLog('API', "API Call to $endpoint", $context);
}

/**
 * Log errors with detailed context
 */
function logDirectPayError($error, $context = [])
{
    $context['error'] = $error;
    $context['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
    writeDirectPayLog('ERROR', "DirectPay Error: $error", $context);
}

/**
 * Get recent logs for support
 */
function getDirectPayLogs($lines = 100)
{
    $logFile = __DIR__ . '/../../logs/directpay.log';
    
    if (!file_exists($logFile)) {
        return "No log file found at: $logFile";
    }
    
    $logs = file($logFile);
    return implode('', array_slice($logs, -$lines));
}

/**
 * Clear old logs to prevent disk space issues
 */
function clearOldLogs($days = 30)
{
    $logFile = __DIR__ . '/../../logs/directpay.log';
    
    if (file_exists($logFile)) {
        $fileTime = filemtime($logFile);
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        if ($fileTime < $cutoffTime) {
            file_put_contents($logFile, '');
            writeDirectPayLog('INFO', "Logs cleared after $days days", []);
        }
    }
}

function getRecurringInfoByInvoiceId($invoiceId)
{
    $recurringInfo = [
        "recurring" => false,
        "start_date" => date("Y-m-d"),
        "end_date" => "3000-12-31",
        "recurring_amount" => 0.00,
        "interval" => "",
        "invalid" => false,
        "details" => ""
    ];

    $mainProductsList = ['HOSTING', 'DOMAINREGISTER', 'DOMAINTRANSFER', 'DOMAINRENEW', 'ITEM', 'DOMAIN', 'INVOICE'];

    try {
        $items = Capsule::table('tblinvoiceitems')
            ->where('invoiceid', '=', $invoiceId)
            ->get();

        debugLog(count($items), '$items');
        debugLog(json_encode($items), '$items');

        $mainProducts = 0;
        $recurringTotal = 0.00;
        $containInvalidProduct = false;
        $invalidDescription = "";
        $mainProduct = null;
        $mainProductInterval = null;
        $multipleInterval = false;
        $isRecurring = false;

        if (!empty($items)) {
            foreach ($items as $item) {
                $paymentItem = getPaymentItemByInvoiceItem($item);

                debugLog($paymentItem->interval, 'IN FOREACH INTERVAL =====');
                debugLog(in_array(strtoupper($item->type), $mainProductsList), 'MAIN PRODUCT ? ');
                debugLog(json_encode($paymentItem), 'IN FOREACH ITEM');

                if (in_array(strtoupper($item->type), $mainProductsList)) {

                    /// Recurring is true if main product is a recurring
                    if ($paymentItem->isRecurring) {
                        $mainProduct = $paymentItem;
                        $mainProducts++;
                        $isRecurring = true;

                        /// Set main product interval if already not set
                        /// Else if already set, check intervals for multiple values
                        /// Multiple intervals cannot proceed with payment
                        if (is_null($mainProductInterval)) {
                            $mainProductInterval = $paymentItem->interval;
                        } else {
                            if ($mainProductInterval != $paymentItem->interval) {
                                $multipleInterval = true;
                            }
                        }
                    }
                }

                if ($paymentItem->isRecurring) {
                    // If any of recurring product is invalid, cannot proceed payment
                    if ($paymentItem->invalidItem) {
                        $containInvalidProduct = true;
                        $invalidDescription = $paymentItem->invalidDescription;
                        break;
                    } else {
                        // Sum of valid product recurring values
//                    debugLog($recurringTotal, 'recurring total');
//                    debugLog($paymentItem->amount, 'add amount');

                        debugLog("Add $paymentItem->amount to $recurringTotal", 'Update Recurring Total');
                        $recurringTotal += $paymentItem->amount;
                    }
                } else {
                    debugLog('NOT RECURRING', 'NOT RECURRING');
                }

            }

            debugLog($recurringTotal, '$recurringTotal');
            debugLog($containInvalidProduct, '$containInvalidProduct');
            debugLog($mainProducts, '$mainProducts');

            if (!$containInvalidProduct) {
                if (($mainProducts == 1) || (($mainProducts > 1) && !$multipleInterval)) {
                    $recurringInfo["recurring"] = $isRecurring;
                    $recurringInfo["end_date"] = $mainProduct->endDate;
                    $recurringInfo["start_date"] = $mainProduct->startDate;
                    $recurringInfo["recurring_amount"] = number_format($recurringTotal, 2, '.', '');
                    $recurringInfo["interval"] = $mainProduct->interval;
                    $recurringInfo["invalid"] = $mainProduct->invalidItem;
                    $recurringInfo["details"] = $mainProduct->invalidDescription;
                } elseif ($mainProducts > 1) {
                    // Cannot proceed as there are more than one recurring item
                    $recurringInfo["invalid"] = true;
                    $recurringInfo["details"] = "More than one recurring products are available in the invoice";
                }
            } else {
                $recurringInfo["invalid"] = true;
                $recurringInfo["details"] = $invalidDescription;
            }
        }
    } catch (Exception $exception) {
        debugLog('[getRecurringItem] | EXCEPTION: ' . $exception->getMessage(), 'EXCEPTION');
        debugLog('[getRecurringItem] | EXCEPTION: ' . $exception->getLine(), 'EXCEPTION');
    }

    return $recurringInfo;
}

function getRecurringInfo($interval, $cycles)
{
    debugLog($interval, '$interval');
    debugLog($cycles, '$cycles');

    $recurringInfo = new RecurringInfoItem();

    $date = new DateTime('now');
    $frequency = $interval;
    $months = 1;

    switch ($interval) {
        case "MONTHLY":
            $months = 1;
            $frequency = "MONTHLY";
            break;
        case "QUARTERLY":
            $months = 3;
            $frequency = "QUARTERLY";
            break;
        case "SEMI-ANNUALLY":
            $months = 6;
            $frequency = "BIANNUAL";
            break;
        case "ANNUALLY":
            $months = 12;
            $frequency = "YEARLY";
            break;
        default:
            $recurringInfo->invalidItem = true;
            $recurringInfo->invalidDescription = "Cannot accept " . ucfirst($interval) . " payments. Please try products with a valid recurring frequency.";
    }

    $totalMonths = $months * $cycles;

    $recurringInfo->endDate = $date->modify("+$totalMonths month")->format('Y-m-d');
    $recurringInfo->interval = $frequency;

    if ($cycles == 0) {
        $recurringInfo->endDate = date("Y-m-d", strtotime("3000-1-1")); // Forever
        $recurringInfo->dontExpire = true;
    }

    return $recurringInfo;
}

function getStartDate($interval, $day) {
    $startDate = Carbon::now();
    $months = 0;

    switch ($interval) {
        case "MONTHLY":
            $months = 1;
            break;
        case "QUARTERLY":
            $months = 3;
            break;
        case "BIANNUAL":
            $months = 6;
            break;
        case "YEARLY":
            $months = 12;
            break;
    }

    $startDate->addMonths($months);

    $date = $startDate->format('Y-m');

    return "$date-" . str_pad($day, 2, '0', STR_PAD_LEFT);
}

function getPaymentItemByInvoiceItem($invoiceItem)
{
    $invoiceItemRelId = $invoiceItem->relid;
    $invoiceType = strtoupper($invoiceItem->type);

    debugLog($invoiceType, '$invoiceType');

    $paymentItem = new PaymentItem();

    try {
        switch ($invoiceType) {
            case 'HOSTING':
                $hostingItem = Capsule::table('tblhosting')->where('id', '=', $invoiceItemRelId)->first();

                if (!$hostingItem) {
                    throw new Exception("Hosting Item not found");
                }

                $interval = strtoupper($hostingItem->billingcycle);

                if (($interval != "") && ($interval != "ONE TIME") && ($interval != "FREE ACCOUNT")) {
                    $packageId = $hostingItem->packageid;
                    $product = Capsule::table('tblproducts')->where('id', '=', $packageId)->first();
                    $recurringCycles = $product->recurringcycles;
                    $prorataBilling = $product->proratabilling;

                    $recurringItem = getRecurringInfo($interval, $recurringCycles);

                    debugLog($prorataBilling == 1, '$prorataBilling');

                    $startDate = Carbon::now()->format('Y-m-d');
                    $endDate = $recurringItem->endDate;

                    if (!$recurringItem->invalidItem) {
                        if ($prorataBilling == 1) {
                            /// Set preferred date as start date because do_initial_payment is always true
                            $startDate = getStartDate($recurringItem->interval, $product->proratadate);
                        }

                        debugLog($product->autoterminatedays, 'autoTerminateDays');

                        if (($product->autoterminatedays != 0) && ($recurringCycles == 0)) {
                            $endDate = Carbon::now()->addDays($product->autoterminatedays)->format('Y-m-d');
                        }
                    }

                    debugLog($startDate, '$startDate');
                    debugLog($endDate, '$endDate');

                    $paymentItem->isRecurring = true;
                    $paymentItem->dontExpire = $recurringItem->dontExpire;
                    $paymentItem->interval = $recurringItem->interval;
                    $paymentItem->endDate = $endDate;
                    $paymentItem->startDate = $startDate;
                    $paymentItem->invalidItem = $recurringItem->invalidItem;
                    $paymentItem->invalidDescription = $recurringItem->invalidDescription;
                    $paymentItem->amount = $hostingItem->amount;
                }

                break;
            case 'DOMAINREGISTER':
//            case 'DOMAINTRANSFER':
//            case 'DOMAINRENEW':
//            case 'DOMAIN':
                $domainItem = Capsule::table('tbldomains')->where('id', '=', $invoiceItemRelId)->first();

                if (!$domainItem) {
                    throw new Exception("Domain Item not found");
                }

                $registrationPeriod = (int)$domainItem->registrationperiod;

                $firstPaymentAmount = $domainItem->firstpaymentamount;
                $recurringAmount = $domainItem->recurringamount;
                $interval = "";

                if ($registrationPeriod == 1) {
                    $interval = INT_ANNUALLY;
                } else {
                    $paymentItem->invalidItem = true;
                    $paymentItem->invalidDescription = "Cannot proceed with selected registration period. Try with Annual registration period.";
                }

                if ($interval != "") {
                    $recurringItem = getRecurringInfo($interval, 0);

                    $paymentItem->isRecurring = true;
                    $paymentItem->dontExpire = true;
                    $paymentItem->interval = $recurringItem->interval;
                    $paymentItem->endDate = $recurringItem->endDate;
                    $paymentItem->invalidItem = $recurringItem->invalidItem;
                    $paymentItem->invalidDescription = $recurringItem->invalidDescription;
                    $paymentItem->amount = $recurringAmount;
                }

                break;
            case 'ADDON':
                $addonItem = Capsule::table('tblhostingaddons')->where('id', '=', $invoiceItemRelId)->first();

                if (!$addonItem) {
                    throw new Exception("Addon Item not found");
                }

                $interval = strtoupper($addonItem->billingcycle);

                if (($interval != "") && ($interval != INT_ONETIME) && ($interval != 'FREE ACCOUNT')) {
                    $recurringItem = getRecurringInfo($interval, 0);

                    $paymentItem->isRecurring = true;
                    $paymentItem->dontExpire = $recurringItem->dontExpire;
                    $paymentItem->interval = $recurringItem->interval;
                    $paymentItem->endDate = $recurringItem->endDate;
                    $paymentItem->invalidItem = $recurringItem->invalidItem;
                    $paymentItem->invalidDescription = $recurringItem->invalidDescription;
                    $paymentItem->amount = $addonItem->recurring;
                }

                break;
            case 'ITEM':
                $item = Capsule::table('tblbillableitems')->where('id', '=', $invoiceItemRelId)->first();

                if (!$item) {
                    throw new Exception("Billable Item not found");
                }

                debugLog($item->invoiceaction, '$invoiceAction');

                if ($item->invoiceaction == 4) {
                    $itemRecur = $item->recur;
                    $itemRecurCycle = strtoupper($item->recurcycle);
                    $itemRecurFor = $item->recurfor;

                    if (!empty($itemRecurCycle)) {
                        $paymentItem->isRecurring = true;
                        $interval = "";

                        debugLog($itemRecur . " $itemRecurCycle for $itemRecurFor Times", 'Recur every');

                        if ($itemRecurCycle == 'YEARS') {
                            if ($itemRecur == 1) {
                                $interval = INT_ANNUALLY;
                            }
                        } else if ($itemRecurCycle == 'MONTHS') {
                            if ($itemRecur == 1) {
                                $interval = INT_MONTHLY;
                            } else if ($itemRecur == 3) {
                                $interval = INT_QUARTERLY;
                            } else if ($itemRecur == 6) {
                                $interval = INT_BIANNUALLY;
                            } else if ($itemRecur == 12) {
                                $interval = INT_ANNUALLY;
                            }
                        } else if ($itemRecurCycle == 'WEEKS') {
                            if ($itemRecur == 4) {
                                $interval = INT_MONTHLY;
                            } else if ($itemRecur == 12) {
                                $interval = INT_QUARTERLY;
                            } else if ($itemRecur == 24) {
                                $interval = INT_BIANNUALLY;
                            } else if ($itemRecur == 48) {
                                $interval = INT_ANNUALLY;
                            }
                        } else if ($itemRecurCycle == 'DAYS') {
                            if ($itemRecur == 30) {
                                $interval = INT_MONTHLY;
                            } else if ($itemRecur == 90) {
                                $interval = INT_QUARTERLY;
                            } else if ($itemRecur == 180) {
                                $interval = INT_BIANNUALLY;
                            } else if ($itemRecur == 365) {
                                $interval = INT_ANNUALLY;
                            }
                        }

                        if ($interval != "") {
                            $recurringItem = getRecurringInfo($interval, $itemRecurFor);
                            $paymentItem->isRecurring = true;
                            $paymentItem->dontExpire = $recurringItem->dontExpire;
                            $paymentItem->interval = $recurringItem->interval;
                            $paymentItem->endDate = $recurringItem->endDate;
                            $paymentItem->invalidItem = $recurringItem->invalidItem;
                            $paymentItem->invalidDescription = $recurringItem->invalidDescription;
                            $paymentItem->amount = $item->amount;
                        } else {
                            $paymentItem->invalidItem = true;
                            $paymentItem->invalidDescription = "Cannot proceed with selected recurring cycle. Please try with an acceptable recurring cycle.";
                        }
                    } else {
                        $paymentItem->invalidItem = true;
                        $paymentItem->invalidDescription = "Cannot proceed with selected recurring cycle. Recurring cycle should be valid for a recurring payment.";
                    }
                }

                break;
            case 'PROMOHOSTING':
                $hostingItem = Capsule::table('tblhosting')->where('id', '=', $invoiceItemRelId)->first();

                if (!$hostingItem) {
                    throw new Exception("Hosting details could not be found while looking up Promotion");
                }

                $promotion = Capsule::table('tblpromotions')->where('id', '=', $hostingItem->promoid)->first();

                if ($promotion->recurring == 1) {
                    $interval = strtoupper($hostingItem->billingcycle);

                    if (($interval != "") && ($interval != INT_ONETIME) && ($interval != 'FREE ACCOUNT')) {
                        $product = Capsule::table('tblproducts')->where('id', '=', $hostingItem->packageid)->first();
                        $recurringCycles = $product->recurringcycles;
                        $recurringItem = getRecurringInfo($interval, $recurringCycles);

                        $paymentItem->isRecurring = true;
                        $paymentItem->dontExpire = $promotion->recurfor == 0;
                        $paymentItem->interval = $recurringItem->interval;
                        $paymentItem->endDate = $recurringItem->endDate;
                        $paymentItem->invalidItem = $recurringItem->invalidItem;
                        $paymentItem->invalidDescription = $recurringItem->invalidDescription;
                        $paymentItem->amount = $invoiceItem->amount; // Negative amount hence it is a promotion
                    }
                }

                break;
            case 'PROMODOMAIN':
                $domainItem = Capsule::table('tbldomains')->where('id', '=', $invoiceItemRelId)->first();

                if (!$domainItem) {
                    throw new Exception("Domain details could not be found whiling looking up Promotion");
                }

                $promotion = Capsule::table('tblpromotions')->where('id', '=', $domainItem->promoid)->first();

                $registrationPeriod = (int)$domainItem->registrationperiod;

                if ($promotion->recurring) {
                    if ($registrationPeriod < 4) {
                        $recurringItem = getRecurringInfo('ANNUALLY', $registrationPeriod);

                        $paymentItem->isRecurring = true;
                        $paymentItem->dontExpire = $promotion->recurfor == 0;
                        $paymentItem->interval = $recurringItem->interval;
                        $paymentItem->endDate = $recurringItem->endDate;
                        $paymentItem->invalidItem = $recurringItem->invalidItem;
                        $paymentItem->invalidDescription = $recurringItem->invalidDescription;
                        $paymentItem->amount = $invoiceItem->amount;

                    }
                }

                break;
            case 'INVOICE':
                try {
                    $invoiceItems = Capsule::table('tblinvoiceitems')->where('invoiceid', '=', $invoiceItemRelId)->get();

                    debugLog(count($invoiceItems), '$invoiceItems in invoice');
                    debugLog(json_encode($invoiceItems), '$items list in invoice');

                    $inv_ContainInvalidProduct = false;
                    $inv_InvalidDescription = "";
                    $inv_interval = null;
                    $inv_isRecurring = false;
                    $inv_totalRecurring = 0.00;
                    $inv_endDate = '3000-12-31';

                    if (!empty($invoiceItems)) {
                        foreach ($invoiceItems as $item) {
                            $_paymentItem = getPaymentItemByInvoiceItem($item);

                            debugLog(json_encode($_paymentItem), 'Invoice id: ' . $item->invoiceid);

                            if ($_paymentItem->invalidItem) {
                                $inv_ContainInvalidProduct = true;
                                $inv_InvalidDescription = $_paymentItem->invalidDescription;
                                break;
                            }

                            if ($_paymentItem->isRecurring) {
                                $inv_isRecurring = true;

                                if (is_null($inv_interval)) {
                                    $inv_interval = $_paymentItem->interval;
                                } else {
                                    if ($_paymentItem->interval != $inv_interval) {
                                        $inv_ContainInvalidProduct = true;
                                        $inv_InvalidDescription = 'Cannot proceed mass payment, recurring frequencies do not match. Please try payment as individual invoices.';
                                        break;
                                    }
                                }

                                if ($_paymentItem->endDate) {
                                    $inv_endDate = $_paymentItem->endDate;
                                }

                                $inv_totalRecurring += $_paymentItem->amount;
                            }

                        }

                        $paymentItem->amount = $inv_totalRecurring;
                        $paymentItem->invalidItem = $inv_ContainInvalidProduct;
                        $paymentItem->invalidDescription = $inv_InvalidDescription;
                        $paymentItem->isRecurring = $inv_isRecurring;
                        $paymentItem->interval = $inv_interval;
                        $paymentItem->endDate = $inv_endDate;
                    }
                } catch (Exception $exception) {
                    debugLog('[getRecurringItem] | EXCEPTION: ' . $exception->getMessage(), 'EXCEPTION');
                    debugLog('[getRecurringItem] | EXCEPTION: ' . $exception->getLine(), 'EXCEPTION');
                }

                break;
            default:
                // invoiceItem types : "", "setup"
        }
    } catch (Exception $exception) {
        debugLog("[getPaymentItemByInvoiceItem] | EXCEPTION: " . $exception->getMessage(), 'EXCEPTION');
        debugLog("[getPaymentItemByInvoiceItem] | EXCEPTION: " . $exception->getLine(), 'EXCEPTION');
    }

    return $paymentItem;
}

function getTotalTaxAmount($invoiceId) {
    $tax = 0.00;
    $taxConfig = Capsule::table('tblconfiguration')
        ->where('setting', '=', 'TaxType')
        ->first();

    if ($taxConfig->value === "Exclusive"){
        $invoice = Capsule::table('tblinvoices')
            ->where('id', '=', $invoiceId)
            ->first();
        $tax = $invoice->tax + $invoice->tax2;
    }

    return $tax;
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
        default:
            return $interval;
    }
}