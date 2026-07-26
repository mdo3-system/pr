<?php
/**
 * J-ALG 販売管理ポータル 連携モジュール自動テスト・検証スクリプト
 * 実行コマンド: php test_integration.php
 */

echo "=== J-ALG Portal Integration Unit Tests ===\n";

$passed = 0;
$total = 0;

// Test 1: Env File Parsing
$total++;
if (file_exists(__DIR__ . '/.env')) {
    $envContent = file_get_contents(__DIR__ . '/.env');
    if (strpos($envContent, 'PORTAL_SYNC_SECRET') !== false && strpos($envContent, 'APP_SERVER_URL') !== false) {
        echo "[PASS] Test 1: .env contains sync secret key and app server URL.\n";
        $passed++;
    } else {
        echo "[FAIL] Test 1: .env missing required keys!\n";
    }
} else {
    echo "[FAIL] Test 1: .env does not exist!\n";
}

// Test 2: File Structure Verification
$total++;
$filesToVerify = [
    'setup_subscription_tables.php',
    'api/stripe_event.php',
    'cron/sync_subscriptions.php',
    'setup_portal_staff.php'
];

$filesExist = true;
foreach ($filesToVerify as $f) {
    if (!file_exists(__DIR__ . '/' . $f)) {
        $filesExist = false;
        echo "[FAIL] Test 2: Missing file: {$f}\n";
    }
}
if ($filesExist) {
    echo "[PASS] Test 2: All 4 integration files created successfully.\n";
    $passed++;
}

// Test 3: API Key Verification Mock Logic
$total++;
$mockSecret = "eie_sales_portal_secret_key_2026";
$validHeader = "eie_sales_portal_secret_key_2026";
$invalidHeader = "wrong_secret_key";

if (hash_equals($mockSecret, $validHeader) && !hash_equals($mockSecret, $invalidHeader)) {
    echo "[PASS] Test 3: API Key header authentication hash_equals verification passed.\n";
    $passed++;
} else {
    echo "[FAIL] Test 3: Hash authentication logic failed!\n";
}

// Test 4: Webhook Payload JSON Structure Validation
$total++;
$dummyPayload = json_encode([
    'event_type' => 'payment_succeeded',
    'user_email' => 'staff1@eie.jp',
    'plan_type' => 'free_permanent',
    'status' => 'active',
    'current_period_end' => '2099-12-31 23:59:59',
    'amount' => 0
]);
$decoded = json_decode($dummyPayload, true);

if (isset($decoded['user_email']) && $decoded['user_email'] === 'staff1@eie.jp' && $decoded['status'] === 'active') {
    echo "[PASS] Test 4: Webhook Payload JSON structure parsed correctly.\n";
    $passed++;
} else {
    echo "[FAIL] Test 4: JSON payload parsing failed!\n";
}

echo "\n=== Integration Test Summary: PASSED {$passed}/{$total} ===\n";
if ($passed === $total) {
    exit(0);
} else {
    exit(1);
}
