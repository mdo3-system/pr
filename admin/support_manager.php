<?php
/**
 * 木造壁量計算WEB「上善如水」管理者用 サポート管理＆ナレッジ昇格ダッシュボード
 * (/admin/support_manager.php)
 */
require_once __DIR__ . '/../api/auth_helper.php';

$user = getAuthenticatedUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>【管理者】サポート・質疑マネージャー | 上善如水</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #090d16;
            --card-bg: #151d2a;
            --border-color: #2a364f;
            --accent-blue: #38bdf8;
            --accent-green: #10b981;
            --accent-amber: #f59e0b;
            --accent-purple: #a855f7;
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Noto Sans JP', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            width: 360px;
            background: #0f172a;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background: #1e293b;
        }
        .sidebar-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent-purple);
        }

        .ticket-list {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }
        .ticket-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ticket-item:hover, .ticket-item.active {
            border-color: var(--accent-purple);
            background: #1e293b;
        }
        .badge-status {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-open { background: #0284c7; color: #fff; }
        .badge-in_progress { background: #d97706; color: #fff; }
        .badge-resolved { background: #16a34a; color: #fff; }
        .badge-closed { background: #4b5563; color: #fff; }

        .main-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-dark);
        }
        .panel-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background: #151d2a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-debug {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #000;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .btn-promote {
            background: linear-gradient(135deg, #a855f7, #7e22ce);
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .chat-body {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .message-bubble {
            max-width: 80%;
            padding: 14px 18px;
            border-radius: 12px;
            line-height: 1.5;
        }
        .message-bubble.user {
            align-self: flex-start;
            background: #1e293b;
            border: 1px solid var(--border-color);
            color: #fff;
        }
        .message-bubble.staff {
            align-self: flex-end;
            background: #0284c7;
            color: #fff;
        }

        .reply-panel {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            background: #151d2a;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .form-row {
            display: flex;
            gap: 12px;
        }
        .input-text {
            width: 100%;
            background: #090d16;
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 200;
        }
        .modal-content {
            background: #151d2a;
            border: 1px solid var(--accent-purple);
            border-radius: 12px;
            width: 700px;
            max-width: 95%;
            padding: 24px;
        }
    </style>
</head>
<body>

    <!-- サイドバー -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">🛠 運営者用 質疑マネージャー</div>
        </div>
        <div id="admin-ticket-list" class="ticket-list">
            <!-- 動的チケットリスト -->
        </div>
    </div>

    <!-- メイン領域 -->
    <div class="main-panel">
        <div class="panel-header">
            <div>
                <h2 id="admin-ticket-title">質問カードを選択してください</h2>
                <div id="admin-ticket-user" style="font-size: 0.85rem; color: var(--text-sub);"></div>
            </div>
            <div style="display: flex; gap: 10px;">
                <form id="form-debug-app" action="https://2025.eie.jp/debug_load.php" method="POST" target="_blank" style="display: inline;">
                    <input type="hidden" id="debug-json-input" name="input_json">
                    <button type="submit" id="btn-debug-launch" class="btn-debug" disabled>🛠 デバッグ起動 (2025.eie.jp)</button>
                </form>
                <button id="btn-open-promote-modal" class="btn-promote" disabled>💡 ナレッジ掲示板へ昇格</button>
            </div>
        </div>

        <div id="admin-chat-body" class="chat-body">
            <div style="text-align: center; color: var(--text-sub); margin-top: 50px;">
                左側のリストから質問カードを選択してください。
            </div>
        </div>

        <!-- スタッフ返信フォーム -->
        <div class="reply-panel">
            <div class="form-row">
                <textarea id="reply-text" class="input-text" rows="3" placeholder="回答・アドバイスを入力..."></textarea>
            </div>
            <div class="form-row">
                <input type="file" id="reply-pdf" accept=".pdf" class="input-text" style="width: 50%;" title="添削PDF">
                <input type="text" id="reply-youtube" class="input-text" style="width: 50%;" placeholder="YouTube URL (限定公開)">
            </div>
            <div class="form-row" style="justify-content: space-between; align-items: center;">
                <input type="text" id="reply-zoom" class="input-text" style="width: 60%;" placeholder="🎥 Zoom接続用URL">
                <button id="btn-submit-reply" style="background: var(--accent-purple); color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 700; cursor: pointer;">返信・送信</button>
            </div>
        </div>
    </div>

    <!-- ナレッジ昇格スクラブ確認モーダル -->
    <div id="modal-promote" class="modal-overlay">
        <div class="modal-content">
            <h3 style="color: var(--accent-purple); margin-bottom: 16px;">💡 ナレッジ掲示板へ昇格＆個人情報スクラブ確認</h3>
            <form id="form-promote-knowledge">
                <input type="hidden" id="promote-ticket-id">
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-sub); margin-bottom: 4px;">公開用タイトル (自動アノニマイズ済)</label>
                    <input type="text" id="promote-title" class="input-text" required>
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-sub); margin-bottom: 4px;">カテゴリー</label>
                    <input type="text" id="promote-category" class="input-text" required>
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 0.85rem; color: var(--text-sub); margin-bottom: 4px;">公開用本文 Markdown (個人情報・図面除外)</label>
                    <textarea id="promote-content-md" class="input-text" rows="10" required></textarea>
                </div>
                <div style="background: #090d16; padding: 12px; border-radius: 6px; border: 1px solid #dc2626; margin-bottom: 16px;">
                    <label style="color: #ef4444; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="chk-confirm-anonymize" required>
                        ☑ 個人情報、会社名、物件名、固有図面データ(DXF/JSON)が一切含まれていないことを確認しました
                    </label>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="document.getElementById('modal-promote').style.display='none'" style="background: transparent; border: 1px solid var(--border-color); color: #fff; padding: 8px 16px; border-radius: 6px; cursor: pointer;">キャンセル</button>
                    <button type="submit" style="background: var(--accent-purple); color: #fff; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 700; cursor: pointer;">ナレッジ掲示板へ公開</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let selectedTicketId = null;

        async function loadAdminTickets() {
            const res = await fetch('/api/support/list_tickets.php');
            const data = await res.json();
            const container = document.getElementById('admin-ticket-list');
            container.innerHTML = '';

            if (data.status === 'success' && data.tickets.length > 0) {
                data.tickets.forEach(t => {
                    const div = document.createElement('div');
                    div.className = `ticket-item ${t.ticket_id === selectedTicketId ? 'active' : ''}`;
                    div.onclick = () => selectAdminTicket(t.ticket_id);
                    div.innerHTML = `
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span class="badge-status badge-${t.status}">${t.status}</span>
                            <span style="font-size: 0.75rem; color: var(--text-sub);">${t.user_name || 'ユーザー'}</span>
                        </div>
                        <div style="font-weight: 600; font-size: 0.95rem;">${t.title}</div>
                        ${t.is_promoted_to_faq ? '<div style="font-size: 0.7rem; color: var(--accent-purple); margin-top: 4px;">✓ ナレッジ昇格済</div>' : ''}
                    `;
                    container.appendChild(div);
                });
            }
        }

        async function selectAdminTicket(ticketId) {
            selectedTicketId = ticketId;
            loadAdminTickets();

            const res = await fetch(`/api/support/get_ticket_detail.php?ticket_id=${ticketId}`);
            const data = await res.json();

            if (data.status !== 'success') return;

            const t = data.ticket;
            document.getElementById('admin-ticket-title').innerText = t.title;
            document.getElementById('admin-ticket-user').innerText = `投稿者: ${t.user_name} (${t.user_email}) | 企業名: ${t.company_name || '未登録'}`;

            // デバッグ起動パラメータセット
            const debugBtn = document.getElementById('btn-debug-launch');
            if (t.input_data_json) {
                document.getElementById('debug-json-input').value = t.input_data_json;
                debugBtn.disabled = false;
            } else {
                debugBtn.disabled = true;
            }

            document.getElementById('btn-open-promote-modal').disabled = false;
            if (t.zoom_url) document.getElementById('reply-zoom').value = t.zoom_url;

            // チャットタイムライン表示
            const body = document.getElementById('admin-chat-body');
            body.innerHTML = '';

            if (data.dxf_download_url) {
                const dxfDiv = document.createElement('div');
                dxfDiv.style.cssText = "background: #1e293b; border: 1px solid var(--accent-blue); padding: 10px; border-radius: 6px;";
                dxfDiv.innerHTML = `📐 <strong>ユーザー添付DXF:</strong> <a href="${data.dxf_download_url}" target="_blank" style="color: var(--accent-blue);">DXFダウンロード</a>`;
                body.appendChild(dxfDiv);
            }

            data.messages.forEach(m => {
                const div = document.createElement('div');
                div.className = `message-bubble ${m.sender_type}`;
                div.innerHTML = `
                    <div style="font-weight: 600; font-size: 0.8rem; margin-bottom: 4px;">${m.sender_name || m.sender_type} (${m.created_at})</div>
                    <div>${m.message_text.replace(/\n/g, '<br>')}</div>
                    ${m.pdf_download_url ? `<div style="margin-top: 6px;">📄 <a href="${m.pdf_download_url}" target="_blank" style="color: #fff;">添付PDF閲覧</a></div>` : ''}
                `;
                body.appendChild(div);
            });

            body.scrollTop = body.scrollHeight;
        }

        // 返信送信
        document.getElementById('btn-submit-reply').onclick = async () => {
            if (!selectedTicketId) return;

            const formData = new FormData();
            formData.append('ticket_id', selectedTicketId);
            formData.append('message_text', document.getElementById('reply-text').value);
            formData.append('youtube_url', document.getElementById('reply-youtube').value);
            formData.append('zoom_url', document.getElementById('reply-zoom').value);

            const pdfFile = document.getElementById('reply-pdf').files[0];
            if (pdfFile) formData.append('attachment_pdf', pdfFile);

            const res = await fetch('/api/support/post_message.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.status === 'success') {
                document.getElementById('reply-text').value = '';
                document.getElementById('reply-pdf').value = '';
                selectAdminTicket(selectedTicketId);
            } else {
                alert(data.message);
            }
        };

        // ナレッジ昇格モーダル表示＆自動スクラブプレビュー取得
        document.getElementById('btn-open-promote-modal').onclick = async () => {
            if (!selectedTicketId) return;
            const res = await fetch(`/api/admin/preview_anonymize.php?ticket_id=${selectedTicketId}`);
            const data = await res.json();

            if (data.status === 'success') {
                document.getElementById('promote-ticket-id').value = data.source_ticket_id;
                document.getElementById('promote-title').value = data.clean_title;
                document.getElementById('promote-category').value = data.clean_category;
                document.getElementById('promote-content-md').value = data.clean_content_md;
                document.getElementById('chk-confirm-anonymize').checked = false;
                document.getElementById('modal-promote').style.display = 'flex';
            }
        };

        // 昇格フォームSubmit
        document.getElementById('form-promote-knowledge').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('source_ticket_id', document.getElementById('promote-ticket-id').value);
            formData.append('title', document.getElementById('promote-title').value);
            formData.append('category', document.getElementById('promote-category').value);
            formData.append('content_md', document.getElementById('promote-content-md').value);
            formData.append('admin_confirm_flag', document.getElementById('chk-confirm-anonymize').checked);

            const res = await fetch('/api/admin/promote_to_knowledge.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.status === 'success') {
                alert('昇格投稿が完了しました！');
                document.getElementById('modal-promote').style.display = 'none';
                loadAdminTickets();
            } else {
                alert(data.message);
            }
        };

        loadAdminTickets();
    </script>
</body>
</html>
