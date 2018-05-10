<?php

require_once "pinpoint-helpers.php";

class JetReservation {
    private $calId;
    private $prodId;
    private $startTime;
    private $endTime;
    private $basePrice;
    private $totalPrice;
    private $daysHoursHistory;
    private $discountInfo;
    private $extraInfo;
    private $extraIds;
    private $hasGasExtra;
    private $gasExtra;

    /**
     * Get calendar ID
     *
     * @return int Calendar ID
     */
    public function getCalendarId() {
        return $this->calId;
    }

    /**
     * Get WooCommerce product ID
     *
     * @return int WooCommerce product ID
     */
    public function getProductId() {
        return $this->prodId;
    }

    /**
     * Get the reservation duration in hours
     *
     * @return float Reservation duration
     */
    public function getDurationHours() {
        $diff = $this->startTime->diff($this->endTime);
        return $diff->h + ($diff->i / 60);
    }

    /**
     * Set the base price
     *
     * @param $price float Base price to set
     */
    public function setBasePrice($price) {
        $this->basePrice = $price;
    }

    public function __construct($calId, $prodId, $startTime, $endTime, $extraIds) {
        $this->calId = $calId;
        $this->prodId = $prodId;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->extraIds = $extraIds;

        // Initialize default discount info
        $this->discountInfo = new stdClass();
        $this->discountInfo->id = 0;
        $this->discountInfo->rule_id = 0;
        $this->discountInfo->operation = "-";
        $this->discountInfo->price = 0;
        $this->discountInfo->price_type = "percent";
        $this->discountInfo->price_by = "once";
        $this->discountInfo->start_date = "";
        $this->discountInfo->end_date = "";
        $this->discountInfo->start_hour = "";
        $this->discountInfo->end_hour = "";
        $this->discountInfo->translation = "";

        // Get the product schedule from Pinpoint
        $this->populatePinpointScheduleData();

        // Get the extra information from Pinpoint
        $this->populatePinpointExtraData();
    }

    /**
     * Calculate the total price including discounts
     */
    private function calculateTotalPrice() {
        $this->totalPrice = $this->basePrice;

        // Check the discount information format
        $di = $this->discountInfo;
        // Get the base price and apply the discount to it
        if ($di->operation !== "-") {
            // No support for other operations right now
            SwLogger::logWarning("Not applying discount " . $di->id . ": Unsupported operation '" . $di->operation . "'");
            return;
        }
        if ($di->price_type !== "fixed") {
            // No support for other than fixed price type for now
            SwLogger::logWarning("Not applying discount " . $di->id . ": Unsupported price type '" . $di->price_type . "'");
            return;
        }
        if ($di->price_by !== "once") {
            // No support for other price_by types for now
            SwLogger::logWarning("Not applying discount " . $di->id . ": Unsupported price by '" . $di->price_by . "'");
            return;
        }
        // Apply the discount
        $this->totalPrice -= $di->price;

        if (!$this->hasGasExtra && $this->getDurationHours() / 0.5 == 1) {
            // If this reservation has no gas extra selected and its duration is 30 minutes
            // Add the missing mandatory gas extra
            if ($this->gasExtra == null) {
                // Get the extra
                $gasExtra = getGasExtraForCalendar($this->calId);
                if ($gasExtra == null) {
                    // Couldn't get the gas extra for this calendar
                    SwLogger::logError("No gas extra for calendar " . $this->calId);
                    return;
                }
                $this->gasExtra = $gasExtra->extras[0];
                SwLogger::logInfo("Fetched missing gas extra for reservation with calendar " . $this->calId);
            }
            if ($this->extraInfo == null) {
                $this->extraInfo = array($this->gasExtra);
            } else {
                array_push($this->extraInfo, $this->gasExtra);
            }
        }

        // Calculate extra prices
        $extras = $this->extraInfo;
        foreach ($extras as $extra) {
            $extraPrice = $extra->price;
            // This code is basically copied from the frontend (search-widget-search1.js)
            if ($extra->price_type == "fixed") {
                if ($extra->price_by == "once") {
                    if ($extra->operation == "+") {
                        $this->totalPrice += $extraPrice;
                    } else if ($extra->operation == "-") {
                        $this->totalPrice -= $extraPrice;
                    } else {
                        // Not supported as of now
                        SwLogger::logWarning("Unsupported extra operation '" . $extra->operation . "'");
                    }
                } else if ($extra->price_by == "period") {
                    // The price is for one hour, but the smallest timeslice is 30 minutes
                    // Calculate reservation duration
                    $sliceCount = $this->getDurationHours() / 0.5;
                    $newExtraPrice = $extraPrice / 2 * $sliceCount;

                    if ($extra->operation == "+") {
                        $this->totalPrice += $newExtraPrice;
                    } else if ($extra->operation === "-") {
                        $this->totalPrice -= $newExtraPrice;
                    } else {
                        // Not supported as of now
                        SwLogger::logWarning("Unsupported extra operation '" . $extra->operation . "'");
                    }
                } else {
                    SwLogger::logWarning("Unsupported extra price by '" . $extra->price_by . "'");
                }
            } else {
                // Not supported as of now
                SwLogger::logWarning("Unsupported extra price type '" . $extra->price_type . "'");
            }
        }
    }

    /**
     * Return Pinpoint-formatted discount information
     *
     * @return array Discount information for Pinpoint
     */
    private function getFormattedDiscountInfo() {
        $di = $this->discountInfo;
        // Get the translation text from the JSON data
        if (count($di->translation) > 0) {
            $transl = json_decode($di->translation);
            $transl = utf8_decode(array_key_exists("fi", $transl) ? $transl->fi : $transl->en);
        } else {
            $transl = "";
        }
        return array(
            "id" => $di->id,
            "rule_id" => 0,
            "operation" => $di->operation,
            "price" => $di->price,
            "price_type" => $di->price_type,
            "price_by" => $di->price_by,
            "start_date" => $this->startTime->format("Y-m-d"),
            "end_date" => $this->endTime->format("Y-m-d"),
            "start_hour" => $this->startTime->format("H:i"),
            "end_hour" => $this->endTime->format("H:i"),
            "translation" => $transl    // TODO: Use custom text always?
        );
    }

    private function getFormattedExtraInfo() {
        $arr = array();
        foreach ($this->extraInfo as $extra) {
            array_push($arr, array(
                "id" => $extra->id,
                "group_id" => $extra->group_id,
                "group_translation" => $extra->group_translation,
                "no_items_multiply" => false,   // TODO: Add support for this?
                "operation" => $extra->operation,
                "price_by" => $extra->price_by,
                "price_total" => $extra->price, // TODO
                "price_type" => $extra->price_type,
                "price" => $extra->price,
                "translation" => $extra->translation
            ));
        }
        return $arr;
    }

    /**
     * Get the Pinpoint schedule data for the current calendar and update this object
     */
    private function populatePinpointScheduleData() {
        // First get the corresponding calendar schedule
        $calSchedule = pp_get_calendar_schedule($this->calId, intval($this->startTime->format("Y")));

        if ($calSchedule == null || !array_key_exists($this->startTime->format("Y-m-d"), $calSchedule)) {
            // No schedule available for the given day, use default schedule
            $this->daysHoursHistory = pp_get_calendar_default_schedule($this->calId);
            return;
        }
        // Schedule available, return it for the given day
        $this->daysHoursHistory = $calSchedule[$this->startTime->format("Y-m-d")];
    }

    private function populatePinpointExtraData() {
        $ret = getCalendarExtrasForBackend($this->calId, $this->extraIds);
        if ($ret == null) {
            return;
        }
        $extras = $ret->extras;
        if ($extras == null) {
            return;
        }

        $this->extraInfo = $extras;
        $this->hasGasExtra = $ret->hasGasExtra;
    }

    /**
     * Set discount information fetched from the Pinpoint database
     *
     * @param $info object Pinpoint discount information
     */
    public function setDiscountInfo($info) {
        $this->discountInfo = $info;
    }

    /**
     * Set extra information fetched from the Pinpoint database
     *
     * @param $info array Extra information
     */
    public function setExtraInfo($info) {
        $this->extraInfo = $info;
    }

    /**
     * Get Pinpoint-formatted reservation data
     *
     * @return array Pinpoint-formatted reservation
     */
    public function getPinpointReservationData() {
        $this->calculateTotalPrice();
        return array(
            "check_in" => $this->startTime->format("Y-m-d"),
            "check_out" => "",
            "start_hour" => $this->startTime->format("H:i"),
            "end_hour" => $this->endTime->format("H:i"),
            "no_items" => 1,
            "price" => $this->totalPrice,
            "price_total" => $this->totalPrice,
            "extras" => $this->getFormattedExtraInfo(),
            "extras_price" => 0,
            "discount" => $this->getFormattedDiscountInfo(),
            "discount_price" => 0,
            "coupon" => array(
                "id" => 0,
                "code" => "",
                "operation" => "",
                "price" => 0,
                "price_type" => "percent",
                "price_by" => "once",
                "translation" => ""
            ),
            "coupon_price" => 0,
            "fees" => array(),
            "fees_price" => 0,
            "deposit" => array(
                "price" => 0,
                "price_type" => "percent"
            ),
            "deposit_price" => 0,
            "days_hours_history" => $this->daysHoursHistory
        );
    }
}

// Exceptions

class BaseJetException extends Exception {
    private $reservation;

    public function getReservation() {
        return $this->reservation;
    }

    public function __construct($reservation) {
        $this->reservation = $reservation;
        parent::__construct();
    }
}

class JetNotAvailableException extends BaseJetException { }
class JetCartOverlapException extends BaseJetException { }
class JetReservationFailedException extends BaseJetException { }
