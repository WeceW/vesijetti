<div id="sw-tab-1-content" class="sw-content-container" style="display:none">
    <div class="sw-content-header sw-search1">
        <span class="sw-tab-title"><?php echo " ".$this->l['tab_bar']['tab_1']; ?></span>

        <span class="sw-tab-content-description">"<?php echo $this->l['descr_tab_1']; ?>"</span>

        <div class="sw-content-header-col">
            <div class="sw-input-group">
                <span class="sw-input-label"><?php echo $this->l['pick_day']; ?>:</span>
                <span class="fa fa-calendar sw-icon">
                    <div class="sw-info-box"><?php echo $this->l['pick_day_long']; ?></div>
                </span>
                <input type="text" id="search1-date" class="sw-input sw-input-date" onchange="SearchWidgetInst.search1.searchAvailableItems()">
            </div>
        </div>

        <div class="sw-content-header-col">
            <div class="sw-input-group">
                <span class="sw-input-label"><?php echo $this->l['pick_rent_time']; ?>:</span>
                <span class="fa fa-hourglass-start sw-icon">
                    <div class="sw-info-box"><?php echo $this->l['pick_rent_time_long']; ?></div>
                </span>
                <?php 
                    $hours = getStartingTimes();
                    array_shift($hours); // First starting time out -> Rent time can't be zero minutes
                    
                    $openingTime = "23:59";
                    foreach ($hours as $hour) {
                        // Probably not the most optimal way of comparing these kinds of "time" strings...
                        $openingTime = min($openingTime, $hour);
                    }

                    echo "<select id=\"search1-time\" class=\"sw-input\" onchange=\"SearchWidgetInst.search1.searchAvailableItems()\">";
                    foreach ($hours as $hour) {
                        $time = explode(":", $hour);
                        // Subtract opening time (hours) from current hour
                        // TODO?: Assuming now that opening time is always xx:00...
                        $time[0] = intval($time[0]) - intval(explode(":", $openingTime)[0]);
                        $time[1] = intval($time[1]);
                        if ($time[0] < 8 || ($time[0] == 8 && $time[1] == 0)) {
                            // If time is 8:00 or less
                            echo "<option value='" . sprintf("%02d:%02d", $time[0], $time[1]) . "'>"; 
                            echo sprintf("%02d:%02d %s", $time[0], $time[1], $this->l['hour_short']) . "</option>";
                        }
                    } 
                    echo "</select>";
                ?>
            </div>
        </div>

        <div class="sw-content-header-col">
            <div class="sw-input-group">
                <span class="sw-input-label"><?php echo $this->l['time_from']; ?>:</span>
                <span class="fa fa-clock-o sw-icon">
                    <div class="sw-info-box"><?php echo $this->l['time_from_long']; ?><br><?php echo $this->l['time_until_long']; ?></div>
                </span>
                <?php 
                    $hours = getStartingTimes();
                    $closingTime = array_pop($hours); // Last hour can't be selected as starting time
                    $openingTime = $closingTime;

                    echo "<select id=\"search1-time-from\" class=\"sw-input\" onchange=\"SearchWidgetInst.search1.searchAvailableItems()\">";
                    date_default_timezone_set("Europe/Helsinki");
                    $now = date("H:i", time());
                    $firstUpcomingSeen = false;
                    $firstUpcomingStartTime = "";
                    foreach ($hours as $hour) {
                        $openingTime = min($openingTime, $hour);
                        echo "<option value='" . $hour . "'";
                        if ($hour > $now && !$firstUpcomingSeen) {
                            $firstUpcomingSeen = true;
                            $firstUpcomingStartTime = $hour;
                            echo " selected='selected'";
                        }
                        echo ">" . $hour . "</option>";
                    } 
                    echo "</select>";
                    echo "<input type='hidden' id='sw-hidden-closing-time' value='".$closingTime."' />";
                    echo "<input type='hidden' id='sw-hidden-opening-time' value='".$openingTime."' />";
                ?>
                &mdash;
                <span class="sw-input-endtime" id="search1-time-until-text"></span>
            </div>
        </div>

    </div>

    <div class="sw-content-results">
        
        <?php include "view-toolbar.php"; ?>

        <div id="sw-jet-result-info" class="sw-content-infobar">
        </div>

        <div id="sw-search1-results">
        </div>

        <div class="sw-content-footer" id="sw-search1-content-footer">
            <div id="search1-selection-info"></div>
            <button class="sw-button" onclick="SearchWidgetInst.search1.addToCart()">
                <span class="fa fa-cart-plus sw-icon"></span>
                <?php echo $this->l['add_to_cart']; ?>
            </button>
        </div>
        
    </div>
</div>
