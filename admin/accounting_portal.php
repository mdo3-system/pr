<?php
/**
 * J-ALG 会計担当ポータル (admin/accounting_portal.php)
 * 契約プラン・サブスクステータス・決済ログ管理ダッシュボード
 */
require_once dirname(__DIR__) . '/api/auth_helper.php';

$user = getAuthenticatedUser();
if (!$user || !in_array($user['role'], ['accounting', 'admin'], true)) {
    header("Location: /index.php?error=unauthorized");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会計担当ポータル | 木造壁量計算WEB「上善如水」</title>
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
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-600 flex items-center justify-center font-bold text-xl shadow-lg">
                <i class="fa-solid fa-calculator text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                    会計・売上管理ポータル
                    <span class="text-xs bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-2 py-0.5 rounded-full font-mono">Accounting</span>
                </h1>
                <p class="text-xs text-slate-400">木造壁量計算WEB「上善如水」 契約・決済・アカウント管理</p>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right text-xs">
                <div class="font-bold text-slate-200"><?= htmlspecialchars($user['user_name'] ?? $user['email']) ?></div>
                <div class="text-emerald-400 font-mono"><?= htmlspecialchars($user['email']) ?> (<?= htmlspecialchars($user['role_label']) ?>)</div>
            </div>
            <a href="/index.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs px-3 py-2 rounded-lg transition border border-slate-700">
                <i class="fa-solid fa-house"></i> 掲示板へ
            </a>
            <a href="/api/auth.php?action=logout&redirect=1" class="bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-xs px-3 py-2 rounded-lg transition border border-rose-500/30">
                ログアウト
            </a>
        </div>
    </header>

    <main class="flex-1 p-6 max-w-7xl w-full mx-auto space-y-6">

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="glass-panel p-5 rounded-2xl">
                <div class="flex justify-between items-start text-slate-400 text-xs mb-2">
                    <span>登録ユーザー総数</span>
                    <i class="fa-solid fa-users text-sky-400 text-base"></i>
                </div>
                <div class="text-3xl font-bold text-slate-100 font-mono" id="stat-total-users">0</div>
                <div class="text-xs text-slate-500 mt-2">全アカウント</div>
            </div>

            <div class="glass-panel p-5 rounded-2xl">
                <div class="flex justify-between items-start text-slate-400 text-xs mb-2">
                    <span>アクティブ有料サブスク</span>
                    <i class="fa-solid fa-file-invoice-dollar text-emerald-400 text-base"></i>
                </div>
                <div class="text-3xl font-bold text-emerald-400 font-mono" id="stat-active-subs">0</div>
                <div class="text-xs text-slate-500 mt-2">Stripe & 銀行振込契約</div>
            </div>

            <div class="glass-panel p-5 rounded-2xl">
                <div class="flex justify-between items-start text-slate-400 text-xs mb-2">
                    <span>社内/無償永久アカウント</span>
                    <i class="fa-solid fa-user-shield text-indigo-400 text-base"></i>
                </div>
                <div class="text-3xl font-bold text-indigo-400 font-mono" id="stat-free-perm">0</div>
                <div class="text-xs text-slate-500 mt-2">管理者・スタッフ・特約店</div>
            </div>

            <div class="glass-panel p-5 rounded-2xl">
                <div class="flex justify-between items-start text-slate-400 text-xs mb-2">
                    <span>今月累計決済額 (税込)</span>
                    <i class="fa-solid fa-yen-sign text-amber-400 text-base"></i>
                </div>
                <div class="text-3xl font-bold text-amber-400 font-mono" id="stat-monthly-revenue">¥0</div>
                <div class="text-xs text-slate-500 mt-2">決済完了ログ集計</div>
            </div>
        </div>

        <!-- User Subscription List Table -->
        <div class="glass-panel p-6 rounded-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-address-book text-emerald-400"></i> ユーザー契約・サブスクリプション一覧
                </h2>
                <div class="flex gap-2">
                    <input type="text" id="user-search-kw" placeholder="メールアドレス / 氏名 / 会社名で検索..." class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-200 w-64">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900/80 text-slate-400 uppercase font-mono">
                        <tr>
                            <th class="p-3">ID</th>
                            <th class="p-3">ユーザー名 / 会社名</th>
                            <th class="p-3">メールアドレス</th>
                            <th class="p-3">権限ロール</th>
                            <th class="p-3">プラン種別</th>
                            <th class="p-3">契約ステータス</th>
                            <th class="p-3">次回更新/有効期限</th>
                        </tr>
                    </thead>
                    <tbody id="accounting-users-body" class="divide-y divide-slate-800">
                        <!-- Loaded by JS -->
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        async function loadAccountingData() {
            try {
                const res = await fetch('/api/admin/get_accounting_data.php');
                const data = await res.json();
                if (data.status === 'success') {
                    document.getElementById('stat-total-users').innerText = data.stats.total_users;
                    document.getElementById('stat-active-subs').innerText = data.stats.active_subs;
                    document.getElementById('stat-free-perm').innerText = data.stats.free_perm;
                    document.getElementById('stat-monthly-revenue').innerText = '¥' + data.stats.monthly_revenue.toLocaleString();

                    renderUsersTable(data.users);
                }
            } catch(e) { console.error(e); }
        }

        function renderUsersTable(users) {
            const tbody = document.getElementById('accounting-users-body');
            tbody.innerHTML = '';

            const kw = document.getElementById('user-search-kw').value.toLowerCase().trim();
            const filtered = users.filter(u => {
                if (!kw) return true;
                return (u.email && u.email.toLowerCase().includes(kw)) ||
                       (u.user_name && u.user_name.toLowerCase().includes(kw)) ||
                       (u.company_name && u.company_name.toLowerCase().includes(kw));
            });

            filtered.forEach(u => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="p-3 font-mono text-slate-500">${u.id}</td>
                    <td class="p-3">
                        <div class="font-bold text-slate-200">${escapeHtml(u.user_name || '-')}</div>
                        <div class="text-slate-400 text-[10px]">${escapeHtml(u.company_name || '-')}</div>
                    </td>
                    <td class="p-3 font-mono text-emerald-400">${escapeHtml(u.email)}</td>
                    <td class="p-3"><span class="bg-slate-800 text-slate-300 border border-slate-700 px-2 py-0.5 rounded text-[10px]">${escapeHtml(u.role_label || u.role)}</span></td>
                    <td class="p-3 font-mono text-sky-400">${escapeHtml(u.plan_type || 'free_trial')}</td>
                    <td class="p-3 font-mono">
                        ${u.sub_status === 'active' ? '<span class="text-emerald-400"><i class="fa-solid fa-circle-check"></i> active</span>' : '<span class="text-slate-500">'+escapeHtml(u.sub_status || 'none')+'</span>'}
                    </td>
                    <td class="p-3 text-[10px] font-mono text-slate-400">${u.current_period_end ? u.current_period_end.substr(0, 10) : '-'}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        document.getElementById('user-search-kw').addEventListener('input', () => {
            loadAccountingData();
        });

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        loadAccountingData();
    </script>
</body>
</html>
