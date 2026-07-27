<?php
/**
 * プレミアムサポート 非公開添付ファイル ダウンロードプロキシ
 */

require_once __DIR__ . '/../auth_helper.php';

$user = getAuthenticatedUser();
if (!$user) {
    http_response_code(401);
    die('Unauthorized');
}

$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$fileType = $_GET['type'] ?? 'dxf'; // 'dxf' または 'pdf'
$messageId = isset($_GET['message_id']) ? (int)$_GET['message_id'] : 0;

if (!$ticketId) {
    http_response_code(400);
    die('Bad Request');
}

$pdo = getPdoConnection();

// チケット所有権検証
$stmt = $pdo->prepare("SELECT ticket_id, user_id, dxf_file_path FROM support_tickets WHERE ticket_id = :id");
$stmt->execute(['id' => $ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    die('Ticket Not Found');
}

// 他人のチケットは閲覧不可（スタッフ除く）
if (!$user['is_staff'] && (int)$ticket['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    die('Forbidden');
}

$filePath = null;
$downloadName = "file";

if ($fileType === 'dxf') {
    $filePath = $ticket['dxf_file_path'];
    $downloadName = "drawing_ticket_" . $ticketId . ".dxf";
} elseif ($fileType === 'pdf' && $messageId > 0) {
    $mStmt = $pdo->prepare("SELECT attachment_pdf_path FROM ticket_messages WHERE message_id = :mid AND ticket_id = :tid");
    $mStmt->execute(['mid' => $messageId, 'tid' => $ticketId]);
    $msg = $mStmt->fetch();
    if ($msg) {
        $filePath = $msg['attachment_pdf_path'];
        $downloadName = "corrected_ticket_" . $ticketId . "_msg_" . $messageId . ".pdf";
    }
}

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    die('File Not Found');
}

$mime = 'application/octet-stream';
if (str_ends_with(strtolower($filePath), '.pdf')) {
    $mime = 'application/pdf';
} elseif (str_ends_with(strtolower($filePath), '.dxf')) {
    $mime = 'image/vnd.dxf';
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($downloadName) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
