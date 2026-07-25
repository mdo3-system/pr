<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * [Cron 01:00 実行] 企業インポート自動バッチスクリプト
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

echo "[" . date('Y-m-d H:i:s') . "] Starting Cron: Company Import Batch...\n";

try {
    $pdo = getPdoConnection();
    $importer = new NtaApiImporter($pdo);

    // デモ・ターゲットバルクインポート（埼玉県・東京都）
    $sampleCompanies = [
        ['corporate_number' => '1100001000001', 'name' => '株式会社 川越建築設計工房', 'prefecture' => '埼玉県', 'city' => '川越市', 'address' => '脇田本町10-1', 'postal_code' => '350-0000', 'category' => 'architect_office'],
        ['corporate_number' => '1100001000002', 'name' => '有限会社 大宮木造工務店', 'prefecture' => '埼玉県', 'city' => 'さいたま市', 'address' => '大宮区桜木町1-1', 'postal_code' => '330-0000', 'category' => 'builder'],
        ['corporate_number' => '1300001000003', 'name' => '合同会社 立川構造設計事務所', 'prefecture' => '東京都', 'city' => '立川市', 'address' => '曙町2-1-1', 'postal_code' => '190-0000', 'category' => 'architect_office']
    ];

    $count = $importer->importCompanies($sampleCompanies);
    echo "[" . date('Y-m-d H:i:s') . "] Successfully imported {$count} companies.\n";

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] [ERROR] Import batch failed: " . $e->getMessage() . "\n";
    exit(1);
}
