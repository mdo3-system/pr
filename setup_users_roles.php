<?php
/**
 * J-ALG (木造壁量計算WEB 上善如水 サポートポータル)
 * ユーザーロール拡張・マジックリンクトークンテーブル作成 & アカウント一括登録スクリプト
 *
 * 実行コマンド: php setup_users_roles.php
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

echo "=== Setup Users Roles & Magic Link Tokens Database ===\n";

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Connected to MySQL successfully.\n";

    // 1. users テーブルの role 列拡張
    echo "Modifying users table role column... ";
    $pdo->exec("
        ALTER TABLE `users` 
        MODIFY COLUMN `role` ENUM('general', 'premium', 'support', 'accounting', 'admin', 'internal_staff') 
        NOT NULL DEFAULT 'general' COMMENT 'ユーザー権限区分';
    ");
    echo "SUCCESS.\n";

    // 2. magic_tokens テーブル構築
    echo "Creating magic_tokens table... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `magic_tokens` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL COMMENT '対象ユーザーID',
            `token` VARCHAR(64) NOT NULL UNIQUE COMMENT 'ワンタイムトークン',
            `expires_at` DATETIME NOT NULL COMMENT '有効期限',
            `used_at` DATETIME NULL COMMENT '使用済日時',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_magic_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_token` (`token`),
            INDEX `idx_user_expires` (`user_id`, `expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "SUCCESS.\n";

    // 3. アカウント登録リスト
    $accounts = [
        [
            'email' => 'koki@t-smile.co.jp',
            'name' => '管理者 (甲木)',
            'company' => '株式会社 T-Smile',
            'role' => 'admin'
        ],
        [
            'email' => 'keiri@t-smile.co.jp',
            'name' => '会計担当',
            'company' => '株式会社 T-Smile',
            'role' => 'accounting'
        ],
        [
            'email' => 'sato@t-smile.co.jp',
            'name' => '動作サポート担当 (佐藤)',
            'company' => '株式会社 T-Smile',
            'role' => 'support'
        ],
        [
            'email' => 'sales@t-smile.co.jp',
            'name' => '動作サポート担当 (営業)',
            'company' => '株式会社 T-Smile',
            'role' => 'support'
        ],
    ];

    $pdo->beginTransaction();

    foreach ($accounts as $acc) {
        // users Upsert
        $stmtUser = $pdo->prepare("
            INSERT INTO users (email, company_name, user_name, role)
            VALUES (:email, :company, :name, :role)
            ON DUPLICATE KEY UPDATE
                role = VALUES(role),
                company_name = VALUES(company_name),
                user_name = VALUES(user_name)
        ");
        $stmtUser->execute([
            ':email' => $acc['email'],
            ':company' => $acc['company'],
            ':name' => $acc['name'],
            ':role' => $acc['role']
        ]);

        $stmtGet = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmtGet->execute([':email' => $acc['email']]);
        $userId = $stmtGet->fetchColumn();

        // subscriptions Upsert (free_permanent & active until 2099)
        $stmtSub = $pdo->prepare("
            INSERT INTO subscriptions (user_id, plan_type, payment_method, status, current_period_start, current_period_end)
            VALUES (:uid, 'free_permanent', 'none', 'active', NOW(), '2099-12-31 23:59:59')
            ON DUPLICATE KEY UPDATE
                plan_type = 'free_permanent',
                payment_method = 'none',
                status = 'active',
                current_period_end = '2099-12-31 23:59:59'
        ");
        $stmtSub->execute([':uid' => $userId]);

        echo "[REGISTERED] Email: {$acc['email']} | Role: {$acc['role']} | User ID: {$userId}\n";
    }

    $pdo->commit();
    echo "\n=== All Accounts Setup Completed Successfully! ===\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n[ERROR] Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}
