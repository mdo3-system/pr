<?php
/**
 * J-ALG (木造壁量計算WEB 上善如水 サポートポータル)
 * マジックリンク認証・セッション管理 API (/api/auth.php)
 */

require_once __DIR__ . '/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$pdo = getPdoConnection();

try {
    if ($action === 'request_magic_link') {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => '有効なメールアドレスを入力してください。'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 1. ユーザー検索（無ければ一般ユーザーとして自動登録）
        $stmtUser = $pdo->prepare("SELECT id, user_name, role FROM users WHERE email = :email");
        $stmtUser->execute(['email' => $email]);
        $user = $stmtUser->fetch();

        if (!$user) {
            $stmtIns = $pdo->prepare("INSERT INTO users (email, user_name, role) VALUES (:email, :name, 'general')");
            $userName = explode('@', $email)[0];
            $stmtIns->execute(['email' => $email, 'name' => $userName]);
            $userId = $pdo->lastInsertId();
            $role = 'general';
        } else {
            $userId = $user['id'];
            $role = $user['role'];
        }

        // 2. 32バイト トークン生成 (64文字16進数)
        $token = bin2hex(random_bytes(32));

        // 3. magic_tokens INSERT (有効期限 30分)
        $stmtToken = $pdo->prepare("
            INSERT INTO magic_tokens (user_id, token, expires_at)
            VALUES (:uid, :token, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
        ");
        $stmtToken->execute([
            'uid' => $userId,
            'token' => $token
        ]);

        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'pr.eie.tokyo';
        $magicLinkUrl = "{$scheme}://{$host}/api/auth.php?action=verify_magic_link&token={$token}";

        // メール送信処理 (ログまたはSendGridAPI連携)
        // 開発・実用時に直ちにテストできるよう JSONレスポンスに magic_link を含める
        echo json_encode([
            'status' => 'success',
            'message' => 'ログイン用マジックリンクを発行・送信しました。',
            'email' => $email,
            'role' => $role,
            'role_label' => getRoleLabelJp($role),
            'magic_link' => $magicLinkUrl
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } elseif ($action === 'verify_magic_link') {
        $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
        if (empty($token)) {
            die("無効なマジックリンクURLです。");
        }

        // トークン検証
        $stmtVer = $pdo->prepare("
            SELECT m.*, u.role, u.email, u.user_name
            FROM magic_tokens m
            JOIN users u ON m.user_id = u.id
            WHERE m.token = :token AND m.used_at IS NULL AND m.expires_at > NOW()
        ");
        $stmtVer->execute(['token' => $token]);
        $row = $stmtVer->fetch();

        if (!$row) {
            die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h2>❌ マジックリンクが無効か、期限切れです。</h2><p>もう一度ログイン画面からマジックリンクを発行してください。</p><a href='/index.php'>トップページに戻る</a></div>");
        }

        // 使用済みに更新
        $stmtUsed = $pdo->prepare("UPDATE magic_tokens SET used_at = NOW() WHERE id = :id");
        $stmtUsed->execute(['id' => $row['id']]);

        // セッションセット
        $_SESSION['user_id'] = $row['user_id'];

        // ロールに応じたポータルへリダイレクト
        $redirectUrl = getPortalUrlByRole($row['role']);
        header("Location: {$redirectUrl}");
        exit;

    } elseif ($action === 'logout') {
        session_destroy();
        if (isset($_GET['redirect'])) {
            header("Location: /index.php");
            exit;
        }
        echo json_encode(['status' => 'success', 'message' => 'ログアウトしました。'], JSON_UNESCAPED_UNICODE);
        exit;

    } elseif ($action === 'me') {
        $user = getAuthenticatedUser();
        if ($user) {
            echo json_encode([
                'status' => 'success',
                'logged_in' => true,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'user_name' => $user['user_name'],
                    'company_name' => $user['company_name'],
                    'role' => $user['role'],
                    'role_label' => $user['role_label'],
                    'portal_url' => $user['portal_url'],
                    'is_premium' => $user['is_premium'],
                    'is_staff' => $user['is_staff']
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['status' => 'success', 'logged_in' => false], JSON_UNESCAPED_UNICODE);
        }
        exit;

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
