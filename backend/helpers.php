<?php 

require_once "jetreservation.php";
require_once "logging.php";

/**
 * Forms an array with values e.g. ['10:00' => '10:00', '10:30' => '10:30', ..., '21:00' => '21:00'].
 *
 * Tries to define these values from the database by scraping hours information
 * from all the active calendars - from today to one week ahead and
 * returning all the values that are encountered.
 *
 * @return hours array (All the possible starting times for the rent)
 */
function getStartingTimes() 
{
    global $wpdb;
    $weekFromNow = date("Y-m-d", strtotime("+1 Week"));
    $q = $wpdb->prepare("
        SELECT wdd.data, wpm.post_id 
        FROM ".$wpdb->prefix."dopbsp_calendars AS wdc 
            INNER JOIN ".$wpdb->prefix."dopbsp_days wdd ON wdc.id = wdd.calendar_id 
            INNER JOIN ".$wpdb->prefix."postmeta wpm ON wpm.meta_key = 'dopbsp_woocommerce_calendar' AND wpm.meta_value = wdc.id 
        WHERE wdc.min_available = 1 AND wdd.day < %s",
        $weekFromNow
    );
    $results = $wpdb->get_results($q);
    $jetCategoryTerm = getJetCategoryTerm();

    $startingTimes = array();
    if (count($results) > 0) {
        foreach ($results as $result) {
            $product = wc_get_product($result->post_id);
            if (!$product) {
                // TODO: Don't stop here but continue and ignore this error?
                wp_send_json_error($this->l["errors"]["general_error"], 500);
            }

            if (!isJetCategory($product, $jetCategoryTerm)) {
                // This product doesn't belong to the jet category, ignore
                continue;
            }

            foreach (json_decode($result->data, true)['hours'] as $hour => $value) {
                $startingTimes[$hour] = $hour;
            }
        }
    }

    ksort($startingTimes);

    return $startingTimes;
}

/**
 * Counts how many PinPoint calendars are in the database
 * Only "active" calendars/items, which actually have one item available.
 * Counting only calendars that are in specific Jet (Jetit?) category
 *
 * @return calendar count
 */
function getPinPointCalendarCount() 
{
    global $wpdb;
    $calendarCount = 0;

    $q = $wpdb->prepare("
        SELECT wdc.id, wpm.post_id 
        FROM ".$wpdb->prefix."dopbsp_calendars wdc 
            INNER JOIN ".$wpdb->prefix."postmeta wpm ON wpm.meta_key = %s AND wpm.meta_value = wdc.id 
        WHERE wdc.min_available = 1", 
        'dopbsp_woocommerce_calendar'
    );

    $calendars = $wpdb->get_results($q);
    $jetCategoryTerm = getJetCategoryTerm();

    foreach ($calendars as $cal) {
        $product = wc_get_product($cal->post_id);
        if (!$product) {
            // TODO: Don't stop here but continue and ignore this error?
            wp_send_json_error($this->l["errors"]["general_error"], 500);
        }

        if (!isJetCategory($product, $jetCategoryTerm)) {
            // This product doesn't belong to the jet category, ignore
            continue;
        }

        $calendarCount++;
    }

    return $calendarCount;
}


/**
 * Calculating ending time for the rent (based on selected renting time)
 *
 * @param $date
 * @param $hour string for the starting time (e.g 12:00)
 * @param $rentTime string for rent time (e.g. 01:30)
 *
 * @return String for the ending time, e.g. "16:30"
 */
function getEndTime($date, $hour, $rentTime) 
{
    // Translate the hours and minutes (e.g. 01:30) into seconds:
    $seconds = (((int)explode(":", $rentTime)[0]) * 3600) + (((int)explode(":", $rentTime)[1]) * 60 - 1);
    // add the seconds to the time
    $endTime = strtotime($date." ".$hour. "+" .$seconds." seconds");

    // If time goes over midnight
    if (strcmp( date("Y-m-d", $endTime), $date) != 0)
        return "23:59";

    return date("H:i", $endTime);
}


function getJetCategoryTerm() 
{
    // Find the jet product category (WordPress term object)
    // TODO: Make the category name configurable (and maybe use slug instead?)
    $possibleJetCategoryTerms = get_terms(array("taxonomy" => "product_cat", "name" => "Jetit"));
    if (count($possibleJetCategoryTerms) != 1) {
        // The categories are not what we expect
        SwLogger::logError("Illegal amount of matches on jet product category term query: " . print_r($possibleJetCategoryTerms, true));
        wp_send_json_error($this->l["errors"]["general_error"], 500);
    }
    return $possibleJetCategoryTerms[0];
}

function isJetCategory($product, $jetCategoryTerm) 
{
    // Check that the product belongs to the jet category or one of its subcategories
    $categories = $product->get_category_ids();
    // Get also the category parent IDs
    // TODO: Handle errors
    // Get parent IDs from category terms and filter out zeroes
    $categoryParents = array_filter(array_map(function ($c) { return get_term($c)->parent; }, $categories), function ($c) { return $c > 0; });
    $mergedCategories = array_merge($categories, $categoryParents);
    if (!(in_array($jetCategoryTerm->term_id, $mergedCategories) || in_array($jetCategoryTerm->parent, $mergedCategories))) {
        return false;
    }
    return true;
}


/**
 * Reset added jet reservations (clear cart)
 *
 * This will be called if a reservation creation fails and there might be other reservations already created
 */
function resetAddedJetReservations() {
    // TODO
}

/**
 * Creates a new reservation and adds it to the WooCommerce cart
 *
 * @param $reservations array Array of JetReservations
 * @param $lang string Language code for Pinpoint (e.g. "fi", "en")
 * @return bool True if all the reservations were added successfully, false otherwise
 *
 * @throws JetNotAvailableException if a reservation could not be made because one jet is unavailable at the given time
 * @throws JetCartOverlapException if the cart contains a reservation for the same jet
 * @throws JetReservationFailedException if the reservation failed
 */
function addJetReservations($reservations, $lang) {
    global $wpdb;
    global $DOPBSP;
    global $DOPBSPWooCommerce;

    $ppWooCommerceCart = $DOPBSPWooCommerce->classes->cart;

    // The general logic and operations are copied from the Pinpoint source

    // Set Pinpoint language
    $DOPBSP->classes->translation->set($lang, false, array("frontend", "calendar", "woocommerce_frontend"));

    // Verify that all the reservation timeslots are available
    foreach ($reservations as $reservation) {
        if (!$ppWooCommerceCart->validateAvailability($reservation->getCalendarId(), $reservation->getPinpointReservationData())) {
            // Not available
            throw new JetNotAvailableException($reservation);
        }
    }

    foreach ($reservations as $reservation) {
        // Calculate reservation duration for the discount
        $durationHours = $reservation->getDurationHours();
        // In the production environment each time step represents 30 minutes, so to calculate the base price,
        // we have to multiply duration hours by 2.
        $priceMultiplier = $durationHours * 2;

        // Get the base price from the database
        $stmt = $wpdb->prepare("SELECT price_max FROM " . $DOPBSP->tables->calendars . " WHERE id = %d", $reservation->getCalendarId());
        $res = $wpdb->get_row($stmt);
        if (!$res) {
            // Couldn't get calendar information
            SwLogger::logError("Couldn't get calendar information for ID " . $reservation->getCalendarId());
            throw new JetReservationFailedException($reservation);
        }
        $slotPrice = $res->price_max;
        // Calculate the base price
        $basePrice = $slotPrice * $priceMultiplier;

        $reservation->setBasePrice($basePrice);

        // Get the discount information from the database
        // First, get the discount ID for this calendar
        $stmt = $wpdb->prepare("SELECT value FROM " . $DOPBSP->tables->settings_calendar . " WHERE name = 'discount' AND calendar_id = %d", $reservation->getCalendarId());
        $res = $wpdb->get_row($stmt);
        if ($res != null) {
            if (!$res) {
                // Fail
                SwLogger::logError("Couldn't get discount ID for calendar " . $reservation->getCalendarId());
                throw new JetReservationFailedException($reservation);
            }
            $discountId = intval($res->value);
            // Get discount item
            $stmt = $wpdb->prepare("SELECT * FROM " . $DOPBSP->tables->discounts_items . " WHERE discount_id = %d AND start_time_lapse = %f", $discountId, $durationHours);
            $res = $wpdb->get_row($stmt);
            if (!$res) {
                // Fail
                SwLogger::logError("Couldn't get discount item for calendar " . $reservation->getCalendarId() . " and discount " . $discountId);
                throw new JetReservationFailedException($reservation);
            }

            /* TODO: It might be possible to not set the discount infromation so that the end user won't see the discounts in the cart,
                     but it still would count towards the price. */
            $reservation->setDiscountInfo($res);
        }

        // Create Pinpoint reservation data
        $resData = array(
            "cart_item_key" => "",
            "token" => "",
            "product_id" => $reservation->getProductId(),
            "calendar_id" => $reservation->getCalendarId(),
            "language" => $lang,
            "currency" => "&#8364;",    // "€"
            "currency_code" => "EUR",
            "data" => json_encode($reservation->getPinpointReservationData())
        );

        // Check if the product already exists in the cart
        $cart = wc()->cart->get_cart();

        $cartAddOk = false;

        foreach ($cart as $cartItemKey => $cartItem) {
            if ($cartItem["product_id"] == $reservation->getProductId()) {
                $resData["cart_item_key"] = $cartItemKey;
                $resData["token"] = $cartItem["dopbsp_token"];

                if (!$ppWooCommerceCart->validateOverlap($reservation->getCalendarId(), $reservation->getProductId(), $resData["cart_item_key"], $resData["token"], $reservation->getPinpointReservationData())) {
                    // Overlap with an item in the cart
                    if (count($reservations) > 1) {
                        resetAddedJetReservations();
                    }
                    throw new JetCartOverlapException($reservation);
                }
                // Insert to the database
                $dbOpResult = $wpdb->insert($DOPBSPWooCommerce->tables->woocommerce, $resData);
                if (!$dbOpResult) {
                    // Failed
                    SwLogger::logError("Reservation DB insert failed for existing cart item");
                    if (count($reservations) > 1) {
                        resetAddedJetReservations();
                    }
                    throw new JetReservationFailedException($reservation);
                }
                // Success
                $cartAddOk = true;
                SwLogger::logDebug("Added reservation for jet (cal: " . $reservation->getCalendarId() . ", prod: " . $reservation->getProductId() . ", cart key: " . $cartItemKey . ")");
                break;
            }
        }

        if ($cartAddOk) {
            // Continue to the next reservation
            continue;
        }

        // If the product does not exist, add it and attach the reservation to it
        $token = $DOPBSP->classes->prototypes->getRandomString(64);
        try {
            $cartItemKey = wc()->cart->add_to_cart($reservation->getProductId(), 1, 0, array(), array("dopbsp_token" => $token));
        } catch (Exception $e) {
            // Cart addition failed
            SwLogger::logError("Failed to add the reservation to the WooCommerce cart: " . $e->getMessage());
            if (count($reservations) > 1) {
                resetAddedJetReservations();
            }
            throw new JetReservationFailedException($reservation);
        }
        if (!$cartItemKey) {
            SwLogger::logError("Failed to add the reservation to the WooCommerce cart: " . print_r($reservation, true));
            if (count($reservations) > 1) {
                resetAddedJetReservations();
            }
            throw new JetReservationFailedException($reservation);
        }

        wc()->cart->maybe_set_cart_cookies();
        $resData["cart_item_key"] = $cartItemKey;
        $resData["token"] = $token;

        $dbOpResult = $wpdb->insert($DOPBSPWooCommerce->tables->woocommerce, $resData);
        if (!$dbOpResult) {
            // Failed
            SwLogger::logError("Reservation DB insert failed for new reservation");
            if (count($reservations) > 1) {
                resetAddedJetReservations();
            }
            throw new JetReservationFailedException($reservation);
        }

        wp_cache_flush();

        // Success
        SwLogger::logDebug("Added reservation for jet (cal: " . $reservation->getCalendarId() . ", prod: " . $reservation->getProductId() . ", cart key: " . $cartItemKey . ")");
    }

    // All reservations added
    return true;
}

/**
 * Get Pinpoint calendar setting by name
 *
 * @param $calId int Pinpoint calendar ID
 * @param $name string Setting name
 * @return null|mixed Setting value or null if no value found
 */
function getPinpointCalendarSetting($calId, $name) {
    global $wpdb;
    global $DOPBSP;

    $stmt = $wpdb->prepare("SELECT value FROM " . $DOPBSP->tables->settings_calendar . " WHERE name = %s AND calendar_id = %d", $name, $calId);
    $res = $wpdb->get_row($stmt);
    if (!$res) {
        // No value for the given name
        return null;
    }
    return $res->value;
}

/**
 * Calculate discounted price for given calendar
 *
 * The returned price may be the same that was given, if the
 * calendar doesn't have a discount item associated with it.
 *
 * @param $calId int Pinpoint calendar ID
 * @param $duration DateInterval Reservation duration
 * @param $basePrice float Price to discount
 * @return int Possibly discounted price
 */
function getDiscountedPrice($calId, $duration, $basePrice) {
    global $wpdb;
    global $DOPBSP;

    $durationHours = $duration->h + ($duration->i / 60);

    // Get the discount information from the database
    // First, get the discount ID for this calendar
    $discountId = getPinpointCalendarSetting($calId, "discount");
    if ($discountId == null) {
        // No discount for this calendar
        return $basePrice;
    }
    $discountId = intval($discountId);
    // Get discount item
    $stmt = $wpdb->prepare("SELECT * FROM " . $DOPBSP->tables->discounts_items . " WHERE discount_id = %d AND start_time_lapse = %f", $discountId, $durationHours);
    $res = $wpdb->get_row($stmt);
    if (!$res) {
        // No discount item for this duration
        return $basePrice;
    }

    return $basePrice - $res->price;
}

/**
 * Get calendar extras from Pinpoint
 *
 * @param $calId int Pinpoint calendar ID
 * @return null|array Array of Pinpoint extras for the given calendar or null if failed
 */
function getCalendarExtras($calId) {
    global $wpdb;
    global $DOPBSP;

    // Get extras ID for the calendar
    $extraId = getPinpointCalendarSetting($calId, "extra");
    if ($extraId == null) {
        // No extras for this calendar
        return null;
    }
    $extraId = intval($extraId);

    // Get extra groups
    $stmt = $wpdb->prepare("SELECT id, position, required, translation FROM ".$DOPBSP->tables->extras_groups." WHERE extra_id = %d ORDER BY position", $extraId);
    $groupRes = $wpdb->get_results($stmt);
    if (!$groupRes) {
        // No extra groups for this extra
        SwLogger::logWarning("No extra groups for extra ID " . $extraId);
        return null;
    }

    $items = array();

    // Get extra items for each group
    foreach ($groupRes as $group) {
        $stmt = $wpdb->prepare(
            "SELECT id, position, operation, price, price_type, price_by, default_value, translation FROM ".$DOPBSP->tables->extras_groups_items." WHERE group_id = %d ORDER BY position",
            $group->id);
        $itemRes = $wpdb->get_results($stmt);

        $transl = json_decode($group->translation);
        $groupName = utf8_decode(array_key_exists("fi", $transl) ? $transl->fi : $transl->en);

        // Add group ID and translation
        foreach ($itemRes as $item) {
            $transl = json_decode($item->translation);

            $iObj = new stdClass();
            $iObj->id = $item->id;
            $iObj->name = utf8_decode(array_key_exists("fi", $transl) ? $transl->fi : $transl->en);
            $iObj->operation = $item->operation;
            $iObj->price = $item->price;
            $iObj->price_type = $item->price_type;
            $iObj->price_by = $item->price_by;
            // Determine if this extra is the gas extra.
            // This is really not nice way to do this, but we have no better alternatives right now.
            $iObj->is_gas = strtolower($groupName) == "polttoaine" && strtolower($iObj->name) == "polttoaine sisältyy";

            $items[] = $iObj;
        }
    }

    return $items;
}

function getCalendarExtrasForBackend($calId, $extraIds) {
    global $wpdb;
    global $DOPBSP;

    // Get extra items
    if (count($extraIds) == 0) {
        // No extra items
        return array();
    }

    // Join the extra IDs into an escaped array for the SQL query
    $escIds = array_map(function ($id) {
        return esc_sql($id);
    }, $extraIds);
    $escIds = implode(",", $escIds);

    $res = $wpdb->get_results(
        "SELECT i.id, group_id, g.translation AS group_transl, operation, price_by, price_type, price, i.translation AS item_transl
         FROM ".$DOPBSP->tables->extras_groups_items." i
         JOIN ".$DOPBSP->tables->extras_groups." g ON i.group_id = g.id
         WHERE i.id IN (".$escIds.")"
    );
    if (!$res) {
        // No results
        SwLogger::logError("No extras found for calendar " . $calId . " with extras: " . $escIds);
        return null;
    }

    $hasGasExtra = false;
    $gasExtra = null;

    // Get translation texts
    foreach ($res as $extra) {
        $groupT = json_decode($extra->group_transl);
        $itemT = json_decode($extra->item_transl);
        $groupName = utf8_decode(array_key_exists("fi", $groupT) ? $groupT->fi : $groupT->en);
        $itemName = utf8_decode(array_key_exists("fi", $itemT) ? $itemT->fi : $itemT->en);
        $extra->group_translation = $groupName;
        $extra->translation = $itemName;
        if (strtolower($groupName) == "polttoaine" && strtolower($itemName) == "polttoaine sisältyy") {
            $hasGasExtra = true;
            $gasExtra = $extra;
        }
    }

    $ret = new stdClass();
    $ret->extras = $res;
    $ret->hasGasExtra = $hasGasExtra;
    $ret->gasExtra = $gasExtra;

    return $ret;
}

function getGasExtraForCalendar($calId) {
    $extras = getCalendarExtras($calId);
    if ($extras == null) {
        SwLogger::logError("No extras for calendar " . $calId);
        return null;
    }
    $gasExtras = array_filter($extras, function ($val) {
        return $val->is_gas;
    });
    if (count($gasExtras) != 1) {
        SwLogger::logError("Invalid gas extra count of " . count($gasExtras) . ", calendar: " . $calId);
        return null;
    }
    // Get the first element ID
    reset($gasExtras);
    $idArr = array(current($gasExtras)->id);
    return getCalendarExtrasForBackend($calId, $idArr);
}
