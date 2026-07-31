<?php
/**
 * 会計用データ取得 API (api/admin/get_accounting_data.php)
 */
require_once dirname(__DIR__) . '/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

$user = getAuthenticatedUser();
if (!$user || !in_array($user['role'], ['accounting', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => '権限がありません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getPdoConnection();

try {
    // 1. ユーザー＆サブスク一覧
    $stmtUsers = $pdo->query("
        SELECT u.id, u.email, u.user_name, u.company_name, u.role, 
               s.plan_type, s.status AS sub_status, s.current_period_end
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.user_id
        ORDER BY u.id DESC
    ");
    $users = $stmtUsers->fetchAll();

    foreach ($users as &$u) {
        $u['role_label'] = getRoleLabelJp($u['role']);
    }

    // 2. 統計数値
    $totalUsers = count($users);
    $activeSubs = 0;
    $freePerm = 0;

    foreach ($users as $u) {
        if ($u['sub_status'] === 'active' && $u['plan_type'] !== 'free_permanent') {
            $activeSubs++;
        }
        if ($u['plan_type'] === 'free_permanent') {
            $freePerm++;
        }
    }

    // 今月決済額
    $stmtRev = $pdo->query("
        SELECT SUM(amount) AS total 
        FROM payment_logs 
        WHERE status = 'succeeded' AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ");
    $monthlyRev = (float)($stmtRev->fetchColumn() ?: 0);

    echo json_encode([
        'status' => 'success',
        'stats' => [
            'total_users' => $totalUsers,
            'active_subs' => $activeSubs,
            'free_perm' => $freePerm,
            'monthly_revenue' => $monthlyRev
        ],
        'users' => $users
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
