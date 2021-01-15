<?php

use WHMCS\Database\Capsule;

const INT_ONETIME = "ONE TIME";
const INT_MONTHLY = "MONTHLY";
const INT_QUARTERLY = "QUARTERLY";
const INT_BIANNUALLY = "SEMI-ANNUALLY";
const INT_ANNUALLY = "ANNUALLY";
const INT_BIENNIALLY = "BIENNIALLY";
const INT_TRIENNIALLY = "TRIENNIALLY";

/**
 * A type to encapsulate recurring details
 * about a product in a unified manner
 */
class DirectPayPaymentItem
{

    /**
     * The invoice item it for this product
     * @var string
     */
    public $invoiceItemId = "";

    /**
     * Is this product recurring?
     * @var boolean
     */
    public $isRecurring = false;

    /**
     * Is this product recurring forever?
     * @var boolean
     */
    public $isRecurringForever = false;

    /**
     * The recurring period. In PayHere terms,
     * the ```recurrence```.
     * E.g: 2 Week
     * @var string
     */
    public $recurringPeriod = "";

    /**
     * The recurring duration. In PayHere terms,
     * the ```duration```.
     * E.g: 2 Week
     * @var string
     */
    public $recurringDuration = "";

    /**
     * The start-up fee for this product.
     * The default value is 0.0, if the product
     * does not have a start-up fee or is not
     * recurring
     * @var float
     */
    public $recurringStartupFee = 0.0;

    /**
     * The amount of the product. If the product
     * is not recurring then it is the equivalent
     * of ```tblinvoiceitems->amount```.
     * If the product is recurring, this value might
     * or might not be equivalent to the amount
     * supplied by the ```tblinvoiceitems``` table.
     * @var float
     */
    public $unitPrice = 0.0;

    /**
     * If this value is true, it signifies that an
     * internal parameter for this product caused
     * it to be incompatible with the PayHere supported
     * recurring constraints.
     *
     * Such products should not be taken into account
     * for startup fees or recurrence amounts.
     *
     * @var boolean
     */
    public $isRecurringButErrornous = false;

    /**
     * Indicates that this product could not be
     * used to construct the ```PayhereConsumableProduct```
     * correctly, since the detail extractor cannot
     * identifier it's product type
     *
     * @var boolean
     */
    public $isUnknownProductType = false;

    //public $tableName = "";
    //public $relativeId = 0;

    /**
     * The total price of the product. If the
     * product is recurring, this value is the total
     * of the start-up fee and the unit price.
     * Otherwise, it is equivalent to the
     * unitprice.
     *
     * Use this method when retrieving the
     * product's price, It is adivsed to not
     * use the raw `recurringStartupFee` or
     * `unitPrice`.
     *
     * @return double
     */
    function getPrice()
    {
        if ($this->isRecurring) {
            return $this->unitPrice + $this->recurringStartupFee;
        } else {
            return $this->unitPrice;
        }
    }
}

/**
 * A type to hold the final startup and
 * recurring total.
 */
class PriceData
{
    /**
     * The startup fee total for the invoice
     * @var float
     */
    public $startupTotal = 0.0;

    /**
     * The recurring total for the invoice
     * @var float
     */
    public $recurringTotal = 0.0;

    /**
     * Constructs a new object
     * @param float $starTot The startup total
     * @param float $recTot The recurrence total
     */
    function __construct($starTot, $recTot)
    {
        $this->startupTotal = $starTot;
        $this->recurringTotal = $recTot;
    }
}

function do_log($message)
{
    if (false) {
        echo "<div><p style='padding: 2px 4px 2px 3px; margin: 3px 3px 1px 3px; display: inline-block; background-color: #9bffd9; border: 1px solid #00a063; border-radius: 5px;'>" . $message . '</p></div>';
    }

}

/**
 * Gets the tax total for an invoice.
 * Takes in to account whether the TaxType
 * configuration in WHMCS is set to Exclusive.
 * @param string $id
 * @return integer
 */
function getTaxByInvoice($id)
{
    $config = Capsule::table('tblconfiguration')->where('setting', '=', 'TaxType')->first();

    if ($config->value === "Exclusive") {
        $invoice = Capsule::table('tblinvoices')->where('id', '=', $id)->first();
        $tax1 = $invoice->tax;
        $tax2 = $invoice->tax2;

        return $tax1 + $tax2;
    } else {
        return 0;
    }
}

/**
 * Returns the startup and recurrence total
 * for a given invoice
 * @param string $invoiceId
 * @param DirectPayPaymentItem $mainProduct
 * @return PriceData
 * @throws Exception
 */
function getPriceDetails($invoiceId, $mainProduct)
{
    $startupFeeTotal = 0.0;
    $recurringTotal = 0.0;

    $invoiceItems = Capsule::table('tblinvoiceitems')->where('invoiceid', '=', $invoiceId)->get();
    foreach ($invoiceItems as $item) {
        $id = $item->id;

        $paymentItem = getItemByInvoiceId($id);

        if (!$paymentItem->isRecurringButErrornous && $paymentItem->isRecurring && !$paymentItem->isUnknownProductType) {

            if (($mainProduct->recurringPeriod == $paymentItem->recurringPeriod) && ($mainProduct->recurringDuration == $paymentItem->recurringDuration)) {
                $startupFeeTotal += $paymentItem->recurringStartupFee;
                $recurringTotal += $paymentItem->unitPrice;
                do_log('Invoice item ' . $id . ' | Recurring | startup fee: ' . $paymentItem->recurringStartupFee . ' | price: ' . $paymentItem->unitPrice);
            } else {
                do_log('Invoice item ' . $id . ' | Recurring period does not match');
            }
        } else if (!$paymentItem->isUnknownProductType && !$paymentItem->isRecurring) {
            // Is genuinely non-recurring (consider as a startup product)
            $startupFeeTotal += $paymentItem->unitPrice;
            do_log('Invoice item ' . $id . ' | Item is not recurring: ' . $paymentItem->unitPrice);
        } else {
            do_log('Unknown invoice item ' . $id);
        }
    }

    return new PriceData($startupFeeTotal, $recurringTotal);
}

/**
 * Returns the Recurring Period and Recurring Duration
 * based on the billing cycle and recurring cycle count.
 * @param string $interval
 * @param integer $recurringCycles
 * getRecurringInfoForBillingCycle
 */
function getRecurringInfo($interval, $recurringCycles)
{
    $recurringItem = array(
        'period' => '',
        'duration' => '',
        'recurring_forever' => false
    );

    if ($interval === INT_MONTHLY) {

        $date = new DateTime('now');
        $date->modify("+$recurringCycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['duration'] = $date;
        $recurringItem['period'] = "MONTHLY";

    } else if ($interval === INT_QUARTERLY) {

        $_cycles = 3 * $recurringCycles;
        $date = new DateTime('now');
        $date->modify("+$_cycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['duration'] = $date;
        $recurringItem['period'] = "QUARTERLY";

    } else if ($interval === INT_BIANNUALLY) {

        $_cycles = 6 * $recurringCycles;
        $date = new DateTime('now');
        $date->modify("+$_cycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['duration'] = $date;
        $recurringItem['period'] = "BIANNUAL";

    } else if ($interval === INT_ANNUALLY) {

        $_cycles = 12 * $recurringCycles;
        $date = new DateTime('now');
        $date->modify("+$_cycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['duration'] = $date;
        $recurringItem['period'] = "YEARLY";

    } else if ($interval === INT_BIENNIALLY) {

        $_cycles = 24 * $recurringCycles;
        $date = new DateTime('now');
        $date->modify("+$_cycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['duration'] = $date;
        $recurringItem['period'] = "BIENNIALLY";

    } else if ($interval === INT_TRIENNIALLY) {

        $_cycles = 36 * $recurringCycles;
        $date = new DateTime('now');
        $date->modify("+$_cycles month");
        $date = $date->format('Y-m-d');

        $recurringItem['duration'] = $date;
        $recurringItem['period'] = "TRIENNIALLY";

    } elseif (($interval == INT_ONETIME) || ($recurringCycles === "ONETIME")) { // TODO fix recurringClcles type issue
        // Do nothing
    }

    if ($recurringCycles == 0) {
        $recurringItem['duration'] = date("Y-m-d", strtotime("3000-1-1")); // Forever
        $recurringItem['recurring_forever'] = true;
    }

    return $recurringItem;
}

/**
 * Returns a DirectPayPaymentItem by itemId
 *
 * @param integer $itemId
 * @return DirectPayPaymentItem
 * @throws Exception
 * getConsumableProductsForTblInvoiceItemId
 */
function getItemByInvoiceId($itemId)
{
    $invoiceItem = Capsule::table('tblinvoiceitems')->where('id', '=', $itemId)->first();

    $paymentItem = new DirectPayPaymentItem();

    // Basic values needed to select the
    // information relevant to this product
    $invoiceItemRelId = $invoiceItem->relid;
    $invoiceItemType = strtolower($invoiceItem->type);

    // Initialize consumable product with information
    // common to all types of invoice items
    $paymentItem->unitPrice = (double)$invoiceItem->amount;
    $paymentItem->invoiceItemId = $itemId;

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

            $paymentItem->isRecurring = true;
            $paymentItem->isRecurringForever = $recurringItem['recurring_forever'];
            $paymentItem->recurringPeriod = $recurringItem['period']; // Interval
            $paymentItem->recurringDuration = $recurringItem['duration']; // End Date
        }

    } else if ($invoiceItemType == "domainregister" || $invoiceItemType == "domaintransfer" || $invoiceItemType == "domainrenew") {
        $domainItem = Capsule::table('tbldomains')->where('id', '=', $invoiceItemRelId)->first();

        if (!$domainItem) {
            throw new Exception("Domain Item not found");
        }

        $registrationPeriod = (int)$domainItem->registrationperiod;

        $unitPrice = $paymentItem->unitPrice;
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
            $paymentItem->isRecurringButErrornous = true;
        }

        if ($interval != "") {
            $recurringItem = getRecurringInfo($interval, 0);

            $paymentItem->isRecurring = true;
            $paymentItem->isRecurringForever = true;
            $paymentItem->recurringPeriod = $recurringItem['period'];
            $paymentItem->recurringDuration = $recurringItem['duration'];

            if ($unitPrice != $firstPaymentAmount) {
                $paymentItem->recurringStartupFee = $unitPrice - $recurringAmount;
                $paymentItem->unitPrice = $recurringAmount;
            } else {
                $paymentItem->recurringStartupFee = $firstPaymentAmount - $recurringAmount;
                $paymentItem->unitPrice = $recurringAmount;
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

            $paymentItem->isRecurring = true;
            $paymentItem->isRecurringForever = $recurringItem['recurring_forever'];
            $paymentItem->recurringPeriod = $recurringItem['period'];
            $paymentItem->recurringDuration = $recurringItem['duration'];
            $paymentItem->recurringStartupFee = $addonItem->setupfee;
            $paymentItem->unitPrice = $addonItem->recurring;
        }
    } else if ($invoiceItemType == "item") { // Billable items

        $item = Capsule::table('tblbillableitems')->where('id', '=', $invoiceItemRelId)->first();

        if (!$item) {
            throw new Exception("Billable Item not found");
        }

        if ($item->invoiceaction == 4) { // is Recurring
            // Recur every $billing_cycle_number $billing_cycle_ordinal for $billing_duration times
            // Ex:   every 3                     Weeks                  for 5                 times

            /**
             * The magnitude of the billing cycle. E.g: 2.
             * @var int
             */
            $itemRecur = $item->recur;
            /**
             * The type of the billing cycle E.g: Weeks.
             * Possible values include 0, days, weeks, months, years
             * @var string
             */
            $itemRecurCycle = strtoupper($item->recurcycle);
            /**
             * The duration of the recurrence. E.g: 2.
             * @var int
             */
            $itemRecurFor = $item->recurfor;

            if ($itemRecurCycle != 'WEEKS' && $itemRecurCycle != 'DAYS') {
                $paymentItem->isRecurring = true;
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
                    $paymentItem->isRecurring = true;
                    $paymentItem->isRecurringForever = false;
                    $paymentItem->recurringPeriod = $recurringItem['period'];
                    $paymentItem->recurringDuration = $recurringItem['duration'];
                } else {
                    $paymentItem->isRecurringButErrornous = true;
                }
            }
        }
    } else if ($invoiceItemType == "invoice") {
        $mainProduct = getRecurringItem($invoiceItemRelId);

        $taxAmt = getTaxByInvoice($invoiceItemRelId);
        $paymentItem->unitPrice = $paymentItem->unitPrice + $taxAmt;

        if ($mainProduct != null) {
            /**
             * The startup and recurrence total for the given invoiceid
             * @var PriceData
             */
            $invRes = getPriceDetails($invoiceItemRelId, $mainProduct);

            if (!$invRes) {
                throw new Exception("Invoice result is null");
            }

            $paymentItem->isRecurring = true;
            $paymentItem->isRecurringForever = $mainProduct->isRecurringForever;
            $paymentItem->recurringPeriod = $mainProduct->recurringPeriod;
            $paymentItem->recurringDuration = $mainProduct->recurringDuration;
            $paymentItem->recurringStartupFee = $invRes->startupTotal;
            $paymentItem->unitPrice = $invRes->recurringTotal;
            $paymentItem->recurringStartupFee = $paymentItem->recurringStartupFee + $taxAmt;
        }
    } else if ($invoiceItemType == "promohosting") {
        // The Product the promotion is associated with
        $hostingItem = Capsule::table('tblhosting')->where('id', '=', $invoiceItemRelId)->first();

        if (!$hostingItem) {
            throw new Exception("Hosting details could not be found while looking up Promotion");
        }

        // Promotion details
        $promotion = Capsule::table('tblpromotions')->where('id', '=', $hostingItem->promoid)->first();

        if ($promotion->recurring == 1) { /* recurring promotion */

            $interval = strtoupper($hostingItem->billingcycle);

            if ($interval != INT_ONETIME) {

                $product = Capsule::table('tblproducts')->where('id', '=', $hostingItem->packageid)->first();
                $recurringCycles = $product->recurringcycles;
                $recurringItem = getRecurringInfo($interval, $recurringCycles);

                $paymentItem->isRecurring = true;
                $paymentItem->isRecurringForever = $promotion->recurfor == 0;
                $paymentItem->recurringPeriod = $recurringItem['period'];
                $paymentItem->recurringDuration = $recurringItem['duration'];
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
                $paymentItem->isRecurring = true;
                $paymentItem->recurringPeriod = "YEARLY";

                $_cycles = $domainItem->registrationperiod * 12;
                $date = new DateTime('now');
                $date->modify("+$_cycles month");
                $date = $date->format('Y-m-d');

                $paymentItem->recurringDuration = $date;
            } else {
                $paymentItem->isRecurring = true;
                $paymentItem->isRecurringButErrornous = true;
            }
        }
    } elseif (!($invoiceItemType == "" && $invoiceItemRelId == 0) || $invoiceItemType != "setup") {
        $paymentItem->isUnknownProductType = true;
    }

    return $paymentItem;
}


/**
 * Returns the recurring product/service by invoice.
 *
 * @param string $id
 * @return DirectPayPaymentItem
 * @throws Exception
 */
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

                if ($paymentItem->isRecurring) {
                    $recurringItem = $paymentItem;
                    break;
                } else {
                    $recurringItem = null;
                }
            }
        }
    } catch (Exception $exception) {
        do_log('getRecurringItem - EXCEPTION: ' . json_encode($exception));
    }

    return $recurringItem;
}