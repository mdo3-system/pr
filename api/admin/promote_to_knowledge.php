<?php
/**
 * 管理者 ナレッジ昇格＆自動スクラブ処理 API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../auth_helper.php';

$user = requireStaffUser();
$pdo = getPdoConnection();

$ticketId = isset($_POST['source_ticket_id']) ? (int)$_POST['source_ticket_id'] : 0;
$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? 'その他');
$contentMd = trim($_POST['content_md'] ?? '');
$confirmFlag = !empty($_POST['admin_confirm_flag']) && $_POST['admin_confirm_flag'] !== 'false';

if (!$ticketId || empty($title) || empty($contentMd)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'タイトルおよび本文は必須です。'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$confirmFlag) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => '個人情報・固有物件データが含まれていないことの確認チェックボックス（admin_confirm_flag）を有効にしてください。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo->beginTransaction();

    // knowledge_posts へ挿入 (DXF/JSON等原データはスキーマに含まれず完全除外)
    $stmt = $pdo->prepare("
        INSERT INTO knowledge_posts (source_ticket_id, title, category, content_md, is_published)
        VALUES (:ticket_id, :title, :category, :content_md, 1)
    ");
    $stmt->execute([
        'ticket_id' => $ticketId,
        'title' => $title,
        'category' => $category,
        'content_md' => $contentMd
    ]);

    $postId = $pdo->lastInsertId();

    // 元の質疑カードの昇格フラグを更新
    $uStmt = $pdo->prepare("UPDATE support_tickets SET is_promoted_to_faq = 1 WHERE ticket_id = :id");
    $uStmt->execute(['id' => $ticketId]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'ナレッジ掲示板（FAQ）へ昇格・公開が完了しました。',
        'post_id' => $postId
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '昇格処理エラー: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
