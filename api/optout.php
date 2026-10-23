<?php
/**
 * J-ALG ワンクリック配信停止処理エンドポイント (api/optout.php)
 * 特定電子メール法に準拠したオプトアウト処理
 */

function getPdoConnection() {
    $envFile = dirname(__DIR__) . '/.env';
    $env = [];
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $env[trim($k)] = trim($v);
            }
        }
    }

    $dbHost = $env['DB_HOST'] ?? 'localhost';
    $dbPort = $env['DB_PORT'] ?? '3306';
    $dbName = $env['DB_DATABASE'] ?? 'mdo3_pr';
    $dbUser = $env['DB_USERNAME'] ?? 'mdo3_toolapp0001';
    $dbPass = $env['DB_PASSWORD'] ?? 'koki2656@';

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    return new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

$token = trim($_GET['token'] ?? '');
$success = false;
$companyName = '';

if (!empty($token)) {
    try {
        $pdo = getPdoConnection();
        $stmt = $pdo->prepare("SELECT id, name, email FROM companies WHERE opt_out_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $company = $stmt->fetch();

        if ($company) {
            $companyName = $company['name'];
            $update = $pdo->prepare("UPDATE companies 
                                     SET is_opt_out = 1, 
                                         opt_out_reason = 'user_click_optout', 
                                         status = 'rejected', 
                                         updated_at = NOW() 
                                     WHERE id = :id");
            $update->execute([':id' => $company['id']]);

            // 未送信のキューがあれば即時キャンセル
            $pdo->prepare("DELETE FROM send_logs WHERE company_id = :cid AND status = 'queued'")
                ->execute([':cid' => $company['id']]);

            $success = true;
        }
    } catch (Exception $e) {
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>配信停止（オプトアウト）完了 | 木造壁量計算WEB 上善如水</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans JP', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center border border-slate-100">
        <?php if ($success): ?>
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
                ✓
            </div>
            <h1 class="text-xl font-bold text-slate-800 mb-2">配信停止を承りました</h1>
            <p class="text-xs text-slate-500 mb-6 font-mono"><?= htmlspecialchars($companyName) ?> 様</p>
            <p class="text-sm text-slate-600 leading-relaxed mb-6">
                木造壁量計算WEB「上善如水」からのご案内メールの配信停止処理が完了いたしました。<br>
                今後、貴社宛てに営業案内メールが送信されることはございません。
            </p>
            <p class="text-xs text-slate-400">
                お手数をおかけいたしました。ご利用ありがとうございました。
            </p>
        <?php else: ?>
            <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
                !
            </div>
            <h1 class="text-xl font-bold text-slate-800 mb-2">処理が完了できませんでした</h1>
            <p class="text-sm text-slate-600 leading-relaxed mb-6">
                URLが無効であるか、既に配信停止処理が完了している可能性がございます。
            </p>
            <p class="text-xs text-slate-400">
                お心当たりのない場合は、大変恐れ入りますがウィンドウをお閉じください。
            </p>
        <?php endif; ?>

        <div class="mt-8 pt-6 border-t border-slate-100 text-[11px] text-slate-400">
            木造壁量計算WEB「上善如水」運営事務局 (pr@eie.tokyo)
        </div>
    </div>
</body>
</html>
