<?php
/**
 * J-ALG 動作サポート担当ポータル (admin/support_portal.php)
 * 質疑カード対応・デバッグ起動・回答返信ポータル
 */
require_once dirname(__DIR__) . '/api/auth_helper.php';

$user = getAuthenticatedUser();
if (!$user || !in_array($user['role'], ['support', 'admin'], true)) {
    header("Location: /index.php?error=unauthorized");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>動作サポートポータル | 木造壁量計算WEB「上善如水」</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Noto Sans JP', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Header -->
    <header class="glass-panel sticky top-0 z-50 px-6 py-4 flex items-center justify-between shadow-lg">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center font-bold text-xl shadow-lg">
                <i class="fa-solid fa-headset text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                    動作サポート管理ポータル
                    <span class="text-xs bg-sky-500/20 text-sky-400 border border-sky-500/30 px-2 py-0.5 rounded-full font-mono">Tech Support</span>
                </h1>
                <p class="text-xs text-slate-400">木造壁量計算WEB「上善如水」 ユーザー質疑カード・検証デバッグサポート</p>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right text-xs">
                <div class="font-bold text-slate-200"><?= htmlspecialchars($user['user_name'] ?? $user['email']) ?></div>
                <div class="text-sky-400 font-mono"><?= htmlspecialchars($user['email']) ?> (<?= htmlspecialchars($user['role_label']) ?>)</div>
            </div>
            <a href="/admin/support_manager.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs px-3 py-2 rounded-lg transition border border-slate-700">
                <i class="fa-solid fa-sliders"></i> 全質疑マネージャー
            </a>
            <a href="/index.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs px-3 py-2 rounded-lg transition border border-slate-700">
                <i class="fa-solid fa-house"></i> 掲示板へ
            </a>
            <a href="/api/auth.php?action=logout&redirect=1" class="bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-xs px-3 py-2 rounded-lg transition border border-rose-500/30">
                ログアウト
            </a>
        </div>
    </header>

    <main class="flex-1 p-6 max-w-7xl w-full mx-auto space-y-6">

        <div class="glass-panel p-6 rounded-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-comments text-sky-400"></i> 受信質疑カード（サポート対象）
                </h2>
                <div class="flex gap-2 text-xs">
                    <select id="flt-status" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-slate-200">
                        <option value="">全ステータス</option>
                        <option value="open">未解決 (open)</option>
                        <option value="in_progress">対応中 (in_progress)</option>
                        <option value="resolved">解決済 (resolved)</option>
                    </select>
                    <button onclick="loadTickets()" class="bg-sky-600 hover:bg-sky-500 text-white px-3 py-1.5 rounded-lg font-medium transition">
                        更新
                    </button>
                </div>
            </div>

            <div id="tickets-list-container" class="space-y-3">
                <!-- Loaded by JS -->
            </div>
        </div>

    </main>

    <script>
        async function loadTickets() {
            const status = document.getElementById('flt-status').value;
            const res = await fetch(`/api/admin/list_tickets.php?status=${status}`);
            const data = await res.json();
            const container = document.getElementById('tickets-list-container');
            container.innerHTML = '';

            if (data.status === 'success' && data.tickets.length > 0) {
                data.tickets.forEach(t => {
                    const div = document.createElement('div');
                    div.className = 'bg-slate-900/90 border border-slate-800 rounded-xl p-4 flex justify-between items-center hover:border-sky-500/50 transition';
                    div.innerHTML = `
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="bg-sky-500/20 text-sky-400 border border-sky-500/30 text-[10px] px-2 py-0.5 rounded-full">${escapeHtml(t.category)}</span>
                                <h3 class="font-bold text-slate-200 text-sm">${escapeHtml(t.title)}</h3>
                            </div>
                            <div class="text-xs text-slate-400 flex items-center gap-4 font-mono">
                                <span>👤 ${escapeHtml(t.user_name || t.user_email)}</span>
                                <span>📅 ${t.created_at}</span>
                                <span>Status: <strong class="text-amber-400">${t.status}</strong></span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <a href="/admin/support_manager.php?ticket_id=${t.ticket_id}" class="bg-sky-600 hover:bg-sky-500 text-white text-xs px-3 py-2 rounded-lg font-medium transition flex items-center gap-1">
                                <i class="fa-solid fa-reply"></i> 対応・返信画面
                            </a>
                        </div>
                    `;
                    container.appendChild(div);
                });
            } else {
                container.innerHTML = '<div class="text-center text-slate-500 py-10 text-xs">現在対応待ちの質疑カードはありません。</div>';
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        loadTickets();
    </script>
</body>
</html>
