<div class="sw-content-toolbar">
    <span class='sw-content-toolbar-icon sw-icon fa fa-caret-left' onclick="SearchWidgetInst.changeAllDates(-1)">
        <div class="sw-info-box"><?php echo $this->l['previous_day']; ?></div>
    </span>
    <span class='sw-content-toolbar-icon sw-icon fa fa-refresh' onclick="SearchWidgetInst.refreshAllSearches()">
        <div class="sw-info-box"><?php echo $this->l['refresh_search']; ?></div>
    </span>
    <span class='sw-content-toolbar-icon sw-icon fa fa-caret-right' onclick="SearchWidgetInst.changeAllDates(1)">
        <div class="sw-info-box"><?php echo $this->l['next_day']; ?></div>
    </span>
    <input type="checkbox" id="sw-show-next-days-checkbox">
</div>
