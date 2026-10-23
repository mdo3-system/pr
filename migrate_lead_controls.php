<?php
/**
 * J-ALG DBマイグレーション: リード抽出制御・送信カウント・重複防止拡張
 * (migrate_lead_controls.php)
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

echo "=== J-ALG Migration: Lead Extraction Controls & Send Count ===\n";
echo "Connecting to DB: {$dbUser}@{$dbHost}/{$dbName}...\n";

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "[OK] Connected to database.\n";

    // 1. companies テーブルのカラム確認と追加
    $cols = $pdo->query("SHOW COLUMNS FROM `companies`")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('send_count', $cols)) {
        $pdo->exec("ALTER TABLE `companies` ADD COLUMN `send_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累計DM送信回数' AFTER `is_opt_out`");
        echo "[OK] Added column `send_count` to `companies`\n";
    } else {
        echo "[SKIP] Column `send_count` already exists.\n";
    }

    if (!in_array('last_sent_at', $cols)) {
        $pdo->exec("ALTER TABLE `companies` ADD COLUMN `last_sent_at` DATETIME NULL COMMENT '最終DM実送信日時' AFTER `send_count`");
        echo "[OK] Added column `last_sent_at` to `companies`\n";
    } else {
        echo "[SKIP] Column `last_sent_at` already exists.\n";
    }

    if (!in_array('opt_out_token', $cols)) {
        $pdo->exec("ALTER TABLE `companies` ADD COLUMN `opt_out_token` VARCHAR(64) NULL COMMENT 'ワンクリック配信停止用トークン' AFTER `opt_out_reason`");
        echo "[OK] Added column `opt_out_token` to `companies`\n";
    } else {
        echo "[SKIP] Column `opt_out_token` already exists.\n";
    }

    // インデックスの確認と追加
    $indexes = $pdo->query("SHOW INDEX FROM `companies`")->fetchAll(PDO::FETCH_ASSOC);
    $idxNames = array_column($indexes, 'Key_name');

    if (!in_array('idx_cooldown', $idxNames)) {
        $pdo->exec("ALTER TABLE `companies` ADD INDEX `idx_cooldown` (`status`, `is_opt_out`, `last_sent_at`)");
        echo "[OK] Added index `idx_cooldown`\n";
    }

    if (!in_array('idx_opt_token', $idxNames)) {
        $pdo->exec("ALTER TABLE `companies` ADD INDEX `idx_opt_token` (`opt_out_token`)");
        echo "[OK] Added index `idx_opt_token`\n";
    }

    // 既存レコードのオプトアウトトークン初期化（未設定のものにランダムトークンを付与）
    $emptyTokens = $pdo->query("SELECT id FROM companies WHERE opt_out_token IS NULL OR opt_out_token = ''")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($emptyTokens)) {
        $stmtToken = $pdo->prepare("UPDATE companies SET opt_out_token = :token WHERE id = :id");
        foreach ($emptyTokens as $cid) {
            $token = bin2hex(random_bytes(16));
            $stmtToken->execute([':token' => $token, ':id' => $cid]);
        }
        echo "[OK] Generated opt_out_token for " . count($emptyTokens) . " existing companies.\n";
    }

    // 2. nta_extract_progress テーブルの作成
    $createProgressSql = "
        CREATE TABLE IF NOT EXISTS `nta_extract_progress` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `prefecture_code` VARCHAR(2) NOT NULL COMMENT '都道府県コード (11=埼玉, 13=東京等)',
          `prefecture_name` VARCHAR(32) NOT NULL COMMENT '都道府県名',
          `city_code` VARCHAR(10) NULL COMMENT '市区町村コード (JISコード)',
          `city_name` VARCHAR(64) NULL COMMENT '市区町村名',
          `search_keyword` VARCHAR(50) NOT NULL COMMENT '検索キーワード (例: 設計, 工務店)',
          `status` ENUM('waiting', 'in_progress', 'completed') DEFAULT 'waiting' COMMENT '抽出ステータス',
          `current_page` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '現在取得済みのページ番号',
          `total_records` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '該当法人総数',
          `imported_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '実際に新規取り込みした件数',
          `last_extracted_at` DATETIME NULL COMMENT '最終抽出日時',
          `next_available_at` DATETIME NULL COMMENT '次回抽出可能日時 (完了から180日後)',
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY `uk_region_keyword` (`prefecture_code`, `city_code`, `search_keyword`),
          INDEX `idx_status_next` (`status`, `next_available_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($createProgressSql);
    echo "[OK] Checked/Created table `nta_extract_progress`\n";

    // 3. 初期進捗レコード（埼玉県・東京都の主要エリア）をプリセット（未登録の場合のみ）
    $presets = [
        ['11', '埼玉県', null, null, '設計'],
        ['11', '埼玉県', null, null, '工務店'],
        ['11', '埼玉県', null, null, '建築'],
        ['13', '東京都', null, null, '設計'],
        ['13', '東京都', null, null, '工務店'],
        ['13', '東京都', null, null, '建築'],
        ['14', '神奈川県', null, null, '設計'],
        ['12', '千葉県', null, null, '設計'],
    ];

    $checkStmt = $pdo->prepare("SELECT id FROM nta_extract_progress WHERE prefecture_code = :pcode AND search_keyword = :kw AND city_code IS NULL");
    $insStmt = $pdo->prepare("INSERT INTO nta_extract_progress (prefecture_code, prefecture_name, search_keyword, status) VALUES (:pcode, :pname, :kw, 'waiting')");

    $presetCount = 0;
    foreach ($presets as $p) {
        $checkStmt->execute([':pcode' => $p[0], ':kw' => $p[4]]);
        if (!$checkStmt->fetchColumn()) {
            $insStmt->execute([':pcode' => $p[0], ':pname' => $p[1], ':kw' => $p[4]]);
            $presetCount++;
        }
    }
    echo "[OK] Inserted {$presetCount} initial region presets into `nta_extract_progress`.\n";

    echo "\n=== Migration Finished Successfully! ===\n";

} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
