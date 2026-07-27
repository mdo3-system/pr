<?php
/**
 * プレミアムサポート チャット返信・データ追加 API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../auth_helper.php';

$user = getAuthenticatedUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'ログインが必要です。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
$messageText = trim($_POST['message_text'] ?? '');
$youtubeUrl = trim($_POST['youtube_url'] ?? '');
$zoomUrl = trim($_POST['zoom_url'] ?? '');
$newStatus = trim($_POST['status'] ?? '');

if (!$ticketId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ticket_id は必須です。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getPdoConnection();

// チケット存在・所有確認
$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE ticket_id = :id");
$stmt->execute(['id' => $ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => '対象チケットが存在しません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$user['is_staff'] && (int)$ticket['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => '権限がありません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdfSavedPath = null;

// PDF添付（スタッフ添削用）処理
if (isset($_FILES['attachment_pdf']) && $_FILES['attachment_pdf']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = dirname(__DIR__, 2) . '/storage/private_uploads/pdf/' . $ticketId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = strtolower(pathinfo($_FILES['attachment_pdf']['name'], PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        $savedName = date('Ymd_His_') . uniqid() . '.pdf';
        $targetFile = $uploadDir . $savedName;
        if (move_uploaded_file($_FILES['attachment_pdf']['tmp_name'], $targetFile)) {
            $pdfSavedPath = $targetFile;
        }
    }
}

$senderType = $user['is_staff'] ? 'staff' : 'user';

try {
    $pdo->beginTransaction();

    if (!empty($messageText) || $pdfSavedPath || !empty($youtubeUrl)) {
        $mStmt = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message_text, attachment_pdf_path, youtube_url)
            VALUES (:ticket_id, :sender_type, :sender_id, :message_text, :pdf_path, :youtube_url)
        ");
        $mStmt->execute([
            'ticket_id' => $ticketId,
            'sender_type' => $senderType,
            'sender_id' => $user['id'],
            'message_text' => $messageText,
            'pdf_path' => $pdfSavedPath,
            'youtube_url' => !empty($youtubeUrl) ? $youtubeUrl : null
        ]);
    }

    // ステータスまたはZoom URL更新
    $updateFields = [];
    $updateParams = ['id' => $ticketId];

    if ($user['is_staff'] && !empty($zoomUrl)) {
        $updateFields[] = "zoom_url = :zoom_url";
        $updateParams['zoom_url'] = $zoomUrl;
    }

    if (!empty($newStatus) && in_array($newStatus, ['open', 'in_progress', 'resolved', 'closed'], true)) {
        $updateFields[] = "status = :status";
        $updateParams['status'] = $newStatus;
    } else {
        // スタッフ返信時はデフォルトで in_progress
        if ($user['is_staff'] && $ticket['status'] === 'open') {
            $updateFields[] = "status = 'in_progress'";
        }
    }

    if (!empty($updateFields)) {
        $updateSql = "UPDATE support_tickets SET " . implode(", ", $updateFields) . " WHERE ticket_id = :id";
        $uStmt = $pdo->prepare($updateSql);
        $uStmt->execute($updateParams);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'メッセージを送信しました。'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'メッセージ送信エラー: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
