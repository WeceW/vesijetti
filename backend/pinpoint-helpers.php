<?php
/**
 * This file contains some helpers / re-implementations of Pinpoint AJAX functions.
 * The functions are re-implemented to get their parameters from the function parameters as the Pinpoint
 * implementation gets the parameters from the POST data.
 */


/**
 * Get a default schedule for a Pinpoint calendar
 *
 * Pinpoint AJAX function: DOPBSPBackEndCalendar::getOptions
 *
 * @param $calId int Calendar ID
 * @return array Calendar schedule
 */
function pp_get_calendar_default_schedule($calId) {
    global $DOPBSP;
    global $wpdb;

    $calendar = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$DOPBSP->tables->calendars.' WHERE id=%d', $calId));

    // If the calendar contains default availability information
    if ($calendar->default_availability != "") {
        return json_decode($calendar->default_availability);
    }
    // If no default availability information is available in the database, return dummy data
    return array(
        "available" => 1,
        "bind" => 0,
        "hours" => array(),
        "hours_definitions" => array(
            array("value" => "00:00")
        ),
        "info" => "",
        "notes" => "",
        "price" => 0,
        "promo" => 0,
        "status" => "none"
    );
    //return '{"available":1,"bind":0,"hours":{},"hours_definitions":[{"value":"00:00"}],"info":"","notes":"","price":0,"promo":0,"status":"none"}';
}

/**
 * Get Pinpoint calendar schedule
 *
 * Pinpoint AJAX function: DOPBSPBackEndCalendarSchedule::get
 *
 * @param $calId int Calendar ID
 * @param $year int Year
 * @return mixed Calendar schedule
 */
function pp_get_calendar_schedule($calId, $year) {
    global $DOT;
    global $DOPBSP;

    // Get calendar settings so that the correct timezone can be set
    $settings_calendar = $DOPBSP->classes->backend_settings->values($calId, "calendar");

    // Set timezone
    if($settings_calendar->timezone != '') {
        date_default_timezone_set($settings_calendar->timezone);
    }

    // Get schedule
    return $DOT->models->calendar_schedule->get($calId, $year);
}