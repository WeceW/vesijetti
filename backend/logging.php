<?php

// Logging helpers

class SwLogger {

    public static function logError($message) {
        wc_get_logger()->error($message);
    }

    public static function logWarning($message) {
        wc_get_logger()->warning($message);
    }

    public static function logInfo($message) {
        wc_get_logger()->info($message);
    }

    public static function logDebug($message) {
        wc_get_logger()->debug($message);
    }
}