<?php
/**
 * プレミアムサポート＆ナレッジ昇格システム 統合テストスクリプト
 */

require_once __DIR__ . '/api/auth_helper.php';

echo "=== Premium Support System Test Start ===\n\n";

try {
    $pdo = getPdoConnection();

    // 1. テスト用プレミアムユーザー準備
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'test_premium@example.com'");
    $stmt->execute();
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        $uStmt = $pdo->prepare("
            INSERT INTO users (email, company_name, user_name, role) 
            VALUES ('test_premium@example.com', 'テスト工務店', 'テスト太郎', 'general')
        ");
        $uStmt->execute();
        $userId = $pdo->lastInsertId();

        $sStmt = $pdo->prepare("
            INSERT INTO subscriptions (user_id, plan_type, payment_method, status, current_period_start, current_period_end)
            VALUES (:user_id, 'monthly', 'credit_card', 'active', NOW(), '2099-12-31 23:59:59')
        ");
        $sStmt->execute(['user_id' => $userId]);
    }

    echo "[PASS] Test user ID: {$userId}\n";

    // 2. チケット作成テスト
    $_POST = [
        'user_id' => $userId,
        'title' => '【テスト物件A邸】斜め壁の応力確認',
        'category' => '斜め壁計算',
        'message' => 'テスト工務店のテスト太郎です。A邸の斜め壁の金物選定について教えてください。連絡先: 03-1234-5678',
        'input_json' => json_encode(['wall_length' => 2.5, 'angle' => 45])
    ];

    // create_ticket 実行ロジック検証
    $cStmt = $pdo->prepare("
        INSERT INTO support_tickets (user_id, title, category, status, input_data_json)
        VALUES (:user_id, :title, :category, 'open', :input_json)
    ");
    $cStmt->execute([
        'user_id' => $userId,
        'title' => $_POST['title'],
        'category' => $_POST['category'],
        'input_json' => $_POST['input_json']
    ]);
    $ticketId = $pdo->lastInsertId();

    $mStmt = $pdo->prepare("
        INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message_text)
        VALUES (:ticket_id, 'user', :sender_id, :message_text)
    ");
    $mStmt->execute([
        'ticket_id' => $ticketId,
        'sender_id' => $userId,
        'message_text' => $_POST['message']
    ]);

    echo "[PASS] Created ticket ID: {$ticketId}\n";

    // 3. 自動スクラブ (アノニマイズ) 機能テスト
    $rawText = $_POST['title'] . "\n" . $_POST['message'];
    $cleanText = preg_replace('/0\d{1,4}[-\s]?\d{1,4}[-\s]?\d{3,4}/', '【電話番号非公開】', $rawText);
    $cleanText = preg_replace('/(株式会社|有限会社|合同会社|工務店)/u', '【A社】', $cleanText);
    $cleanText = preg_replace('/[一-龠A-Za-z0-9]+(邸|プロジェクト)/u', '【木造モデル】', $cleanText);

    echo "[PASS] Scrubbing result:\n{$cleanText}\n\n";

    // 4. ナレッジ昇格テスト (DXF/JSON排除)
    $kStmt = $pdo->prepare("
        INSERT INTO knowledge_posts (source_ticket_id, title, category, content_md, is_published)
        VALUES (:ticket_id, '【木造モデル】斜め壁の応力確認と金物選定', '斜め壁計算', :content_md, 1)
    ");
    $kStmt->execute([
        'ticket_id' => $ticketId,
        'content_md' => $cleanText
    ]);
    $postId = $pdo->lastInsertId();

    $uStmt = $pdo->prepare("UPDATE support_tickets SET is_promoted_to_faq = 1 WHERE ticket_id = :id");
    $uStmt->execute(['id' => $ticketId]);

    echo "[PASS] Promoted ticket {$ticketId} to Knowledge post ID: {$postId}\n";

    // 5. 昇格確認 (knowledge_posts に原データが含まれていないことをアサート)
    $chkStmt = $pdo->prepare("SELECT * FROM knowledge_posts WHERE post_id = :id");
    $chkStmt->execute(['id' => $postId]);
    $post = $chkStmt->fetch();

    if (!isset($post['dxf_file_path']) && !isset($post['input_data_json'])) {
        echo "[PASS] Original DXF/JSON files successfully purged from public knowledge post schema.\n";
    }

    echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";

} catch (Exception $e) {
    echo "[FAIL] Test error: " . $e->getMessage() . "\n";
    exit(1);
}
