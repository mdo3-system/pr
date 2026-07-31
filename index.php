<?php
/**
 * 木造壁量計算WEB「上善如水」公式サポート掲示板 & ナレッジポータル
 * (index.php)
 */
require_once __DIR__ . '/api/auth_helper.php';
$currentUser = getAuthenticatedUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>木造壁量計算WEB「上善如水」公式サポート掲示板 & ナレッジベース</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Noto Sans JP', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.75); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .badge-admin { background: rgba(225, 29, 72, 0.2); color: #f43f5e; border: 1px solid rgba(225, 29, 72, 0.4); }
        .badge-accounting { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.4); }
        .badge-support { background: rgba(56, 189, 248, 0.2); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4); }
        .badge-premium { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
        .badge-general { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; border: 1px solid rgba(148, 163, 184, 0.4); }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Header Navigation -->
    <header class="glass-panel sticky top-0 z-50 px-6 py-4 flex items-center justify-between shadow-xl">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center font-bold text-xl text-white shadow-lg">
                🌊
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                    上善如水 サポート掲示板
                    <span class="text-xs bg-sky-500/20 text-sky-400 border border-sky-500/30 px-2 py-0.5 rounded-full font-mono">v1.0.7</span>
                </h1>
                <p class="text-xs text-slate-400">木造壁量計算WEBソフト「上善如水」 公式ナレッジ base & サポートフォーラム</p>
            </div>
        </div>

        <!-- Auth Status & Portal Buttons -->
        <div class="flex items-center space-x-4">
            <?php if ($currentUser): ?>
                <div class="text-right text-xs">
                    <div class="font-bold text-slate-200"><?= htmlspecialchars($currentUser['user_name'] ?? $currentUser['email']) ?></div>
                    <div class="flex items-center gap-1.5 justify-end mt-0.5">
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono badge-<?= htmlspecialchars($currentUser['role']) ?>">
                            <i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($currentUser['role_label']) ?>
                        </span>
                    </div>
                </div>

                <!-- Role-based Portal Direct Link -->
                <a href="<?= htmlspecialchars($currentUser['portal_url']) ?>" class="bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-lg transition flex items-center gap-2">
                    <i class="fa-solid fa-right-to-bracket"></i> <?= htmlspecialchars($currentUser['role_label']) ?> ポータルへ
                </a>

                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="/admin/support_manager.php" title="管理者総合マネージャー" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs px-3 py-2 rounded-xl border border-slate-700 transition">
                        <i class="fa-solid fa-gear"></i> 管理者全画面
                    </a>
                <?php endif; ?>

                <a href="/api/auth.php?action=logout&redirect=1" class="bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-xs px-3 py-2 rounded-xl border border-rose-500/30 transition">
                    ログアウト
                </a>
            <?php else: ?>
                <button onclick="openLoginModal()" class="bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-lg transition flex items-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> マジックリンクでログイン
                </button>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 p-6 max-w-7xl w-full mx-auto space-y-8">

        <!-- Search & Hero Banner -->
        <section class="glass-panel rounded-3xl p-8 text-center space-y-6 relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-72 h-72 bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="inline-block bg-sky-500/15 border border-sky-500/30 text-sky-400 text-xs px-3 py-1 rounded-full font-semibold mb-2">
                <i class="fa-solid fa-book-bookmark"></i> 実務計算・構造適正化の知見を完全公開
            </div>

            <h2 class="text-2xl md:text-3xl font-extrabold text-slate-100">
                壁量計算・斜め壁・基礎計算の解決策を検索
            </h2>
            <p class="text-sm text-slate-400 max-w-2xl mx-auto">
                上善如水プレミアムサポートで解決した実務ケースやDXF下地連動、金物配置のQAを匿名化ナレッジとして公開中。
            </p>

            <!-- Search Form -->
            <div class="max-w-2xl mx-auto flex gap-3">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                    <input type="text" id="forum-kw" placeholder="例: 斜め壁, 金物, N値計算, 基礎..." class="w-full bg-slate-900/90 border border-slate-700/80 rounded-2xl pl-11 pr-4 py-3 text-sm text-slate-200 focus:outline-none focus:border-sky-500">
                </div>
                <button onclick="searchKnowledge()" class="bg-sky-600 hover:bg-sky-500 text-white font-bold px-6 py-3 rounded-2xl shadow-lg transition text-sm flex items-center gap-2">
                    検索
                </button>
            </div>

            <!-- Quick Category Tags -->
            <div class="flex flex-wrap justify-center gap-2 text-xs">
                <button onclick="filterCategory('')" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 px-3 py-1 rounded-lg">すべて</button>
                <button onclick="filterCategory('斜め壁計算')" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-sky-400 px-3 py-1 rounded-lg"># 斜め壁計算</button>
                <button onclick="filterCategory('基礎計算')" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-emerald-400 px-3 py-1 rounded-lg"># 基礎計算</button>
                <button onclick="filterCategory('DXF下地')" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-indigo-400 px-3 py-1 rounded-lg"># DXF下地</button>
                <button onclick="filterCategory('その他')" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-amber-400 px-3 py-1 rounded-lg"># その他</button>
            </div>
        </section>

        <!-- Premium Support Teaser Banner -->
        <section class="glass-panel rounded-2xl p-6 flex flex-col md:flex-row justify-between items-center gap-4 border-sky-500/20">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="bg-amber-500/20 text-amber-400 border border-amber-500/30 text-xs px-2.5 py-0.5 rounded-md font-bold">1対1 完全個室サポート</span>
                    <h3 class="font-bold text-slate-100">プレミアムサポート会員様 専用ポータル</h3>
                </div>
                <p class="text-xs text-slate-400">図面データ(.dxf)や計算パラメーターの直接検証、YouTube動画添削、Zoom対面相談を受け付けています。</p>
            </div>
            <div>
                <?php if ($currentUser && $currentUser['is_premium']): ?>
                    <a href="/my/support_dashboard.php" class="bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-950 font-extrabold text-xs px-5 py-2.5 rounded-xl shadow-lg transition flex items-center gap-2">
                        <i class="fa-solid fa-comments"></i> 質問カードの作成・履歴はこちら
                    </a>
                <?php else: ?>
                    <button onclick="openLoginModal()" class="bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs px-5 py-2.5 rounded-xl border border-slate-700 transition flex items-center gap-2">
                        <i class="fa-solid fa-lock"></i> ログインして質疑カードを作成
                    </button>
                <?php endif; ?>
            </div>
        </section>

        <!-- Knowledge Cards Grid -->
        <section class="space-y-4">
            <h3 class="text-base font-bold text-slate-200 flex items-center gap-2">
                <i class="fa-solid fa-lightbulb text-amber-400"></i> 公開ナレッジ・FAQ一覧
            </h3>

            <div id="knowledge-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <!-- Loaded dynamically by JS -->
            </div>
        </section>

    </main>

    <!-- Modal: Knowledge Detail -->
    <div id="modal-knowledge" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md hidden p-4">
        <div class="glass-panel w-full max-w-3xl rounded-3xl p-6 max-h-[85vh] overflow-y-auto relative space-y-4 border-slate-700">
            <button onclick="closeKnowledgeModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div id="modal-k-category" class="inline-block bg-sky-500/20 text-sky-400 text-xs px-3 py-1 rounded-full font-bold">カテゴリー</div>
            <h3 id="modal-k-title" class="text-xl font-bold text-slate-100">タイトル</h3>
            <div class="text-xs text-slate-500 font-mono" id="modal-k-date">2026-07-31</div>
            <hr class="border-slate-800">
            <div id="modal-k-body" class="text-sm text-slate-300 space-y-3 leading-relaxed whitespace-pre-wrap font-mono bg-slate-950/50 p-4 rounded-2xl border border-slate-800">
                本文
            </div>
        </div>
    </div>

    <!-- Modal: Magic Link Login -->
    <div id="modal-login" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md hidden p-4">
        <div class="glass-panel w-full max-w-md rounded-3xl p-6 relative space-y-5 border-sky-500/30 shadow-2xl">
            <button onclick="closeLoginModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center font-bold text-2xl text-white mx-auto shadow-lg">
                    ✨
                </div>
                <h3 class="text-lg font-bold text-slate-100">マジックリンクでログイン</h3>
                <p class="text-xs text-slate-400">登録メールアドレスを入力すると、ワンクリックでログインできる魔法のリンクを発行・送信します。</p>
            </div>

            <!-- Login Form -->
            <form id="form-magic-link" onsubmit="handleSendMagicLink(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">メールアドレス</label>
                    <input type="email" id="login-email" required placeholder="koki@t-smile.co.jp" class="w-full bg-slate-900 border border-slate-700 rounded-xl p-3 text-sm text-slate-200 focus:outline-none focus:border-sky-500 font-mono">
                </div>

                <button type="submit" id="btn-submit-magic" class="w-full bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-400 hover:to-blue-500 text-white font-bold py-3 rounded-xl shadow-lg transition text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> マジックリンクを送信
                </button>
            </form>

            <!-- Result Box -->
            <div id="login-result-box" class="hidden p-4 rounded-2xl bg-slate-900 border border-emerald-500/40 space-y-3">
                <div class="text-xs text-emerald-400 font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check"></i> <span id="login-result-msg">送信しました</span>
                </div>
                <div class="text-[11px] text-slate-300">
                    権限: <strong id="login-result-role" class="text-sky-400"></strong>
                </div>
                <div class="space-y-1">
                    <div class="text-[10px] text-slate-400">【開発・動作テスト用】発行されたマジックリンク:</div>
                    <a id="login-result-link" href="#" class="block bg-sky-600 hover:bg-sky-500 text-white font-bold text-center text-xs py-2 px-3 rounded-xl shadow transition text-ellipsis overflow-hidden">
                        🚀 このマジックリンクで直ちにログイン
                    </a>
                </div>
            </div>

            <hr class="border-slate-800">

            <!-- Quick Select Preset Accounts -->
            <div class="space-y-2">
                <div class="text-[11px] text-slate-400 font-semibold text-center">クイックアカウント選択 (開発・デモ用)</div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <button type="button" onclick="quickSelectAccount('koki@t-smile.co.jp')" class="bg-rose-500/15 hover:bg-rose-500/25 border border-rose-500/30 text-rose-300 p-2 rounded-xl text-left transition">
                        <div class="font-bold">👑 管理者</div>
                        <div class="text-[10px] font-mono text-rose-400/80">koki@t-smile...</div>
                    </button>
                    <button type="button" onclick="quickSelectAccount('keiri@t-smile.co.jp')" class="bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-300 p-2 rounded-xl text-left transition">
                        <div class="font-bold">💼 会計担当</div>
                        <div class="text-[10px] font-mono text-emerald-400/80">keiri@t-smile...</div>
                    </button>
                    <button type="button" onclick="quickSelectAccount('sato@t-smile.co.jp')" class="bg-sky-500/15 hover:bg-sky-500/25 border border-sky-500/30 text-sky-300 p-2 rounded-xl text-left transition">
                        <div class="font-bold">🛠 動作サポート(佐藤)</div>
                        <div class="text-[10px] font-mono text-sky-400/80">sato@t-smile...</div>
                    </button>
                    <button type="button" onclick="quickSelectAccount('sales@t-smile.co.jp')" class="bg-sky-500/15 hover:bg-sky-500/25 border border-sky-500/30 text-sky-300 p-2 rounded-xl text-left transition">
                        <div class="font-bold">🛠 動作サポート(営業)</div>
                        <div class="text-[10px] font-mono text-sky-400/80">sales@t-smile...</div>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="glass-panel border-t border-slate-800 p-6 text-center text-xs text-slate-500">
        © 2026 木造壁量計算WEB「上善如水」 Official Knowledge & Support Portal. All rights reserved.
    </footer>

    <!-- JS Logic -->
    <script src="Version.js"></script>
    <script>
        let currentCategory = '';

        async function loadKnowledge(kw = '', cat = '') {
            try {
                let url = '/api/community/list_knowledge.php';
                const params = new URLSearchParams();
                if (kw) params.append('keyword', kw);
                if (cat) params.append('category', cat);
                if (params.toString()) url += '?' + params.toString();

                const res = await fetch(url);
                const data = await res.json();

                const grid = document.getElementById('knowledge-grid');
                grid.innerHTML = '';

                if (data.status === 'success' && data.posts.length > 0) {
                    data.posts.forEach(p => {
                        const card = document.createElement('div');
                        card.className = 'glass-panel rounded-2xl p-5 border border-slate-800 hover:border-sky-500/50 transition cursor-pointer space-y-3';
                        card.onclick = () => openKnowledgeDetail(p);

                        card.innerHTML = `
                            <div class="flex justify-between items-center">
                                <span class="bg-sky-500/15 text-sky-400 border border-sky-500/30 text-[10px] font-semibold px-2.5 py-0.5 rounded-full">${escapeHtml(p.category)}</span>
                                <span class="text-[10px] text-slate-500 font-mono">👁 ${p.views_count || 0}</span>
                            </div>
                            <h4 class="font-bold text-slate-100 text-sm line-clamp-2">${escapeHtml(p.title)}</h4>
                            <p class="text-xs text-slate-400 line-clamp-3 font-mono leading-relaxed">${escapeHtml(p.content_md.replace(/#|\*|`/g, ''))}</p>
                            <div class="flex justify-between items-center text-[10px] text-slate-500 pt-2 border-t border-slate-800/80 font-mono">
                                <span>📅 ${p.created_at.substr(0,10)}</span>
                                <span class="text-sky-400 font-semibold">詳細を見る ➔</span>
                            </div>
                        `;
                        grid.appendChild(card);
                    });
                } else {
                    grid.innerHTML = '<div class="col-span-full text-center text-slate-500 py-12 text-sm">該当するナレッジ記事が見つかりませんでした。</div>';
                }
            } catch(e) { console.error(e); }
        }

        function searchKnowledge() {
            const kw = document.getElementById('forum-kw').value.trim();
            loadKnowledge(kw, currentCategory);
        }

        function filterCategory(cat) {
            currentCategory = cat;
            loadKnowledge(document.getElementById('forum-kw').value.trim(), cat);
        }

        function openKnowledgeDetail(post) {
            document.getElementById('modal-k-category').innerText = post.category;
            document.getElementById('modal-k-title').innerText = post.title;
            document.getElementById('modal-k-date').innerText = '公開日時: ' + post.created_at;
            document.getElementById('modal-k-body').innerText = post.content_md;
            document.getElementById('modal-knowledge').classList.remove('hidden');
        }

        function closeKnowledgeModal() {
            document.getElementById('modal-knowledge').classList.add('hidden');
        }

        function openLoginModal() {
            document.getElementById('modal-login').classList.remove('hidden');
        }

        function closeLoginModal() {
            document.getElementById('modal-login').classList.add('hidden');
        }

        function quickSelectAccount(email) {
            document.getElementById('login-email').value = email;
            document.getElementById('form-magic-link').requestSubmit();
        }

        async function handleSendMagicLink(e) {
            e.preventDefault();
            const email = document.getElementById('login-email').value.trim();
            const btn = document.getElementById('btn-submit-magic');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 送信中...';

            try {
                const formData = new FormData();
                formData.append('action', 'request_magic_link');
                formData.append('email', email);

                const res = await fetch('/api/auth.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success') {
                    document.getElementById('login-result-msg').innerText = data.message;
                    document.getElementById('login-result-role').innerText = data.role_label + ' (' + data.role + ')';
                    document.getElementById('login-result-link').href = data.magic_link;
                    document.getElementById('login-result-box').classList.remove('hidden');
                } else {
                    alert(data.message || 'エラーが発生しました。');
                }
            } catch(err) {
                console.error(err);
                alert('通信エラーが発生しました。');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> マジックリンクを送信';
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Init Load
        loadKnowledge();
    </script>
</body>
</html>
