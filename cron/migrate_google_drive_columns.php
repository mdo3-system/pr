<?php
/**
 * cron/migrate_google_drive_columns.php
 * 
 * Google Drive 連携用カラム（drive_folder_id / drive_file_id）のDBマイグレーション
 */

function getEnvParam($key, $default = null) {
    static $env = null;
    if ($env === null) {
        $envFile = __DIR__ . '/../.env';
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

echo "=== Google Drive Migration Start ===\n";

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Connected to MySQL successfully.\n";

    // カラム存在チェック関数
    $columnExists = function($pdo, $table, $column) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :col");
        $stmt->execute(['col' => $column]);
        return $stmt->fetch() !== false;
    };

    // 1. users.drive_folder_id
    if (!$columnExists($pdo, 'users', 'drive_folder_id')) {
        echo "Adding `drive_folder_id` to `users`... ";
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `drive_folder_id` VARCHAR(255) NULL COMMENT 'ユーザー専用Google DriveフォルダID' AFTER `role`");
        echo "SUCCESS.\n";
    } else {
        echo "`users.drive_folder_id` already exists.\n";
    }

    // 2. support_tickets.drive_folder_id & drive_file_id
    if (!$columnExists($pdo, 'support_tickets', 'drive_folder_id')) {
        echo "Adding `drive_folder_id` to `support_tickets`... ";
        $pdo->exec("ALTER TABLE `support_tickets` ADD COLUMN `drive_folder_id` VARCHAR(255) NULL COMMENT 'チケット用Google DriveフォルダID' AFTER `dxf_file_path`");
        echo "SUCCESS.\n";
    }
    if (!$columnExists($pdo, 'support_tickets', 'drive_file_id')) {
        echo "Adding `drive_file_id` to `support_tickets`... ";
        $pdo->exec("ALTER TABLE `support_tickets` ADD COLUMN `drive_file_id` VARCHAR(255) NULL COMMENT 'メイン添付のGoogle Drive File ID' AFTER `drive_folder_id`");
        echo "SUCCESS.\n";
    }

    // 3. ticket_messages.drive_file_id
    if (!$columnExists($pdo, 'ticket_messages', 'drive_file_id')) {
        echo "Adding `drive_file_id` to `ticket_messages`... ";
        $pdo->exec("ALTER TABLE `ticket_messages` ADD COLUMN `drive_file_id` VARCHAR(255) NULL COMMENT '添削ファイル等のGoogle Drive File ID' AFTER `attachment_pdf_path`");
        echo "SUCCESS.\n";
    }

    echo "\n=== Google Drive Migration Completed Successfully! ===\n";

} catch (PDOException $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
