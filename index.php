<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>J-ALG WEB管理ダッシュボード | WEB構造計算 上善如水 Lead Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Noto Sans JP', sans-serif; background-color: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .opt-out-row { background-color: rgba(225, 29, 72, 0.15) !important; border-left: 4px solid #f43f5e; }
        .tab-btn.active { border-bottom: 2px solid #38bdf8; color: #38bdf8; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Header -->
    <header class="glass-panel sticky top-0 z-50 px-6 py-4 flex items-center justify-between shadow-lg">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center font-bold text-xl shadow-lg">
                J
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                    J-ALG 管理ダッシュボード 
                    <span class="text-xs bg-sky-500/20 text-sky-400 border border-sky-500/30 px-2 py-0.5 rounded-full font-mono">v1.0.3</span>
                </h1>
                <p class="text-xs text-slate-400">上善如水 アライアンス・リードジェネレーター (Jozen Alliance Lead Generator)</p>
            </div>
        </div>
        <div class="flex items-center space-x-4 text-xs font-mono text-slate-400">
            <div><i class="fa-solid fa-server text-emerald-400"></i> XServer: eie.tokyo/pr</div>
            <div><i class="fa-solid fa-shield-halved text-sky-400"></i> 特電法自動判定エンジン: ACTIVE</div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 p-6 max-w-7xl w-full mx-auto space-y-6">

        <!-- Navigation Tabs -->
        <nav class="flex border-b border-slate-800 space-x-8 text-sm font-medium">
            <button onclick="switchTab('dashboard')" id="tab-dashboard" class="tab-btn pb-3 active flex items-center gap-2">
                <i class="fa-solid fa-chart-pie"></i> KPI メーター
            </button>
            <button onclick="switchTab('companies')" id="tab-companies" class="tab-btn pb-3 flex items-center gap-2">
                <i class="fa-solid fa-building-user"></i> リード精査・承認
            </button>
            <button onclick="switchTab('templates')" id="tab-templates" class="tab-btn pb-3 flex items-center gap-2">
                <i class="fa-solid fa-envelope-open-text"></i> DMテンプレート管理
            </button>
            <button onclick="switchTab('logs')" id="tab-logs" class="tab-btn pb-3 flex items-center gap-2">
                <i class="fa-solid fa-list-check"></i> 配信キュー＆ログ
            </button>
        </nav>

        <!-- [TAB 1] Dashboard KPI Section -->
        <section id="sec-dashboard" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="glass-panel p-5 rounded-2xl">
                    <div class="flex justify-between items-start text-slate-400 text-xs mb-2">
                        <span>全収集ターゲット企業数</span>
                        <i class="fa-solid fa-database text-sky-400 text-base"></i>
                    </div>
                    <div class="text-3xl font-bold text-slate-100 font-mono" id="kpi-total">0</div>
                    <div class="text-xs text-slate-500 mt-2">国税庁API & 建築士名簿</div>
                </div>
                <div class="glass-panel p-5 rounded-2xl">
                    <div class="flex justify-between items-start text-slate-400 text-xs mb-2">
                        <span>有効メールアドレス取得数</span>
                        <i class="fa-solid fa-at text-emerald-400 text-base"></i>
                    </div>
                    <div class="text-3xl font-bold text-emerald-400 font-mono" id="kpi-emails">0</div>
                    <div class="text-xs text-slate-500 mt-2">Serper & Scraper 抽出</div>
                </div>
                <div class="glass-panel p-5 rounded-2xl border-rose-500/30">
                    <div class="flex justify-between items-start text-slate-400 text-xs mb-2">
                        <span>特電法・自動除外件数</span>
                        <i class="fa-solid fa-ban text-rose-400 text-base"></i>
                    </div>
                    <div class="text-3xl font-bold text-rose-400 font-mono" id="kpi-optout">0</div>
                    <div class="text-xs text-rose-400/70 mt-2"><i class="fa-solid fa-gavel"></i> 営業お断りワード自動検知</div>
                </div>
                <div class="glass-panel p-5 rounded-2xl">
                    <div class="flex justify-between items-start text-slate-400 text-xs mb-2">
                        <span>承認済 (Approved) リスト</span>
                        <i class="fa-solid fa-circle-check text-sky-400 text-base"></i>
                    </div>
                    <div class="text-3xl font-bold text-sky-400 font-mono" id="kpi-approved">0</div>
                    <div class="text-xs text-slate-500 mt-2">配信キュー準備完了</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="glass-panel p-5 rounded-2xl">
                    <div class="text-xs text-slate-400 mb-1">送信待ちキュー</div>
                    <div class="text-2xl font-bold text-amber-400 font-mono" id="kpi-queued">0</div>
                </div>
                <div class="glass-panel p-5 rounded-2xl">
                    <div class="text-xs text-slate-400 mb-1">送信完了 / 到達数</div>
                    <div class="text-2xl font-bold text-emerald-400 font-mono" id="kpi-delivered">0</div>
                </div>
                <div class="glass-panel p-5 rounded-2xl">
                    <div class="text-xs text-slate-400 mb-1">バウンス / スパム報告</div>
                    <div class="text-2xl font-bold text-rose-400 font-mono" id="kpi-bounced">0</div>
                </div>
            </div>
        </section>

        <!-- [TAB 2] Lead Review & Approval Section -->
        <section id="sec-companies" class="space-y-4 hidden">
            <!-- Filter Bar -->
            <div class="glass-panel p-4 rounded-xl flex flex-wrap gap-4 items-center justify-between">
                <div class="flex flex-wrap gap-3 items-center text-xs">
                    <select id="flt-pref" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200">
                        <option value="">すべての都道府県</option>
                        <option value="埼玉県">埼玉県</option>
                        <option value="東京都">東京都</option>
                    </select>
                    <select id="flt-status" class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-200">
                        <option value="">すべてのステータス</option>
                        <option value="pending">未調査 (pending)</option>
                        <option value="crawled">調査済 (crawled)</option>
                        <option value="approved">承認済 (approved)</option>
                        <option value="rejected">却下 (rejected)</option>
                    </select>
                    <button onclick="loadCompanies()" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-lg font-medium shadow-md transition">
                        <i class="fa-solid fa-filter"></i> 絞り込み
                    </button>
                </div>
                <div>
                    <button onclick="bulkApprove()" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-xs font-semibold shadow-md transition">
                        <i class="fa-solid fa-check-double"></i> 有効メール保持企業を一括承認 (Approve All Valid)
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="glass-panel rounded-2xl overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/80 text-slate-400 uppercase font-mono">
                            <tr>
                                <th class="p-3">ID</th>
                                <th class="p-3">法人名 / 市区町村</th>
                                <th class="p-3">メールアドレス</th>
                                <th class="p-3">FAX / URL</th>
                                <th class="p-3">特電法チェック</th>
                                <th class="p-3">ステータス</th>
                                <th class="p-3 text-right">アクション</th>
                            </tr>
                        </thead>
                        <tbody id="company-table-body" class="divide-y divide-slate-800">
                            <!-- Rows loaded by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- [TAB 3] Template Management Section -->
        <section id="sec-templates" class="space-y-4 hidden">
            <div class="glass-panel p-6 rounded-2xl space-y-4">
                <h3 class="text-sm font-bold text-slate-200 border-b border-slate-700 pb-2">営業DMテンプレート作成・編集</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-3 text-xs">
                        <div>
                            <label class="block mb-1 text-slate-400">管理名称</label>
                            <input type="text" id="tpl-name" class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2 text-slate-200" placeholder="例: 木造工務店向けスポット訴求Ver1">
                        </div>
                        <div>
                            <label class="block mb-1 text-slate-400">件名 (置換タグ {{company_name}}, {{city}} 対応)</label>
                            <input type="text" id="tpl-subject" class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2 text-slate-200" placeholder="【Mac対応/斜め壁計算】WEB構造計算ツールのご案内">
                        </div>
                        <div>
                            <label class="block mb-1 text-slate-400">本文 (プレーンテキスト)</label>
                            <textarea id="tpl-body" rows="8" class="w-full bg-slate-900 border border-slate-700 rounded-lg p-2 text-slate-200 font-mono" placeholder="{{company_name}} 様&#10;&#10;お世話になっております。{{city}}にて建築設計・施工を手掛けられている皆様へ..."></textarea>
                        </div>
                        <button onclick="saveTemplate()" class="bg-sky-600 hover:bg-sky-500 text-white px-5 py-2 rounded-lg font-medium transition">
                            <i class="fa-solid fa-floppy-disk"></i> テンプレート保存
                        </button>
                    </div>
                    <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2">
                        <div class="text-xs font-mono text-slate-500 border-b border-slate-800 pb-1">リアルタイムプレビュー (置換後表示)</div>
                        <div class="text-xs font-bold text-sky-400" id="prev-subject">件名プレビュー</div>
                        <div class="text-xs text-slate-300 whitespace-pre-wrap font-mono" id="prev-body">本文プレビュー</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- [TAB 4] Send Logs Section -->
        <section id="sec-logs" class="space-y-4 hidden">
            <div class="glass-panel p-4 rounded-2xl overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900 text-slate-400 font-mono">
                        <tr>
                            <th class="p-3">送信ID</th>
                            <th class="p-3">宛先企業</th>
                            <th class="p-3">メールアドレス</th>
                            <th class="p-3">ステータス</th>
                            <th class="p-3">送信予定 / 実日時</th>
                        </tr>
                    </thead>
                    <tbody id="logs-table-body" class="divide-y divide-slate-800">
                        <!-- Loaded by JS -->
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <!-- JS Logic -->
    <script src="Version.js"></script>
    <script>
        function switchTab(tabName) {
            ['dashboard', 'companies', 'templates', 'logs'].forEach(t => {
                document.getElementById('sec-' + t).classList.add('hidden');
                document.getElementById('tab-' + t).classList.remove('active');
            });
            document.getElementById('sec-' + tabName).classList.remove('hidden');
            document.getElementById('tab-' + tabName).classList.add('active');

            if (tabName === 'dashboard') loadKpi();
            if (tabName === 'companies') loadCompanies();
            if (tabName === 'logs') loadLogs();
        }

        async function loadKpi() {
            try {
                const res = await fetch('api/logs.php?type=kpi');
                const data = await res.json();
                if (data.status === 'success') {
                    const k = data.kpi;
                    document.getElementById('kpi-total').innerText = k.total_companies.toLocaleString();
                    document.getElementById('kpi-emails').innerText = k.valid_emails.toLocaleString();
                    document.getElementById('kpi-optout').innerText = k.opt_out_count.toLocaleString();
                    document.getElementById('kpi-approved').innerText = k.approved_count.toLocaleString();
                    document.getElementById('kpi-queued').innerText = k.queued_mails.toLocaleString();
                    document.getElementById('kpi-delivered').innerText = k.delivered_mails.toLocaleString();
                    document.getElementById('kpi-bounced').innerText = k.bounced_mails.toLocaleString();
                }
            } catch (e) { console.error(e); }
        }

        async function loadCompanies() {
            const pref = document.getElementById('flt-pref').value;
            const status = document.getElementById('flt-status').value;
            const res = await fetch(`api/companies.php?prefecture=${encodeURIComponent(pref)}&status=${status}`);
            const data = await res.json();
            const tbody = document.getElementById('company-table-body');
            tbody.innerHTML = '';

            if (data.status === 'success') {
                data.data.forEach(c => {
                    const isOpt = c.is_opt_out == 1;
                    const tr = document.createElement('tr');
                    if (isOpt) tr.className = 'opt-out-row';

                    tr.innerHTML = `
                        <td class="p-3 font-mono text-slate-500">${c.id}</td>
                        <td class="p-3">
                            <div class="font-bold text-slate-200">${c.name}</div>
                            <div class="text-slate-400 text-[10px]">${c.prefecture} ${c.city}</div>
                        </td>
                        <td class="p-3 font-mono text-sky-400">${c.email || '<span class="text-slate-600">未取得</span>'}</td>
                        <td class="p-3">
                            <div>${c.fax || '-'}</div>
                            ${c.official_url ? `<a href="${c.official_url}" target="_blank" class="text-[10px] text-indigo-400 underline">公式サイト</a>` : ''}
                        </td>
                        <td class="p-3">
                            ${isOpt ? `<span class="bg-rose-500/20 text-rose-400 border border-rose-500/40 px-2 py-0.5 rounded text-[10px]"><i class="fa-solid fa-triangle-exclamation"></i> 営業お断り</span>` : `<span class="text-emerald-400 text-[10px]"><i class="fa-solid fa-shield"></i> OK</span>`}
                        </td>
                        <td class="p-3 font-mono text-[10px]">${c.status}</td>
                        <td class="p-3 text-right space-x-1">
                            <button onclick="updateCompanyStatus(${c.id}, 'approved')" class="bg-sky-600 hover:bg-sky-500 text-white px-2 py-1 rounded text-[10px]">Approve</button>
                            <button onclick="updateCompanyStatus(${c.id}, 'rejected')" class="bg-slate-700 hover:bg-slate-600 text-slate-300 px-2 py-1 rounded text-[10px]">Reject</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        }

        async function updateCompanyStatus(id, newStatus) {
            await fetch('api/companies.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'update_status', company_id: id, status: newStatus })
            });
            loadCompanies();
        }

        async function bulkApprove() {
            if (!confirm('有効なメールアドレスを持つ未オプトアウト企業を一括承認しますか？')) return;
            const res = await fetch('api/companies.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'bulk_approve' })
            });
            const data = await res.json();
            alert(`一括承認を完了しました (${data.approved_count} 件)`);
            loadCompanies();
        }

        async function loadLogs() {
            const res = await fetch('api/logs.php?type=logs');
            const data = await res.json();
            const tbody = document.getElementById('logs-table-body');
            tbody.innerHTML = '';
            if (data.status === 'success') {
                data.data.forEach(l => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="p-3 font-mono">${l.id}</td>
                        <td class="p-3">${l.company_name}</td>
                        <td class="p-3 font-mono text-sky-400">${l.email_to}</td>
                        <td class="p-3 font-mono text-xs">${l.status}</td>
                        <td class="p-3 text-[10px] text-slate-400">${l.scheduled_at}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        }

        // Live Preview Binding
        document.getElementById('tpl-subject').addEventListener('input', updatePrev);
        document.getElementById('tpl-body').addEventListener('input', updatePrev);
        function updatePrev() {
            const subj = document.getElementById('tpl-subject').value;
            const body = document.getElementById('tpl-body').value;
            document.getElementById('prev-subject').innerText = subj.replace('{{company_name}}', '株式会社 川越設計工房').replace('{{city}}', '川越市');
            document.getElementById('prev-body').innerText = body.replace('{{company_name}}', '株式会社 川越設計工房').replace('{{city}}', '川越市');
        }

        // Init
        loadKpi();
    </script>
</body>
</html>
