<?php
/**
 * Application Version Configuration
 * Update this version number when you make changes to CSS/JS files
 * This enables proper browser caching while allowing cache invalidation when needed
 */

// Current application version - increment when deploying changes to static assets
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.2.8');
}

// Asset version helper function
function asset_version() {
    return APP_VERSION;
}
