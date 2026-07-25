<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * データベース自動テスト・検証スクリプト
 */

function getEnvParam($key, $default = null) {
    static $env = null;
    if ($env === null) {
        $envFile = __DIR__ . '/.env';
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

$dbHost = getEnvParam('DB_HOST', 'localhost');
$dbPort = getEnvParam('DB_PORT', '3306');
$dbName = getEnvParam('DB_DATABASE', 'mdo3_pr');
$dbUser = getEnvParam('DB_USERNAME', 'mdo3_toolapp0001');
$dbPass = getEnvParam('DB_PASSWORD', 'koki2656@');

echo "=== Automated Test: Database Structure Verification ===\n";

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $requiredTables = ['companies', 'crawl_logs', 'email_templates', 'send_logs'];
    $passed = 0;

    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        $result = $stmt->fetch();
        if ($result) {
            echo "[PASS] Table `{$table}` exists.\n";
            $passed++;
        } else {
            echo "[FAIL] Table `{$table}` DOES NOT exist!\n";
        }
    }

    if ($passed === count($requiredTables)) {
        echo "\nALL TESTS PASSED ({$passed}/" . count($requiredTables) . " tables verified).\n";
        exit(0);
    } else {
        echo "\nSOME TESTS FAILED ({$passed}/" . count($requiredTables) . " passed).\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "[ERROR] Automated test failed: " . $e->getMessage() . "\n";
    exit(1);
}
