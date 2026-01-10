<?php
/**
 * reCAPTCHA Debug Script
 * This file helps diagnose reCAPTCHA configuration issues
 * DELETE THIS FILE after debugging!
 */

define('APP_ACCESS', true);
require_once '../includes/config.php';

header('Content-Type: text/plain');

echo "=== reCAPTCHA Debug Information ===\n\n";

echo "Current Domain: " . $_SERVER['HTTP_HOST'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "HTTPS Enabled: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Yes' : 'No') . "\n\n";

echo "Is Localhost: " . (defined('IS_LOCALHOST') && IS_LOCALHOST ? 'Yes' : 'No') . "\n\n";

echo "Database Config:\n";
echo "  DB_NAME: " . DB_NAME . "\n";
echo "  DB_USER: " . DB_USER . "\n\n";

echo "reCAPTCHA Configuration:\n";
echo "  Site Key: " . RECAPTCHA_SITE_KEY . "\n";
echo "  Secret Key: " . substr(RECAPTCHA_SECRET_KEY, 0, 10) . "..." . "\n\n";

echo "Site Key Length: " . strlen(RECAPTCHA_SITE_KEY) . " characters\n";
echo "Secret Key Length: " . strlen(RECAPTCHA_SECRET_KEY) . " characters\n\n";

echo "Expected Site Key: 6LeJ5kUsAAAAAHJQkM9upH2rVKlFb15MikEPG1gw\n";
echo "Keys Match: " . (RECAPTCHA_SITE_KEY === '6LeJ5kUsAAAAAHJQkM9upH2rVKlFb15MikEPG1gw' ? 'Yes' : 'No') . "\n\n";

if (RECAPTCHA_SITE_KEY !== '6LeJ5kUsAAAAAHJQkM9upH2rVKlFb15MikEPG1gw') {
    echo "MISMATCH DETECTED!\n";
    echo "Character-by-character comparison:\n";
    $expected = '6LeJ5kUsAAAAAHJQkM9upH2rVKlFb15MikEPG1gw';
    $actual = RECAPTCHA_SITE_KEY;
    for ($i = 0; $i < max(strlen($expected), strlen($actual)); $i++) {
        $e = isset($expected[$i]) ? $expected[$i] : '(missing)';
        $a = isset($actual[$i]) ? $actual[$i] : '(missing)';
        if ($e !== $a) {
            echo "  Position $i: Expected '$e' but got '$a'\n";
        }
    }
}

echo "\n=== DELETE THIS FILE AFTER DEBUGGING ===\n";
