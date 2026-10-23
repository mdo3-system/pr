<?php
/**
 * クールダウン制御・同一アドレス重複排除テスト (test_queue_cooldown.php)
 */

require_once __DIR__ . '/src/QueueManager.php';

function getPdo() {
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

$pdo = getPdo();
$qm = new QueueManager($pdo);

echo "=== Testing QueueManager Cooldown & Duplicate Elimination ===\n";

// テスト用データ投入
$testEmail1 = "test_cooldown_" . time() . "@example.com";
$testEmail2 = "test_shared_" . time() . "@example.com";

// 1. 通常の未送信企業 (キューに入るべき)
$pdo->prepare("INSERT INTO companies (name, clean_name, prefecture, city, address, email, status, send_count, last_sent_at) 
               VALUES ('テスト設計事務所A', 'テスト設計事務所A', '埼玉県', '川越市', 'address', :email, 'approved', 0, NULL)")
    ->execute([':email' => $testEmail1]);
$idA = $pdo->lastInsertId();

// 2. 送信後30日しか経過していない企業 (クールダウンで除外されるべき)
$pdo->prepare("INSERT INTO companies (name, clean_name, prefecture, city, address, email, status, send_count, last_sent_at) 
               VALUES ('テスト設計事務所B', 'テスト設計事務所B', '埼玉県', '川越市', 'address', 'b@example.com', 'approved', 1, DATE_SUB(NOW(), INTERVAL 30 DAY))")
    ->execute();
$idB = $pdo->lastInsertId();

// 3. 送信後100日経過した企業 (クールダウン解除で入るべき)
$pdo->prepare("INSERT INTO companies (name, clean_name, prefecture, city, address, email, status, send_count, last_sent_at) 
               VALUES ('テスト設計事務所C', 'テスト設計事務所C', '埼玉県', '川越市', 'address', 'c@example.com', 'approved', 1, DATE_SUB(NOW(), INTERVAL 100 DAY))")
    ->execute();
$idC = $pdo->lastInsertId();

// 4. 累計送信回数が3回に達した企業 (上限キャップで除外されるべき)
$pdo->prepare("INSERT INTO companies (name, clean_name, prefecture, city, address, email, status, send_count, last_sent_at) 
               VALUES ('テスト設計事務所D', 'テスト設計事務所D', '埼玉県', '川越市', 'address', 'd@example.com', 'approved', 3, DATE_SUB(NOW(), INTERVAL 120 DAY))")
    ->execute();
$idD = $pdo->lastInsertId();

// 5. 同一メールアドレスを持つ2つの別法人 (EとF)
$pdo->prepare("INSERT INTO companies (name, clean_name, prefecture, city, address, email, status, send_count, last_sent_at) 
               VALUES ('グループ企業E', 'グループ企業E', '埼玉県', '川越市', 'address', :email, 'approved', 0, NULL)")
    ->execute([':email' => $testEmail2]);
$idE = $pdo->lastInsertId();

$pdo->prepare("INSERT INTO companies (name, clean_name, prefecture, city, address, email, status, send_count, last_sent_at) 
               VALUES ('グループ企業F', 'グループ企業F', '埼玉県', '川越市', 'address', :email, 'approved', 0, NULL)")
    ->execute([':email' => $testEmail2]);
$idF = $pdo->lastInsertId();

// テンプレートID取得
$tplId = $pdo->query("SELECT id FROM email_templates LIMIT 1")->fetchColumn();

// キュー生成実行
$queued = $qm->generateQueue((int)$tplId, 20);
echo "Queued count: {$queued}\n";

// 検証
$queuedIds = $pdo->query("SELECT company_id FROM send_logs WHERE status = 'queued'")->fetchAll(PDO::FETCH_COLUMN);

$passA = in_array($idA, $queuedIds);
$passB = !in_array($idB, $queuedIds); // クールダウン中なので除外
$passC = in_array($idC, $queuedIds);  // 100日経過なので許可
$passD = !in_array($idD, $queuedIds); // 3回送信済みなので除外

echo "Test A (未送信): " . ($passA ? "[PASS]" : "[FAIL]") . "\n";
echo "Test B (30日経過・クールダウン除外): " . ($passB ? "[PASS]" : "[FAIL]") . "\n";
echo "Test C (100日経過・クールダウン解除): " . ($passC ? "[PASS]" : "[FAIL]") . "\n";
echo "Test D (3回上限到達除外): " . ($passD ? "[PASS]" : "[FAIL]") . "\n";

// クリーンアップ
$pdo->prepare("DELETE FROM companies WHERE id IN (:a, :b, :c, :d, :e, :f)")
    ->execute([':a' => $idA, ':b' => $idB, ':c' => $idC, ':d' => $idD, ':e' => $idE, ':f' => $idF]);
$pdo->prepare("DELETE FROM send_logs WHERE company_id IN (:a, :b, :c, :d, :e, :f)")
    ->execute([':a' => $idA, ':b' => $idB, ':c' => $idC, ':d' => $idD, ':e' => $idE, ':f' => $idF]);

if ($passA && $passB && $passC && $passD) {
    echo "\n=== ALL COOLDOWN & DUPLICATE TESTS PASSED ===\n";
    exit(0);
} else {
    echo "\n=== SOME TESTS FAILED ===\n";
    exit(1);
}
