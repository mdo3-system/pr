<?php
/**
 * J-ALG 販売管理ポータル (pr.eie.tokyo)
 * 社内スタッフ用 永久無償アカウント一括登録スクリプト
 *
 * 実行コマンド: php setup_portal_staff.php
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

$dbHost = getEnvParam('DB_HOST', 'localhost');
$dbPort = getEnvParam('DB_PORT', '3306');
$dbName = getEnvParam('DB_DATABASE', 'mdo3_pr');
$dbUser = getEnvParam('DB_USERNAME', 'mdo3_toolapp0001');
$dbPass = getEnvParam('DB_PASSWORD', 'koki2656@');

$staffList = [
    ['email' => 'staff1@eie.jp', 'name' => '社内スタッフ1', 'company' => '自社 (2025.eie.jp)'],
    ['email' => 'staff2@eie.jp', 'name' => '社内スタッフ2', 'company' => '自社 (2025.eie.jp)'],
    ['email' => 'info@2025.eie.jp', 'name' => '社内スタッフ3', 'company' => '自社 (2025.eie.jp)'],
];

echo "=== Registering Internal Staff Permanent Free Accounts (Portal DB) ===\n";

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $pdo->beginTransaction();

    foreach ($staffList as $staff) {
        // 1. users Upsert
        $stmtUser = $pdo->prepare("
            INSERT INTO users (email, company_name, user_name, role)
            VALUES (:email, :company, :name, 'internal_staff')
            ON DUPLICATE KEY UPDATE
                role = 'internal_staff',
                company_name = VALUES(company_name),
                user_name = VALUES(user_name)
        ");
        $stmtUser->execute([
            ':email' => $staff['email'],
            ':company' => $staff['company'],
            ':name' => $staff['name']
        ]);

        $stmtGet = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmtGet->execute([':email' => $staff['email']]);
        $userId = $stmtGet->fetchColumn();

        // 2. subscriptions Upsert (free_permanent & active until 2099)
        $stmtSub = $pdo->prepare("
            INSERT INTO subscriptions (user_id, plan_type, payment_method, status, current_period_start, current_period_end)
            VALUES (:uid, 'free_permanent', 'none', 'active', NOW(), '2099-12-31 23:59:59')
            ON DUPLICATE KEY UPDATE
                plan_type = 'free_permanent',
                payment_method = 'none',
                status = 'active',
                current_period_end = '2099-12-31 23:59:59'
        ");
        $stmtSub->execute([':uid' => $userId]);

        echo "[SUCCESS] Internal Staff Registered: {$staff['email']} (User ID: {$userId})\n";
    }

    $pdo->commit();
    echo "\n=== All 3 Staff Accounts Setup Successfully! ===\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "[ERROR] Failed to register staff accounts: " . $e->getMessage() . "\n";
    exit(1);
}
