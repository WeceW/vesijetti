<div id="sw-tab-2-content" class="sw-content-container" style="display:none">
    <div class="sw-content-header sw-search2">
        <span class="sw-tab-title"><?php echo " ".$this->l['tab_bar']['tab_2']; ?></span>

        <span class="sw-tab-content-description">"<?php echo $this->l['descr_tab_2']; ?>"</span>
        
        <div class="sw-content-header-col">
            <div class="sw-input-group">
                <span class="sw-input-label"><?php echo $this->l['pick_day']; ?>:</span>
                <span class="fa fa-calendar sw-icon">
                    <div class="sw-info-box"><?php echo $this->l['pick_day_long']; ?></div>
                </span>
                <input type="text" id="search2-date-from" class="sw-input sw-input-date" onchange="SearchWidgetInst.search2.searchAvailableTimes()">
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

                    echo "<select id=\"search2-time\" class=\"sw-input\" onchange=\"SearchWidgetInst.search2.searchAvailableTimes()\">";
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
            <div class="sw-input-group" id="sw-input-group-quantity">
                <span class="sw-input-label"><?php echo $this->l['pick_quantity']; ?>:</span>
                <span class="fa fa-user-plus sw-icon">
                    <div class="sw-info-box"><?php echo $this->l['pick_quantity_long']; ?></div>
                </span>
                <select class="sw-input" id="search2-quantity" onchange="SearchWidgetInst.search2.searchAvailableTimes()">
                    <?php for ($i = 1; $i <= getPinPointCalendarCount(); $i++) echo "<option value='$i'>$i</option>"; ?>
                </select>
            </div>
        </div>

    </div>

    <div class="sw-content-results">

        <?php include "view-toolbar.php"; ?>

        <div id="sw-search2-result-info"  class="sw-content-infobar">
        </div>
        
        <div id="sw-search2-results">
        </div>

        <!-- <div class="sw-content-footer"> -->
        <!-- </div> -->
        
    </div>
</div>
