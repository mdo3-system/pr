<?php
/**
 * 動作サポート担当用 チケット一覧 API (api/admin/list_tickets.php)
 */
require_once dirname(__DIR__) . '/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

$user = getAuthenticatedUser();
if (!$user || !in_array($user['role'], ['support', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => '権限がありません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getPdoConnection();
$status = $_GET['status'] ?? '';

try {
    $where = [];
    $params = [];

    if ($status) {
        $where[] = "t.status = :status";
        $params['status'] = $status;
    }

    $whereClause = $where ? "WHERE " . implode(' AND ', $where) : "";

    $stmt = $pdo->prepare("
        SELECT t.*, u.user_name, u.email AS user_email
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        {$whereClause}
        ORDER BY t.created_at DESC
    ");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    echo json_encode(['status' => 'success', 'tickets' => $tickets], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
