<?php
/**
 * 木造壁量計算WEB「上善如水」プレミアムサポートダッシュボード
 * (/my/support_dashboard.php)
 */
require_once __DIR__ . '/../api/auth_helper.php';

$user = getAuthenticatedUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プレミアムサポート ポータル | 上善如水</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-bg: #1e293b;
            --border-color: #334155;
            --accent-blue: #38bdf8;
            --accent-green: #10b981;
            --accent-purple: #8b5cf6;
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

        /* サイドバー */
        .sidebar {
            width: 320px;
            background: #111827;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sidebar-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent-blue);
        }
        .btn-new-ticket {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-new-ticket:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.4);
        }

        .ticket-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .ticket-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: border 0.2s, background 0.2s;
        }
        .ticket-item:hover, .ticket-item.active {
            border-color: var(--accent-blue);
            background: #24324d;
        }
        .ticket-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
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

        .ticket-title {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 4px;
            color: #f1f5f9;
        }
        .ticket-meta {
            font-size: 0.75rem;
            color: var(--text-sub);
        }

        /* メイン詳細ビュー */
        .main-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #0f172a;
        }
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background: #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-title-group h2 {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }
        .chat-category {
            font-size: 0.8rem;
            color: var(--accent-blue);
        }

        .btn-zoom {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 0 12px rgba(16, 185, 129, 0.4);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .chat-body {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .message-bubble {
            max-width: 75%;
            padding: 14px 18px;
            border-radius: 12px;
            line-height: 1.5;
            font-size: 0.95rem;
            position: relative;
        }
        .message-bubble.user {
            align-self: flex-end;
            background: #0284c7;
            color: #ffffff;
            border-bottom-right-radius: 2px;
        }
        .message-bubble.staff {
            align-self: flex-start;
            background: #1e293b;
            border: 1px solid var(--border-color);
            color: #f8fafc;
            border-bottom-left-radius: 2px;
        }
        .message-meta {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.7);
            margin-top: 6px;
            text-align: right;
        }
        .message-bubble.staff .message-meta {
            color: var(--text-sub);
        }

        .chat-attachment {
            margin-top: 10px;
            padding: 10px;
            background: rgba(0,0,0,0.2);
            border-radius: 6px;
        }
        .chat-attachment a {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 600;
        }

        .video-container {
            margin-top: 10px;
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 8px;
        }
        .video-container iframe {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
        }

        .chat-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border-color);
            background: #1e293b;
            display: flex;
            gap: 12px;
        }
        .input-chat {
            flex: 1;
            background: #0f172a;
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            font-size: 0.95rem;
            resize: none;
            height: 50px;
        }
        .btn-send {
            background: var(--accent-blue);
            color: #0f172a;
            border: none;
            padding: 0 20px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 100;
        }
        .modal-content {
            background: #1e293b;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            width: 500px;
            max-width: 90%;
            padding: 24px;
        }
        .modal-title {
            font-size: 1.2rem;
            margin-bottom: 16px;
            color: var(--accent-blue);
        }
        .form-group {
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-sub);
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            background: #0f172a;
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <!-- サイドバー: カード一覧 -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">💬 プレミアムサポート</div>
            <button id="btn-open-create-modal" class="btn-new-ticket">＋ 新規質問</button>
        </div>
        <div id="ticket-list-container" class="ticket-list">
            <!-- 質疑カード動的展開 -->
        </div>
    </div>

    <!-- メイン領域: カードチャット詳細 -->
    <div class="main-chat">
        <div class="chat-header">
            <div class="chat-title-group">
                <h2 id="chat-ticket-title">質問カードを選択してください</h2>
                <div id="chat-ticket-category" class="chat-category"></div>
            </div>
            <div id="zoom-button-slot"></div>
        </div>

        <div id="chat-body-container" class="chat-body">
            <div style="text-align: center; color: var(--text-sub); margin-top: 50px;">
                👈 左側のリストから質疑カードを選択するか、「＋ 新規質問」ボタンでご質問をお送りください。
            </div>
        </div>

        <div class="chat-footer">
            <textarea id="chat-input-text" class="input-chat" placeholder="返信内容を入力..."></textarea>
            <button id="btn-send-message" class="btn-send">送信</button>
        </div>
    </div>

    <!-- 新規質問モーダル -->
    <div id="modal-create-ticket" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-title">＋ 新しい質問カードを作成</div>
            <form id="form-create-ticket" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="create-title">質問タイトル (必須)</label>
                    <input type="text" id="create-title" name="title" class="form-control" placeholder="例: 平面斜め壁の壁率計算について" required>
                </div>
                <div class="form-group">
                    <label for="create-category">カテゴリー</label>
                    <select id="create-category" name="category" class="form-control">
                        <option value="斜め壁計算">斜め壁計算</option>
                        <option value="基礎計算">基礎計算</option>
                        <option value="DXF下地読込">DXF下地読込</option>
                        <option value="法改正対応">法改正対応</option>
                        <option value="その他">その他</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="create-message">質問本文</label>
                    <textarea id="create-message" name="message" class="form-control" rows="4" placeholder="具体的な質問内容を入力してください"></textarea>
                </div>
                <div class="form-group">
                    <label for="create-dxf">DXF図面添付スロット (.dxf専用)</label>
                    <input type="file" id="create-dxf" name="dxf_file" accept=".dxf" class="form-control">
                </div>
                <div class="form-group">
                    <label for="create-json">計算アプリ検証パラメータ (JSONデータ)</label>
                    <textarea id="create-json" name="input_json" class="form-control" rows="2" placeholder='{"wall_length": 3.64, "angle": 45}'></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" id="btn-close-create-modal" style="background: transparent; border: 1px solid var(--border-color); color: #fff; padding: 8px 16px; border-radius: 6px; cursor: pointer;">キャンセル</button>
                    <button type="submit" class="btn-new-ticket">質問を作成</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentTicketId = null;

        // モーダル開閉
        document.getElementById('btn-open-create-modal').onclick = () => document.getElementById('modal-create-ticket').style.display = 'flex';
        document.getElementById('btn-close-create-modal').onclick = () => document.getElementById('modal-create-ticket').style.display = 'none';

        // チケット一覧読み込み
        async function loadTicketList() {
            const res = await fetch('/api/support/list_tickets.php');
            const data = await res.json();
            const container = document.getElementById('ticket-list-container');
            container.innerHTML = '';

            if (data.status === 'success' && data.tickets.length > 0) {
                data.tickets.forEach(t => {
                    const div = document.createElement('div');
                    div.className = `ticket-item ${t.ticket_id === currentTicketId ? 'active' : ''}`;
                    div.onclick = () => selectTicket(t.ticket_id);
                    
                    const statusText = {
                        'open': '未回答',
                        'in_progress': '対応中',
                        'resolved': '解決済',
                        'closed': '完了'
                    }[t.status] || t.status;

                    div.innerHTML = `
                        <div class="ticket-item-header">
                            <span class="badge-status badge-${t.status}">${statusText}</span>
                            <span class="ticket-meta">${t.created_at.substr(0,10)}</span>
                        </div>
                        <div class="ticket-title">${escapeHtml(t.title)}</div>
                        <div class="ticket-meta">カテゴリ: ${escapeHtml(t.category)}</div>
                    `;
                    container.appendChild(div);
                });
            } else {
                container.innerHTML = '<div style="color: var(--text-sub); text-align: center; padding: 20px;">質問カードはありません。</div>';
            }
        }

        // チケット詳細選択
        async function selectTicket(ticketId) {
            currentTicketId = ticketId;
            loadTicketList();

            const res = await fetch(`/api/support/get_ticket_detail.php?ticket_id=${ticketId}`);
            const data = await res.json();

            if (data.status !== 'success') {
                alert(data.message);
                return;
            }

            const ticket = data.ticket;
            const messages = data.messages;

            document.getElementById('chat-ticket-title').innerText = ticket.title;
            document.getElementById('chat-ticket-category').innerText = `カテゴリ: ${ticket.category} (${ticket.created_at})`;

            // Zoomボタン表示
            const zoomSlot = document.getElementById('zoom-button-slot');
            if (ticket.zoom_url) {
                zoomSlot.innerHTML = `<a href="${escapeHtml(ticket.zoom_url)}" target="_blank" class="btn-zoom">🎥 Zoomサポートに接続</a>`;
            } else {
                zoomSlot.innerHTML = '';
            }

            // チャットタイムライン表示
            const body = document.getElementById('chat-body-container');
            body.innerHTML = '';

            // DXF添付情報があれば上部にカード配置
            if (data.dxf_download_url) {
                const dxfCard = document.createElement('div');
                dxfCard.style.cssText = "background: #1e293b; border: 1px solid var(--accent-blue); padding: 12px; border-radius: 8px;";
                dxfCard.innerHTML = `📐 <strong>添付DXF図面:</strong> <a href="${data.dxf_download_url}" style="color: var(--accent-blue);">DXFファイルをダウンロード</a>`;
                body.appendChild(dxfCard);
            }

            messages.forEach(m => {
                const bubble = document.createElement('div');
                bubble.className = `message-bubble ${m.sender_type}`;
                
                let contentHtml = `<div>${escapeHtml(m.message_text).replace(/\n/g, '<br>')}</div>`;
                
                if (m.pdf_download_url) {
                    contentHtml += `
                        <div class="chat-attachment">
                            📄 添削PDF: <a href="${m.pdf_download_url}" target="_blank">添削PDFを開く</a>
                        </div>
                    `;
                }

                if (m.youtube_url) {
                    const embedUrl = convertYoutubeEmbed(m.youtube_url);
                    if (embedUrl) {
                        contentHtml += `
                            <div class="video-container">
                                <iframe src="${embedUrl}" frameborder="0" allowfullscreen></iframe>
                            </div>
                        `;
                    }
                }

                contentHtml += `<div class="message-meta">${m.sender_name || m.sender_type} - ${m.created_at.substr(11,5)}</div>`;
                bubble.innerHTML = contentHtml;
                body.appendChild(bubble);
            });

            body.scrollTop = body.scrollHeight;
        }

        // 新規質問作成 submit
        document.getElementById('form-create-ticket').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);

            const res = await fetch('/api/support/create_ticket.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.status === 'success') {
                document.getElementById('modal-create-ticket').style.display = 'none';
                e.target.reset();
                currentTicketId = data.ticket_id;
                loadTicketList();
                selectTicket(data.ticket_id);
            } else {
                alert(data.message);
            }
        };

        // メッセージ返信
        document.getElementById('btn-send-message').onclick = async () => {
            if (!currentTicketId) {
                alert('質問カードを選択してください。');
                return;
            }
            const input = document.getElementById('chat-input-text');
            const text = input.value.trim();
            if (!text) return;

            const formData = new FormData();
            formData.append('ticket_id', currentTicketId);
            formData.append('message_text', text);

            const res = await fetch('/api/support/post_message.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.status === 'success') {
                input.value = '';
                selectTicket(currentTicketId);
            } else {
                alert(data.message);
            }
        };

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function convertYoutubeEmbed(url) {
            const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
            const match = url.match(regExp);
            return (match && match[2].length === 11) ? `https://www.youtube.com/embed/${match[2]}` : null;
        }

        // 初期ロード
        loadTicketList();
    </script>
</body>
</html>
