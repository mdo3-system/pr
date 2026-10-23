# 【セッション引き継ぎ書】J-ALG BtoB営業リード獲得・配信システム
**最終更新日:** 2026年10月23日  
**現在のバージョン:** `v1.0.9` ([Version.js](file:///d:/Dropbox/■設計ｻﾎﾟｰﾄ/■note/antigravity/pr/Version.js))  
**本番Webサーバー:** `https://pr.eie.tokyo` (XServer: `eie.tokyo/public_html/pr`)  
**Gitリポジトリ:** `git@github.com:mdo3-system/pr.git` (ブランチ: `main`)

---

## 1. 直前の対応完了事項（本日完了したこと）

1. **国税庁 Web-API 連携・疎通テスト（完全成功）**:
   - アプリケーションID: `Kk8QGG6E7tVRM`
   - `.env`（ローカルおよびXServer本番）に `NTA_API_ID=Kk8QGG6E7tVRM` を反映完了。
   - 法人番号公表API（XML形式）およびインボイス公表API（JSON形式）双方のHTTP 200疎通・パースを確認。
2. **地域抽出の重複防止＆自動巡回進捗管理（実装・デプロイ完了）**:
   - `nta_extract_progress` テーブルを本番DB（`mdo3_pr`）に新設。
   - 埼玉県・東京都・千葉県・神奈川県等の地域進捗管理を実装。
   - 全件取得完了地域には自動的に **「180日間の再抽出ロック」** を適用し、次回巡回時に重複抽出せず自動で次の地域へ進むラウンドロビン方式を実装・本番テスト完了。
3. **同一企業への短期間再送防止（90日間クールダウン）＆送信回数カウント（実装・デプロイ完了）**:
   - `companies` テーブルに `send_count`（送信回数）、`last_sent_at`（最終送信日）、`opt_out_token`（配信停止トークン）を追加。
   - [src/QueueManager.php](file:///d:/Dropbox/■設計ｻﾎﾟｰﾄ/■note/antigravity/pr/src/QueueManager.php) にて、`send_count < 3` かつ `last_sent_at` から90日以上経過した企業のみを抽出。同一メールアドレス宛ての過去90日以内の重複送信も完全排除。
   - 本番自動テスト（`test_queue_cooldown.php`）で全パターンPASS確認済み。
4. **特定電子メール法（特電法）ワンクリック配信停止機能**:
   - [api/optout.php](file:///d:/Dropbox/■設計ｻﾎﾟｰﾄ/■note/antigravity/pr/api/optout.php) を実装・本番公開（HTTP 200）。メール本文末尾に固有トークン付きURLを自動付与。
5. **営業専用ドメイン・送信元設定**:
   - 送信専用ドメイン: `eie.tokyo`
   - 送信元アドレス: `pr@eie.tokyo`
   - 送信者名: `木造壁量計算WEB 上善如水`
6. **A4印刷・PDF保存用マニュアル作成**:
   - [j_alg_operation_manual_a4.html](file:///d:/Dropbox/■設計ｻﾎﾟｰﾄ/■note/antigravity/pr/j_alg_operation_manual_a4.html) を作成（ブラウザでCtrl+PからPDF保存可能）。

---

## 2. 現在保留中の事項（待ちステータス）

* **SendGrid 新規アカウント登録 & APIキー発行待ち**:
  - ユーザー様側で `pr@eie.tokyo` を用いてSendGridアカウントの新規登録作業中（サーバー側メールアカウント準備完了後に実施予定）。
  - 発行された新規 `SENDGRID_API_KEY` を `.env` に設定予定。

---

## 3. 次回復帰時の初手アクション（再開手順）

戻られた際は、チャットで以下のようにメッセージいただければ即座に再開可能です：

> **「SendGridのAPIキーが用意できたので設定してください: SG.xxxxxx...」**  
> または  
> **「再開します。現在の状況を確認してください。」**

### 再開後のAIエージェントの作業手順:
1. `HANDOVER_STATUS.md` および `FIXED_LOGIC.md` を読み込み、最新ステータスを確認。
2. SendGridの新しいAPIキーを `.env`（ローカル & XServer本番）へ設定。
3. 送信テストスクリプトを実行し、`pr@eie.tokyo` からのテストメール送信とWebhookの疎通確認。
4. 定期実行（Cron）の自動巡回バッチ（地域抽出 ➔ クローラー ➔ 日中分散配信）のスケジュール確認・本番無人稼働の開始。
