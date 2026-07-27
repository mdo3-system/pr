<?php
/**
 * プレミアムサポート 質疑カード新規作成 API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../auth_helper.php';

$user = requirePremiumUser();
$pdo = getPdoConnection();

$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? 'その他');
$messageText = trim($_POST['message'] ?? '');
$inputJson = $_POST['input_json'] ?? null;

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '質問タイトルは必須です。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dxfSavedPath = null;

// DXFファイルアップロード処理
if (isset($_FILES['dxf_file']) && $_FILES['dxf_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = dirname(__DIR__, 2) . '/storage/private_uploads/dxf/' . $user['id'] . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $origName = basename($_FILES['dxf_file']['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    
    if ($ext === 'dxf') {
        $savedFilename = date('Ymd_His_') . uniqid() . '.dxf';
        $targetFile = $uploadDir . $savedFilename;
        
        if (move_uploaded_file($_FILES['dxf_file']['tmp_name'], $targetFile)) {
            $dxfSavedPath = $targetFile;
        }
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO support_tickets (user_id, title, category, status, dxf_file_path, input_data_json)
        VALUES (:user_id, :title, :category, 'open', :dxf_path, :input_json)
    ");
    $stmt->execute([
        'user_id' => $user['id'],
        'title' => $title,
        'category' => $category,
        'dxf_path' => $dxfSavedPath,
        'input_json' => $inputJson
    ]);

    $ticketId = $pdo->lastInsertId();

    if (!empty($messageText)) {
        $msgStmt = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message_text)
            VALUES (:ticket_id, 'user', :sender_id, :message_text)
        ");
        $msgStmt->execute([
            'ticket_id' => $ticketId,
            'sender_id' => $user['id'],
            'message_text' => $messageText
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => '質疑カードを作成しました。',
        'ticket_id' => $ticketId
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'データベース処理中にエラーが発生しました: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
