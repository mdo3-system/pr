<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * [Cron 平日 09:30 実行] 送信キュー生成バッチスクリプト
 */

require_once __DIR__ . '/../src/QueueManager.php';

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

echo "[" . date('Y-m-d H:i:s') . "] Starting Cron: Queue Generation Batch...\n";

try {
    $pdo = getPdoConnection();
    $qm = new QueueManager($pdo);

    // デフォルト有効テンプレートの取得
    $stmt = $pdo->query("SELECT id FROM email_templates WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
    $tplId = $stmt->fetchColumn();

    if (!$tplId) {
        // テンプレートが無ければテスト用を作成
        $pdo->exec("INSERT INTO email_templates (template_name, subject, body_text) VALUES ('標準訴求テンプレート', '【Mac対応/斜め壁計算】WEB構造計算ツールのご案内', '{{company_name}} 様\n\nお世話になっております。{{city}}にて設計を手掛けられている皆様へ...')");
        $tplId = $pdo->lastInsertId();
    }

    $queuedCount = $qm->generateQueue((int)$tplId, 50);
    echo "[" . date('Y-m-d H:i:s') . "] Queue generation completed: {$queuedCount} emails scheduled.\n";

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] [ERROR] Queue generation failed: " . $e->getMessage() . "\n";
    exit(1);
}
