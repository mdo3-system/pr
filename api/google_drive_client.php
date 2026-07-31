<?php
/**
 * api/google_drive_client.php
 * 
 * Google Drive API 連携クライアントモジュール
 * 自動フォルダ生成、アクセストークン自動更新、パーミッション設定、ファイルアップロード機能を提供
 */

// Composer Autoload の読み込み
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}

/**
 * 簡易.env読み込み処理
 */
if (!function_exists('load_env_if_needed')) {
    function load_env_if_needed($file_path = null) {
        if ($file_path === null) {
            $file_path = __DIR__ . '/../.env';
        }
        if (!file_exists($file_path)) return;
        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $value = trim($value, '"\' ');
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}
load_env_if_needed();

/**
 * Google Drive サービスインスタンスの取得（自動トークン更新付き）
 * 
 * @return \Google\Service\Drive
 * @throws Exception
 */
function get_google_drive_service() {
    static $service = null;
    if ($service !== null) return $service;

    $credentials_path = getenv('GOOGLE_APPLICATION_CREDENTIALS') 
        ? (strpos(getenv('GOOGLE_APPLICATION_CREDENTIALS'), '/') === 0 || strpos(getenv('GOOGLE_APPLICATION_CREDENTIALS'), ':\\') !== false 
            ? getenv('GOOGLE_APPLICATION_CREDENTIALS') 
            : __DIR__ . '/../' . getenv('GOOGLE_APPLICATION_CREDENTIALS'))
        : __DIR__ . '/../credentials.json';

    if (!file_exists($credentials_path)) {
        throw new Exception("Google認証キーファイルが見つかりません: " . $credentials_path);
    }

    $client = new Google\Client();
    $client->setAuthConfig($credentials_path);
    $client->addScope(Google\Service\Drive::DRIVE);
    $client->setAccessType('offline');

    $token_path = __DIR__ . '/../token.json';
    if (file_exists($token_path)) {
        $accessToken = json_decode(file_get_contents($token_path), true);
        if (is_array($accessToken) && isset($accessToken['access_token'])) {
            $client->setAccessToken($accessToken);
        }
    }

    // トークン期限切れ時のサイレント更新処理
    if ($client->isAccessTokenExpired()) {
        $refreshToken = $client->getRefreshToken();
        if ($refreshToken) {
            $new_token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            if (!isset($new_token['refresh_token'])) {
                $new_token['refresh_token'] = $refreshToken;
            }
            file_put_contents($token_path, json_encode($new_token));
            $client->setAccessToken($new_token);
        } else {
            throw new Exception("Google Driveが未認証です。初回認証(oauth2callback.php)を完了させてください。");
        }
    }

    $service = new Google\Service\Drive($client);
    return $service;
}

/**
 * 新規フォルダ作成 ＆ 閲覧権限(anyone/reader)の付与
 * 
 * @param string $folder_name フォルダ名
 * @param string|null $parent_folder_id 親フォルダID
 * @return string 生成された Drive フォルダ ID
 */
function create_google_drive_folder($folder_name, $parent_folder_id = null) {
    $service = get_google_drive_service();
    
    $file_metadata = new Google\Service\Drive\DriveFile([
        'name' => $folder_name,
        'mimeType' => 'application/vnd.google-apps.folder'
    ]);
    if ($parent_folder_id) {
        $file_metadata->setParents([$parent_folder_id]);
    }
    
    $folder = $service->files->create($file_metadata, [
        'fields' => 'id',
        'supportsAllDrives' => true
    ]);
    $folder_id = $folder->id;

    // 全員への閲覧権限を付与
    try {
        $permission = new Google\Service\Drive\Permission([
            'role' => 'reader',
            'type' => 'anyone'
        ]);
        $service->permissions->create($folder_id, $permission, ['supportsAllDrives' => true]);
    } catch (Exception $e) {
        error_log("フォルダ権限付与エラー: " . $e->getMessage());
    }

    return $folder_id;
}

/**
 * 指定フォルダ内の同名フォルダ検索
 * 
 * @param string $folder_name フォルダ名
 * @param string|null $parent_folder_id 親フォルダID
 * @return string|null フォルダID (未発見時はnull)
 */
function find_google_drive_folder($folder_name, $parent_folder_id = null) {
    $service = get_google_drive_service();
    $escaped_name = str_replace("'", "\\'", $folder_name);
    $query = "mimeType = 'application/vnd.google-apps.folder' and name = '{$escaped_name}' and trashed = false";
    if ($parent_folder_id) {
        $query .= " and '{$parent_folder_id}' in parents";
    }

    $response = $service->files->listFiles([
        'q' => $query,
        'spaces' => 'drive',
        'fields' => 'files(id, name)',
        'supportsAllDrives' => true
    ]);

    return (count($response->files) > 0) ? $response->files[0]->id : null;
}

/**
 * ユーザー別（またはロール・企業別）のGoogle Driveフォルダ取得・自動生成 ＆ DBキャッシング
 * 
 * @param PDO $pdo DB接続
 * @param int $user_id ユーザーID
 * @return string ユーザー専用フォルダID
 */
function get_or_create_user_drive_folder($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id, name, company_name, email, role, drive_folder_id FROM users WHERE id = :uid");
    $stmt->execute(['uid' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("対象ユーザーが存在しません。ID: " . $user_id);
    }

    // すでにDBにフォルダIDが保存されていればそれを返す
    if (!empty($user['drive_folder_id'])) {
        return $user['drive_folder_id'];
    }

    $root_folder_id = getenv('GOOGLE_DRIVE_FOLDER_ID');
    if (empty($root_folder_id)) {
        throw new Exception("GOOGLE_DRIVE_FOLDER_ID が設定されていません。");
    }

    // フォルダ名の定義（会社名またはユーザー名_Role）
    $display_name = !empty($user['company_name']) ? $user['company_name'] : $user['name'];
    $folder_name = "User_" . $user['id'] . "_" . preg_replace('/[^\w\-\.\s]/u', '_', $display_name);

    // 検索または作成
    $folder_id = find_google_drive_folder($folder_name, $root_folder_id)
        ?: create_google_drive_folder($folder_name, $root_folder_id);

    // DBへキャッシング
    $update_stmt = $pdo->prepare("UPDATE users SET drive_folder_id = :fid WHERE id = :uid");
    $update_stmt->execute(['fid' => $folder_id, 'uid' => $user_id]);

    return $folder_id;
}

/**
 * 指定フォルダへファイルをアップロード ＆ 閲覧権限(anyone/reader)の付与
 * 
 * @param string $local_file_path ローカルファイルパス
 * @param string $file_name アップロード名
 * @param string $mime_type MIMEタイプ
 * @param string|null $parent_folder_id 保存先フォルダID
 * @return string 保存された Drive File ID
 */
function upload_to_google_drive_folder($local_file_path, $file_name, $mime_type, $parent_folder_id) {
    $service = get_google_drive_service();

    $file_metadata = new Google\Service\Drive\DriveFile(['name' => $file_name]);
    if ($parent_folder_id) {
        $file_metadata->setParents([$parent_folder_id]);
    }

    $content = file_get_contents($local_file_path);
    $file = $service->files->create($file_metadata, [
        'data' => $content,
        'mimeType' => $mime_type,
        'uploadType' => 'multipart',
        'fields' => 'id',
        'supportsAllDrives' => true
    ]);
    $file_id = $file->id;

    // 閲覧権限の付与
    try {
        $permission = new Google\Service\Drive\Permission([
            'role' => 'reader',
            'type' => 'anyone'
        ]);
        $service->permissions->create($file_id, $permission, ['supportsAllDrives' => true]);
    } catch (Exception $e) {
        error_log("ファイル権限付与エラー: " . $e->getMessage());
    }

    return $file_id;
}

/**
 * Google Drive 上のファイルアクセス用 URL の取得ヘルパー
 * 
 * @param string $file_id Google Drive File ID
 * @param bool $is_image 画像サムネイル表示かどうか
 * @return string URL
 */
function get_google_drive_file_url($file_id, $is_image = false) {
    if ($is_image) {
        return "https://drive.google.com/thumbnail?id={$file_id}&sz=w800";
    }
    return "https://drive.google.com/file/d/{$file_id}/view?usp=drivesdk";
}
