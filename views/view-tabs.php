<div class="sw-tab-bar">
    <div class="sw-tabs">
        <div id="sw-tab-1" class="sw-tab tab-active" onclick="SearchWidgetInst.openTab('sw-tab-1-content')">
            <span class="fa fa-search sw-icon"></span>
            <span class="sw-tab-label"><?php echo " ".$this->l['tab_bar']['tab_1']; ?></span>
        </div>
        <div id="sw-tab-2" class="sw-tab" onclick="SearchWidgetInst.openTab('sw-tab-2-content')">
            <span class="fa fa-calendar sw-icon"></span> 
            <span class="sw-tab-label"><?php echo " ".$this->l['tab_bar']['tab_2']; ?></span>
        </div>
        <div id="sw-tab-h" class="sw-tab" onclick="SearchWidgetInst.openTab('sw-tab-h-content')">
            <span class="fa fa-question-circle sw-icon"></span> 
            <span class="sw-tab-label"><?php echo " ".$this->l['tab_bar']['tab_h']; ?></span>
        </div>
    </div>
    <div class="sw-title">
        <!-- <span class="fa fa-angellist sw-icon"></span> -->
        <!-- <?php echo $this->l['tab_bar']['title']; ?> -->
    </div>
</div>
