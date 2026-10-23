<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * [Cron 平日 10:00-16:00 5分毎実行] インターバル分散メール送信バッチスクリプト
 * 送信回数カウントアップ・最終送信日時更新・ワンクリック配信停止リンク付与
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
    
    // .env から環境設定
    $envFile = __DIR__ . '/../.env';
    $apiKey = null;
    $appUrl = 'https://pr.eie.tokyo';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), 'SENDGRID_API_KEY=') === 0) {
                $apiKey = trim(substr(trim($line), strlen('SENDGRID_API_KEY=')));
            }
            if (strpos(trim($line), 'APP_URL=') === 0) {
                $appUrl = rtrim(trim(substr(trim($line), strlen('APP_URL='))), '/');
            }
        }
    }

    $mailer = new SendGridMailer($apiKey);

    // 送信予定時刻が到来した未送信キューを1件取得
    $sql = "SELECT sl.*, c.name as company_name, c.clean_name, c.city, c.opt_out_token,
                   et.subject, et.body_text, et.body_html
            FROM send_logs sl
            JOIN companies c ON sl.company_id = c.id
            JOIN email_templates et ON sl.template_id = et.id
            WHERE sl.status = 'queued' 
              AND sl.scheduled_at <= NOW()
              AND c.is_opt_out = 0
            ORDER BY sl.scheduled_at ASC
            LIMIT 1";

    $stmt = $pdo->query($sql);
    $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$queueItem) {
        echo "[" . date('Y-m-d H:i:s') . "] No queued items ready for dispatch.\n";
        exit(0);
    }

    // ステータスを sending へ更新
    $updateStmt = $pdo->prepare("UPDATE send_logs SET status = 'sending' WHERE id = :id");
    $updateStmt->execute([':id' => $queueItem['id']]);

    // オプトアウトURL
    $token = $queueItem['opt_out_token'];
    if (empty($token)) {
        $token = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE companies SET opt_out_token = :token WHERE id = :cid")->execute([':token' => $token, ':cid' => $queueItem['company_id']]);
    }
    $optOutUrl = "{$appUrl}/api/optout.php?token={$token}";

    // 変数パーサー
    $vars = [
        'company_name' => $queueItem['company_name'],
        'clean_name'   => $queueItem['clean_name'] ?? $queueItem['company_name'],
        'city'         => $queueItem['city'],
        'sender_name'  => '上善如水 (IT技術エバンジェリスト)',
        'opt_out_url'  => $optOutUrl
    ];

    $subject  = SendGridMailer::parseTemplate($queueItem['subject'], $vars);
    $bodyText = SendGridMailer::parseTemplate($queueItem['body_text'], $vars);

    // フッターに配信停止リンクを付与（特電法対応）
    if (strpos($bodyText, $optOutUrl) === false) {
        $bodyText .= "\n\n--------------------------------------------------\n";
        $bodyText .= "※本メールは公開情報に基づき、建築関連業務のご案内として配信しております。\n";
        $bodyText .= "今後このようなご案内がご不要な場合は、大変お手数ですが下記URLより配信停止をお願い申し上げます。\n";
        $bodyText .= "【配信停止（ワンクリック）】: {$optOutUrl}\n";
        $bodyText .= "--------------------------------------------------\n";
    }

    $bodyHtml = null;
    if (!empty($queueItem['body_html'])) {
        $bodyHtml = SendGridMailer::parseTemplate($queueItem['body_html'], $vars);
        if (strpos($bodyHtml, $optOutUrl) === false) {
            $bodyHtml .= "<br><hr style='border:none;border-top:1px solid #ccc;margin:20px 0;'>";
            $bodyHtml .= "<p style='font-size:11px;color:#666;'>※本メールは公開情報に基づき配信しております。配信停止をご希望の場合は <a href='{$optOutUrl}'>こちらをクリック</a> してください。</p>";
        }
    }

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
        // 1. send_logs を delivered へ更新
        $finalStmt = $pdo->prepare("UPDATE send_logs SET status = 'delivered', sendgrid_message_id = :msg_id, sent_at = NOW() WHERE id = :id");
        $finalStmt->execute([':msg_id' => $res['message_id'], ':id' => $queueItem['id']]);

        // 2. companies テーブルの累計送信回数をインクリメント、最終送信日時をNOW()に更新
        $compStmt = $pdo->prepare("UPDATE companies SET send_count = send_count + 1, last_sent_at = NOW(), status = 'sent' WHERE id = :cid");
        $compStmt->execute([':cid' => $queueItem['company_id']]);

        echo "[" . date('Y-m-d H:i:s') . "] Mail successfully sent to {$queueItem['email_to']} (Mode: {$res['mode']})\n";
        echo "  - Company ID: {$queueItem['company_id']} (send_count updated)\n";
    } else {
        $finalStmt = $pdo->prepare("UPDATE send_logs SET status = 'error', error_message = :err WHERE id = :id");
        $finalStmt->execute([':err' => $res['error'] ?? 'Unknown error', ':id' => $queueItem['id']]);
        echo "[" . date('Y-m-d H:i:s') . "] [ERROR] Failed to send mail to {$queueItem['email_to']}: " . ($res['error'] ?? '') . "\n";
    }

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] [ERROR] Send batch failed: " . $e->getMessage() . "\n";
    exit(1);
}
