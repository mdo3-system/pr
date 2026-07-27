<?php
/**
 * プレミアムサポート＆ナレッジ昇格システム
 * データベースマイグレーションスクリプト
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

echo "=== Premium Support System Database Migration Start ===\n";
echo "Connecting to MySQL: {$dbUser}@{$dbHost}/{$dbName}\n";

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Connected to MySQL successfully.\n\n";

    $sqls = [
        "support_tickets" => "
            CREATE TABLE IF NOT EXISTS `support_tickets` (
                `ticket_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL COMMENT '質問者 (users.id)',
                `title` VARCHAR(255) NOT NULL COMMENT '質問タイトル',
                `category` VARCHAR(100) NOT NULL DEFAULT 'その他' COMMENT '分類',
                `status` ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
                `dxf_file_path` VARCHAR(500) NULL COMMENT '保存ファイルパス',
                `input_data_json` LONGTEXT NULL COMMENT 'アプリ側デバッグ用JSON',
                `zoom_url` VARCHAR(500) NULL COMMENT 'Zoom接続用URL',
                `is_promoted_to_faq` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ナレッジ昇格フラグ',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_support_tickets_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                INDEX `idx_user_status` (`user_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "ticket_messages" => "
            CREATE TABLE IF NOT EXISTS `ticket_messages` (
                `message_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `ticket_id` BIGINT NOT NULL COMMENT '対象チケット',
                `sender_type` ENUM('user', 'staff') NOT NULL COMMENT '送信者区分',
                `sender_id` INT UNSIGNED NOT NULL COMMENT '送信者ID',
                `message_text` TEXT NOT NULL COMMENT '本文',
                `attachment_pdf_path` VARCHAR(500) NULL COMMENT '添削PDFパス',
                `youtube_url` VARCHAR(500) NULL COMMENT '限定公開動画URL',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT `fk_ticket_messages_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets`(`ticket_id`) ON DELETE CASCADE,
                INDEX `idx_ticket_id` (`ticket_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ",
        "knowledge_posts" => "
            CREATE TABLE IF NOT EXISTS `knowledge_posts` (
                `post_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `source_ticket_id` BIGINT NULL COMMENT '昇格元の質疑カードID',
                `title` VARCHAR(255) NOT NULL COMMENT '匿名化タイトル',
                `category` VARCHAR(100) NOT NULL DEFAULT 'その他' COMMENT 'カテゴリー',
                `content_md` LONGTEXT NOT NULL COMMENT '一般化本文(Markdown)',
                `public_image_path` VARCHAR(500) NULL COMMENT '公開可能解説画像',
                `is_published` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '公開フラグ',
                `views_count` INT NOT NULL DEFAULT 0 COMMENT '閲覧数',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT `fk_knowledge_posts_ticket` FOREIGN KEY (`source_ticket_id`) REFERENCES `support_tickets`(`ticket_id`) ON DELETE SET NULL,
                INDEX `idx_category` (`category`),
                INDEX `idx_published` (`is_published`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        "
    ];

    foreach ($sqls as $tableName => $sql) {
        echo "Creating table [{$tableName}]... ";
        $pdo->exec($sql);
        echo "SUCCESS.\n";
    }

    echo "\n=== Premium Support System Migration Completed Successfully! ===\n";

} catch (PDOException $e) {
    echo "\n[ERROR] Premium Support Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
