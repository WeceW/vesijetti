"use strict";

class SearchWidgetTimeSearch {

    searchAvailableTimes() {
        const date = jQuery("#search2-date-from").datepicker("getDate");
        const time = jQuery("#search2-time").val();
        const quantity = jQuery("#search2-quantity").val();
        const showNextDays = true;

        const closingTime = jQuery("#sw-hidden-closing-time").val();

        // Update values on available items -tab
        jQuery("#search1-time").val(time);
        jQuery("#search1-date").datepicker("setDate", date);

        const data = {
            "action": "available_times",
            "date": date.toLocaleDateString(),
            "time": time
        };

        const resultInfoBar = jQuery("#sw-search2-result-info").removeClass(" sw-error");

        // Clear search results
        const resultElem = jQuery("#sw-search2-results");
        resultElem.empty();

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
            resultInfoBar.text(_l("pick_start_time"));
            resultInfoBar.slideDown("fast");

            if (resp.data.available_times.length === 0) {
                // No times
                resultInfoBar.text(_l("no_results"));
                resultInfoBar.slideDown("fast");
                return;
            }

            for (let i = 0; i < resp.data.available_times.length; i++) {
                const entry = resp.data.available_times[i];
                const htmlDay = jQuery("<div class='sw-search2-results-day'>");
                const date = new Date(entry.dateStr);

                // headerStr format: "Weekday DD.MM.YYYY"
                const headerStr = `${_l("weekdays")[date.getDay()]} ${date.getDate()}.${date.getMonth()+1}.${date.getFullYear()}`;

                // Headers & table for every hour
                let htmlDayTable;
                if (entry.is_first_day || showNextDays) {
                    htmlDay.append(jQuery(`<div class='sw-search2-results-table-header'>${headerStr}</div>`));
                    htmlDayTable = jQuery("<div class='sw-search2-results-table'>");
                } else {
                    htmlDay.addClass("hidden");
                    htmlDay.append(jQuery(`<div class='sw-search2-results-table-header hidden'>${headerStr}</div>`));
                    htmlDayTable = jQuery("<div class='sw-search2-results-table hidden'>");
                }

                // Every starting hour
                const now = new Date();
                for (const key in entry.hours) {
                    if (entry.hours.hasOwnProperty(key)) {
                        const starttime = new Date(Date.parse(entry.dateStr.split("-").join("/") + " " + key));
                        const dateTimeStr = key+"-"+SearchWidget.getEndTime(starttime, entry.rent_time);
                        if (entry.hours[key]['available'] && entry.hours[key]['quantity_available'] >= quantity && starttime > now){
                            // Success: selected amount of items available for this particular starting time
                            htmlDayTable.append(jQuery("<div class='sw-search2-results-hour' onclick=\"SearchWidgetInst.search2.switchToSearch1('"+key+"', '"+entry.rent_time+"', '"+entry.dateStr+"')\">" +
                                key +
                                "<div class=\"sw-info-box\">" +
                                "<b class=\"sw-infotext-success\">" + dateTimeStr + "</b>" +
                                "<b class=\"sw-infotext-success\">" + _l("n_items", [entry.hours[key]['quantity_available']]) + "</b><br>" +
                                _l("show_available_items") +
                                "</div>" +
                                "</div>"));
                        } else {
                            // No items available
                            let infotext = `${_l("no_items_available")} <br>`;
                            let quantityLabel = `<b class=\"sw-infotext-fail\">${_l("n_items", [entry.hours[key]['quantity_available']])}</b><br>`;
                            if (!(starttime > now)) {
                                // Starting time is in the past
                                infotext = _l("time_has_passed");
                                quantityLabel = "<br>";
                            }
                            else if (new Date((entry.dateStr.split("-").join("/") + " " + SearchWidget.getEndTime(starttime, entry.rent_time))) > new Date(entry.dateStr.split("-").join("/") + " " + closingTime)) {
                                // End time is after closing time.
                                infotext = _l("over_closing_time");
                                quantityLabel = "<br>";
                            }
                            htmlDayTable.append(jQuery("<div class=\"sw-search2-results-hour unavailable\">" +
                                key +
                                "<div class=\"sw-info-box\"><b class=\"sw-infotext-fail\">"+dateTimeStr+"</b>" +
                                quantityLabel +
                                infotext +
                                "</div>" +
                                "</div>"));
                        }
                    }
                }
                htmlDay.append(htmlDayTable);
                resultElem.append(htmlDay);
            }


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

    /**
     * Shows or hides next 6 six days from the available times list
     *
     */
    static showNextDays()
    {
        const days = jQuery(".sw-search2-results-day");
        const headers = jQuery(".sw-search2-results-table-header");
        const hours = jQuery(".sw-search2-results-table");

        for (let i = 1; i < headers.length; i++) {
            days[i].classList.toggle('hidden');
            headers[i].classList.toggle('hidden');
            hours[i].classList.toggle('hidden');
        }
        jQuery('#sw-show-week-button')[0].classList.toggle('open');

        // This is hidden field, for the ajax call
        const checkbox = document.getElementById('sw-show-next-days-checkbox');
        checkbox.checked = !checkbox.checked;
    }

    /**
     * Switches to "search 1" tab and
     *
     */
    switchToSearch1(startTime, time, dateStr)
    {
        // Set DATE to datepicker in search 1
        const search1Date = jQuery("#search1-date");
        search1Date.datepicker("setDate", new Date(dateStr));

        // Set START TIME to drop down list in search 1
        const startTimeDropDown = jQuery('#search1-time-from')[0];
        for (let i = 0; i < startTimeDropDown.options.length; i++)
            if (startTimeDropDown.options[i].value === startTime)
                startTimeDropDown.options[i].selected = true;

        SearchWidgetInst.openTab("sw-tab-1-content");
    }


}
