<?php
/**
 * プレミアムサポート チケット詳細・スレッドメッセージ取得 API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../auth_helper.php';

$user = getAuthenticatedUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'ログインが必要です。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
if (!$ticketId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ticket_id が指定されていません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getPdoConnection();

$stmt = $pdo->prepare("
    SELECT t.*, u.user_name, u.company_name, u.email AS user_email
    FROM support_tickets t
    JOIN users u ON t.user_id = u.id
    WHERE t.ticket_id = :id
");
$stmt->execute(['id' => $ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => '対象の質疑カードが見つかりません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 他人のチケットは閲覧不可（スタッフを除く）
if (!$user['is_staff'] && (int)$ticket['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'アクセス権限がありません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

// メッセージ一覧取得
$mStmt = $pdo->prepare("
    SELECT m.*, u.user_name AS sender_name
    FROM ticket_messages m
    LEFT JOIN users u ON m.sender_id = u.id
    WHERE m.ticket_id = :tid
    ORDER BY m.created_at ASC
");
$mStmt->execute(['tid' => $ticketId]);
$messages = $mStmt->fetchAll();

// ダウンロード用プロキシURLおよびGoogle Drive URL生成
$dxfDownloadUrl = null;
if (!empty($ticket['dxf_file_path'])) {
    $dxfDownloadUrl = "/api/support/download_private_file.php?ticket_id={$ticketId}&type=dxf";
}

$ticket['google_drive_file_url'] = !empty($ticket['drive_file_id']) ? "https://drive.google.com/file/d/{$ticket['drive_file_id']}/view?usp=drivesdk" : null;
$ticket['google_drive_folder_url'] = !empty($ticket['drive_folder_id']) ? "https://drive.google.com/drive/folders/{$ticket['drive_folder_id']}" : null;

foreach ($messages as &$msg) {
    if (!empty($msg['attachment_pdf_path'])) {
        $msg['pdf_download_url'] = "/api/support/download_private_file.php?ticket_id={$ticketId}&type=pdf&message_id={$msg['message_id']}";
    }
    if (!empty($msg['drive_file_id'])) {
        $msg['google_drive_file_url'] = "https://drive.google.com/file/d/{$msg['drive_file_id']}/view?usp=drivesdk";
    }
}
unset($msg);

echo json_encode([
    'status' => 'success',
    'ticket' => $ticket,
    'messages' => $messages,
    'dxf_download_url' => $dxfDownloadUrl
], JSON_UNESCAPED_UNICODE);
