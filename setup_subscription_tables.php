<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * 契約・決済・サーバー間同期データベースマイグレーション
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

echo "=== J-ALG Subscription Database Migration Start ===\n";
echo "Connecting to MySQL: {$dbUser}@{$dbHost}/{$dbName}\n";

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Connected to MySQL successfully.\n\n";

    $sqls = [
        "users" => "
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `company_id` BIGINT UNSIGNED NULL COMMENT 'J-ALGのリード経由の場合、companies(id)と関連付け',
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NULL,
                `company_name` VARCHAR(100) NULL,
                `user_name` VARCHAR(50) NULL,
                `role` ENUM('general', 'internal_staff', 'admin') NOT NULL DEFAULT 'general' COMMENT 'internal_staffは無償アカウント',
                `stripe_customer_id` VARCHAR(100) NULL UNIQUE COMMENT 'Stripe側の顧客ID (cus_xxx)',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL,
                INDEX `idx_email` (`email`),
                INDEX `idx_role` (`role`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "subscriptions" => "
            CREATE TABLE IF NOT EXISTS `subscriptions` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `stripe_subscription_id` VARCHAR(100) NULL UNIQUE COMMENT 'sub_xxx (銀行振込単発・無償アカウント等はNULL)',
                `plan_type` ENUM('spot', 'monthly', 'yearly', 'free_permanent', 'free_trial') NOT NULL DEFAULT 'free_trial',
                `payment_method` ENUM('credit_card', 'bank_transfer', 'none') NOT NULL DEFAULT 'none',
                `status` ENUM('active', 'past_due', 'canceled', 'pending_transfer') NOT NULL DEFAULT 'pending_transfer',
                `current_period_start` DATETIME NOT NULL,
                `current_period_end` DATETIME NOT NULL COMMENT 'free_permanentの場合は 2099-12-31 23:59:59 等を設定',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_subs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                UNIQUE KEY `uk_user_id` (`user_id`),
                INDEX `idx_user_status` (`user_id`, `status`),
                INDEX `idx_period_end` (`current_period_end`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "payment_logs" => "
            CREATE TABLE IF NOT EXISTS `payment_logs` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `stripe_invoice_id` VARCHAR(100) NULL COMMENT 'in_xxx',
                `amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '税込決済金額',
                `tax_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT '内消費税額',
                `currency` VARCHAR(10) DEFAULT 'jpy',
                `payment_method` ENUM('credit_card', 'bank_transfer', 'manual', 'internal', 'none') NOT NULL DEFAULT 'credit_card',
                `status` ENUM('succeeded', 'failed', 'refunded', 'pending') NOT NULL DEFAULT 'succeeded',
                `paid_at` DATETIME NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                INDEX `idx_paid_at` (`paid_at`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "api_sync_logs" => "
            CREATE TABLE IF NOT EXISTS `api_sync_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `sync_type` ENUM('webhook_push', 'api_pull', 'manual_sync') NOT NULL,
                `target_user_id` INT UNSIGNED NULL,
                `request_payload` JSON NULL,
                `response_code` INT NOT NULL,
                `error_message` TEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_created_at` (`created_at`),
                INDEX `idx_response_code` (`response_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        "
    ];

    foreach ($sqls as $tableName => $sql) {
        echo "Creating table [{$tableName}]... ";
        $pdo->exec($sql);
        echo "SUCCESS.\n";
    }

    echo "\n=== Subscription Migration Completed Successfully! ===\n";

} catch (PDOException $e) {
    echo "\n[ERROR] Subscription Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
