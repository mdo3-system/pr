<?php
/**
 * プレミアムサポート チケット一覧取得 API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../auth_helper.php';

$user = getAuthenticatedUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'ログインが必要です。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getPdoConnection();

$status = $_GET['status'] ?? null;
$category = $_GET['category'] ?? null;

$where = [];
$params = [];

if (!$user['is_staff']) {
    // 一般ユーザーは自分のチケットのみ
    $where[] = "t.user_id = :user_id";
    $params['user_id'] = $user['id'];
} elseif (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $where[] = "t.user_id = :target_user_id";
    $params['target_user_id'] = $_GET['user_id'];
}

if ($status) {
    $where[] = "t.status = :status";
    $params['status'] = $status;
}

if ($category) {
    $where[] = "t.category = :category";
    $params['category'] = $category;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT t.*, u.user_name, u.company_name, u.email AS user_email,
           (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.ticket_id) AS message_count
    FROM support_tickets t
    JOIN users u ON t.user_id = u.id
    {$whereSql}
    ORDER BY t.updated_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

echo json_encode([
    'status' => 'success',
    'tickets' => $tickets
], JSON_UNESCAPED_UNICODE);
