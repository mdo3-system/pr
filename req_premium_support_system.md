# 【要件定義・実装仕様書】木造壁量計算WEB「上善如水」プレミアムサポート＆ナレッジ昇格システム
**ドキュメントバージョン:** Ver 1.0 (2026-07)  
**対象サーバー:** 販売管理ポータル (`pr.eie.tokyo`) / アプリ連携 (`2025.eie.jp`)  
**開発対象:** プレミアムユーザー限定プライベートダッシュボード、質疑カード(チャット/ファイルスロット)、管理者ナレッジ昇格(匿名化)機能

---

## 1. システム概要・基本思想
本システムは、木造壁量計算WEBソフト「上善如水（じょうぜん みずのごとし）」のプレミアム版（サポート付）契約ユーザーを対象とした、**完全プライベートな1対1の質疑対応システム**と、そこから得られたノウハウを個人情報を保護して**公開ナレッジ掲示板へと安全に転用する昇格システム**です。

### 1.1 大原則（セキュリティ＆アクセス制御）
1. **完全プライベートの確保:**  
   各プレミアムユーザーは独自のダッシュボードを所有し、質疑カード（スレッド）、チャットメッセージ、アップロードしたDXFデータ・計算JSONデータは、**「本人のみ」および「管理者（スタッフ）」しか閲覧・アクセスできない完全隔離空間（1対1）**とする。他ユーザーからの閲覧・推測アクセスはシステム層・URLルーター層で物理的に遮断する。
2. **個人情報・物件固有情報の徹底保護（スクラブ・昇格ルール）:**  
   管理者が質疑カードをナレッジ（オープン掲示板）へ昇格させる際、原データ（DXF/JSON等）は**「一切引き継がない（自動除外）」**仕様とする。さらに、企業名、物件名、氏名、電話番号等を自動および手動で匿名化（マスキング）できる「昇格プレビュー＆スクラブ編集画面」を必ず経由させることで、コンプライアンスを100%遵守する。

---

## 2. データベース設計 (MySQL / MariaDB)

既存の `users`, `subscriptions` テーブルと連携する以下の3テーブルを新規構築すること。

### 2.1 `support_tickets`（質疑カードテーブル：プライベート）
| カラム名 | 型 | 制約 / 説明 |
| :--- | :--- | :--- |
| `ticket_id` | BIGINT | PRIMARY KEY, AUTO_INCREMENT |
| `user_id` | BIGINT | NOT NULL, INDEX, FK -> `users.id` (所有ユーザー) |
| `title` | VARCHAR(255) | NOT NULL (質疑タイトル) |
| `category` | VARCHAR(100) | NOT NULL (「斜め壁計算」「基礎計算」「DXF下地」「その他」) |
| `status` | ENUM | 'open'(未解決), 'in_progress'(対応中), 'resolved'(解決済), 'closed'(終了) |
| `dxf_file_path` | VARCHAR(500) | NULL (アップロードされたDXF下地ファイルパス) |
| `input_data_json` | LONGTEXT | NULL (計算アプリ側の検証用パラメータ JSON) |
| `zoom_url` | VARCHAR(500) | NULL (Zoomダイレクト接続用URL) |
| `is_promoted_to_faq` | TINYINT(1) | DEFAULT 0 (ナレッジ昇格済フラグ) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP |
| `updated_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |

### 2.2 `ticket_messages`（カード内チャットメッセージ：プライベート）
| カラム名 | 型 | 制約 / 説明 |
| :--- | :--- | :--- |
| `message_id` | BIGINT | PRIMARY KEY, AUTO_INCREMENT |
| `ticket_id` | BIGINT | NOT NULL, INDEX, FK -> `support_tickets.ticket_id` |
| `sender_type` | ENUM | 'user'(ユーザー本人), 'staff'(運営スタッフ) |
| `sender_id` | BIGINT | NOT NULL (送信者のID) |
| `message_text` | TEXT | NOT NULL (本文) |
| `attachment_pdf_path` | VARCHAR(500) | NULL (添削・書き込み済PDFファイルパス) |
| `youtube_url` | VARCHAR(500) | NULL (限定公開YouTube解説動画URL) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP |

### 2.3 `knowledge_posts`（ナレッジ掲示板テーブル：パブリック/ノーサポート向け）
| カラム名 | 型 | 制約 / 説明 |
| :--- | :--- | :--- |
| `post_id` | BIGINT | PRIMARY KEY, AUTO_INCREMENT |
| `source_ticket_id` | BIGINT | NULL (昇格元の質疑カードID / トレーサビリティ用) |
| `title` | VARCHAR(255) | NOT NULL (匿名化済タイトル) |
| `category` | VARCHAR(100) | NOT NULL (カテゴリータグ) |
| `content_md` | LONGTEXT | NOT NULL (匿名化・一般化された解説本文・Markdown) |
| `public_image_path` | VARCHAR(500) | NULL (個人情報マスキング済みの解説用画像) |
| `is_published` | TINYINT(1) | DEFAULT 1 (公開フラグ) |
| `views_count` | INT | DEFAULT 0 (閲覧数) |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP |

---

## 3. 機能要件・画面設計

### 3.1 ユーザー側：プライベートダッシュボード (`/my/support_dashboard.php`)
* **アクセス制御:** セッション中の `user_id` と契約ステータスが `premium` であることを検証。非プレミアムユーザーはアップグレード案内画面へリダイレクト。
* **質疑カード一覧:** 自身の `user_id` に紐づくカードのみを表示（他人のデータはSQLの `WHERE user_id = :session_user_id` で厳密に除外）。
* **新規カード作成＆ファイルスロット機能:**
  * **DXFデータスロット:** 意匠設計者の図面（.dxf）をドラッグ＆ドロップで保存。保存先は非公開ディレクトリ（`/private_uploads/dxf/{user_id}/`）とし、直接URLアクセスを禁止（ダウンロードは認証付きプロキシスクリプト経由のみ）。
  * **入力データスロット:** WEBツール（`2025.eie.jp`）の現在の計算状況（JSON）を自動または手動で添付するボタンを設置。
* **カード内チャット＆ビューア機能:**
  * チャットタイムライン形式でやり取りを表示。
  * 管理者からPDFが添付された場合、画面内でインラインプレビュー（PDF.js等）表示。
  * 管理者からYouTubeリンクが投稿された場合、自動で `<iframe>` 埋め込みプレイヤーを展開し、画面内で動画再生可能とする。
  * `zoom_url` が発行されている場合、上部に「🎥 サポートチームとZoom接続する」クイックボタンをハイライト表示。

### 3.2 管理者側：質疑管理＆対応ダッシュボード (`/admin/support_manager.php`)
* **全カード管理:** 全プレミアムユーザーの質疑カードをステータス・カテゴリー・ユーザー名で検索・ソート表示。
* **ワンクリック検証環境起動:**  
  カード内の「🛠 このデータで計算ソフトをデバッグ起動」ボタンを押すと、添付された `input_data_json` をパラメータとして `https://2025.eie.jp/debug_load.php` へ POST し、ユーザーの画面状態をスタッフのブラウザで瞬時に再現する。
* **リッチ返信アクション:**
  * テキスト入力、PDF添付（書き込み添削用）、YouTube URL入力フォーム。
  * 「🎥 Zoom招待リンク発行」ボタン（Zoom API連携または手動URL貼り付け）。

### 3.3 管理者側：ナレッジ昇格機能（個人情報保護＆スクラブワークフロー）
カードが解決（`status = 'resolved'`）した際、管理者がそのノウハウをナレッジ掲示板へ公開するための機能。

#### 【昇格ステップ・安全設計フロー】
1. **昇格トリガー:**  
   管理者画面の質疑カード右上の **「💡 ナレッジ掲示板へ昇格」** ボタンをクリック。
2. **セキュリティ除外（自動フィルタ）:**  
   システムは、添付されていた **`dxf_file_path` および `input_data_json` を昇格対象から完全に除外** する（ナレッジテーブルへの移行を禁止）。
3. **自動アノニマイズ（スクラブ前処理）:**  
   カード内のチャットメッセージ（ユーザー質問＋スタッフ回答）を統合・要約し、以下の正規表現・辞書マッチングにより個人情報を自動マスキングする。
   * 会社名（〇〇工務店、株式会社〇〇 等） ➔ `【A社／設計事務所様】`
   * 人名、電話番号、メールアドレス ➔ `【非公開】`
   * 特定の地名・物件名（〇〇邸、〇〇市〇〇プロジェクト 等） ➔ `【木造２階建・斜め壁モデル】`
4. **昇格プレビュー＆編集モーダル（手動確認・必須作業）:**  
   自動マスキング後のテキストがMarkdownエディタにロードされ、管理者が目視で最終調整を行う画面を表示。
   * **チェックボックス:** 「☑ 個人情報、物件名、固有図面データが一切含まれていないことを確認しました」という確認チェックを入れない限り「公開投稿」ボタンを押せない仕様とする。
5. **公開＆通知:**  
   `knowledge_posts` テーブルへ INSERT 実行。公開後、必要に応じてWEBソフト内の「お知らせ・掲示板」タブや、X（旧Twitter）連動バッチへ要約テキストを転送する。

---

## 4. API / エンドポイント仕様 (PHP/PDO)

エージェントが実装すべき主要エンドポイント一覧：

1. **`POST /api/support/create_ticket.php`**
   * 入力: `title`, `category`, `message`, `dxf_file`($_FILES), `input_json`
   * 処理: 認証チェック、ファイル保存（非公開領域）、DBレコード作成。
2. **`GET /api/support/get_ticket_detail.php?ticket_id={id}`**
   * 処理: `user_id` の一致を検証し、カード詳細とメッセージ一覧、添付ファイルアクセスプロキシURLをJSONで返す。
3. **`POST /api/admin/promote_to_knowledge.php`**
   * 入力: `source_ticket_id`, `title`, `category`, `content_md`, `admin_confirm_flag`
   * 処理: 管理者権限チェック、`admin_confirm_flag == true` の検証、`knowledge_posts` への登録、および `support_tickets.is_promoted_to_faq = 1` の更新。

---

## 5. エージェントへの開発指示（実装ステップ）

開発エージェント（Google Antigravity IDE）は、以下のステップ順に実装を進めてください。

* **Phase 1: DBマイグレーションとディレクトリ構築**
  * `setup_premium_support_tables.php` を作成し、上記3テーブルを構築。
  * DXF等保存用の非公開ディレクトリ `/storage/private_uploads/` の生成と `.htaccess`（`Deny from all`）の配置。
* **Phase 2: プライベートダッシュボード＆カードUIの実装**
  * ユーザー用 `/my/support_dashboard.php` および管理者用 `/admin/support_manager.php` のフロントエンド（HTML/CSS/JS/Ajax）およびバックエンドAPI実装。
  * YouTubeの `<iframe>` 変換処理、およびPDFプレビュー機能の組み込み。
* **Phase 3: ナレッジ昇格ワークフローと個人情報スクラブ機能の実装**
  * 昇格ボタンクリック時に起動する「アノニマイズ＆編集モーダル」のUI構築。
  * 個人情報保護チェックボックスのバリデーションと、`knowledge_posts` へのパブリッシュ処理の実装。