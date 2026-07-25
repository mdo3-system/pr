<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * データベース・マイグレーション実行スクリプト (Step 1)
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

echo "=== J-ALG Database Migration Start (v1.0.0) ===\n";
echo "Connecting to MySQL: {$dbUser}@{$dbHost}/{$dbName}\n";

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Connected to MySQL successfully.\n\n";

    $sqls = [
        "companies" => "
            CREATE TABLE IF NOT EXISTS `companies` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `corporate_number` VARCHAR(13) NULL COMMENT '国税庁法人番号 (13桁)',
              `name` VARCHAR(255) NOT NULL COMMENT '正規法人名・事務所名',
              `clean_name` VARCHAR(255) NOT NULL COMMENT '検索用クリーン企業名',
              `prefecture` VARCHAR(32) NOT NULL COMMENT '都道府県',
              `city` VARCHAR(64) NOT NULL COMMENT '市区町村',
              `address` VARCHAR(255) NOT NULL COMMENT '以降の所在地',
              `postal_code` VARCHAR(10) NULL COMMENT '郵便番号',
              `category` ENUM('architect_office', 'constructor', 'builder', 'other') DEFAULT 'architect_office' COMMENT '業種種別',
              `official_url` VARCHAR(512) NULL COMMENT '公式サイトURL',
              `contact_url` VARCHAR(512) NULL COMMENT 'お問い合わせページURL',
              `email` VARCHAR(255) NULL COMMENT '抽出メールアドレス',
              `fax` VARCHAR(50) NULL COMMENT '抽出FAX番号',
              `phone` VARCHAR(50) NULL COMMENT '電話番号',
              `source_type` VARCHAR(50) DEFAULT 'nta_api' COMMENT 'データ出所',
              `status` ENUM('pending', 'crawled', 'approved', 'rejected', 'sent', 'failed') DEFAULT 'pending' COMMENT '処理ステータス',
              `is_opt_out` BOOLEAN DEFAULT FALSE COMMENT '特電法・拒否フラグ',
              `opt_out_reason` VARCHAR(255) NULL COMMENT '拒否理由',
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY `uk_corporate_number` (`corporate_number`),
              INDEX `idx_prefecture_city` (`prefecture`, `city`),
              INDEX `idx_status_opt` (`status`, `is_opt_out`),
              INDEX `idx_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "crawl_logs" => "
            CREATE TABLE IF NOT EXISTS `crawl_logs` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `company_id` BIGINT UNSIGNED NOT NULL,
              `target_url` VARCHAR(512) NOT NULL COMMENT 'クロールしたURL',
              `http_status` SMALLINT NULL COMMENT 'HTTPステータスコード',
              `extracted_email` VARCHAR(255) NULL COMMENT '抽出メールアドレス',
              `extracted_fax` VARCHAR(50) NULL COMMENT '抽出FAX番号',
              `detected_optout_keywords` TEXT NULL COMMENT '検知したお断りキーワード',
              `raw_text_snippet` TEXT NULL COMMENT '証跡用スニペット',
              `crawled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
              INDEX `idx_company_id` (`company_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "email_templates" => "
            CREATE TABLE IF NOT EXISTS `email_templates` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `template_name` VARCHAR(100) NOT NULL COMMENT '管理用名称',
              `subject` VARCHAR(255) NOT NULL COMMENT '件名',
              `body_text` TEXT NOT NULL COMMENT 'プレーンテキスト本文',
              `body_html` TEXT NULL COMMENT 'HTML本文',
              `is_active` BOOLEAN DEFAULT TRUE COMMENT 'デフォルト有効フラグ',
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "send_logs" => "
            CREATE TABLE IF NOT EXISTS `send_logs` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `company_id` BIGINT UNSIGNED NOT NULL,
              `template_id` BIGINT UNSIGNED NOT NULL,
              `email_to` VARCHAR(255) NOT NULL,
              `sendgrid_message_id` VARCHAR(255) NULL COMMENT 'SendGrid Message-ID',
              `status` ENUM('queued', 'sending', 'delivered', 'opened', 'clicked', 'bounced', 'spam_report', 'error') DEFAULT 'queued',
              `error_message` TEXT NULL,
              `scheduled_at` DATETIME NOT NULL COMMENT '送信予定日時',
              `sent_at` DATETIME NULL COMMENT '実送信日時',
              `opened_at` DATETIME NULL COMMENT '初回開封日時',
              `clicked_at` DATETIME NULL COMMENT '初回クリック日時',
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
              FOREIGN KEY (`template_id`) REFERENCES `email_templates`(`id`),
              INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
              INDEX `idx_sg_msg_id` (`sendgrid_message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        "
    ];

    foreach ($sqls as $tableName => $sql) {
        echo "Creating table [{$tableName}]... ";
        $pdo->exec($sql);
        echo "SUCCESS.\n";
    }

    echo "\n=== Migration Completed Successfully! ===\n";

} catch (PDOException $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
