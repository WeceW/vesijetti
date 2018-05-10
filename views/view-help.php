<div id="sw-tab-h-content" class="sw-content-container" style="display:none">
    <div class="sw-content-header sw-help">
        <span class="sw-tab-title"><?php echo " ".$this->l['tab_bar']['tab_h']; ?></span>

        <span class="sw-tab-content-description">"<?php echo $this->l['descr_tab_h']; ?>"</span>
        
        <div class="sw-header-help-container">

            <div class="sw-content-header-col" id="sw-help-box-1">
                <?php
                for ($i = 0; $i < count($this->l['general_info']); $i++) {
                    echo $this->l['general_info'][$i];
                }
                ?>
            </div>

            <div class="sw-content-header-col" id="sw-help-box-2">
                <?php
                echo "<h5 class='sw-primary-color'>" . $this->l['general_info_tab_1'][0] . ":</h5>";
                echo "<ul>";
                for ($i = 1; $i < count($this->l['general_info_tab_1']); $i++) {
                    echo "<li>" . $this->l['general_info_tab_1'][$i] . "</li>";
                }
                echo "</ul>";
                ?>
            </div>

            <div class="sw-content-header-col" id="sw-help-box-3">
                <?php
                echo "<h5 class='sw-secondary-color'>" . $this->l['general_info_tab_2'][0] . ":</h5>";
                echo "<ul>";
                for ($i = 1; $i < count($this->l['general_info_tab_2']); $i++) {
                    echo "<li>" . $this->l['general_info_tab_2'][$i] . "</li>";
                }
                echo "</ul>";
                ?>
            </div>

        </div>
    </div>

    <div class="sw-content-results">

    </div>
</div>
