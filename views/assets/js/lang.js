var _l = function () {

    // Localized strings
    var loc = {
        "en": {
            "weekdays":             ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday" , "Saturday"],
            "next_days":            "Next days", 
            "show_available_items": "Show available jets!", 
            "no_items_available":   "Not enough jets available.",
            "time_has_passed":      "Starting time has already passed.",
            "pick_start_time":      "Pick a suitable starting time, or adjust search settings.",
            "searching":            "Searching...",
            "search_res_one":       "For the selected time found one jet ski.",
            "search_res_n":         "For the selected time found %s jet skis.",
            "select_jet":           "If you would like to rent this jet, select it and add it into the cart.",
            "select_jets":          "Pick one or more jets from the list, and add them into the cart.",
            "no_results":           "No results.",
            "check_search_times":   "Check the start time and the date.",
            "check_date_input":     "Check the date.", 
            "end_after_closing":    "End time is after closing time.",
            "product_page":         "Product page", 
            "open_product_page":    "Open product page in new tab.", 
            "from_short":           "from",
            "hour_short":           "h",
            "extras":               "Extras:",
            "extra_own_fuel":       "Without gasoline",
            "extras_addition_info": "Additional info about extras",
            "general_error":        "Something went wrong. Please try again.",
            "one_jet_selected":     "One jet selected",
            "n_jets_selected":      "%s jets selected",
            "n_items":              "%s pcs.",
            "jet_selected_and":     "and",
            "one_extra_selected":   "one extra",
            "n_extras_selected":    "%s extras selected",
            "err_not_available":    "One or multiple of the selected jets are not available at the selected time.",
            "err_overlap":          "One or more of the selected jets reservation times are overlapping with one reservation already in the cart.",
            "err_reservation_fail": "Could not add one or more of the selected jets into the cart due to an unexpected error.",
            "total_sum":            "Total %s € (inc. VAT)",
            "total_price_w_extras": "Total (w/ extras): %s €",
            "extras_gas_included":  "Reservations for 30 minutes include the gas"
        },
        "fi": {
            "weekdays":             ["Sunnuntai", "Maanantai", "Tiistai", "Keskiviikko", "Torstai", "Perjantai" , "Lauantai"],
            "next_days":            "Seuraavat päivät", 
            "show_available_items": "Näytä vapaana olevat vesijetit!", 
            "no_items_available":   "Ei riittävää määrää laitteita saatavilla.", 
            "time_has_passed":      "Ajankohta on jo mennyt.",
            "over_closing_time":    "Lopetus sulkemisajan ulkopuolella.",
            "pick_start_time":      "Valitse sopiva aloitusaika, tai säädä hakuasetuksia.",
            "searching":            "Haetaan...",
            "search_res_one":       "Valitulle ajalle löytyi yksi vesijetti.",
            "search_res_n":         "Valitulle ajalle löytyi %s vesijettiä.",
            "select_jet":           "Mikäli haluat varata tämän laitteen, valitse se ja lisää lopuksi ostoskoriin.",
            "select_jets":          "Valitse listalta yksi tai useampi vesijetti, kuhunkin laitteeseen tarvittavat lisävarusteet ja lopuksi lisää tuotteet ostoskoriin alareunasta löytyvällä painikkeella.",
            "no_results":           "Valittuna aikana yhtään vesijettiä ei ole vapaana.",
            "check_search_times":   "Tarkista antamasi aloitusaika ja päiväys.",
            "check_date_input":     "Tarkista antamasi päivämäärä.", 
            "end_after_closing":    "Valittu lopetusaika on sulkemisajan jälkeen. Muuta ajoajan kestoa lyhyemmäksi tai aloitusaikaa aikaisemmaksi.",
            "product_page":         "Tuotesivu", 
            "open_product_page":    "Avaa tuotesivu uuteen välilehteen.", 
            "from_short":           "alk.",
            "hour_short":           "t",
            "extras":               "Lisäpalvelut:",
            "extra_own_fuel":       "Oma tankkaus",
            "extras_addition_info": "Lisätietoa varusteista",
            "general_error":        "Jotain meni vikaan, yritä uudestaan.",
            "one_jet_selected":     "Valittuna yksi vesijetti",
            "n_jets_selected":      "Valittuna %s vesijettiä",
            "n_items":              "%s kpl",
            "jet_selected_and":     "ja",
            "one_extra_selected":   "yksi lisävaruste",
            "n_extras_selected":    "%s lisävarustetta",
            "err_not_available":    "Yksi tai useampi valittu laite ei ole saatavilla valittuun aikaan.",
            "err_overlap":          "Yhden tai useamman laitteen varausaika on päällekäinen jo ostoskorissa olevan laitteen varausajan kanssa.",
            "err_reservation_fail": "Yhden tai useamman laitteen lisääminen ostoskoriin epäonnistui.",
            "total_sum":            "Kokonaishinta %s € (sis. alv.)",
            "total_price_w_extras": "Yhteensä %s €",
            "extras_gas_included":  "Polttoaine sisältyy puolen tunnin varauksien hintaan"
        }
    };

    // Logic ==================================================================

    if (arguments.length === 0) {
        // Not enough arguments
        return;
    }

    var key = arguments[0];
    var lang = "fi";    // Default language is "fi"
    var params = [];

    for (var a = 1; a <= arguments.length; a++) {
        if (Array.isArray(arguments[a])) {
            params = arguments[a];
        } else if (typeof arguments[a] === "string") {
            lang = arguments[a];
        }
    }

    // Always return a string. From other language or "[missing translation]"
    if (Object.keys(loc[lang]).indexOf(key) === -1) {
        // Check "en"
        if (Object.keys(loc["en"]).indexOf(key) === -1) {
            // Unknown key
            return "[missing translation]";
        } else {
            lang = "en";
        }
    }

    // Get the template string
    var rawStr = loc[lang][key];

    // Replace "%s" with parameters
    for (var i = 0; i < params.length; i++) {
        rawStr = rawStr.replace("%s", params[i]);
    }

    return rawStr;
};