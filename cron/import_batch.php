<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * [Cron 01:00 実行] 企業インポート自動バッチスクリプト
 * 国税庁 法人番号システム Web-API 連携＆地域進捗管理連動版
 */

require_once __DIR__ . '/../src/NtaApiImporter.php';

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

echo "[" . date('Y-m-d H:i:s') . "] Starting Cron: Company Import Batch (NTA Web-API)...\n";

try {
    $pdo = getPdoConnection();

    // .env から NTA_API_ID を取得
    $envFile = __DIR__ . '/../.env';
    $ntaApiId = null;
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), 'NTA_API_ID=') === 0) {
                $ntaApiId = trim(substr(trim($line), strlen('NTA_API_ID=')));
                break;
            }
        }
    }

    $importer = new NtaApiImporter($pdo, $ntaApiId);

    // 地域進捗テーブル (nta_extract_progress) に基づき、次に巡回すべき地域を1件実行
    $res = $importer->executeScheduledExtraction();

    if ($res['success']) {
        echo "[" . date('Y-m-d H:i:s') . "] Extraction Success: " . ($res['region'] ?? '') . "\n";
        echo "  - Page: " . ($res['page'] ?? 1) . "\n";
        echo "  - Imported/Updated: " . ($res['imported'] ?? 0) . " records (Total hits: " . ($res['total_records'] ?? 0) . ")\n";
        echo "  - Status: " . ($res['status'] ?? '') . "\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Message: " . ($res['message'] ?? 'No action') . "\n";
    }

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] [ERROR] Import batch failed: " . $e->getMessage() . "\n";
    exit(1);
}
