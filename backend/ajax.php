<?php

require_once "helpers.php";

class JetSearchAJAX {

    // Variable to contain localized strings
    private $l;
    private $pinpointLang;

    function __construct($localization, $pinpointLang)
    {
        $this->l = $localization;
        $this->pinpointLang = $pinpointLang;

        // Add actions
        add_action('wp_ajax_available_times', array($this, "checkAvailableTimes"));
        add_action('wp_ajax_nopriv_available_times', array($this, "checkAvailableTimes"));

        add_action('wp_ajax_available_jets', array($this, "getAvailableJets"));
        add_action('wp_ajax_nopriv_available_jets', array($this, "getAvailableJets"));

        add_action("wp_ajax_add_jets_to_cart", array($this, "addJetsToCart"));
        add_action("wp_ajax_nopriv_add_jets_to_cart", array($this, "addJetsToCart"));
    }

    /**
     * Checks "hourly" availability for the given day (and few following days)
     * 
     */
    function checkAvailableTimes()
    {
        // TODO: Not the optimal place for this variable.
        // Tells how many days will be shown in front-end.
        // This probably should be in some sort of control panel 
        // (WP dashboard?) so that it could be edited by the (admin?) user
        $DAYS_TO_SHOW = 6;  

        global $wpdb;
        date_default_timezone_set("Europe/Helsinki");

        $dateStr = date("Y-m-d", strtotime($_POST["date"]));
        $rentTime = $_POST["time"];

        // If the given date is before the current day, return an error
        if (strtotime($_POST["date"] . " 23:59:59") < time()) {
            wp_send_json_error($this->l["errors"]["check_date_input"], 400);
        }

        $timeInfo = array();
        for ($i = 0; $i < $DAYS_TO_SHOW; $i++) {
            $hours = $this->getDefaultValuesForHours();
            // Get data for the calendars that are linked to Woocommerse products
            $q = $wpdb->prepare("SELECT wda.calendar_id, wpm.post_id, wda.date_start, wda.date_end 
                FROM ".$wpdb->prefix."dopbsp_availability wda 
                INNER JOIN ".$wpdb->prefix."dopbsp_calendars wdc ON wda.calendar_id = wdc.id 
                INNER JOIN ".$wpdb->prefix."postmeta wpm ON meta_key = 'dopbsp_woocommerce_calendar' AND meta_value = wdc.id
                WHERE date_start >= %s AND date_end <= %s",
                $dateStr." 00:00", $dateStr." 23:59:59"
            );

            $results = $wpdb->get_results($q);
            $hours = $this->defineAvailability($results, $hours, $rentTime);

            // Gather all the needed information and return it as JSON 
            array_push($timeInfo,
                array(
                    "dateStr" => $dateStr,
                    "hours" => $hours,
                    "is_first_day" => $i == 0,
                    "rent_time" => $rentTime,
                )
            );
            $dateStr = date("Y-m-d", strtotime($dateStr."+1 days"));
        }

        wp_send_json_success(array(
            "available_times" => $timeInfo
        ));
        wp_die();
    }

    function getAvailableJets()
    {
        global $wpdb;
        global $DOPBSP;

        date_default_timezone_set("Europe/Helsinki");

        // Get POST data
        $date = $_POST["date"];
        $startTime = $_POST["startTime"];
        $endTime = $_POST["endTime"];

        if (empty($startTime) || empty($endTime)) {
            wp_send_json_error($this->l["errors"]["check_search_times"], 400);
        }

        $startTimestamp = strtotime($date . " " . $startTime);
        $endTimestamp = strtotime($date . " " . $endTime);

        // Create date strings for the database query
        // Format: "YYYY-MM-dd HH:mm:ss"
        $startDate = date("Y-m-d H:i:s", $startTimestamp);
        $endDate = date("Y-m-d H:i:s", $endTimestamp);
        // Minus one second so that the date matches with DB values
        $endDateForDB = date("Y-m-d H:i:s", $endTimestamp-1);

        // If the start or the end times are before the current moment in time, return an error
        if ($startTimestamp < time() || $endTimestamp < time() || $endTimestamp < $startTimestamp) {
            wp_send_json_error($this->l["errors"]["check_search_times"], 400);
        }

        $jetCategoryTerm = getJetCategoryTerm();

        // Calculate duration
        $duration = date_diff(new DateTime($startDate), new DateTime($endDate));

        // Get available jets
        $q = $wpdb->prepare("SELECT calendar_id, date_start, date_end FROM ".$DOPBSP->tables->availability." WHERE %s >= date_start AND %s <= date_end", $startDate, $endDateForDB);
        $results = $wpdb->get_results($q);

        $jetInfo = array();

        // Loop through available jets and get the correct Pinpoint calendars and WooCommerce products
        foreach ($results as $jet) {
            $calendarId = $jet->calendar_id;
            // Get the WP post ID for the product by searching wp_postmeta for dopbsp_woocommerce_calendar
            $q = $wpdb->prepare("SELECT post_id FROM ".$wpdb->prefix."postmeta WHERE meta_key = 'dopbsp_woocommerce_calendar' AND meta_value = %d", $calendarId);
            $postmetaRes = $wpdb->get_results($q);
            if (count($postmetaRes) === 0) {
                // No post information for this calendar!
                // Ignore
                continue;
            }

            // Now we have the WP post ID for the product
            $postId = $postmetaRes[0]->post_id;

            // Get the WooCommerce product
            $product = wc_get_product($postId);
            if (!$product) {
                // Couldn't get WooCommerce product instance
                // TODO: Don't stop here but continue and ignore this error?
                wp_send_json_error($this->l["errors"]["general_error"], 500);
            }

            /*
            // Check that the product belongs to the jet category or one of its subcategories
            $categories = $product->get_category_ids();
            // Get also the category parent IDs
            // TODO: Handle errors
            // Get parent IDs from category terms and filter out zeroes
            $categoryParents = array_filter(array_map(function ($c) { return get_term($c)->parent; }, $categories), function ($c) { return $c > 0; });
            $mergedCategories = array_merge($categories, $categoryParents);
            if (!(in_array($jetCategoryTerm->term_id, $mergedCategories) || in_array($jetCategoryTerm->parent, $mergedCategories))) {
                // This product doesn't belong to the jet category, ignore
                continue;
            }
            CHANGED THIS ABOVE PART INTO THIS:                              */
            if (!isJetCategory($product, $jetCategoryTerm)) {
                // This product doesn't belong to the jet category, ignore
                continue;
            }

            // Get the pricing information from Pinpoint
            // TODO: Use Pinpoint functions instead?
            $q = $wpdb->prepare("SELECT hours_enabled, hours_interval_enabled, price_min, price_max FROM ".$DOPBSP->tables->calendars." WHERE id = %d", $calendarId);
            $calendarRes = $wpdb->get_results($q);
            if (count($calendarRes) === 0) {
                // No calendar entry!
                // TODO: Don't stop here but continue and ignore this error?
                wp_send_json($this->l["errors"]["general_error"], 500);
            }
            $calEntry = $calendarRes[0];
            $hoursEnabled = filter_var($calEntry->hours_enabled, FILTER_VALIDATE_BOOLEAN);
            $hoursIntervalEnabled = filter_var($calEntry->hours_interval_enabled, FILTER_VALIDATE_BOOLEAN);
            if (!$hoursEnabled && !$hoursIntervalEnabled) {
                // Not a valid calendar for our use
                // TODO: Don't stop here but continue and ignore this error?
                wp_send_json_error($this->l["errors"]["general_error"], 500);
            }
            $minPrice = $calEntry->price_min;
            $maxPrice = $calEntry->price_max;

            // This is kinda hacky because I don't really know how the price_min and price_max work.
            // TODO: Figure out the price calculation
            $basePrice = max($minPrice, $maxPrice);

            // Calculate price for the current duration
            // One timeslot is 30 minutes, so divide the duration in slots of 30 minutes.
            $durationHours = ($duration->h + ($duration->i / 60));
            $slotCount = $durationHours / 0.5;
            $price = $slotCount * $basePrice;

            $thumbUrl = get_the_post_thumbnail_url($postId);
            if (!$thumbUrl) {
                $thumbUrl = wc_placeholder_img_src();
            }

            // Get discounted price
            $discountedPrice = getDiscountedPrice($calendarId, $duration, $price);
            if (!$discountedPrice) {
                wp_send_json_error($this->l["errors"]["general_error"], 500);
            }

            // Get extras
            $extras = getCalendarExtras($calendarId);
            if ($extras == null) {
                $extras = array();
            }

            // If the reservation duration is 30 minutes the price must contain the cost of the gas.
            if ($slotCount == 1) {
                // Get the gas extra
                $gasExtra = null;
                foreach ($extras as $extra) {
                    if ($extra->is_gas) {
                        $gasExtra = $extra;
                        break;
                    }
                }
                if ($gasExtra == null) {
                    // No gas extra available
                    SwLogger::logError("Gas extra was not found for calendar ".$calendarId);
                    wp_send_json_error($this->l["errors"]["general_error"], 500);
                }
                // Get the gas price and add it to the base price
                // This has to be divided by 2 because the Pinpoint price is for one hour instead of 30 minutes.
                $discountedPrice += $gasExtra->price / 2;
            }

            // Gather all the needed information and return it as JSON

            // Add "-sivu" suffix at the end of the URL
            $suffix = "-sivu";
            $permalink = $product->get_permalink();
            if (substr($permalink, -1) === '/') {
                $permalink = substr($permalink, 0, strlen($permalink)-1) . $suffix . "/";
            } else {
                $permalink .= $suffix;
            }

            array_push($jetInfo,
                array(
                    "name" => $product->get_name(),
                    "permalink" => $permalink,
                    "short_desc" => $product->get_short_description(),
                    "image" => $thumbUrl,
                    "price" => $discountedPrice,
                    "calendar_id" => $calendarId,
                    "product_id" => $postId,
                    "extras" => $extras
                )
            );
        }

        // Order the jets by the price
        // The ordering is done here rather than in the database query, because Pinpoint stores the price in
        // two fields and by now we have the correct price available.
        usort($jetInfo, "JetSearchAjax::jetInfoPriceComparer");

        wp_send_json_success(array(
            "available_jet_count" => count($jetInfo),
            "available_jets" => $jetInfo,
            // TODO: How to get this url with some function?
            "extras_category_page_url" => "/tuote-osasto/lisavarusteet/"
        ));

        // Die, just in case
        // wp_send_json should call wp_die automatically
        wp_die();
    }

    /**
     * Comparison function for usort in getAvailableJets
     *
     * @param $a array Info array A
     * @param $b array Info array B
     * @return int Comparison output
     */
    private static function jetInfoPriceComparer($a, $b) {
        $ap = $a["price"];
        $bp = $b["price"];
        // Return -1 if the price of A is less than the price of B
        // Return 0 if the prices are equal
        // Return 1 if the price of A is greater than the price of B
        return $ap < $bp ? -1 : $ap === $bp ? 0 : 1;
    }

    /**
     * "Initializes" the $hours array with defaul values:
     * Available: false & Quantity: 0 (Basically meaning everething is unavailable)
     *
     * @return $hours array with default values
     */
    function getDefaultValuesForHours()
    {
        $hours = array();
        foreach (getStartingTimes() as $hour) {
            $hours[$hour]['available'] = false;
            $hours[$hour]['quantity_available'] = 0;
        }
        // Pop out last value, since the final hour can't be selected as a starting time
        array_pop($hours);
        return $hours;
    }

    /**
     * Defines if the certain time is available or not
     * Counts also how many items are available (if any)
     *
     * @param $results from the database
     * @param $hours array for every starting time
     * @param $rentTime
     *
     * @return $hours array with availability information
     */
    function defineAvailability($results, $hours, $rentTime)
    {
        $jetCategoryTerm = getJetCategoryTerm();

        if (count($results) > 0) {
            foreach ($results as $row) {
                $calendarDate = date("Y-m-d", strtotime($row->date_start));
                $calendarStartTime = date("H:i:s", strtotime($row->date_start));
                $calendarEndTime = date("H:i:s", strtotime($row->date_end));

                $product = wc_get_product($row->post_id);
                if (!$product) {
                    // TODO: Don't stop here but continue and ignore this error?
                    wp_send_json_error($this->l["errors"]["general_error"], 500);
                }
                if (!isJetCategory($product, $jetCategoryTerm)) {
                    // This product doesn't belong to the jet category, ignore
                    continue;
                }

                foreach ($hours as $hour => $key) {
                    $endTime = getEndTime($calendarDate, $hour, $rentTime);
                    if (strtotime($hour) >= strtotime($calendarStartTime) && strtotime($endTime) <= strtotime($calendarEndTime)){
                        $hours[$hour]['available'] = true;
                        $hours[$hour]['quantity_available'] += 1;
                    }
                }
            }
        } 
        return $hours;
    }

    function addJetsToCart() {
        date_default_timezone_set("Europe/Helsinki");

        $reservations = array();

        $date = $_POST["date"];
        $startTime = new DateTime($date . " " . $_POST["startTime"]);
        $endTime = new DateTime($date . " " . $_POST["endTime"]);

        $jets = $_POST["jets"];

        // Create JetReservation instances
        foreach ($jets as $jet) {
            $calId = intval($jet["calId"]);
            $prodId = intval($jet["prodId"]);
            $extraIds = $jet["extras"];
            $reservations[] = new JetReservation($calId, $prodId, $startTime, $endTime, $extraIds);
        }

        try {
            // Add reservation(s)
            addJetReservations($reservations, $this->pinpointLang);
        } catch (JetCartOverlapException $e) {
            // Same jet already exists in the cart
            wp_send_json_error(array("reason" => "overlap"), 403);
        } catch (JetNotAvailableException $e) {
            // Jet unavailable at the given time
            wp_send_json_error(array("reason" => "not_available"), 404);
        } catch (JetReservationFailedException $e) {
            // General server failure (database etc.)
            wp_send_json_error(array("reason" => "fail"), 500);
        }

        // Everything is OK, send success response with the WC cart URL so that the client can redirect
        wp_send_json_success(array("cart_url" => get_permalink(wc_get_page_id("cart"))));
    }

}
