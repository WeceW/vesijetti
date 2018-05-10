<?php

/*
Plugin Name: Vesijettien hakuwidget
Description: Hakuwidget, jolla voidaan hakea vapaita vesijettejä tai sopivia aloitusaikoja.
Author: Tampereen vesijettivuokraus
Version: 1.0.0
*/

// AJAX
require_once "backend/ajax.php";

class JetSearch {

    private $l;
    private $ajax;
    private $langCode;

    function __construct() {
        // Setup shortcodes and actions
        add_shortcode("search-widget", array($this, "search_widget"));
        add_action("wp_enqueue_scripts", array($this, "search_widget_scripts"));

        // Get translations
        $this->l = json_decode(file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/wp-content/plugins/search-widget/lang/lang-FI.json"), true);

        // Get Pinpoint language information
        $this->initialize_language();

        // Init AJAX
        $this->ajax = new JetSearchAJAX($this->l, $this->langCode);
    }

    function initialize_language() {
        global $wpdb;
        global $DOPBSP;

        // Get active Pinpoint languages
        $res = $wpdb->get_results("SELECT code FROM ".$DOPBSP->tables->languages." WHERE enabled = 'true'");

        $activeLangs = array();
        foreach ($res as $row) {
            array_push($activeLangs, $row->code);
        }

        if (in_array("fi", $activeLangs)) {
            // Use fi
            $this->langCode = "fi";
        } else {
            // Use en
            $this->langCode = "en";
        }
    }

    // Search widget view
    function search_widget() {
        // Include styles
        wp_enqueue_style("search-widget");
        wp_enqueue_style("font-awesome");
        wp_enqueue_style("jquery-ui-css", "https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css");

        // Load scripts
        wp_enqueue_script("search-widget-js");

        // Insert the view
        require_once "backend/helpers.php";
        require_once "views/search-widget-views.php";
    }

    function search_widget_scripts() {
        wp_register_style("search-widget", plugins_url("/search-widget/views/assets/css/search-widget.css?v=".time()), array(), null);

        wp_register_style("font-awesome", plugins_url("/search-widget/views/assets/css/font-awesome.min.css".time()), array(), null);

        wp_register_script("my-ajax-handle", plugins_url("/search-widget/views/assets/js/ajax.js?v=".time()), array("jquery"), null, true);
        wp_localize_script("my-ajax-handle", "the_ajax_script", array("ajaxurl" => admin_url("admin-ajax.php")));

        wp_register_script("search-widget-lang", plugins_url("/search-widget/views/assets/js/lang.js?v=".time()));
        wp_register_script("datepicker-fi", plugins_url("/search-widget/views/assets/js/datepicker-fi.js?v=".time()));

        wp_register_script("search-widget-js", plugins_url("/search-widget/views/assets/js/search-widget.js?v=".time()), array("datepicker-fi", "search-widget-search1", "search-widget-search2", "jquery-ui-core"), null, true);
        wp_register_script("search-widget-search1", plugins_url("/search-widget/views/assets/js/search-widget-search1.js?v=".time()), array("my-ajax-handle", "search-widget-lang"), null);
        wp_register_script("search-widget-search2", plugins_url("/search-widget/views/assets/js/search-widget-search2.js?v=".time()), array("my-ajax-handle", "search-widget-lang"), null);
    }
}

// Initialize the plugin
new JetSearch();
