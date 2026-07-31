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

    // --- Google Drive 連携処理 ---
    $driveFolderId = null;
    $driveFileId = null;
    try {
        require_once __DIR__ . '/google_drive_client.php';
        
        // 1. ユーザーフォルダを取得・生成
        $userDriveFolderId = get_or_create_user_drive_folder($pdo, $user['id']);
        
        // 2. チケット専用フォルダを作成
        $ticketFolderName = "Ticket_#" . $ticketId . "_" . preg_replace('/[^\w\-\.\s]/u', '_', $title);
        $driveFolderId = create_google_drive_folder($ticketFolderName, $userDriveFolderId);

        // 3. DXFファイルが存在すれば Google Drive へアップロード
        if ($dxfSavedPath && file_exists($dxfSavedPath)) {
            $mimeType = 'application/octet-stream';
            $driveFileId = upload_to_google_drive_folder($dxfSavedPath, basename($dxfSavedPath), $mimeType, $driveFolderId);
        }

        // DBにDrive情報を更新
        $updateDriveStmt = $pdo->prepare("
            UPDATE support_tickets 
            SET drive_folder_id = :dfid, drive_file_id = :dfileid 
            WHERE ticket_id = :tid
        ");
        $updateDriveStmt->execute([
            'dfid' => $driveFolderId,
            'dfileid' => $driveFileId,
            'tid' => $ticketId
        ]);
    } catch (Exception $driveEx) {
        error_log("Google Drive Sync Notice: " . $driveEx->getMessage());
        // Google Driveエラー発生時もローカル処理は成功とする（ローカルフォールバック）
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => '質疑カードを作成しました。',
        'ticket_id' => $ticketId,
        'drive_folder_id' => $driveFolderId,
        'drive_file_id' => $driveFileId
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
