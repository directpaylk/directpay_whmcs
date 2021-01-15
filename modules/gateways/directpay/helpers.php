<?php

use WHMCS\Database\Capsule;

const INT_ONETIME = "ONE TIME";
const INT_MONTHLY = "MONTHLY";
const INT_QUARTERLY = "QUARTERLY";
const INT_BIANNUALLY = "SEMI-ANNUALLY";
const INT_ANNUALLY = "ANNUALLY";
const INT_BIENNIALLY = "BIENNIALLY";
const INT_TRIENNIALLY = "TRIENNIALLY";


class DirectPayPaymentItem
{

    public $_invoiceId = "";
    public $_isRecurring = false;
    public $_dontExpire = false;
    public $_interval = "";
    public $_endDate = "";
    public $_paymentFee = 0.0;
    public $_amount = 0.0;
    public $_ambiguous = false;
    public $_typeUnknown = false;

    function getPrice()
    {
        if ($this->_isRecurring) {
            return $this->_amount + $this->_paymentFee;
        } else {
            return $this->_amount;
        }
    }
}


class PriceData
{
    public $_startupTotal = 0.0;
    public $_recurringTotal = 0.0;

    function __construct($startup, $recurring)
    {
        $this->_startupTotal = $startup;
        $this->_recurringTotal = $recurring;
    }
}

function printToLog($message)
{
    if (false) {
        echo "
            <div>
                <p
                    style=' padding: 10px; 
                            margin: 5px; 
                            display: inline-block; 
                            background-color: white; 
                            border: 2px solid red;
                            font-weight: bold;
                            letter-spacing: 1px;
                            border-radius: 5px;'
                >
                    $message
                </p>
            </div>
        ";
    }

}

function getTaxByInvoice($id)
{
    $config = Capsule::table('tblconfiguration')->where('setting', '=', 'TaxType')->first();

    if ($config->value === "Exclusive") {
        $invoice = Capsule::table('tblinvoices')->where('id', '=', $id)->first();

        return $invoice->tax + $invoice->tax2;
    }
    return 0;
}

function getPriceDetails($invoiceId, $mainProduct)
{
    $startupFeeTotal = 0.0;
    $recurringTotal = 0.0;

    $invoiceItems = Capsule::table('tblinvoiceitems')->where('invoiceid', '=', $invoiceId)->get();
    foreach ($invoiceItems as $item) {
        $id = $item->id;

        $paymentItem = getItemByInvoiceId($id);

        if (!$paymentItem->_ambiguous && $paymentItem->_isRecurring && !$paymentItem->_typeUnknown) {

            if (($mainProduct->_interval == $paymentItem->_interval) && ($mainProduct->_endDate == $paymentItem->_endDate)) {
                $startupFeeTotal += $paymentItem->_paymentFee;
                $recurringTotal += $paymentItem->_amount;
            }
        } else if (!$paymentItem->_typeUnknown && !$paymentItem->_isRecurring) {
            $startupFeeTotal += $paymentItem->_amount;
        }
    }

    return new PriceData($startupFeeTotal, $recurringTotal);
}

function getRecurringInfo($interval, $recurringCycles)
{
    $recurringItem = array(
        'interval' => '',
        'endDate' => '',
        'dontExpire' => false
    );

    if ($interval === INT_MONTHLY) {

        $date = new DateTime('now');
        $date->modify("+$recurringCycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['endDate'] = $date;
        $recurringItem['interval'] = "MONTHLY";

    } else if ($interval === INT_QUARTERLY) {

        $_cycles = 3 * $recurringCycles;
        $date = new DateTime('now');
        $date->modify("+$_cycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['endDate'] = $date;
        $recurringItem['interval'] = "QUARTERLY";

    } else if ($interval === INT_BIANNUALLY) {

        $_cycles = 6 * $recurringCycles;
        $date = new DateTime('now');
        $date->modify("+$_cycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['endDate'] = $date;
        $recurringItem['interval'] = "BIANNUAL";

    } else if ($interval === INT_ANNUALLY) {

        $_cycles = 12 * $recurringCycles;
        $date = new DateTime('now');
        $date->modify("+$_cycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['endDate'] = $date;
        $recurringItem['interval'] = "YEARLY";

    } else if ($interval === INT_BIENNIALLY) {

        $_cycles = 24 * $recurringCycles;
        $date = new DateTime('now');
        $date->modify("+$_cycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['endDate'] = $date;
        $recurringItem['interval'] = "BIENNIALLY";

    } else if ($interval === INT_TRIENNIALLY) {

        $_cycles = 36 * $recurringCycles;
        $date = new DateTime('now');
        $date->modify("+$_cycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['endDate'] = $date;
        $recurringItem['interval'] = "TRIENNIALLY";

    }

    if ($recurringCycles == 0) {
        $recurringItem['endDate'] = date("Y-m-d", strtotime("3000-1-1")); // Forever
        $recurringItem['dontExpire'] = true;
    }

    return $recurringItem;
}

function getItemByInvoiceId($itemId)
{
    $invoiceItem = Capsule::table('tblinvoiceitems')->where('id', '=', $itemId)->first();

    $paymentItem = new DirectPayPaymentItem();

    $invoiceItemRelId = $invoiceItem->relid;
    $invoiceItemType = strtolower($invoiceItem->type);

    $paymentItem->_amount = (double)$invoiceItem->amount;
    $paymentItem->_invoiceId = $itemId;

    if ($invoiceItemType == "hosting") {

        $hostingItem = Capsule::table('tblhosting')->where('id', '=', $invoiceItemRelId)->first();

        if (!$hostingItem) {
            throw new Exception("Hosting Item not found");
        }

        $interval = strtoupper($hostingItem->billingcycle);

        if ($interval != "ONE TIME") {

            $packageId = $hostingItem->packageid;
            $product = Capsule::table('tblproducts')->where('id', '=', $packageId)->first();
            $recurringCycles = $product->recurringcycles;

            $recurringItem = getRecurringInfo($interval, $recurringCycles);

            $paymentItem->_isRecurring = true;
            $paymentItem->_dontExpire = $recurringItem['dontExpire'];
            $paymentItem->_interval = $recurringItem['interval'];
            $paymentItem->_endDate = $recurringItem['endDate'];
        }

    } else if ($invoiceItemType == "domainregister" || $invoiceItemType == "domaintransfer" || $invoiceItemType == "domainrenew") {
        $domainItem = Capsule::table('tbldomains')->where('id', '=', $invoiceItemRelId)->first();

        if (!$domainItem) {
            throw new Exception("Domain Item not found");
        }

        $registrationPeriod = (int)$domainItem->registrationperiod;

        $unitPrice = $paymentItem->_amount;
        $firstPaymentAmount = $domainItem->firstpaymentamount;
        $recurringAmount = $domainItem->recurringamount;
        $interval = "";

        if ($registrationPeriod == 1) {
            $interval = INT_ANNUALLY;
        } else if ($registrationPeriod == 2) {
            $interval = INT_BIENNIALLY;
        } else if ($registrationPeriod == 3) {
            $interval = INT_TRIENNIALLY;
        } else {
            $paymentItem->_ambiguous = true;
        }

        if ($interval != "") {
            $recurringItem = getRecurringInfo($interval, 0);

            $paymentItem->_isRecurring = true;
            $paymentItem->_dontExpire = true;
            $paymentItem->_interval = $recurringItem['interval'];
            $paymentItem->_endDate = $recurringItem['endDate'];

            if ($unitPrice != $firstPaymentAmount) {
                $paymentItem->_paymentFee = $unitPrice - $recurringAmount;
                $paymentItem->_amount = $recurringAmount;
            } else {
                $paymentItem->_paymentFee = $firstPaymentAmount - $recurringAmount;
                $paymentItem->_amount = $recurringAmount;
            }
        }

    } else if ($invoiceItemType == "addon") {
        $addonItem = Capsule::table('tblhostingaddons')->where('id', '=', $invoiceItemRelId)->first();

        if (!$addonItem) {
            throw new Exception("Addon Item not found");
        }

        $interval = strtoupper($addonItem->billingcycle);

        if ($interval != INT_ONETIME) {

            $recurringItem = getRecurringInfo($interval, 0);

            $paymentItem->_isRecurring = true;
            $paymentItem->_dontExpire = $recurringItem['dontExpire'];
            $paymentItem->_interval = $recurringItem['interval'];
            $paymentItem->_endDate = $recurringItem['endDate'];
            $paymentItem->_paymentFee = $addonItem->setupfee;
            $paymentItem->_amount = $addonItem->recurring;
        }
    } else if ($invoiceItemType == "item") {

        $item = Capsule::table('tblbillableitems')->where('id', '=', $invoiceItemRelId)->first();

        if (!$item) {
            throw new Exception("Billable Item not found");
        }

        if ($item->invoiceaction == 4) {

            $itemRecur = $item->recur;
            $itemRecurCycle = strtoupper($item->recurcycle);
            $itemRecurFor = $item->recurfor;

            if ($itemRecurCycle != 'WEEKS' && $itemRecurCycle != 'DAYS') {
                $paymentItem->_isRecurring = true;
                $interval = "";

                if ($itemRecurCycle == 'YEARS') {
                    if ($itemRecur == 1) {
                        $interval = INT_ANNUALLY;
                    } else if ($itemRecur == 2) {
                        $interval = INT_BIENNIALLY;
                    } else if ($itemRecur == 3) {
                        $interval = INT_TRIENNIALLY;
                    }
                } else if ($itemRecurCycle == 'MONTHS') {
                    if ($itemRecur == 1) {
                        $interval = INT_MONTHLY;
                    } else if ($itemRecur == 3) {
                        $interval = INT_QUARTERLY;
                    } else if ($itemRecur == 6) {
                        $interval = INT_BIANNUALLY;
                    }
                }

                if ($interval != "") {
                    $recurringItem = getRecurringInfo($interval, $itemRecurFor);
                    $paymentItem->_isRecurring = true;
                    $paymentItem->_dontExpire = false;
                    $paymentItem->_interval = $recurringItem['interval'];
                    $paymentItem->_endDate = $recurringItem['endDate'];
                } else {
                    $paymentItem->_ambiguous = true;
                }
            }
        }
    } else if ($invoiceItemType == "invoice") {
        $mainProduct = getRecurringItem($invoiceItemRelId);

        $taxAmt = getTaxByInvoice($invoiceItemRelId);
        $paymentItem->_amount = $paymentItem->_amount + $taxAmt;

        if ($mainProduct != null) {

            $invRes = getPriceDetails($invoiceItemRelId, $mainProduct);

            if (!$invRes) {
                throw new Exception("Invoice result is null");
            }

            $paymentItem->_isRecurring = true;
            $paymentItem->_dontExpire = $mainProduct->_dontExpire;
            $paymentItem->_interval = $mainProduct->_interval;
            $paymentItem->_endDate = $mainProduct->_endDate;
            $paymentItem->_paymentFee = $invRes->_startupTotal;
            $paymentItem->_amount = $invRes->_recurringTotal;
            $paymentItem->_paymentFee = $paymentItem->_paymentFee + $taxAmt;
        }
    } else if ($invoiceItemType == "promohosting") {
        $hostingItem = Capsule::table('tblhosting')->where('id', '=', $invoiceItemRelId)->first();

        if (!$hostingItem) {
            throw new Exception("Hosting details could not be found while looking up Promotion");
        }

        $promotion = Capsule::table('tblpromotions')->where('id', '=', $hostingItem->promoid)->first();

        if ($promotion->recurring == 1) {

            $interval = strtoupper($hostingItem->billingcycle);

            if ($interval != INT_ONETIME) {

                $product = Capsule::table('tblproducts')->where('id', '=', $hostingItem->packageid)->first();
                $recurringCycles = $product->recurringcycles;
                $recurringItem = getRecurringInfo($interval, $recurringCycles);

                $paymentItem->_isRecurring = true;
                $paymentItem->_dontExpire = $promotion->recurfor == 0;
                $paymentItem->_interval = $recurringItem['interval'];
                $paymentItem->_endDate = $recurringItem['endDate'];
            }
        }
    } else if ($invoiceItemType == "promodomain") {
        $domainItem = Capsule::table('tbldomains')->where('id', '=', $invoiceItemRelId)->first();

        if (!$domainItem) {
            throw new Exception("Domain details could not be found whiling looking up Promotion");
        }

        $promotion = Capsule::table('tblpromotions')->where('id', '=', $domainItem->promoid)->first();

        if ($promotion->recurring) {
            if ($domainItem->registrationperiod < 4) {
                $paymentItem->_isRecurring = true;
                $paymentItem->_interval = "YEARLY";

                $_cycles = $domainItem->registrationperiod * 12;
                $date = new DateTime('now');
                $date->modify("+$_cycles month");
                $date = $date->format('Y-m-d');

                $paymentItem->_endDate = $date;
            } else {
                $paymentItem->_isRecurring = true;
                $paymentItem->_ambiguous = true;
            }
        }
    } elseif (!($invoiceItemType == "" && $invoiceItemRelId == 0) || $invoiceItemType != "setup") {
        $paymentItem->_typeUnknown = true;
    }

    return $paymentItem;
}

function getRecurringItem($id)
{
    $recurringItem = null;
    try {
        $items = Capsule::table('tblinvoiceitems')->where([
            ['invoiceid', '=', $id],
            ['type', '=', 'Hosting']
        ])->get();

        if (!empty($items)) {
            foreach ($items as $item) {
                $itemId = $item->id;
                $paymentItem = getItemByInvoiceId($itemId);

                if ($paymentItem->_isRecurring) {
                    $recurringItem = $paymentItem;
                    break;
                } else {
                    $recurringItem = null;
                }
            }
        }
    } catch (Exception $exception) {
        printToLog('getRecurringItem - EXCEPTION: ' . json_encode($exception));
    }

    return $recurringItem;
}