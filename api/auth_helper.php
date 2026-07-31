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
 * 各ロールに応じたポータルURLを取得
 */
function getPortalUrlByRole($role) {
    switch ($role) {
        case 'admin':
            return '/admin/support_manager.php';
        case 'accounting':
            return '/admin/accounting_portal.php';
        case 'support':
            return '/admin/support_portal.php';
        case 'premium':
        case 'general':
        case 'internal_staff':
        default:
            return '/my/support_dashboard.php';
    }
}

/**
 * ロール表示名（日本語）
 */
function getRoleLabelJp($role) {
    switch ($role) {
        case 'admin':
            return '管理者';
        case 'accounting':
            return '会計担当';
        case 'support':
            return '動作サポート担当';
        case 'premium':
            return 'プレミアムサポート会員';
        case 'internal_staff':
            return '社内スタッフ';
        case 'general':
        default:
            return '一般ユーザー';
    }
}

/**
 * ログインユーザー情報とプレミアム・ロール権限を取得
 */
function getAuthenticatedUser() {
    $pdo = getPdoConnection();
    
    $userId = $_SESSION['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;
    
    if (!$userId) {
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
        $isStaff = in_array($user['role'], ['admin', 'accounting', 'support', 'internal_staff'], true);
        $isSubActive = in_array($user['sub_status'], ['active'], true) || in_array($user['plan_type'], ['free_permanent'], true);
        
        $user['is_staff'] = $isStaff;
        $user['is_premium'] = $isStaff || $isSubActive || $user['role'] === 'premium';
        $user['portal_url'] = getPortalUrlByRole($user['role']);
        $user['role_label'] = getRoleLabelJp($user['role']);
    }

    return $user;
}

/**
 * プレミアムユーザーまたは各種スタッフであるかを検証（違反時JSONエラー出力）
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
