<?php
/**
 * パブリック ナレッジ掲示板 (FAQ) 記事一覧取得 API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../auth_helper.php';

$pdo = getPdoConnection();

$category = $_GET['category'] ?? null;
$keyword = $_GET['keyword'] ?? null;

$where = ["is_published = 1"];
$params = [];

if ($category) {
    $where[] = "category = :category";
    $params['category'] = $category;
}

if ($keyword) {
    $where[] = "(title LIKE :kw OR content_md LIKE :kw)";
    $params['kw'] = '%' . $keyword . '%';
}

$whereSql = "WHERE " . implode(" AND ", $where);

$stmt = $pdo->prepare("
    SELECT post_id, source_ticket_id, title, category, content_md, views_count, created_at
    FROM knowledge_posts
    {$whereSql}
    ORDER BY created_at DESC
");
$stmt->execute($params);
$posts = $stmt->fetchAll();

echo json_encode([
    'status' => 'success',
    'posts' => $posts
], JSON_UNESCAPED_UNICODE);
