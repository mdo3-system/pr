<?php
/**
 * J-ALG DBマイグレーション: 同一メール複数社重複フラグ (is_duplicate_email) 追加
 */

function getEnvParam($key, $default = null) {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return $default;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $t = trim($line);
        if ($t === '' || strpos($t, '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === $key) return trim($v, " \t\n\r\0\x0B\"'");
    }
    return $default;
}

$dbHost = getEnvParam('DB_HOST', 'localhost');
$dbPort = getEnvParam('DB_PORT', '3306');
$dbName = getEnvParam('DB_DATABASE', 'mdo3_pr');
$dbUser = getEnvParam('DB_USERNAME', 'mdo3_toolapp0001');
$dbPass = getEnvParam('DB_PASSWORD', '');

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $cols = $pdo->query("SHOW COLUMNS FROM `companies`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_duplicate_email', $cols)) {
        $pdo->exec("ALTER TABLE `companies` ADD COLUMN `is_duplicate_email` BOOLEAN NOT NULL DEFAULT FALSE COMMENT '同一メール複数社重複フラグ' AFTER `is_opt_out`");
        echo "[OK] Added column `is_duplicate_email` to `companies`\n";
    } else {
        echo "[SKIP] Column `is_duplicate_email` already exists.\n";
    }

    $indexes = $pdo->query("SHOW INDEX FROM `companies`")->fetchAll(PDO::FETCH_ASSOC);
    $idxNames = array_column($indexes, 'Key_name');
    if (!in_array('idx_dup_email', $idxNames)) {
        $pdo->exec("ALTER TABLE `companies` ADD INDEX `idx_dup_email` (`is_duplicate_email`)");
        echo "[OK] Added index `idx_dup_email`\n";
    }

    // 既存の重複メールアドレスにフラグを反映
    $pdo->exec("
        UPDATE companies c
        JOIN (
            SELECT email FROM companies
            WHERE email IS NOT NULL AND email != ''
            GROUP BY email HAVING COUNT(*) > 1
        ) dup ON c.email = dup.email
        SET c.is_duplicate_email = 1
    ");
    echo "[OK] Flagged existing duplicate emails.\n";

} catch (Exception $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
