<?php

class RecurringInfoItem {
    public $amount = 0.00;
    public $interval = "";
    public $endDate = "";
    public $dontExpire = false;
    public $invalidItem = false;
    public $invalidDescription = "";
}

class PaymentItem {
    public $interval = "";
    public $endDate = "";
    public $amount = 0.0;
    public $isRecurring = false;
    public $dontExpire = false;
    public $invalidItem = false;
    public $invalidDescription = "";
}