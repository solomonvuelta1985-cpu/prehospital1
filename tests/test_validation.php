<?php
/**
 * Basic Unit Tests for Form Validation Functions
 * Run with: php tests/test_validation.php
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Running Form Validation Tests...\n\n";

$tests_passed = 0;
$tests_total = 0;

function test($description, $condition) {
    global $tests_passed, $tests_total;
    $tests_total++;
    if ($condition) {
        echo "✅ PASS: $description\n";
        $tests_passed++;
    } else {
        echo "❌ FAIL: $description\n";
    }
}

// Test validate_date
test('validate_date accepts valid Y-m-d format', validate_date('2024-01-15'));
test('validate_date rejects invalid format', !validate_date('01-15-2024'));
test('validate_date rejects non-existent date', !validate_date('2024-02-30'));

// Test validate_time
test('validate_time accepts valid H:i format', validate_time('14:30'));
test('validate_time rejects invalid format', !validate_time('25:00'));
test('validate_time rejects invalid minutes', !validate_time('14:60'));

// Test validate_datetime
test('validate_datetime accepts valid Y-m-dTH:i format', validate_datetime('2024-01-15T14:30'));
test('validate_datetime rejects invalid format', !validate_datetime('2024-01-15 14:30'));

// Test sanitize
test('sanitize removes HTML tags', sanitize('<script>alert("xss")</script>', false) === htmlspecialchars(strip_tags('<script>alert("xss")</script>'), ENT_QUOTES, 'UTF-8'));
test('sanitize handles arrays', is_array(sanitize(['<b>test</b>', '<i>test2</i>'])));

// Test check_rate_limit (simulate session)
$_SESSION = [];
test('check_rate_limit allows first attempt', check_rate_limit('test_action', 2, 60));
test('check_rate_limit allows second attempt', check_rate_limit('test_action', 2, 60));
test('check_rate_limit blocks third attempt', !check_rate_limit('test_action', 2, 60));

// Test check_daily_form_limit (mock data)
$user_id = 1;
test('check_daily_form_limit allows under limit', check_daily_form_limit($user_id, 50));

// Test malformed input handling
$malformed_data = [
    'form_date' => 'invalid-date',
    'patient_name' => '',
    'date_of_birth' => 'not-a-date',
    'age' => 'not-a-number',
    'gender' => 'invalid-gender'
];

test('validate_date rejects malformed date', !validate_date($malformed_data['form_date']));
test('validate_date rejects malformed DOB', !validate_date($malformed_data['date_of_birth']));

// Test array validation
$valid_array = ['item1', 'item2'];
$invalid_array = 'not-an-array';

test('sanitize handles valid array', is_array(sanitize($valid_array)));
test('sanitize handles string input', is_string(sanitize($invalid_array)));

echo "\nTest Results: $tests_passed / $tests_total passed\n";

if ($tests_passed === $tests_total) {
    echo "🎉 All tests passed!\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Please review the validation functions.\n";
    exit(1);
}
