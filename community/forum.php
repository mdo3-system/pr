<?php
/**
 * 木造壁量計算WEB「上善如水」公式コミュニティ＆ナレッジ掲示板 (FAQ)
 * (/community/forum.php)
 */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ナレッジ掲示板・コミュニティ | 木造壁量計算WEB「上善如水」</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0f172a;
            --card-bg: #1e293b;
            --border-color: #334155;
            --accent-blue: #38bdf8;
            --accent-green: #10b981;
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Noto Sans JP', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            min-height: 100vh;
        }

        header {
            background: #1e293b;
            border-bottom: 1px solid var(--border-color);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--accent-blue);
        }

        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .search-hero {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        .search-hero h1 {
            font-size: 1.6rem;
            margin-bottom: 12px;
        }
        .search-hero p {
            color: var(--text-sub);
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        .search-box {
            display: flex;
            max-width: 600px;
            margin: 0 auto;
            gap: 10px;
        }
        .input-search {
            flex: 1;
            background: #0f172a;
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn-search {
            background: var(--accent-blue);
            color: #0f172a;
            border: none;
            padding: 0 24px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        .knowledge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .post-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            transition: transform 0.2s, border-color 0.2s;
            cursor: pointer;
        }
        .post-card:hover {
            transform: translateY(-2px);
            border-color: var(--accent-blue);
        }
        .post-category {
            display: inline-block;
            background: rgba(56, 189, 248, 0.15);
            color: var(--accent-blue);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 12px;
            margin-bottom: 10px;
        }
        .post-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .post-snippet {
            font-size: 0.88rem;
            color: var(--text-sub);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 12px;
        }
        .post-footer {
            font-size: 0.75rem;
            color: var(--text-sub);
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

    <header>
        <div class="logo-title">💡 上善如水 公式ナレッジベース & コミュニティ</div>
        <div>
            <a href="/my/support_dashboard.php" style="color: var(--accent-blue); text-decoration: none; font-weight: 600;">プレミアムサポートポータル ➔</a>
        </div>
    </header>

    <div class="container">
        <div class="search-hero">
            <h1>知見と解決策を検索</h1>
            <p>プレミアムユーザーのサポートで得られた実務ノウハウや斜め壁・基礎計算の知見を公開しています。</p>
            <div class="search-box">
                <input type="text" id="forum-search-kw" class="input-search" placeholder="例: 斜め壁, 金物選定, 基礎 calculation...">
                <button id="btn-forum-search" class="btn-search">検索</button>
            </div>
        </div>

        <div id="knowledge-posts-grid" class="knowledge-grid">
            <!-- 記事動的ロード -->
        </div>
    </div>

    <script>
        async function loadKnowledgePosts(kw = '') {
            const url = kw ? `/api/community/list_knowledge.php?keyword=${encodeURIComponent(kw)}` : '/api/community/list_knowledge.php';
            const res = await fetch(url);
            const data = await res.json();

            const grid = document.getElementById('knowledge-posts-grid');
            grid.innerHTML = '';

            if (data.status === 'success' && data.posts.length > 0) {
                data.posts.forEach(p => {
                    const card = document.createElement('div');
                    card.className = 'post-card';
                    card.innerHTML = `
                        <div class="post-category">${escapeHtml(p.category)}</div>
                        <div class="post-title">${escapeHtml(p.title)}</div>
                        <div class="post-snippet">${escapeHtml(p.content_md.replace(/#|\*|`/g, ''))}</div>
                        <div class="post-footer">
                            <span>📅 ${p.created_at.substr(0,10)}</span>
                            <span>💡 実務解決ガイド</span>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            } else {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--text-sub); padding: 40px;">該当するナレッジ記事が見つかりませんでした。</div>';
            }
        }

        document.getElementById('btn-forum-search').onclick = () => {
            loadKnowledgePosts(document.getElementById('forum-search-kw').value.trim());
        };

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        loadKnowledgePosts();
    </script>
</body>
</html>
