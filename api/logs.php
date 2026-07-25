<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * ダッシュボード KPI 集計＆配信ログ API コントローラー
 */

header('Content-Type: application/json');

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

try {
    $pdo = getPdoConnection();
    $type = $_GET['type'] ?? 'kpi';

    if ($type === 'kpi') {
        // KPI 指標リアルタイム集計
        $totalCompanies = (int)$pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
        $validEmails = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE email IS NOT NULL AND email != ''")->fetchColumn();
        $optOutCount = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE is_opt_out = 1")->fetchColumn();
        $approvedCount = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'approved'")->fetchColumn();
        
        $queuedMails = (int)$pdo->query("SELECT COUNT(*) FROM send_logs WHERE status = 'queued'")->fetchColumn();
        $deliveredMails = (int)$pdo->query("SELECT COUNT(*) FROM send_logs WHERE status IN ('delivered', 'opened', 'clicked')")->fetchColumn();
        $bouncedMails = (int)$pdo->query("SELECT COUNT(*) FROM send_logs WHERE status IN ('bounced', 'spam_report', 'error')")->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'kpi' => [
                'total_companies' => $totalCompanies,
                'valid_emails' => $validEmails,
                'opt_out_count' => $optOutCount,
                'approved_count' => $approvedCount,
                'queued_mails' => $queuedMails,
                'delivered_mails' => $deliveredMails,
                'bounced_mails' => $bouncedMails
            ]
        ]);

    } elseif ($type === 'logs') {
        $sql = "SELECT sl.*, c.name as company_name 
                FROM send_logs sl 
                JOIN companies c ON sl.company_id = c.id 
                ORDER BY sl.id DESC LIMIT 100";
        $stmt = $pdo->query($sql);
        $logs = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $logs]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
