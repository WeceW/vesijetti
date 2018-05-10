"use strict";

class SearchWidgetJetSearch {

    constructor() {
        this.results = [];
        this.selected_results_idx = [];

        jQuery("body").on("change", ".sw-extra-checkbox", e => {
            // An extra checkbox was clicked
            const el = jQuery(e.target);
            const extraId = el.data("extra-id");
            const resIdx = el.data("res-idx");
            const checked = el.is(":checked");

            const selectedExtras = this.results[resIdx].selected_extras;
            if (typeof(selectedExtras) === "undefined") {
                // No entry for this result index, add one
                if (checked) {
                    this.results[resIdx].selected_extras = [extraId];
                }
            } else {
                if (checked) {
                    // Add to the list
                    selectedExtras.push(extraId);
                } else {
                    // Remove from the list
                    const idx = selectedExtras.indexOf(extraId);
                    if (idx > -1) {
                        selectedExtras.splice(idx, 1);
                    }
                }
                this.results[resIdx].selected_extras = selectedExtras;
            }

            this.applyExtraPrices();

            // Update the total price text of this jet
            const totalPriceElem = jQuery("#sw-item-total-price-"+resIdx);
            totalPriceElem.text(_l("total_price_w_extras", [this.results[resIdx].total_price]));

            // Update info text
            this.updateSelectionInfoText();
        });

        // Converts "hh:mm" strings to {Date} objects (with the current day)
        String.prototype.timeToDate = function () {
            const now = new Date();
            const split = this.split(":").map(n => parseInt(n, 10));
            return new Date(now.getFullYear(), now.getMonth(), now.getDate(), split[0], split[1]);
        };
    }

    applyExtraPrices() {
        // Go through all selected jets
        this.selected_results_idx.forEach(resIdx => {
            let jetInfo = this.results[resIdx];
            let totalPrice = jetInfo.price;

            // Calculate reservation duration
            const resStart = jQuery("#search1-time-from").val().timeToDate();
            const resEnd = jQuery("#search1-time-until-text").text().timeToDate();
            const diffMs = resEnd - resStart;
            // Milliseconds to seconds -> to minutes -> how many half hours have passed?
            const sliceCount = diffMs / 1000 / 60 / 30;

            // Go through all selected extras for this jet
            if (typeof(jetInfo.selected_extras) === "undefined") {
                // No extras selected, reset total price for this jet
                jetInfo.total_price = jetInfo.price;
                // Ignore
                return;
            }
            // Select all the extras that have their ID in the selected_extras array of the jet object
            let extras = jetInfo.extras.filter(e => jetInfo.selected_extras.includes(parseInt(e.id, 10)));
            for (let a = 0; a < extras.length; a++) {
                const extra = extras[a];
                if (extra.is_gas && sliceCount === 1) {
                    // If this is the gas extra and the reservation duration is 30 minutes, don't add the gas price
                    // here, because the backend has already done that.
                    continue;
                }

                const extraPrice = parseInt(extra.price, 10);
                if (extra.price_type === "fixed") {
                    if (extra.price_by === "once") {
                        if (extra.operation === "+") {
                            totalPrice += extraPrice;
                        } else if (extra.operation === "-") {
                            totalPrice -= extraPrice;
                        } else {
                            // Not supported as of now
                            console.warn(`Unsupported extra operation '${extra.operation}'`);
                        }
                    } else if (extra.price_by === "period") {
                        // The price is for one hour, but the smallest timeslice is 30 minutes
                        const newExtraPrice = extraPrice / 2 * sliceCount;

                        if (extra.operation === "+") {
                            totalPrice += newExtraPrice;
                        } else if (extra.operation === "-") {
                            totalPrice -= newExtraPrice;
                        } else {
                            // Not supported as of now
                            console.warn(`"Unsupported extra operation '${extra.operation}'`);
                        }
                    } else {
                        console.warn(`"Unsupported extra price by '${extra.price_by}'`);
                    }
                } else {
                    // Not supported as of now
                    console.warn(`"Unsupported extra price type '${extra.price_type}'`);
                }

                // Save the total price
                jetInfo.total_price = totalPrice;
            }
            jetInfo.total_price = totalPrice;
            if (extras.length === 0) {
                // No extras selected, reset total price for this jet
                jetInfo.total_price = jetInfo.price;
            }
        });
    }

    searchAvailableItems() {
        const startTimePicker = jQuery("#search1-date");

        const date = startTimePicker.datepicker("getDate");
        const startTime = jQuery("#search1-time-from").val();
        const time = jQuery('#search1-time').val();

        // Transform the "hh:mm" string to an integer array [h, m] and update the date with the values,
        // e.g. "02:00" -> [2, 0]
        // Then calculate the ending time
        const hours = startTime.split(":").map(n => parseInt(n, 10));
        const endTime = SearchWidget.getEndTime(date.setHours.apply(date, hours), time);

        const endDateTime = new Date(date);
        endDateTime.setHours(endDateTime.getHours() + parseInt(time.split(":")[0]));
        endDateTime.setMinutes(endDateTime.getMinutes() + parseInt(time.split(":")[1]));

        const closingHours = jQuery("#sw-hidden-closing-time").val().split(":").map(n => parseInt(n, 10));
        const closingDateTime = new Date(date);
        closingDateTime.setHours.apply(closingDateTime, closingHours);

        const timeSlotCount = (endDateTime - date) / 1000 / 60 / 60 / 0.5;

        // Make footer unactive (no items are selected, since user's making a new search now)
        jQuery("#sw-search1-content-footer").removeClass("active");

        // Update values on available times -tab
        jQuery("#search2-time").val(time);
        jQuery("#search2-date-from").datepicker("setDate", date);

        const data = {
            "action": "available_jets",
            "date": date.toLocaleDateString(),
            "startTime": startTime,
            "endTime": endTime
        };

        const resultInfoBar = jQuery("#sw-jet-result-info").removeClass(" sw-error");

        // Clear search results
        const resultElem = jQuery("#sw-search1-results");
        resultElem.empty();

        // Clear local information
        this.results.length = 0;
        this.selected_results_idx.length = 0;

        // Clear selection info text
        jQuery("#search1-selection-info").text("");

        // Check if end time goes over the closing time
        jQuery("#search1-time-until-text").text(endTime).removeClass("unavailable");
        if (endDateTime > closingDateTime) {
            jQuery("#search1-time-until-text").addClass("unavailable");
            resultInfoBar.text(_l("end_after_closing")).addClass(" sw-error");
            return;
        }

        // TODO: Loading animation?
        resultInfoBar.text(_l("searching"));
        resultInfoBar.slideDown("fast");
        jQuery.post(the_ajax_script.ajaxurl, data, resp => {
            if (!resp.success) {
                // TODO: Replace this with inline error
                resultInfoBar.text(resp.data).addClass(" sw-error");
                return;
            }
            // Success
            const jetCount = resp.data.available_jet_count;
            resultInfoBar.text("");
            const selectedTime = `${_l("weekdays")[date.getDay()]} ${date.getDate()}.${date.getMonth()+1}. klo ${startTime}&mdash;${endTime}`;
            if (jetCount === 1) {
                resultInfoBar.append(jQuery(`<b>${_l("search_res_one")}</b><br>(${selectedTime})<br><br><i>${_l("select_jet")}</i>`));
                resultInfoBar.slideDown("fast");
            } else if (jetCount > 1) {
                resultInfoBar.append(jQuery(`<b>${_l("search_res_n", [jetCount])}</b><br>(${selectedTime})<br><br><i>${_l("select_jets")}</i>`));
            }
            // Display entries
            if (resp.data.available_jets.length === 0) {
                // No jets
                resultInfoBar.text(_l("no_results"));
                resultInfoBar.slideDown("fast");
                return;
            }
            this.results = resp.data.available_jets;
            for (let i = 0; i < resp.data.available_jets.length; i++) {
                // Add a new field to keep track of the total price with extras
                resp.data.available_jets[i].total_price = resp.data.available_jets[i].price;

                const entry = resp.data.available_jets[i];
                const id = i+1;
                const htmlElem = jQuery("<div id='"+id+"' class='sw-search1-results sw-item' data-res-idx='"+i+"' />");
                // Add click event listener
                htmlElem.click(evt => this.selectItem(evt));
                htmlElem.append(jQuery(
                    "<div class=\"sw-search1-results sw-item-col sw-item-checkbox\">" +
                    "<span id=\"sw-item-checkbox-"+id+"\" class=\"fa fa-check sw-icon\"></span>" +
                    "</div>")
                );
                htmlElem.append(jQuery(
                    "<div class=\"sw-search1-results sw-item-col sw-item-img\">" +
                    "<img src=\"" + entry.image + "\">" +
                    "</div>"
                ));
                htmlElem.append(jQuery(
                    "<div class=\"sw-search1-results sw-item-col sw-item-details\">" +
                    "    <span class=\"sw-item-price\">" + _l("from_short") + " " + entry.price + " €</span>" +
                    "    <span class=\"sw-item-title\">" + entry.name + "</span>" +
                    "    <span class=\"sw-item-text\">" + entry.short_desc + "</span>" +
                    "</div>" +
                    "<div class=\"sw-search1-results sw-item-col sw-item-info\">" +
                    "    <a href='" + entry.permalink + "' target='_blank' class=\"fa fa-info-circle sw-icon sw-item-product-link\">" +
                    "        <div class=\"sw-info-box\">" + _l("open_product_page") + "</div>" +
                    "        <div class=\"sw-item-product-link-label\">" + _l("product_page") + "</div>" +
                    "    </a>" +
                    "</div>"
                ));
                resultElem.append(htmlElem);
                // Format extras
                const extrasElem = jQuery("<div id=\"sw-item-extras-"+id+"\" class=\"sw-search1-results sw-item-extras-container hidden\" />");
                extrasElem.append(jQuery("<span class=\"sw-item-extras-title\">"+_l("extras")+" <a href='"+resp.data.extras_category_page_url+"' target='_blank' class='sw-item-extras-info'> "+_l("extras_addition_info")+"</a></span>"));
                if (timeSlotCount === 1) {
                    extrasElem.append(jQuery("<span class='sw-item-extras-title'>"+_l("extras_gas_included")+"</span>"));
                }
                for (let a = 0; a < entry.extras.length; a++) {
                    const extra = entry.extras[a];
                    let priceText = "(" + extra.operation + " ";
                    if (extra.price_by === "once") {
                        priceText += extra.price + " €";
                    } else if (extra.price_by === "period") {
                        priceText += extra.price/2 + " €/30 min";
                    }
                    priceText += ")";
                    if (extra.is_gas && timeSlotCount === 1) {
                        // If this extra is the gas extra and the reservation duration is 30 minutes
                        // Add the gas extra as selected
                        entry.selected_extras = [parseInt(entry.extras.filter(e => e.is_gas)[0].id, 10)];
                        // This is otherwise the same element as the normal one, but the user can't select the checkbox
                        extrasElem.append(jQuery(
                            "<span class='sw-item-extras selected disabled' id='sw-item-extra-"+id+"-"+(a+1)+"'>" +
                            "<input class='sw-extra-checkbox' type='checkbox' checked='true' disabled='true'>&nbsp;" +
                            "<label for='sw-item-extras-"+id+"-"+(a+1)+"'>"+extra.name+"&nbsp;"+priceText+"</label>" +
                            "</span>"
                        ));
                    } else {
                        extrasElem.append(jQuery(
                            "<span class='sw-item-extras' id='sw-item-extra-"+id+"-"+(a+1)+"'>" +
                            "<input class='sw-extra-checkbox' id='sw-item-extras-"+id+"-"+(a+1)+"' type='checkbox' data-res-idx='"+i+"' data-extra-id='"+extra.id+"' onClick='SearchWidgetJetSearch.selectExtraItem("+id+","+(a+1)+")'>&nbsp;" +
                            "<label for='sw-item-extras-"+id+"-"+(a+1)+"'>"+extra.name+"&nbsp;"+priceText+"</label>" +
                            "</span>"
                        ));
                    }
                }
                extrasElem.append(jQuery("<br><span id=\"sw-item-total-price-"+i+"\" class=\"sw-item-total-price \">" + _l("total_price_w_extras", [entry.price]) + "</span>"))
                resultElem.append(extrasElem);
            }
            // Prevent certain clild class(es) from propagation
            // -> I.e. don't trigger the parent elements 'onClick()' function
            jQuery(".sw-item-product-link").click(e => e.stopPropagation());
        })
            .fail((xhr, status, err) => {
                // Error
                const data = xhr.responseJSON;
                // TODO: Display inline error
                if (!data.success) {
                    console.log(data.data);
                    resultInfoBar.text(data.data).addClass(" sw-error");
                } else {
                    resultInfoBar.text(_l("general_error")).addClass(" sw-error");
                    console.log("Unknown error");
                    console.log(data);
                }
            });
    }

    addToCart() {
        const date = jQuery("#search1-date").datepicker("getDate");
        const dateStr = date.toLocaleDateString();
        const startTime = jQuery("#search1-time-from").val();
        const endTime = jQuery("#search1-time-until-text").text();

        // Go through the selected jets and get their prices and calendar/product IDs

        // TODO: Indicate loading

        // Add selected jets to the cart
        const selectedJetsCount = this.selected_results_idx.length;
        if (selectedJetsCount === 0) {
            // No jets
            return;
        }

        const selectedJets = this.results.filter((r, i) => this.selected_results_idx.includes(i));

        const data = {
            action: "add_jets_to_cart",
            date: dateStr,
            startTime: startTime,
            endTime: endTime,
            jets: selectedJets.map(function (j) { return { calId: j.calendar_id, prodId: j.product_id, extras: j.selected_extras }})
        };

        jQuery.post(the_ajax_script.ajaxurl, data, data => {
            if (data.success) {
                if (typeof(data.data.cart_url) !== "undefined") {
                    // Redirect to the cart page
                    window.location = data.data.cart_url;
                }
            } else {
                // TODO: Handle
            }
        })
            .fail((xhr, status, err) => {
                const data = xhr.responseJSON;
                const resultInfoBar = jQuery("#sw-jet-result-info").removeClass(" sw-error");
                jQuery('html, body').animate({scrollTop: jQuery("#sw-jet-result-info").offset().top - 120}, 500);
                if (!data.success) {
                    const reason = data.data.reason;
                    console.error("Add to cart failed, reason: " + reason);
                    console.log(data.data);
                    let errMsgKey = "";
                    switch (reason) {
                        case "not_available":
                            errMsgKey = "err_not_available";
                            break;
                        case "overlap":
                            errMsgKey = "err_overlap";
                            break;
                        case "fail":
                            errMsgKey = "err_reservation_fail";
                            break;
                    }
                    resultInfoBar.text(_l(errMsgKey)).addClass(" sw-error");
                } else {
                    resultInfoBar.text(_l("general_error")).addClass(" sw-error");;
                    console.log("Unknown error");
                    console.log(data);
                }
            });
    }

    selectItem(evt) {
        const id = evt.currentTarget.id;
        const elem = jQuery("#" + id);
        elem.toggleClass("item-selected");
        jQuery("#sw-item-extras-"+id).toggleClass("hidden");

        const resultIdx = elem.data("res-idx");
        // Keep track of selected items
        const selIdx = this.selected_results_idx.indexOf(resultIdx);
        if (selIdx === -1) {
            this.selected_results_idx.push(resultIdx);
        } else {
            this.selected_results_idx.splice(selIdx, 1);
        }

        // Tell footer when it's active (when any products are selected)
        if (this.selected_results_idx.length > 0) {
            jQuery("#sw-search1-content-footer").addClass("active");
        } else {
            jQuery("#sw-search1-content-footer").removeClass("active");
        }

        // Update selection count text
        this.updateSelectionInfoText();
    }

    updateSelectionInfoText() {
        // Update selection count text
        const selectedInfoElem = jQuery("#search1-selection-info").text("");
        let text = "";
        const selectedJetsCount = this.selected_results_idx.length;
        if (selectedJetsCount === 1) {
            text = _l("one_jet_selected");
        } else if (selectedJetsCount > 1) {
            text = _l("n_jets_selected", [selectedJetsCount]);
        }

        // Extra selection text
        //
        // Commented out, because this info text was actually originally meant for
        // the other (additional) products other than jets (e.g. trailers, sup-boards etc.)
        // These were meant to list after the jests in own section, but thats not done (yet?)
        //
        // So... no reason to point out how many extra services are selected?
        //
        /*if (SELECTED_EXTRAS_COUNT > 0) {
            text += " " + _l("jet_selected_and") + " ";
            if (SELECTED_EXTRAS_COUNT === 1) {
                text += _l("one_extra_selected");
            } else {
                text += _l("n_extras_selected", [SELECTED_EXTRAS_COUNT]);
            }
        }*/

        // Total price text
        const prices = this.selected_results_idx.map(i => this.results[i].total_price);
        let totalSum = 0;
        if (prices.length > 0) {
            totalSum = prices.reduce((a, b) => a + b);
        }

        if (selectedJetsCount >= 1) {
            text += "<br>";
            text += _l("total_sum", [totalSum]);
        }

        selectedInfoElem.append(text);
    }

    static selectExtraItem(itemId, extraId) {
        jQuery("#sw-item-extra-"+itemId+"-"+extraId).toggleClass("selected");
    }
}
