<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * [Cron 平日 10:00-16:00 5分毎実行] インターバル分散メール送信バッチスクリプト
 */

require_once __DIR__ . '/../src/SendGridMailer.php';

function getPdoConnection() {
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

    $dbHost = $env['DB_HOST'] ?? 'localhost';
    $dbPort = $env['DB_PORT'] ?? '3306';
    $dbName = $env['DB_DATABASE'] ?? 'mdo3_pr';
    $dbUser = $env['DB_USERNAME'] ?? 'mdo3_toolapp0001';
    $dbPass = $env['DB_PASSWORD'] ?? 'koki2656@';

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    return new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

echo "[" . date('Y-m-d H:i:s') . "] Starting Cron: Send Queue Process Batch...\n";

try {
    $pdo = getPdoConnection();
    $apiKey = getEnvParam('SENDGRID_API_KEY', null);
    $mailer = new SendGridMailer($apiKey);

    // 送信予定時刻が到来した未送信キューを1件取得
    $sql = "SELECT sl.*, c.name as company_name, c.city, et.subject, et.body_text, et.body_html
            FROM send_logs sl
            JOIN companies c ON sl.company_id = c.id
            JOIN email_templates et ON sl.template_id = et.id
            WHERE sl.status = 'queued' 
              AND sl.scheduled_at <= NOW()
              AND c.is_opt_out = 0
            ORDER BY sl.scheduled_at ASC
            LIMIT 1";

    $stmt = $pdo->query($sql);
    $queueItem = $stmt->fetch();

    if (!$queueItem) {
        echo "[" . date('Y-m-d H:i:s') . "] No queued items ready for dispatch.\n";
        exit(0);
    }

    // ステータスを sending へ更新
    $updateStmt = $pdo->prepare("UPDATE send_logs SET status = 'sending' WHERE id = :id");
    $updateStmt->execute([':id' => $queueItem['id']]);

    // 変数パーサー
    $vars = [
        'company_name' => $queueItem['company_name'],
        'city'         => $queueItem['city'],
        'sender_name'  => '上善如水 (IT技術エバンジェリスト)'
    ];

    $subject  = SendGridMailer::parseTemplate($queueItem['subject'], $vars);
    $bodyText = SendGridMailer::parseTemplate($queueItem['body_text'], $vars);
    $bodyHtml = $queueItem['body_html'] ? SendGridMailer::parseTemplate($queueItem['body_html'], $vars) : null;

    $payload = $mailer->buildPayload(
        $queueItem['email_to'],
        $queueItem['company_name'],
        $subject,
        $bodyText,
        $bodyHtml,
        ['company_id' => $queueItem['company_id'], 'send_log_id' => $queueItem['id']]
    );

    // メール送信実行
    $res = $mailer->send($payload);

    if ($res['success']) {
        $finalStmt = $pdo->prepare("UPDATE send_logs SET status = 'delivered', sendgrid_message_id = :msg_id, sent_at = NOW() WHERE id = :id");
        $finalStmt->execute([':msg_id' => $res['message_id'], ':id' => $queueItem['id']]);
        echo "[" . date('Y-m-d H:i:s') . "] Mail successfully sent to {$queueItem['email_to']} (Mode: {$res['mode']})\n";
    } else {
        $finalStmt = $pdo->prepare("UPDATE send_logs SET status = 'error', error_message = :err WHERE id = :id");
        $finalStmt->execute([':err' => $res['error'] ?? 'Unknown error', ':id' => $queueItem['id']]);
        echo "[" . date('Y-m-d H:i:s') . "] [ERROR] Failed to send mail to {$queueItem['email_to']}\n";
    }

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] [ERROR] Send batch failed: " . $e->getMessage() . "\n";
    exit(1);
}
