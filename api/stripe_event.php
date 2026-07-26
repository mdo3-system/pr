<?php
/**
 * J-ALG 販売管理ポータル (pr.eie.tokyo)
 * Stripe Webhook イベント受信＆連携同期エンドポイント (方式B)
 *
 * エンドポイント: POST /api/stripe_event.php
 * ヘッダー認証: X-API-KEY または X-Portal-Sync-Token
 */

header('Content-Type: application/json; charset=utf-8');

// 設定・環境変数の読み込み関数
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

$secretToken = getEnvParam('PORTAL_SYNC_SECRET', 'eie_sales_portal_secret_key_2026');

// 1. HTTP ヘッダー認証
$headers = getallheaders();
$providedToken = null;
foreach ($headers as $key => $value) {
    $lowerKey = strtolower($key);
    if ($lowerKey === 'x-api-key' || $lowerKey === 'x-portal-sync-token') {
        $providedToken = $value;
        break;
    }
}
if (!$providedToken && isset($_SERVER['HTTP_X_API_KEY'])) {
    $providedToken = $_SERVER['HTTP_X_API_KEY'];
}
if (!$providedToken && isset($_SERVER['HTTP_X_PORTAL_SYNC_TOKEN'])) {
    $providedToken = $_SERVER['HTTP_X_PORTAL_SYNC_TOKEN'];
}

if (!$providedToken || hash_equals($secretToken, $providedToken) === false) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized: Invalid or missing API key header.'
    ]);
    exit;
}

// 2. リクエストボディ (JSON) パース
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['user_email'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Bad Request: Missing required parameter (user_email).'
    ]);
    exit;
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

    $email = trim($data['user_email']);
    $stripeCustomerId = !empty($data['stripe_customer_id']) ? $data['stripe_customer_id'] : null;
    $planType = !empty($data['plan_type']) ? $data['plan_type'] : 'monthly';
    $paymentMethod = !empty($data['payment_method']) ? $data['payment_method'] : 'credit_card';
    $status = !empty($data['status']) ? $data['status'] : 'active';
    $periodEnd = !empty($data['current_period_end']) ? $data['current_period_end'] : date('Y-m-d H:i:s', strtotime('+1 month'));
    $periodStart = !empty($data['current_period_start']) ? $data['current_period_start'] : date('Y-m-d H:i:s');
    $amount = isset($data['amount']) ? (float)$data['amount'] : 0.00;
    $paidAt = !empty($data['paid_at']) ? $data['paid_at'] : date('Y-m-d H:i:s');

    // 3. User の存在チェック＆更新/新規登録
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        $userId = $user['id'];
        if ($stripeCustomerId) {
            $stmtUp = $pdo->prepare("UPDATE users SET stripe_customer_id = :cid WHERE id = :id");
            $stmtUp->execute([':cid' => $stripeCustomerId, ':id' => $userId]);
        }
    } else {
        // companies テーブルでメールアドレスが一致するリード企業があるか照会
        $companyId = null;
        $stmtComp = $pdo->prepare("SELECT id, name FROM companies WHERE email = :email LIMIT 1");
        $stmtComp->execute([':email' => $email]);
        $company = $stmtComp->fetch();
        if ($company) {
            $companyId = $company['id'];
            $companyName = $company['name'];
        } else {
            $companyName = explode('@', $email)[0];
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO users (company_id, email, company_name, user_name, role, stripe_customer_id)
            VALUES (:comp_id, :email, :comp_name, :user_name, 'general', :cid)
        ");
        $stmtIns->execute([
            ':comp_id' => $companyId,
            ':email' => $email,
            ':comp_name' => $companyName,
            ':user_name' => $companyName,
            ':cid' => $stripeCustomerId
        ]);
        $userId = $pdo->lastInsertId();
    }

    // 4. Subscriptions テーブルの更新 (Upsert)
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
        ':sub_id' => !empty($data['stripe_subscription_id']) ? $data['stripe_subscription_id'] : null,
        ':plan_type' => $planType,
        ':pay_method' => $paymentMethod,
        ':status' => $status,
        ':p_start' => $periodStart,
        ':p_end' => $periodEnd
    ]);

    // 5. Payment Logs の記録 (金額が 0 より大きい場合または決済通知イベント時)
    if ($amount > 0 || !empty($data['event_type'])) {
        $taxAmount = round($amount * 0.10 / 1.10, 2);
        $stmtPay = $pdo->prepare("
            INSERT INTO payment_logs (user_id, stripe_invoice_id, amount, tax_amount, currency, payment_method, status, paid_at)
            VALUES (:uid, :inv_id, :amount, :tax, 'jpy', :pay_method, 'succeeded', :paid_at)
        ");
        $stmtPay->execute([
            ':uid' => $userId,
            ':inv_id' => !empty($data['stripe_invoice_id']) ? $data['stripe_invoice_id'] : null,
            ':amount' => $amount,
            ':tax' => $taxAmount,
            ':pay_method' => $paymentMethod === 'bank_transfer' ? 'bank_transfer' : 'credit_card',
            ':paid_at' => $paidAt
        ]);
    }

    // 6. API Sync Logs の記録
    $stmtLog = $pdo->prepare("
        INSERT INTO api_sync_logs (sync_type, target_user_id, request_payload, response_code, error_message)
        VALUES ('webhook_push', :uid, :payload, 200, NULL)
    ");
    $stmtLog->execute([
        ':uid' => $userId,
        ':payload' => json_encode($data, JSON_UNESCAPED_UNICODE)
    ]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Subscription synced successfully',
        'user_id' => (int)$userId,
        'email' => $email
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // エラーログの記録
    try {
        if (isset($pdo)) {
            $stmtErr = $pdo->prepare("
                INSERT INTO api_sync_logs (sync_type, request_payload, response_code, error_message)
                VALUES ('webhook_push', :payload, 500, :err)
            ");
            $stmtErr->execute([
                ':payload' => $rawInput,
                ':err' => $e->getMessage()
            ]);
        }
    } catch (Exception $ex) {}

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal Server Error: ' . $e->getMessage()
    ]);
}
