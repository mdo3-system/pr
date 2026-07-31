<?php
/**
 * oauth2callback.php
 * 
 * Google Drive API 初回OAuth2認可スクリプト
 * 管理者がブラウザでアクセスし、認可コードから token.json を生成・保存します。
 */

$autoload_path = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload_path)) {
    die("Composer の依存ライブラリが見つかりません。サーバー上で `composer install` を実行してください。");
}
require_once $autoload_path;

$credentials_path = __DIR__ . '/credentials.json';
if (!file_exists($credentials_path)) {
    die("credentials.json が見つかりません。");
}

$client = new Google\Client();
$client->setAuthConfig($credentials_path);
$client->addScope(Google\Service\Drive::DRIVE);
$client->setAccessType('offline');
$client->setPrompt('consent'); // refresh_tokenを確実に取得するために設定

$redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . '/oauth2callback.php';
$client->setRedirectUri($redirect_uri);

if (!isset($_GET['code'])) {
    // 認可URLへリダイレクト
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
    exit;
} else {
    // 認可コードの受け取りとアクセストークンの取得
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        if (isset($token['error'])) {
            throw new Exception("トークン取得エラー: " . json_encode($token));
        }
        file_put_contents(__DIR__ . '/token.json', json_encode($token));
        
        // 成功メッセージ表示
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>Google Drive 連携完了</title>';
        echo '<style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f4f6f9;color:#333;} .card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.1);display:inline-block;max-width:500px;} h1{color:#28a745;}</style></head><body>';
        echo '<div class="card"><h1>✅ Google Drive 連携成功！</h1><p>アクセストークン (token.json) の保存が完了しました。<br>これでプレミアム会員および管理者のポータルでGoogle Driveフォルダの自動作成とファイル連携が利用可能です。</p><p><a href="/my/support_dashboard.php">ポータル画面へ戻る</a></p></div>';
        echo '</body></html>';
    } catch (Exception $e) {
        die("OAuth認証失敗: " . htmlspecialchars($e->getMessage()));
    }
}
