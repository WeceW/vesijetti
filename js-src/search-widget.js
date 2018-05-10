class SearchWidget {

    constructor() {
        this.lateHour = 20;    // After this hour, the default dates will be tomorrow.
        this.activeTab = "";

        this.search1 = new SearchWidgetJetSearch();
        this.search2 = new SearchWidgetTimeSearch();

        this.init();
    }

    init() {
        const search1Date = jQuery("#search1-date");
        const search2Date = jQuery("#search2-date-from");

        // Initialize the datepickers
        search1Date.datepicker(jQuery.datepicker.regional["fi"]);
        search2Date.datepicker(jQuery.datepicker.regional["fi"]);

        // Setting default dates to datepickers ("today" or "tomorrow").
        const today = new Date();
        const tomorrow = new Date();
        tomorrow.setDate(today.getDate()+1);
        search1Date.datepicker("setDate", today.getHours() >= this.lateHour ? tomorrow : today);
        search2Date.datepicker("setDate", today.getHours() >= this.lateHour ? tomorrow : today);
        if (today.getHours() >= this.lateHour) {
            // Default date is tomorrow -> starting time in the morning:
            jQuery("#search1-time-from").val(jQuery("#sw-hidden-opening-time").val());
        }

        // Tab to open when the page loads
        this.openTab("sw-tab-1-content");
    }

    refreshAllSearches() {
        this.search1.searchAvailableItems();
        this.search2.searchAvailableTimes();
    }

    /**
     * Takes care of swiching between the tabs
     */
    openTab(tabName) {
        if (tabName === this.activeTab) {
            // Same tab, no need to change
            return;
        }

        let i, x, tablinks;
        x = document.getElementsByClassName("sw-content-container");
        for (i = 0; i < x.length; i++) {
            x[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("sw-tab");
        for (i = 0; i < x.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" tab-active", "");
        }
        document.getElementById(tabName).style.display = "block";

        let activeTab = tabName.split('-');
        activeTab.pop();
        activeTab = activeTab.join('-');
        document.getElementById(activeTab).className += " tab-active";

        this.activeTab = tabName;

        if (tabName === "sw-tab-1-content") {
            this.search1.searchAvailableItems();
        } else if (tabName === "sw-tab-2-content") {
            this.search2.searchAvailableTimes();
        }
    }

    changeAllDates(days) {
        const datePickers = jQuery(".sw-input-date");
        for (let i = 0; i < datePickers.length; i++) {
            const date = jQuery(datePickers[i]).datepicker("getDate");
            date.setDate(date.getDate() + days);
            jQuery(datePickers[i]).datepicker("setDate", date);
        }
        this.refreshAllSearches();
    }

    /**
     * Counts the end time for the rend
     * based on the given date (=starting time) and selected duration
     */
    static getEndTime(date, time)
    {
        const sec = (time.split(':')[0]*3600) + (time.split(':')[1]*60);
        const endTime = new Date(date);
        endTime.setSeconds(endTime.getSeconds() + sec);
        return ("0"+endTime.getHours()).slice(-2)+ ":" + ("0"+endTime.getMinutes()).slice(-2);
    }

}

// Pad a number with one zero, if needed (e.g. 2 -> "02", 11 -> "11")
Number.prototype.toPaddedString = function () {
    return this.toString().length === 1 ? "0" + this : this.toString();
};

// Override toLocaleDateString to produce nicer dates
Date.prototype.toLocaleDateString = function () {
    const day = this.getDate();
    const month = this.getMonth() + 1;
    const year = this.getFullYear();
    const monthStr = month.toPaddedString();
    const dayStr = day.toPaddedString();
    return `${year.toString()}-${monthStr}-${dayStr}`;
};


/**
 * Changes the current date in the datepicker
 * Gets amount of days to go back or forward
 * and ID of the target element (datepicker)
 */
/* Commented out because the functionality was changed into changeAllDates(days).
   Still this function might be useful at some point later...
function changeDate(days, targetID) 
{
    var date = new Date(document.getElementById(targetID).value);
    date.setDate(date.getDate() + days);
    document.getElementById(targetID).value = date.toDateInputValue();

    if (targetID == 'search1-date')
        searchAvailableItems();
    if (targetID == 'search2-date-from')
        searchAvailableTimes();
} */

// Initialize the widget
const SearchWidgetInst = new SearchWidget();