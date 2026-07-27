<?php
/**
 * プレミアムサポート＆ナレッジ昇格システム
 * 認証・認可共通ヘルパー (api/auth_helper.php)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getPdoConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $envFile = dirname(__DIR__) . '/.env';
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
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    return $pdo;
}

/**
 * ログインユーザー情報とプレミアム権限を取得
 */
function getAuthenticatedUser() {
    $pdo = getPdoConnection();
    
    // セッションまたは開発用クエリ/ヘッダーからのユーザー識別
    $userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;
    
    if (!$userId) {
        // 未ログインの場合、ゲストまたはデモ用ユーザー1（存在しなければnull）
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT u.*, s.plan_type, s.status AS sub_status 
        FROM users u 
        LEFT JOIN subscriptions s ON u.id = s.user_id 
        WHERE u.id = :id
    ");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if ($user) {
        // プレミアム判定: admin, internal_staff, または activeサブスクリプション
        $isStaff = in_array($user['role'], ['admin', 'internal_staff'], true);
        $isSubActive = in_array($user['sub_status'], ['active'], true) || in_array($user['plan_type'], ['free_permanent'], true);
        
        $user['is_staff'] = $isStaff;
        $user['is_premium'] = $isStaff || $isSubActive;
    }

    return $user;
}

/**
 * プレミアムユーザーまたはスタッフであるかを検証（違反時JSONエラー出力）
 */
function requirePremiumUser() {
    $user = getAuthenticatedUser();
    if (!$user || !$user['is_premium']) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'プレミアムサポート対象のユーザーアカウントまたはログインが必要です。'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $user;
}

/**
 * 管理者・スタッフ権限であるかを検証
 */
function requireStaffUser() {
    $user = getAuthenticatedUser();
    if (!$user || !$user['is_staff']) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => '管理者・運営スタッフ権限が必要です。'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $user;
}
