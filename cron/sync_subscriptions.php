<?php
/**
 * J-ALG 販売管理ポータル (pr.eie.tokyo)
 * アプリ側 (2025.eie.jp) 照会 API 一括自動同期バッチ (方式A)
 *
 * 実行コマンド: php cron/sync_subscriptions.php
 */

function getEnvParam($key, $default = null) {
    static $env = null;
    if ($env === null) {
        $envFile = __DIR__ . '/../.env';
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

$appServerUrl = rtrim(getEnvParam('APP_SERVER_URL', 'https://2025.eie.jp'), '/');
$secretToken = getEnvParam('PORTAL_SYNC_SECRET', 'eie_sales_portal_secret_key_2026');

$targetUrl = "{$appServerUrl}/api/get_user_subscription.php?list=all";

echo "=== J-ALG Subscription Pull Sync Batch (Method A) ===\n";
echo "Fetching active subscriptions from: {$targetUrl}\n";

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        "X-API-KEY: {$secretToken}",
        "Accept: application/json"
    ],
    CURLOPT_SSL_VERIFYPEER => false // 動作環境に応じて適切に制御
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || empty($response)) {
    echo "[ERROR] Failed to fetch subscription data. HTTP Code: {$httpCode}, cURL Error: {$curlError}\n";
    exit(1);
}

$json = json_decode($response, true);
if (!$json || empty($json['data']) || !is_array($json['data'])) {
    echo "[WARNING] Received invalid or empty payload from application server.\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
    exit(0);
}

$dbHost = getEnvParam('DB_HOST', 'localhost');
$dbPort = getEnvParam('DB_PORT', '3306');
$dbName = getEnvParam('DB_DATABASE', 'mdo3_pr');
$dbUser = getEnvParam('DB_USERNAME', 'mdo3_toolapp0001');
$dbPass = getEnvParam('DB_PASSWORD', 'koki2656@');

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $pdo->beginTransaction();

    $syncedCount = 0;
    foreach ($json['data'] as $subItem) {
        if (empty($subItem['email'])) continue;

        $email = trim($subItem['email']);
        $companyName = !empty($subItem['company_name']) ? $subItem['company_name'] : explode('@', $email)[0];
        $userName = !empty($subItem['user_name']) ? $subItem['user_name'] : $companyName;
        $role = !empty($subItem['role']) ? $subItem['role'] : 'general';
        $stripeCustomerId = !empty($subItem['stripe_customer_id']) ? $subItem['stripe_customer_id'] : null;

        $planType = !empty($subItem['plan_type']) ? $subItem['plan_type'] : (!empty($subItem['plan_key']) ? $subItem['plan_key'] : 'monthly');
        $paymentMethod = !empty($subItem['payment_method']) ? $subItem['payment_method'] : 'none';
        $status = !empty($subItem['status']) ? $subItem['status'] : 'active';
        $periodStart = !empty($subItem['current_period_start']) ? $subItem['current_period_start'] : date('Y-m-d H:i:s');
        $periodEnd = !empty($subItem['current_period_end']) ? $subItem['current_period_end'] : '2099-12-31 23:59:59';

        // 1. users Upsert
        $stmtUser = $pdo->prepare("
            INSERT INTO users (email, company_name, user_name, role, stripe_customer_id)
            VALUES (:email, :cname, :uname, :role, :cid)
            ON DUPLICATE KEY UPDATE
                company_name = VALUES(company_name),
                user_name = VALUES(user_name),
                role = VALUES(role),
                stripe_customer_id = COALESCE(VALUES(stripe_customer_id), stripe_customer_id)
        ");
        $stmtUser->execute([
            ':email' => $email,
            ':cname' => $companyName,
            ':uname' => $userName,
            ':role' => $role,
            ':cid' => $stripeCustomerId
        ]);

        $userId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM users WHERE email = " . $pdo->quote($email))->fetchColumn();

        // 2. subscriptions Upsert
        $stmtSub = $pdo->prepare("
            INSERT INTO subscriptions (user_id, stripe_subscription_id, plan_type, payment_method, status, current_period_start, current_period_end)
            VALUES (:uid, :sub_id, :plan_type, :pay_method, :status, :p_start, :p_end)
            ON DUPLICATE KEY UPDATE
                plan_type = VALUES(plan_type),
                payment_method = VALUES(payment_method),
                status = VALUES(status),
                current_period_start = VALUES(current_period_start),
                current_period_end = VALUES(current_period_end)
        ");
        $stmtSub->execute([
            ':uid' => $userId,
            ':sub_id' => !empty($subItem['stripe_subscription_id']) ? $subItem['stripe_subscription_id'] : null,
            ':plan_type' => $planType,
            ':pay_method' => $paymentMethod,
            ':status' => $status,
            ':p_start' => $periodStart,
            ':p_end' => $periodEnd
        ]);

        $syncedCount++;
        echo " - Synced: {$email} (User ID: {$userId}, Plan: {$planType}, Status: {$status})\n";
    }

    // 3. api_sync_logs 記録
    $stmtLog = $pdo->prepare("
        INSERT INTO api_sync_logs (sync_type, request_payload, response_code, error_message)
        VALUES ('api_pull', :payload, 200, NULL)
    ");
    $stmtLog->execute([
        ':payload' => json_encode(['count' => $syncedCount, 'url' => $targetUrl], JSON_UNESCAPED_UNICODE)
    ]);

    $pdo->commit();
    echo "\n=== Sync Batch Completed Successfully! (Total Synced: {$syncedCount}) ===\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "[ERROR] Batch Sync failed: " . $e->getMessage() . "\n";
    exit(1);
}
