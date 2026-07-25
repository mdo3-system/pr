<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * SendGrid Event Webhook 受信・自動サプレッション反映コントローラー
 */

header('Content-Type: application/json');

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

$input = file_get_contents('php://input');
$events = json_decode($input, true);

if (!is_array($events)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

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

    $processed = 0;
    foreach ($events as $evt) {
        $event = $evt['event'] ?? '';
        $email = $evt['email'] ?? '';
        $companyId = $evt['company_id'] ?? null;
        $sendgridMsgId = $evt['sg_message_id'] ?? null;

        if (empty($email)) continue;

        // 1. バウンス・ドロップ・スパム報告時の即時オプトアウト反映
        if (in_array($event, ['bounce', 'dropped', 'spamreport'])) {
            $optSql = "UPDATE companies SET is_opt_out = 1, opt_out_reason = :reason, status = 'failed' WHERE email = :email";
            $stmt = $pdo->prepare($optSql);
            $stmt->execute([
                ':reason' => 'webhook_' . $event,
                ':email'  => $email
            ]);

            $logSql = "UPDATE send_logs SET status = :status, error_message = :err WHERE email_to = :email";
            $logStmt = $pdo->prepare($logSql);
            $logStmt->execute([
                ':status' => ($event === 'spamreport') ? 'spam_report' : 'bounced',
                ':err'    => $evt['reason'] ?? $event,
                ':email'  => $email
            ]);
        }
        // 2. 開封イベント
        elseif ($event === 'open') {
            $logSql = "UPDATE send_logs SET status = 'opened', opened_at = CURRENT_TIMESTAMP WHERE email_to = :email AND opened_at IS NULL";
            $logStmt = $pdo->prepare($logSql);
            $logStmt->execute([':email' => $email]);
        }
        // 3. クリックイベント
        elseif ($event === 'click') {
            $logSql = "UPDATE send_logs SET status = 'clicked', clicked_at = CURRENT_TIMESTAMP WHERE email_to = :email AND clicked_at IS NULL";
            $logStmt = $pdo->prepare($logSql);
            $logStmt->execute([':email' => $email]);
        }
        $processed++;
    }

    echo json_encode(['status' => 'success', 'processed' => $processed]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
