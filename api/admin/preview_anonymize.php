<?php
/**
 * ナレッジ昇格用 自動アノニマイズ（スクラブ）プレビュー API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../auth_helper.php';

$user = requireStaffUser();
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;

if (!$ticketId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ticket_id が指定されていません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getPdoConnection();

$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE ticket_id = :id");
$stmt->execute(['id' => $ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => '対象チケットが見つかりません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mStmt = $pdo->prepare("SELECT * FROM ticket_messages WHERE ticket_id = :id ORDER BY created_at ASC");
$mStmt->execute(['id' => $ticketId]);
$messages = $mStmt->fetchAll();

// テキスト構築
$rawDraft = "### 【質問】 " . $ticket['title'] . "\n\n";
foreach ($messages as $m) {
    $senderLabel = ($m['sender_type'] === 'staff') ? "【サポート回答】" : "【ユーザー質問】";
    $rawDraft .= "**{$senderLabel}** (" . substr($m['created_at'], 0, 16) . ")\n";
    $rawDraft .= $m['message_text'] . "\n\n";
}

// 自動アノニマイズ（スクラブ前処理）の正規表現・辞書置換
function scrubPersonalData($text) {
    // メールアドレス
    $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '【メールアドレス非公開】', $text);
    // 電話番号 (ハイフンあり/なし)
    $text = preg_replace('/0\d{1,4}[-\s]?\d{1,4}[-\s]?\d{3,4}/', '【電話番号非公開】', $text);
    // 法人名・工務店名パターン (〇〇工務店, 株式会社〇〇等)
    $text = preg_replace('/(株式会社|有限会社|合同会社|一級建築士事務所|二級建築士事務所|工務店|建設|建築|\b[A-Z]{2,}\b)/u', '【A社／設計事務所様】', $text);
    // 〇〇邸、〇〇様邸、〇〇プロジェクト
    $text = preg_replace('/[一-龠A-Za-z0-9]+(邸|様邸|プロジェクト|現場)/u', '【木造モデル物件】', $text);

    return $text;
}

$cleanTitle = scrubPersonalData($ticket['title']);
$cleanDraft = scrubPersonalData($rawDraft);

echo json_encode([
    'status' => 'success',
    'source_ticket_id' => $ticketId,
    'clean_title' => $cleanTitle,
    'clean_category' => $ticket['category'],
    'clean_content_md' => $cleanDraft
], JSON_UNESCAPED_UNICODE);
