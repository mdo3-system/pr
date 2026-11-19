<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * リード精査・承認管理画面 (/admin/leads_manager.php)
 */
require_once __DIR__ . '/../api/auth_helper.php';
$user = getAuthenticatedUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>【管理者】リード精査・承認マネージャー | 上善如水</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Noto Sans JP', sans-serif; background-color: #090d16; color: #f8fafc; }
        .glass-panel { background: rgba(21, 29, 42, 0.85); backdrop-filter: blur(12px); border: 1px solid #2a364f; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Header -->
    <header class="glass-panel sticky top-0 z-40 px-6 py-4 flex flex-wrap items-center justify-between gap-4 shadow-xl">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center font-bold text-lg text-white shadow-lg">
                <i class="fa-solid fa-building-circle-check"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                    リード精査・承認マネージャー
                    <span class="text-xs bg-sky-500/20 text-sky-400 border border-sky-500/30 px-2 py-0.5 rounded-full font-mono">J-ALG</span>
                </h1>
                <p class="text-xs text-slate-400">抽出企業の「承認 / 却下 / 保留 / 再クロール」精査ダッシュボード</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="/admin/support_manager.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs px-3 py-2 rounded-xl border border-slate-700 transition">
                <i class="fa-solid fa-headset mr-1"></i> サポート管理
            </a>
            <a href="/" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs px-3 py-2 rounded-xl border border-slate-700 transition">
                <i class="fa-solid fa-house mr-1"></i> トップへ
            </a>
        </div>
    </header>

    <main class="flex-1 p-6 max-w-[1440px] w-full mx-auto space-y-5">

        <!-- Summary Cards -->
        <section class="grid grid-cols-2 md:grid-cols-6 gap-3" id="summary-cards">
            <button onclick="quickFilter('crawled', '1')" class="glass-panel rounded-2xl p-4 text-left hover:border-amber-400/60 transition cursor-pointer">
                <div class="text-xs text-slate-400">承認待ち（メール有）</div>
                <div class="text-2xl font-bold text-amber-400 mt-1" id="cnt-crawled-email">-</div>
            </button>
            <button onclick="quickFilter('crawled', '')" class="glass-panel rounded-2xl p-4 text-left hover:border-sky-400/60 transition cursor-pointer">
                <div class="text-xs text-slate-400">承認待ち（全体）</div>
                <div class="text-2xl font-bold text-sky-400 mt-1" id="cnt-crawled">-</div>
            </button>
            <button onclick="quickFilter('approved', '')" class="glass-panel rounded-2xl p-4 text-left hover:border-emerald-400/60 transition cursor-pointer">
                <div class="text-xs text-slate-400">承認済み (approved)</div>
                <div class="text-2xl font-bold text-emerald-400 mt-1" id="cnt-approved">-</div>
            </button>
            <button onclick="quickFilter('rejected', '')" class="glass-panel rounded-2xl p-4 text-left hover:border-rose-400/60 transition cursor-pointer">
                <div class="text-xs text-slate-400">却下 (rejected)</div>
                <div class="text-2xl font-bold text-rose-400 mt-1" id="cnt-rejected">-</div>
            </button>
            <button onclick="quickFilter('pending', '')" class="glass-panel rounded-2xl p-4 text-left hover:border-slate-400/60 transition cursor-pointer">
                <div class="text-xs text-slate-400">未処理 (pending)</div>
                <div class="text-2xl font-bold text-slate-300 mt-1" id="cnt-pending">-</div>
            </button>
            <button onclick="quickFilter('sent', '')" class="glass-panel rounded-2xl p-4 text-left hover:border-purple-400/60 transition cursor-pointer">
                <div class="text-xs text-slate-400">送信済み (sent)</div>
                <div class="text-2xl font-bold text-purple-400 mt-1" id="cnt-sent">-</div>
            </button>
        </section>

        <!-- Filter & Bulk Action Bar -->
        <section class="glass-panel rounded-2xl p-4 space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">ステータス</label>
                    <select id="filter-status" onchange="loadLeads()" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-200">
                        <option value="crawled" selected>crawled（承認待ち）</option>
                        <option value="approved">approved（承認済み）</option>
                        <option value="rejected">rejected（却下）</option>
                        <option value="pending">pending（未処理）</option>
                        <option value="sent">sent（送信済み）</option>
                        <option value="failed">failed（失敗）</option>
                        <option value="">すべて</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">メール有無</label>
                    <select id="filter-email" onchange="loadLeads()" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-200">
                        <option value="1" selected>メールありのみ</option>
                        <option value="">すべて（未抽出含む）</option>
                        <option value="0">メールなしのみ</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">重複メール</label>
                    <select id="filter-dup" onchange="loadLeads()" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-200">
                        <option value="">すべて</option>
                        <option value="1">重複フラグありのみ</option>
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[11px] text-slate-400 mb-1">キーワード検索（社名・メール・URL・法人番号）</label>
                    <div class="flex gap-2">
                        <input type="text" id="filter-search" onkeydown="if(event.key==='Enter') loadLeads()" placeholder="例: 設計室アルファ / @nifty..." class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-sky-500">
                        <button onclick="loadLeads()" class="bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-800">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-slate-400 mr-1">選択中の <strong id="selected-count" class="text-sky-400">0</strong> 件を一括操作:</span>
                    <button onclick="bulkAction('approved')" class="bg-emerald-600/90 hover:bg-emerald-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                        <i class="fa-solid fa-check mr-1"></i> 一括承認
                    </button>
                    <button onclick="bulkAction('rejected')" class="bg-rose-600/90 hover:bg-rose-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                        <i class="fa-solid fa-xmark mr-1"></i> 一括却下
                    </button>
                    <button onclick="bulkAction('crawled')" class="bg-amber-600/80 hover:bg-amber-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                        <i class="fa-solid fa-pause mr-1"></i> 一括保留
                    </button>
                    <button onclick="bulkAction('recrawl_email')" class="bg-sky-700/80 hover:bg-sky-600 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition" title="メールをクリアして再クロール待ちに戻す">
                        <i class="fa-solid fa-rotate-right mr-1"></i> メール再クロール
                    </button>
                    <button onclick="bulkAction('recrawl_url')" class="bg-indigo-700/80 hover:bg-indigo-600 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition" title="URLとメールをクリアしてSerper検索からやり直す">
                        <i class="fa-solid fa-globe mr-1"></i> URLから再取得
                    </button>
                </div>
                <div class="text-xs text-slate-400">表示件数: <span id="display-count" class="font-bold text-slate-200">0</span> 件</div>
            </div>
        </section>

        <!-- Leads Table -->
        <section class="glass-panel rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-900/90 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
                            <th class="p-3 w-10 text-center">
                                <input type="checkbox" id="chk-all" onchange="toggleAll(this.checked)" class="rounded bg-slate-800 border-slate-600">
                            </th>
                            <th class="p-3 w-16">ID</th>
                            <th class="p-3 min-w-[220px]">企業名 / 所在地</th>
                            <th class="p-3 min-w-[220px]">公式URL</th>
                            <th class="p-3 min-w-[220px]">抽出メール / FAX</th>
                            <th class="p-3 w-28">状態</th>
                            <th class="p-3 w-[320px] text-right">精査アクション</th>
                        </tr>
                    </thead>
                    <tbody id="leads-tbody" class="divide-y divide-slate-800/80 text-xs">
                        <tr><td colspan="7" class="p-8 text-center text-slate-400">読み込み中...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- Toast -->
    <div id="toast" class="fixed bottom-5 right-5 z-50 hidden px-4 py-3 rounded-xl shadow-2xl text-xs font-semibold border transition-all"></div>

    <script>
        let currentRows = [];

        function showToast(msg, isError = false) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.className = 'fixed bottom-5 right-5 z-50 px-4 py-3 rounded-xl shadow-2xl text-xs font-semibold border ' +
                (isError
                    ? 'bg-rose-950/95 text-rose-200 border-rose-500/50'
                    : 'bg-emerald-950/95 text-emerald-200 border-emerald-500/50');
            t.classList.remove('hidden');
            clearTimeout(window._toastTimer);
            window._toastTimer = setTimeout(() => t.classList.add('hidden'), 3000);
        }

        function esc(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>"']/g, s => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[s]));
        }

        function quickFilter(status, hasEmail) {
            document.getElementById('filter-status').value = status;
            document.getElementById('filter-email').value = hasEmail;
            document.getElementById('filter-dup').value = '';
            loadLeads();
        }

        async function loadLeads() {
            const status = document.getElementById('filter-status').value;
            const hasEmail = document.getElementById('filter-email').value;
            const dupOnly = document.getElementById('filter-dup').value;
            const search = document.getElementById('filter-search').value.trim();

            const params = new URLSearchParams();
            if (status !== '') params.set('status', status);
            if (hasEmail !== '') params.set('has_email', hasEmail);
            if (dupOnly !== '') params.set('is_duplicate', dupOnly);
            if (search !== '') params.set('search', search);
            params.set('limit', '300');

            try {
                const res = await fetch('/api/companies.php?' + params.toString());
                const json = await res.json();
                if (json.status !== 'success') throw new Error(json.message || '取得エラー');

                currentRows = json.data || [];
                updateCounts(json.counts || {});
                renderTable(currentRows);
            } catch (err) {
                showToast('データ取得に失敗しました: ' + err.message, true);
            }
        }

        function updateCounts(c) {
            document.getElementById('cnt-crawled-email').textContent = c.crawled_with_email ?? 0;
            document.getElementById('cnt-crawled').textContent = c.crawled ?? 0;
            document.getElementById('cnt-approved').textContent = c.approved ?? 0;
            document.getElementById('cnt-rejected').textContent = c.rejected ?? 0;
            document.getElementById('cnt-pending').textContent = c.pending ?? 0;
            document.getElementById('cnt-sent').textContent = c.sent ?? 0;
        }

        function statusBadge(st, isOptOut) {
            if (Number(isOptOut) === 1) {
                return '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40">opt_out</span>';
            }
            const map = {
                crawled: 'bg-amber-500/20 text-amber-300 border-amber-500/40',
                approved: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40',
                rejected: 'bg-rose-500/20 text-rose-300 border-rose-500/40',
                pending: 'bg-slate-600/30 text-slate-300 border-slate-500/40',
                sent: 'bg-purple-500/20 text-purple-300 border-purple-500/40',
                failed: 'bg-red-500/20 text-red-300 border-red-500/40',
            };
            const cls = map[st] || 'bg-slate-700 text-slate-300 border-slate-600';
            return `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold border ${cls}">${esc(st)}</span>`;
        }

        function renderTable(rows) {
            const tbody = document.getElementById('leads-tbody');
            document.getElementById('display-count').textContent = rows.length;
            document.getElementById('chk-all').checked = false;
            updateSelectedCount();

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-400">該当するリードはありません。</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(r => {
                const isTest = r.source_type === 'test';
                const isDup = Number(r.is_duplicate_email) === 1;
                const urlHtml = r.official_url
                    ? `<a href="${esc(r.official_url)}" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline break-all">${esc(r.official_url)} <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i></a>`
                    : '<span class="text-slate-500">（未取得）</span>';
                const emailHtml = r.email
                    ? `<div class="font-mono text-slate-100 break-all">${esc(r.email)}</div>`
                    : '<span class="text-slate-500">（なし）</span>';
                const faxHtml = r.fax ? `<div class="text-[11px] text-slate-400 mt-0.5">FAX: ${esc(r.fax)}</div>` : '';

                return `
                <tr class="hover:bg-slate-800/40 transition" id="row-${r.id}">
                    <td class="p-3 text-center">
                        <input type="checkbox" class="row-chk rounded bg-slate-800 border-slate-600" value="${r.id}" onchange="updateSelectedCount()">
                    </td>
                    <td class="p-3 font-mono text-slate-400">#${r.id}</td>
                    <td class="p-3">
                        <div class="font-bold text-slate-100 flex items-center gap-1.5 flex-wrap">
                            ${esc(r.name)}
                            ${isTest ? '<span class="px-1.5 py-0.5 rounded text-[10px] bg-rose-500/20 text-rose-300 border border-rose-500/40">TEST</span>' : ''}
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5">${esc(r.prefecture || '')} ${esc(r.city || '')} ${r.corporate_number ? ' / 法人番号: ' + esc(r.corporate_number) : ''}</div>
                    </td>
                    <td class="p-3 max-w-[260px]">${urlHtml}</td>
                    <td class="p-3">
                        ${emailHtml}
                        ${isDup ? '<span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/40"><i class="fa-solid fa-triangle-exclamation mr-1"></i>重複メール</span>' : ''}
                        ${faxHtml}
                    </td>
                    <td class="p-3">${statusBadge(r.status, r.is_opt_out)}</td>
                    <td class="p-3 text-right">
                        <div class="inline-flex flex-wrap justify-end gap-1.5">
                            <button onclick="setStatus(${r.id}, 'approved')" class="bg-emerald-600 hover:bg-emerald-500 text-white px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition" title="承認して送信対象へ">
                                <i class="fa-solid fa-check"></i> 承認
                            </button>
                            <button onclick="setStatus(${r.id}, 'rejected')" class="bg-rose-600 hover:bg-rose-500 text-white px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition" title="営業対象外として却下">
                                <i class="fa-solid fa-xmark"></i> 却下
                            </button>
                            <button onclick="setStatus(${r.id}, 'crawled')" class="bg-amber-600/80 hover:bg-amber-500 text-white px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition" title="承認待ち（保留）のまま維持">
                                <i class="fa-solid fa-pause"></i> 保留
                            </button>
                            <button onclick="resetRecrawl(${r.id}, false)" class="bg-sky-700 hover:bg-sky-600 text-white px-2 py-1.5 rounded-lg text-[11px] transition" title="メールをクリアして再クロール">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                            <button onclick="resetRecrawl(${r.id}, true)" class="bg-indigo-700 hover:bg-indigo-600 text-white px-2 py-1.5 rounded-lg text-[11px] transition" title="URLからSerperで再取得">
                                <i class="fa-solid fa-globe"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        function toggleAll(checked) {
            document.querySelectorAll('.row-chk').forEach(el => { el.checked = checked; });
            updateSelectedCount();
        }

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.row-chk:checked')).map(el => Number(el.value));
        }

        function updateSelectedCount() {
            document.getElementById('selected-count').textContent = getSelectedIds().length;
        }

        async function setStatus(id, status) {
            try {
                const res = await fetch('/api/companies.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_status', company_id: id, status })
                });
                const json = await res.json();
                if (json.status !== 'success') throw new Error(json.message || '更新エラー');
                const labelMap = { approved: '承認', rejected: '却下', crawled: '保留' };
                showToast(`#${id} を「${labelMap[status] || status}」に更新しました`);
                loadLeads();
            } catch (err) {
                showToast('更新失敗: ' + err.message, true);
            }
        }

        async function resetRecrawl(id, resetUrl) {
            const msg = resetUrl
                ? `#${id} の公式URLとメールをクリアして Serper 再取得（pending）に戻しますか？`
                : `#${id} のメールをクリアして再クロール（pending）に戻しますか？`;
            if (!confirm(msg)) return;
            try {
                const res = await fetch('/api/companies.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset_recrawl', company_id: id, reset_url: resetUrl })
                });
                const json = await res.json();
                if (json.status !== 'success') throw new Error(json.message || '更新エラー');
                showToast(`#${id} を再取得待ち（pending）に戻しました`);
                loadLeads();
            } catch (err) {
                showToast('更新失敗: ' + err.message, true);
            }
        }

        async function bulkAction(mode) {
            const ids = getSelectedIds();
            if (ids.length === 0) {
                showToast('対象企業をチェックボックスで選択してください', true);
                return;
            }
            const labels = {
                approved: '一括承認',
                rejected: '一括却下',
                crawled: '一括保留',
                recrawl_email: 'メール再クロール（pending戻し）',
                recrawl_url: 'URLから再取得（pending戻し）'
            };
            if (!confirm(`選択中の ${ids.length} 件を「${labels[mode]}」にしますか？`)) return;

            try {
                const res = await fetch('/api/companies.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'bulk_update_status', ids, mode })
                });
                const json = await res.json();
                if (json.status !== 'success') throw new Error(json.message || '一括更新エラー');
                showToast(`${json.affected} 件を「${labels[mode]}」にしました`);
                loadLeads();
            } catch (err) {
                showToast('一括更新失敗: ' + err.message, true);
            }
        }

        loadLeads();
    </script>
</body>
</html>
