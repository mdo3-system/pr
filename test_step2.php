<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * Step 2 自動テスト・検証スクリプト
 */

require_once __DIR__ . '/src/NtaApiImporter.php';
require_once __DIR__ . '/src/SerperSearch.php';

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

echo "=== Automated Test: Step 2 API Wrappers & Crawler Engine ===\n\n";

$passCount = 0;
$testCount = 0;

// Test 1: NtaApiImporter::cleanCompanyName
$testCount++;
$rawName = "株式会社 川越建築設計事務所";
$clean = NtaApiImporter::cleanCompanyName($rawName);
if ($clean === "川越建築設計事務所") {
    echo "[PASS] Test 1: cleanCompanyName('{$rawName}') -> '{$clean}'\n";
    $passCount++;
} else {
    echo "[FAIL] Test 1: cleanCompanyName failed. Got '{$clean}'\n";
}

// Test 2: SerperSearch::isIgnoredDomain
$testCount++;
$portalUrl = "https://suumo.jp/chintai/saitama/";
$officialUrl = "https://www.kawagoe-arch.co.jp/";

if (SerperSearch::isIgnoredDomain($portalUrl) === true && SerperSearch::isIgnoredDomain($officialUrl) === false) {
    echo "[PASS] Test 2: SerperSearch::isIgnoredDomain domain filtering correctly identifies portals vs official sites.\n";
    $passCount++;
} else {
    echo "[FAIL] Test 2: SerperSearch::isIgnoredDomain domain filtering failed.\n";
}

// Test 3: DB Integration & Company Import Test
$testCount++;
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

    $importer = new NtaApiImporter($pdo);
    $dummyData = [
        [
            'corporate_number' => '1234567890123',
            'name' => '有限会社 埼玉木造建築',
            'prefecture' => '埼玉県',
            'city' => '川越市',
            'address' => '脇田本町1-1',
            'postal_code' => '350-0000',
            'category' => 'builder',
            'source_type' => 'test'
        ]
    ];

    $count = $importer->importCompanies($dummyData);
    if ($count === 1) {
        echo "[PASS] Test 3: NtaApiImporter::importCompanies successfully inserted dummy record into MySQL.\n";
        $passCount++;
    } else {
        echo "[FAIL] Test 3: importCompanies returned count {$count}.\n";
    }

} catch (Exception $e) {
    echo "[FAIL] Test 3: DB Exception: " . $e->getMessage() . "\n";
}

// Test 4: Python Scraper Engine Execution Test
$testCount++;
$pythonCmd = "python3 crawler/scraper_engine.py 2>&1";
exec($pythonCmd, $output, $returnVar);

if ($returnVar === 0 && !empty($output)) {
    echo "[PASS] Test 4: Python crawler/scraper_engine.py executed successfully.\n";
    echo "       Output sample: " . implode(" | ", array_slice($output, 0, 2)) . "\n";
    $passCount++;
} else {
    // Windowsローカル環境でpython3ではなくpythonの場合のフォールバックテスト
    $pythonCmdFallback = "python crawler/scraper_engine.py 2>&1";
    exec($pythonCmdFallback, $outputFb, $returnVarFb);
    if ($returnVarFb === 0) {
        echo "[PASS] Test 4: Python crawler/scraper_engine.py executed successfully via python.\n";
        $passCount++;
    } else {
        echo "[FAIL] Test 4: Python crawler execution failed. Exit code: {$returnVar}\n";
    }
}

echo "\n=== Step 2 Automated Test Summary: {$passCount}/{$testCount} Passed ===\n";

if ($passCount === $testCount) {
    exit(0);
} else {
    exit(1);
}
