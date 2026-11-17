<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * [Cron 02:00 実行] Serper API 公式WEBサイト特定バッチスクリプト
 *
 * 1回あたり最大50件まで未調査企業 (status='pending' AND official_url IS NULL) を検索し、
 * 公式URLを保存する。見つからない場合は status='rejected' (no_official_website) とし再検索を防ぐ。
 */

require_once __DIR__ . '/../src/SerperSearch.php';

function loadEnvMap(string $envFile): array {
    $env = [];
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
            }
        }
    }
    return $env;
}

echo "[" . date('Y-m-d H:i:s') . "] Starting Cron: Serper Official URL Search Batch...\n";

try {
    $env = loadEnvMap(__DIR__ . '/../.env');
    $apiKey = $env['SERPER_API_KEY'] ?? '';
    if (empty($apiKey)) {
        throw new RuntimeException("SERPER_API_KEY が .env に設定されていません。");
    }

    $dbHost = $env['DB_HOST'] ?? 'localhost';
    $dbPort = $env['DB_PORT'] ?? '3306';
    $dbName = $env['DB_DATABASE'] ?? 'mdo3_pr';
    $dbUser = $env['DB_USERNAME'] ?? 'mdo3_toolapp0001';
    $dbPass = $env['DB_PASSWORD'] ?? '';

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $dailyLimit = 50;
    $stmt = $pdo->prepare("SELECT id, name, clean_name, city 
                           FROM companies 
                           WHERE status = 'pending' 
                             AND official_url IS NULL 
                             AND is_opt_out = 0
                           ORDER BY id ASC 
                           LIMIT :limit");
    $stmt->bindValue(':limit', $dailyLimit, PDO::PARAM_INT);
    $stmt->execute();
    $targets = $stmt->fetchAll();

    if (empty($targets)) {
        echo "[" . date('Y-m-d H:i:s') . "] No pending companies without official_url.\n";
        exit(0);
    }

    $serper = new SerperSearch($apiKey);
    $updateFound = $pdo->prepare("UPDATE companies SET official_url = :url, updated_at = NOW() WHERE id = :id");
    $updateNotFound = $pdo->prepare("UPDATE companies SET official_url = '', status = 'rejected', opt_out_reason = 'no_official_website', updated_at = NOW() WHERE id = :id");

    $foundCount = 0;
    $notFoundCount = 0;
    $consecutiveErrors = 0;

    foreach ($targets as $comp) {
        try {
            $url = $serper->searchOfficialUrl($comp['clean_name'], $comp['city']);
            $consecutiveErrors = 0;

            if ($url) {
                $updateFound->execute([':url' => $url, ':id' => $comp['id']]);
                $foundCount++;
                echo "  [FOUND] #{$comp['id']} {$comp['name']} -> {$url}\n";
            } else {
                $updateNotFound->execute([':id' => $comp['id']]);
                $notFoundCount++;
                echo "  [NONE]  #{$comp['id']} {$comp['name']} (No official URL)\n";
            }
        } catch (Exception $apiEx) {
            $consecutiveErrors++;
            echo "  [ERR]   #{$comp['id']} {$comp['name']}: " . $apiEx->getMessage() . "\n";
            if ($consecutiveErrors >= 3) {
                throw new RuntimeException("Serper APIエラーが3回連続したため安全停止します。");
            }
        }
        usleep(300000); // 0.3秒待機
    }

    echo "[" . date('Y-m-d H:i:s') . "] Serper batch completed. Found: {$foundCount}, NotFound: {$notFoundCount}\n";

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] [ERROR] Serper batch failed: " . $e->getMessage() . "\n";
    exit(1);
}
