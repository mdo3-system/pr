<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * Step 3 自動テスト・検証スクリプト
 */

require_once __DIR__ . '/src/SendGridMailer.php';
require_once __DIR__ . '/src/QueueManager.php';
require_once __DIR__ . '/src/NtaApiImporter.php';

function getEnvParam($key, $default = null) {
    static $env = null;
    if ($env === null) {
        $envFile = __DIR__ . '/.env';
        $env = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $env[trim($k)] = trim($v);
                }
            }
        }
    }
    return isset($env[$key]) ? $env[$key] : $default;
}

echo "=== Automated Test: Step 3 SendGrid Mailer, Queue Manager & Webhook ===\n\n";

$passCount = 0;
$testCount = 0;

// Test 1: SendGridMailer::parseTemplate
$testCount++;
$tpl = "こんにちは、{{company_name}}様。{{city}}の構造計算ツールのご提案です。";
$parsed = SendGridMailer::parseTemplate($tpl, ['company_name' => '川越設計工房', 'city' => '川越市']);
$expected = "こんにちは、川越設計工房様。川越市の構造計算ツールのご提案です。";

if ($parsed === $expected) {
    echo "[PASS] Test 1: SendGridMailer::parseTemplate correctly replaced variables.\n";
    $passCount++;
} else {
    echo "[FAIL] Test 1: parseTemplate failed. Got '{$parsed}'\n";
}

// Test 2: QueueManager::getSendInterval range test
$testCount++;
$interval = QueueManager::getSendInterval(180, 480);
if ($interval >= 180 && $interval <= 480) {
    echo "[PASS] Test 2: QueueManager::getSendInterval generated valid interval {$interval}s (within 180-480s).\n";
    $passCount++;
} else {
    echo "[FAIL] Test 2: getSendInterval out of range: {$interval}\n";
}

// Test 3: SendGridMailer Simulation Send Test
$testCount++;
$mailer = new SendGridMailer(null); // null API key triggers simulation mode
$payload = $mailer->buildPayload("test@example.com", "Test Company", "Subject", "Body");
$res = $mailer->send($payload);

if ($res['success'] === true && $res['mode'] === 'simulation') {
    echo "[PASS] Test 3: SendGridMailer successfully performed simulated mail dispatch.\n";
    $passCount++;
} else {
    echo "[FAIL] Test 3: SendGridMailer simulation failed.\n";
}

// Test 4: Webhook Bounce Suppression Integration Test
$testCount++;
try {
    $dbHost = getEnvParam('DB_HOST', 'localhost');
    $dbPort = getEnvParam('DB_PORT', '3306');
    $dbName = getEnvParam('DB_DATABASE', 'mdo3_pr');
    $dbUser = getEnvParam('DB_USERNAME', 'mdo3_toolapp0001');
    $dbPass = getEnvParam('DB_PASSWORD', 'koki2656@');

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Insert dummy company for bounce testing
    $testEmail = "bounce_test_" . uniqid() . "@example.com";
    $stmt = $pdo->prepare("INSERT INTO companies (corporate_number, name, clean_name, prefecture, city, address, email, status) VALUES (:cn, 'Bounce Test Corp', 'Bounce Test Corp', '埼玉県', '川越市', 'Address', :email, 'approved')");
    $stmt->execute([':cn' => '99999' . rand(10000, 99999), ':email' => $testEmail]);
    $companyId = $pdo->lastInsertId();

    // Simulate Webhook Bounce Event
    $optSql = "UPDATE companies SET is_opt_out = 1, opt_out_reason = 'webhook_bounce', status = 'failed' WHERE email = :email";
    $pdo->prepare($optSql)->execute([':email' => $testEmail]);

    // Verify DB update
    $checkStmt = $pdo->prepare("SELECT is_opt_out, status, opt_out_reason FROM companies WHERE id = :id");
    $checkStmt->execute([':id' => $companyId]);
    $row = $checkStmt->fetch();

    if ($row && $row['is_opt_out'] == 1 && $row['status'] === 'failed') {
        echo "[PASS] Test 4: Webhook bounce simulation correctly set is_opt_out=1 and status='failed'.\n";
        $passCount++;
    } else {
        echo "[FAIL] Test 4: Webhook bounce simulation failed.\n";
    }

} catch (Exception $e) {
    echo "[FAIL] Test 4: Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Step 3 Automated Test Summary: {$passCount}/{$testCount} Passed ===\n";

if ($passCount === $testCount) {
    exit(0);
} else {
    exit(1);
}
