# 【システム総合設計書】建築業界向けターゲットリスト自動生成＆営業DM自動配信システム
**〜WEB版 壁量・N値・基礎計算プログラム「上善如水」アライアンス・リードジェネレーター (J-ALG) 〜**

---

## ドキュメント管理情報
* **プロジェクト名**: 上善如水 アライアンス・リードジェネレーター (Jozen Alliance Lead Generator: **J-ALG**)
* **ドキュメント目的**: Google Antigravity IDE エージェントへの完全引き継ぎ仕様書・実装設計書
* **アーキテクチャ基本方針**: 「案1（Serper API × 自作スクレイパー）」×「案4（国税庁法人番号API / 公的リスト起点）」融合モデル
* **メール送信エンジン**: SendGrid Web API v3（専用ドメイン・送信IP分離運用）
* **コンプライアンス要件**: 特定電子メール法（特電法）の完全遵守、本番インフラ（IP・ドメインレピュテーション）の絶対保護

---

## 1. プロジェクト概要とシステム要件

### 1.1 背景と目的
2025年建築基準法改正により審査業務が極限まで増大する中、Mac/VectorWorks/Archicadユーザーや小規模設計事務所・工務店向けに開発されたWEBクラウド構造計算SaaS「上善如水」の販売ルートを確立する。
本システムは、**「実在する正規の建築士事務所・木造住宅工務店」**の公開情報を国税庁および都道府県の公表リストから自動取得し、Google検索APIと自作スクレイパーで公式サイトおよびメールアドレス・FAX番号を補完した上で、特電法に準拠した営業DM（コールドメール）をSendGrid経由で安全に自動配信するリードジェネレーション・プラットフォームである。

### 1.2 コア・アーキテクチャ理念
1. **本番環境と営業DMインフラの完全分離（レピュテーション保護）**:
   - 本番のSaaS提供ドメイン（`jozen-calc.jp`等）および本番WEBサーバーのIPアドレスから営業DMを直接送信しない。
   - DM送信には専用ドメイン（例: `jozen-info.com`）を用い、送信インフラにはクラウドメール配信サービス **SendGrid API** を利用してSMTPリレー/API送信を行う。
2. **ノイズの徹底排除と到達精度100%の追求**:
   - 検索エンジンの曖昧なキーワード検索だけでリストを作らず、**「国税庁法人番号システム API」および公的な「建築士事務所登録簿」を基点**とし、確実に実在する事業者のみをターゲットとする。
3. **特定電子メール法（特電法）のプログラムによる準拠**:
   - クローリング時に公式サイト内の「営業メールお断り」「セールス禁止」等のキーワードを自動検知し、該当事業者を送信キューから自動除外（オプトアウトフラグを立上げる）するエンジンを標準搭載する。
4. **ヒューマンインザループ（人間による承認・精査）と自動化のバランス**:
   - 日々のデータ収集は夜間バッチで完全自動化しつつ、WEB上の「管理ダッシュボード」から目視確認・手動承認（Approve）を行ったリストのみを配信キューへ流せる安全設計とする。

---

## 2. システム全体構成とデータフロー

本システムは、お手持ちのWEBサーバー上で動作するアプリケーション（PHP/Python + MySQL）と、3つの外部クラウドAPI（国税庁API、Serper API、SendGrid API）によって構成される。

```
+-----------------------------------------------------------------------------------+
|                        自社WEBサーバー (Linux / Nginx / PHP / Python)             |
|                                                                                   |
|  +-----------------------+      +---------------------+      +-----------------+  |
|  |   夜間バッチ (Cron)   | ---> |  データ抽出エンジン | ---> |   MySQL 8.0     |  |
|  +-----------------------+      +---------------------+      +-----------------+  |
|                                            |                          |           |
|                                            v                          v           |
|                                 +---------------------+      +-----------------+  |
|                                 | クローリングエンジン| ---> | 特電法除外判定  |  |
|                                 +---------------------+      +-----------------+  |
|                                                                       |           |
|  +-----------------------+                                            v           |
|  |  WEB管理ダッシュボード| <---------------------------------- [送信キュー管理]   |
|  |  (承認/テンプレート等)|                                            |           |
|  +-----------------------+                                            v           |
|                                                              +-----------------+  |
|                                                              |  日中送信バッチ |  |
|                                                              +-----------------+  |
+-----------------------------------------------------------------------|-----------+
                                                                        |
            +-----------------------------------+-----------------------+
            | (HTTP REST API)                   | (HTTPS / JSON API)    | (HTTPS Web API v3)
            v                                   v                       v
+-----------------------+           +-----------------------+   +-------------------+
|  国税庁 法人番号API   |           |      Serper API       |   | SendGrid Web API  |
|  (実在事業者リスト)   |           |  (Google検索結果取得) |   | (DM自動分散配信)  |
+-----------------------+           +-----------------------+   +-------------------+
                                                                        |
                                                                        v
                                                            +-----------------------+
                                                            | ターゲット事業者      |
                                                            | (設計事務所・工務店)  |
                                                            +-----------------------+
```

### 2.1 3段階のデータ処理フェーズ
* **Phase 1: 収集フェーズ（01:00〜03:00 実行）**
  - 国税庁法人番号API（または都道府県別の建築士事務所登録データ）から「埼玉・東京・関東圏」の「建築設計業」「木造工務店」を抽出し、DB（`companies`テーブル）へ登録。
* **Phase 2: 補完・スクレイピングフェーズ（03:00〜06:00 実行）**
  - 未調査の事業者を対象にSerper APIで検索を実行（`"{法人名}" "{市区町村}"`）。
  - ヒットした公式サイトURLのHTMLを取得し、メールアドレス、FAX番号を正規表現で抽出。
  - 同時に「営業お断り」ワードをスキャンし、該当すれば `is_opt_out = TRUE` に設定。
* **Phase 3: 送信・追跡フェーズ（平日 10:00〜16:00 実行）**
  - WEB管理ダッシュボードでステータスが「承認（Approved）」かつ未送信のレコードをキューから取得。
  - SendGrid Web APIを介して、ランダムインターバル（180秒〜480秒間隔）で分散配信。
  - SendGrid Webhook経由で開封・クリック・バウンスステータスをリアルタイムDB反映。

---

## 3. 推奨技術スタック＆サーバー環境要件

Google Antigravity IDE エージェントは以下の技術スタックを基準としてコード実装・環境構築を進めること。

| レイヤー | 技術コンポーネント | 採用理由 / 仕様要件 |
| :--- | :--- | :--- |
| **OS / Server** | Linux (Ubuntu 24.04 LTS / Debian 12) | 既存WEBサーバーホスティング環境（VPS等） |
| **WEB Server** | Nginx + PHP-FPM (8.2以上) | 高速・軽量なリクエスト処理、Webhook受信エッジ |
| **Web Framework** | PHP: **Laravel 11** (または Vanilla PHP + FastRoute) | CRUDダッシュボード、ORM(Eloquent)、Job Queue構築の最適解 |
| **Scraper Engine** | Python 3.11+ (`requests`, `BeautifulSoup4`, `pydantic`, `lxml`) | 堅牢で高速なHTMLパース、正規表現処理、テキスト分析 |
| **Database** | MySQL 8.0+ / MariaDB 10.6+ | トランザクション、JSONカラム対応、インデックス最適化 |
| **Task Queue** | Redis + Laravel Horizon (または DB Queue + Cron/Systemd) | 非同期クローリングおよびインターバルメール送信の管理 |
| **Mail API** | **SendGrid Web API v3** (`sendgrid/sendgrid` PHP SDK) | SMTPの通信ブロック回避、高い到達率、Webhookによるトラッキング |
| **Search API** | **Serper API** (`https://google.serper.dev/search`) | 1クエリ約0.0004ドルの圧倒的低コストでGoogle検索上位を取得 |

---

## 4. データベース物理設計（DDL・テーブル定義）

Google Antigravity IDE は、以下の仕様に従ってマイグレーションファイル（または DDL SQL）を生成すること。

### 4.1 `companies` テーブル（企業マスター・ベースデータ）
国税庁APIおよびリストから取り込んだ実在企業の基本情報を一元管理する。

```sql
CREATE TABLE `companies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `corporate_number` VARCHAR(13) NULL COMMENT '国税庁法人番号 (13桁)',
  `name` VARCHAR(255) NOT NULL COMMENT '正規法人名・事務所名',
  `clean_name` VARCHAR(255) NOT NULL COMMENT '検索用クリーン企業名 (株式会社/有限会社等を除外)',
  `prefecture` VARCHAR(32) NOT NULL COMMENT '都道府県 (例: 埼玉県)',
  `city` VARCHAR(64) NOT NULL COMMENT '市区町村 (例: 川越市)',
  `address` VARCHAR(255) NOT NULL COMMENT '以降の所在地',
  `postal_code` VARCHAR(10) NULL COMMENT '郵便番号',
  `category` ENUM('architect_office', 'constructor', 'builder', 'other') DEFAULT 'architect_office' COMMENT '業種種別',
  `official_url` VARCHAR(512) NULL COMMENT '公式サイトURL (Serperで特定)',
  `contact_url` VARCHAR(512) NULL COMMENT 'お問い合わせページURL',
  `email` VARCHAR(255) NULL COMMENT '抽出メールアドレス',
  `fax` VARCHAR(50) NULL COMMENT '抽出FAX番号',
  `phone` VARCHAR(50) NULL COMMENT '電話番号',
  `source_type` VARCHAR(50) DEFAULT 'nta_api' COMMENT 'データ出所 (nta_api, register_list, manual)',
  `status` ENUM('pending', 'crawled', 'approved', 'rejected', 'sent', 'failed') DEFAULT 'pending' COMMENT '処理ステータス',
  `is_opt_out` BOOLEAN DEFAULT FALSE COMMENT '特電法・拒否フラグ (TRUEで送信禁止)',
  `opt_out_reason` VARCHAR(255) NULL COMMENT '拒否理由 (例: keyword_detected, bounced, manual_exclude)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_corporate_number` (`corporate_number`),
  INDEX `idx_prefecture_city` (`prefecture`, `city`),
  INDEX `idx_status_opt` (`status`, `is_opt_out`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 `crawl_logs` テーブル（クローリング実行＆判定ログ）
WEBサイトのスクレイピング履歴と、特電法ワード検知の詳細ログを記録する。

```sql
CREATE TABLE `crawl_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `target_url` VARCHAR(512) NOT NULL COMMENT '実際にクロールしたURL',
  `http_status` SMALLINT NULL COMMENT 'HTTPステータスコード (200, 404等)',
  `extracted_email` VARCHAR(255) NULL COMMENT '抽出したメールアドレス',
  `extracted_fax` VARCHAR(50) NULL COMMENT '抽出したFAX番号',
  `detected_optout_keywords` TEXT NULL COMMENT '検知したお断りキーワード (JSON等で保持)',
  `raw_text_snippet` TEXT NULL COMMENT '検知周辺のテキストスニペット (証跡用)',
  `crawled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  INDEX `idx_company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.3 `email_templates` テーブル（営業DMテンプレート）
件名・本文のテンプレートとパーソナライズ変数を管理する。

```sql
CREATE TABLE `email_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `template_name` VARCHAR(100) NOT NULL COMMENT '管理用名称 (例: 週額スポット訴求Ver1)',
  `subject` VARCHAR(255) NOT NULL COMMENT 'メール件名 (変数パース対応)',
  `body_text` TEXT NOT NULL COMMENT 'プレーンテキスト本文 ({{name}}等のタグ対応)',
  `body_html` TEXT NULL COMMENT 'HTML本文 (ボタンや見付図画像リンク内包)',
  `is_active` BOOLEAN DEFAULT TRUE COMMENT 'デフォルト利用フラグ',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.4 `send_logs` テーブル（配信キュー＆トラッキング履歴）
SendGridへの送信履歴、Message ID、開封・バウンスステータスを管理する。

```sql
CREATE TABLE `send_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `template_id` BIGINT UNSIGNED NOT NULL,
  `email_to` VARCHAR(255) NOT NULL,
  `sendgrid_message_id` VARCHAR(255) NULL COMMENT 'SendGridのX-Message-Id',
  `status` ENUM('queued', 'sending', 'delivered', 'opened', 'clicked', 'bounced', 'spam_report', 'error') DEFAULT 'queued',
  `error_message` TEXT NULL,
  `scheduled_at` DATETIME NOT NULL COMMENT '送信予定日時',
  `sent_at` DATETIME NULL COMMENT '実送信日時',
  `opened_at` DATETIME NULL COMMENT '初回開封日時 (Webhook受信)',
  `clicked_at` DATETIME NULL COMMENT '初回URLクリック日時 (Webhook受信)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`template_id`) REFERENCES `email_templates`(`id`),
  INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
  INDEX `idx_sg_msg_id` (`sendgrid_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. 外部API連携仕様とコアロジック

### 5.1 国税庁 法人番号システム Web-API 連携仕様
* **目的**: 正規建築関連企業の母集団を信頼できる政府公認データから作成する。
* **エンドポイント**: `https://api.houjin-bangou.nta.go.jp/4/num`
* **認証**: アプリケーションID（無料登録で取得可能・パラメータ `id` に付与）。
* **リクエストパラメータ構造**:
  - `type`: `12` (Unicode XML または CSV/JSON形式を取得)
  - `address`: 都道府県コード（例: 埼玉県 = `11`、東京都 = `13`）＋ 市区町村コード
  - `kind`: `01` (国の機関を除く法人等)
* **実装要件 (Antigravity IDE への指示)**:
  1. レスポンスから「名称（`name`）」と「所在地（`prefecture`, `city`, `address`）」をパース。
  2. 法人名から「株式会社」「有限会社」「合同会社」を除去した文字列を `clean_name` として保存。
  3. 初回は「埼玉県全域」「東京都多摩地域」等のターゲットエリアをパラメータ指定して初期バルクインポートを実行するコマンドを作成せよ。

### 5.2 Serper API (Google Search Results API) 連携仕様
* **目的**: 企業名と所在地から「公式WEBサイトのURL」を1位で特定する。
* **エンドポイント**: `https://google.serper.dev/search` (POST HTTP request)
* **ヘッダー**: `X-API-KEY: {SERPER_API_KEY}`, `Content-Type: application/json`
* **クエリビルダ・アルゴリズム**:
  ```json
  {
    "q": "株式会社{clean_name} {city} (設計事務所 OR 工務店 OR 建築)",
    "gl": "jp",
    "hl": "ja",
    "num": 3
  }
  ```
* **URL判定＆クリーンアップ仕様**:
  1. 検索結果 `organic` 配列の1位（`organic[0].link`）を抽出。
  2. 除外ドメインフィルタリング: ヒットしたURLが以下のドメインを含む場合は公式URLとみなさず除外する。
     - `suumo.jp`, `homes.co.jp`, `itp.ne.jp` (タウンページ), `ekiten.jp`, `houjin.jp`, `facebook.com`, `instagram.com`, `twitter.com`, `x.com`, `bing.com`, `yahoo.co.jp`
  3. 有効なドメインであれば `companies.official_url` に保存し、ステータスを `crawled` 対象へ変更する。

### 5.3 SendGrid Web API v3 連携＆リレー配信仕様
* **エンドポイント**: `https://api.sendgrid.com/v3/mail/send`
* **認証**: `Authorization: Bearer {SENDGRID_API_KEY}`
* **メールヘッダー＆ドメイン設計（最重要・レピュテーション保護）**:
  - `From Address`: `info@jozen-info.com`（※DM専用に新規取得するドメイン。本番ドメイン `jozen-calc.jp` は絶対に使わないこと）
  - `From Name`: `WEB構造計算 上善如水 - 壁量・N値・基礎計算ツール`
  - `Reply-To`: ご自身の実際の実務用または確認用メールアドレス
* **ペイロード構造例 (JSON)**:
  ```json
  {
    "personalizations": [
      {
        "to": [{"email": "target-architect@example.com", "name": "株式会社川越設計工房 様"}],
        "substitutions": {
          "{{company_name}}": "株式会社川越設計工房",
          "{{city}}": "川越市",
          "{{sender_name}}": "上善如水 (IT技術エバンジェリスト)"
        },
        "custom_args": {
          "company_id": "1042",
          "campaign": "saitama_architect_summer_2026"
        }
      }
    ],
    "from": {"email": "info@jozen-info.com", "name": "WEB構造計算 上善如水"},
    "subject": "【Mac対応/斜め壁計算】WEBブラウザだけで動く木造壁量・N値計算ツールのご提案",
    "content": [
      {
        "type": "text/plain",
        "value": "{パーソナライズされたテキスト本文...}"
      },
      {
        "type": "text/html",
        "value": "{見付図画像やデモURLリンクを含むHTML本文...}"
      }
    ],
    "tracking_settings": {
      "click_tracking": {"enable": true, "enable_text": false},
      "open_tracking": {"enable": true}
    }
  }
  ```
* **インターバル送信制御（人間らしさの演出）**:
  - バッチスクリプト内で `send_logs` の `queued` レコードを1件処理するごとに、`sleep(rand(180, 480))` を実行する。
  - 3分〜8分間のランダムウェイトを挟むことで、1時間あたり約8〜15通の極めて自然なペースで分散送信され、スパムフィルター検知リスクを大幅に低下させる。

---

## 6. クローリング＆情報抽出エンジン設計（特電法準拠ロジック）

Google Antigravity IDE は、Python で分離したマイクロサービスまたは CLI スクリプトとして以下のクローラーエンジン（`scraper_engine.py`）を実装すること。

### 6.1 クローリング対象ページの絞り込み
サーバー負荷削減と処理速度向上のため、1企業あたり最大**3URL**までしかクロールしない。
1. トップページ (`official_url`)
2. 「会社概要」「企業情報」リンク ( `<a href="*about*">`, `<a href="*company*">`, `<a href="*gaiyo*">` を正規表現検知)
3. 「お問い合わせ」「お問合せ」リンク ( `<a href="*contact*">`, `<a href="*inquiry*">`, `<a href="*toiawase*">` を正規表現検知)

### 6.2 メールアドレスおよびFAX番号の抽出ロジック（Regex）
```python
import re
from bs4 import BeautifulSoup

# メールアドレス抽出正規表現 (画像の難読化やダミー文字を除外)
EMAIL_REGEX = r'[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+'

# FAX番号抽出正規表現
FAX_REGEX = r'(?:FAX|Fax|fax|ファックス|ﾌｧｯｸｽ)[\s:：]*([0-9]{2,4}[-\s][0-9]{2,4}[-\s][0-9]{3,4})'

def extract_contact_info(html_text: str):
    emails = set(re.findall(EMAIL_REGEX, html_text))
    clean_emails = [e for e in emails if not any(x in e.lower() for x in ['wix.com', 'sentry.io', 'example.com', 'xxx@', 'domain.com', 'jpg', 'png', 'css'])]
    
    faxes = re.findall(FAX_REGEX, html_text)
    clean_fax = faxes[0] if faxes else None
    
    return (clean_emails[0] if clean_emails else None), clean_fax
```

### 6.3 【最重要】特電法対策：営業お断りキーワード検知エンジン
特定電子メール法に基づき、営業メール受信を拒否する旨が表示されているページから抽出したアドレスへの送信は違法となる。以下のロジックで自動検知・排除を行う。

```python
# お断りキーワード辞書
OPTOUT_KEYWORDS = [
    "営業お断り", "セールスお断り", "勧誘お断り", "営業メールはお断り",
    "営業のご案内はお控え", "セールス等はお断り", "営業等はご遠慮",
    "特定電子メール", "営業のご連絡はご遠慮", "セールスご遠慮",
    "売込みお断り", "売り込みお断り", "一切お断りしております",
    "営業目的のお問い合わせは", "営業メール等はご遠慮"
]

def check_opt_out_compliance(html_text: str) -> tuple[bool, list[str], str]:
    soup = BeautifulSoup(html_text, 'lxml')
    for script in soup(["script", "style"]):
        script.decompose()
    text = soup.get_text(separator=' ')
    
    detected = []
    snippet = ""
    for kw in OPTOUT_KEYWORDS:
        if kw in text:
            detected.append(kw)
            idx = text.find(kw)
            start = max(0, idx - 30)
            end = min(len(text), idx + len(kw) + 30)
            snippet = text[start:end].strip()
            break
            
    return (len(detected) > 0), detected, snippet
```
**【実装指示】**: クローラースクリプトは、この関数で `True` が返された場合、即座に MySQL の `companies` テーブルを以下のように更新すること。
`UPDATE companies SET is_opt_out = 1, opt_out_reason = 'keyword_detected', status = 'rejected' WHERE id = ?;`

---

## 7. WEB管理ダッシュボード機能要件

日々のリード精査と配信管理をブラウザ上で快適に行うための管理画面（Laravel Blade/TailwindCSS または Vue/React によるSPA）を実装する。

### 7.1 画面遷移・ナビゲーション構成
1. **ダッシュボードTOP (`/admin/dashboard`)**
   - KPI指標メーター: 「本日収集件数」「有効メール取得数」「送信待ちキュー件数」「本日の送信完了数」「バウンス・エラー件数」「特電法自動除外件数」をリアルタイムグラフ表示。
2. **リード管理・精査画面 (`/admin/companies`) — ★最重要オペレーション画面**
   - フィルター機能: 都道府県、市区町村、業種、ステータス（`pending`, `crawled`, `approved`, `rejected`）、メールアドレス有無で絞り込み。
   - **ハイライト表示**: `is_opt_out = 1` の行は赤背景で警告表示し、検知されたスニペットテキストをポップアップ確認できるようにする。
   - **ワンクリック承認/却下ボタン**: 目視確認し、「承認 (Approve)」を押すとステータスが `approved` になり配信キューへ投入準備完了となる。「却下 (Reject)」で送信対象外へ。
   - **一括承認アクション**: 「埼玉県川越市のメールアドレス有、かつ `is_opt_out=0` の企業を一括で Approved に変更する」バルクアクションボタン。
3. **DMテンプレート管理 (`/admin/templates`)**
   - 週額プラン用、月額プラン用、Macユーザー特化用など複数のテンプレートを作成・編集するエディタ。
   - `{{name}}`（法人名）、`{{city}}`（市区町村名）の変数が正しく変換されるか確認できるプレビューモーダル。
4. **配信キュー＆ログモニター (`/admin/logs`)**
   - 現在キューに入っている送信予定リストのタイムライン表示。
   - SendGrid Webhookから送られてきた「開封済（Opened / 緑バー）」「クリック済（Clicked / 金色バー）」「バウンス（Bounced / 赤バー）」のリアルタイム追跡状況一覧。

---

## 8. バッチ処理（Cronジョブ）自動スケジュール設計

サーバー上の `/etc/crontab` または Laravel スケジューラー (`php artisan schedule:run`) により、以下のスケジュールで無人自動運用させる。

```bash
# =====================================================================
# 上善如水 J-ALG 自動運用スケジューラー (タイムゾーン: Asia/Tokyo)
# =====================================================================

# [1] 毎日 深夜 01:00 : 国税庁APIまたは新規登録データからのターゲット企業インポート
0 1 * * * www-data /usr/bin/php /var/www/jozen-alg/artisan alg:import-companies >> /var/log/jozen/import.log 2>&1

# [2] 毎日 深夜 03:00 : Serper APIによる公式サイト検索＆クローリング・メール抽出・特電法チェック
0 3 * * * www-data /usr/bin/python3 /var/www/jozen-alg/crawler/run_scraper.py --limit 200 >> /var/log/jozen/crawler.log 2>&1

# [3] 平日(月-金) 朝 09:30 : 送信予定キューの生成 ( Approved ステータスの企業から1日上限50件を抽出してキュー作成)
30 9 * * 1-5 www-data /usr/bin/php /var/www/jozen-alg/artisan alg:generate-queue --daily-limit 50 >> /var/log/jozen/queue.log 2>&1

# [4] 平日(月-金) 日中 10:00〜16:00 : インターバル自動分散メール送信 (5分毎に起動し、キュー内に予定時間到来したメールがあれば1件送信してランダムsleep)
*/5 10-16 * * 1-5 www-data /usr/bin/php /var/www/jozen-alg/artisan alg:process-send-queue >> /var/log/jozen/send.log 2>&1
```

---

## 9. IP・ドメインレピュテーション保護＆セキュリティ防御指針

### 9.1 DNS認証レコード設計（必須設定要件）
DM専用ドメイン（例: `jozen-info.com`）の DNS レコードに対して、SendGrid経由のメールが高確率でインボックス（受信箱）へ届くよう以下のレコードを設定すること。

1. **SPFレコード (TXT)**:
   `v=spf1 include:u1234567.wl.sendgrid.net ~all`（※SendGrid画面の指示値を使用）
2. **DKIMレコード (CNAME)**:
   `s1._domainkey.jozen-info.com` -> `s1.domainkey.u1234567.wl.sendgrid.net`
3. **DMARCレコード (TXT) — 初回導入時**:
   `_dmarc.jozen-info.com` -> `v=DMARC1; p=none; rua=mailto:dmarc-report@jozen-info.com; ruf=mailto:dmarc-report@jozen-info.com; fo=1;`

### 9.2 バウンス＆スパム報告の自動サプレッション管理
* SendGrid の **Event Webhook** を自社WEBサーバー (`https://admin.jozen-calc.jp/api/webhooks/sendgrid`) で受信するルートを構築すること。
* Webhook で `event = bounce` または `event = spamreport` が飛んできた場合、当該アドレスおよび企業レコードを即時に以下のように処理する。
  ```sql
  UPDATE companies SET status = 'failed', is_opt_out = 1, opt_out_reason = 'bounced_or_spam' WHERE email = :email;
  ```
* これにより、一度跳ね返ったアドレスやスパム報告した業者への再送信（最大のブラックリスト入り要因）を100%遮断する。

---

## 10. Google Antigravity IDE エージェント 実装ロードマップ（指示書）

エージェントは以下のStep順に開発を進め、各Step完了ごとに単体テストを実行して成果を報告すること。

* **Step 1: 環境構築とマイグレーション**
  - Laravel 11 / PHP 8.2+ / Python 3.11+ 環境の初期化と `.env` 設定（APIキー格納用変数: `SENDGRID_API_KEY`, `SERPER_API_KEY`, `NTA_API_ID` の定義）。
  - 本設計書第4章の DDL をベースとしたマイグレーションファイルおよび Eloquent モデルの作成。
* **Step 2: 外部APIラッパー＆クローラーエンジンの実装**
  - 国税庁APIのレスポンスパーサーとインポートコマンドの実装。
  - Serper API呼び出しおよび BeautifulSoup + Regex によるメール/FAX/特電法ワード抽出 Python スクリプトの実装。
* **Step 3: SendGrid送信エンジン＆インターバル制御キューの実装**
  - SendGrid SDK を利用したメール送信サービスクラスおよび、ランダムインターバル（180s〜480s）を制御するコマンド (`alg:process-send-queue`) の開発。
  - SendGrid Event Webhook 受信コントローラーとステータス自動反映ロジックの実装。
* **Step 4: WEB管理ダッシュボード（CRUD & UI）の構築**
  - Blade/TailwindCSS または Vue.js を用いた管理画面のUI開発（TOPメーター、企業一覧承認フィルター、テンプレート編集画面）。
* **Step 5: 統合テストとバッチスケジュール組み込み**
  - スクラッチでのターゲットエリアを想定したテストラン実行（ご自身のアドレスへのテスト配信確認）。
  - Cron スケジュールの本番サーバーへの登録と動作確認。

---
*End of Document / Upper-Right Watermark: Jozen Alliance Lead Generator J-ALG Architecture*
