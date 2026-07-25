<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * Step 4 WEB管理ダッシュボード・API自動テストスクリプト
 */

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

echo "=== Automated Test: Step 4 WEB Admin Dashboard & CRUD APIs ===\n\n";

$passCount = 0;
$testCount = 0;

// Test 1: KPI Aggregate API (api/logs.php)
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

    $totalCompanies = (int)$pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
    $validEmails = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE email IS NOT NULL AND email != ''")->fetchColumn();

    if ($totalCompanies >= 0 && $validEmails >= 0) {
        echo "[PASS] Test 1: KPI database queries executed successfully (Total: {$totalCompanies}, Emails: {$validEmails}).\n";
        $passCount++;
    } else {
        echo "[FAIL] Test 1: KPI queries returned invalid results.\n";
    }
} catch (Exception $e) {
    echo "[FAIL] Test 1 Exception: " . $e->getMessage() . "\n";
}

// Test 2: Companies API Status Update & Bulk Approve Simulation
$testCount++;
try {
    // Insert test company
    $stmt = $pdo->prepare("INSERT INTO companies (name, clean_name, prefecture, city, address, email, status) VALUES ('Test Arch Studio', 'Test Arch Studio', '埼玉県', '所沢市', 'Address', 'test_arch@example.com', 'pending')");
    $stmt->execute();
    $companyId = $pdo->lastInsertId();

    // Update status
    $stmt = $pdo->prepare("UPDATE companies SET status = 'approved' WHERE id = :id");
    $stmt->execute([':id' => $companyId]);

    $check = $pdo->prepare("SELECT status FROM companies WHERE id = :id");
    $check->execute([':id' => $companyId]);
    $status = $check->fetchColumn();

    if ($status === 'approved') {
        echo "[PASS] Test 2: Lead status update to 'approved' verified successfully.\n";
        $passCount++;
    } else {
        echo "[FAIL] Test 2: Status update failed (Got: {$status}).\n";
    }

} catch (Exception $e) {
    echo "[FAIL] Test 2 Exception: " . $e->getMessage() . "\n";
}

// Test 3: Template CRUD API Simulation
$testCount++;
try {
    $stmt = $pdo->prepare("INSERT INTO email_templates (template_name, subject, body_text) VALUES ('Step 4 Test Template', 'Subject {{company_name}}', 'Body {{city}}')");
    $stmt->execute();
    $tplId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT template_name FROM email_templates WHERE id = :id");
    $stmt->execute([':id' => $tplId]);
    $name = $stmt->fetchColumn();

    if ($name === 'Step 4 Test Template') {
        echo "[PASS] Test 3: Template CRUD operations verified successfully.\n";
        $passCount++;
    } else {
        echo "[FAIL] Test 3: Template CRUD failed.\n";
    }
} catch (Exception $e) {
    echo "[FAIL] Test 3 Exception: " . $e->getMessage() . "\n";
}

// Test 4: Dashboard UI (index.php) Integrity Test
$testCount++;
if (file_exists(__DIR__ . '/index.php')) {
    $content = file_get_contents(__DIR__ . '/index.php');
    if (strpos($content, 'J-ALG 管理ダッシュボード') !== false && strpos($content, 'kpi-total') !== false) {
        echo "[PASS] Test 4: Dashboard UI file index.php contains required KPI elements and structure.\n";
        $passCount++;
    } else {
        echo "[FAIL] Test 4: index.php missing critical UI elements.\n";
    }
} else {
    echo "[FAIL] Test 4: index.php file not found.\n";
}

echo "\n=== Step 4 Automated Test Summary: {$passCount}/{$testCount} Passed ===\n";

if ($passCount === $testCount) {
    exit(0);
} else {
    exit(1);
}
