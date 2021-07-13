<?php

class RecurringInfoItem {
    public $amount = 0.00;
    public $interval = "";
    public $endDate = "3000-12-31";
    public $startDate = "";
    public $dontExpire = false;
    public $invalidItem = false;
    public $invalidDescription = "";

    public function __construct()
    {
        $this->startDate = date('Y-m-d');
    }
}

class PaymentItem {
    public $interval = "";
    public $endDate = "3000-12-31";
    public $startDate = "";
    public $amount = 0.0;
    public $isRecurring = false;
    public $dontExpire = false;
    public $invalidItem = false;
    public $invalidDescription = "";

    public function __construct()
    {
        $this->startDate = date('Y-m-d');
    }
}