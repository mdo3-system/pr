# 【確定仕様書】J-ALG (上善如水 アライアンス・リードジェネレーター) FIXED_LOGIC.md

---

## 1. プロジェクト基本概要
* **システム名**: J-ALG (Jozen Alliance Lead Generator)
* **目的**: 実在する正規の建築士事務所・木造工務店の公開情報を国税庁API/都道府県リストから自動取得し、Google Search API (Serper) 及びスクレイパーで連絡先を補完、特電法に準拠した営業DMをSendGrid経由で安全に自動配信するリードジェネレーションシステム。
* **ドメイン情報**:
  * WEB管理画面 URL: `https://pr.eie.tokyo`
  * メール送信専用ドメイン: `jozen-info.com` (本番SaaSドメイン `jozen-calc.jp` と完全分離)

---

## 2. 確定データベース物理設計 (MySQL 8.0)

### 2.1 `companies` テーブル
```sql
CREATE TABLE IF NOT EXISTS `companies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `corporate_number` VARCHAR(13) NULL COMMENT '国税庁法人番号 (13桁)',
  `name` VARCHAR(255) NOT NULL COMMENT '正規法人名・事務所名',
  `clean_name` VARCHAR(255) NOT NULL COMMENT '検索用クリーン企業名',
  `prefecture` VARCHAR(32) NOT NULL COMMENT '都道府県',
  `city` VARCHAR(64) NOT NULL COMMENT '市区町村',
  `address` VARCHAR(255) NOT NULL COMMENT '以降の所在地',
  `postal_code` VARCHAR(10) NULL COMMENT '郵便番号',
  `category` ENUM('architect_office', 'constructor', 'builder', 'other') DEFAULT 'architect_office' COMMENT '業種種別',
  `official_url` VARCHAR(512) NULL COMMENT '公式サイトURL',
  `contact_url` VARCHAR(512) NULL COMMENT 'お問い合わせページURL',
  `email` VARCHAR(255) NULL COMMENT '抽出メールアドレス',
  `fax` VARCHAR(50) NULL COMMENT '抽出FAX番号',
  `phone` VARCHAR(50) NULL COMMENT '電話番号',
  `source_type` VARCHAR(50) DEFAULT 'nta_api' COMMENT 'データ出所',
  `status` ENUM('pending', 'crawled', 'approved', 'rejected', 'sent', 'failed') DEFAULT 'pending' COMMENT '処理ステータス',
  `is_opt_out` BOOLEAN DEFAULT FALSE COMMENT '特電法・拒否フラグ (TRUEで送信禁止)',
  `opt_out_reason` VARCHAR(255) NULL COMMENT '拒否理由',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_corporate_number` (`corporate_number`),
  INDEX `idx_prefecture_city` (`prefecture`, `city`),
  INDEX `idx_status_opt` (`status`, `is_opt_out`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 `crawl_logs` テーブル
```sql
CREATE TABLE IF NOT EXISTS `crawl_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `target_url` VARCHAR(512) NOT NULL COMMENT 'クロールしたURL',
  `http_status` SMALLINT NULL COMMENT 'HTTPステータスコード',
  `extracted_email` VARCHAR(255) NULL COMMENT '抽出メールアドレス',
  `extracted_fax` VARCHAR(50) NULL COMMENT '抽出FAX番号',
  `detected_optout_keywords` TEXT NULL COMMENT '検知したお断りキーワード',
  `raw_text_snippet` TEXT NULL COMMENT '証跡用スニペット',
  `crawled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  INDEX `idx_company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.3 `email_templates` テーブル
```sql
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `template_name` VARCHAR(100) NOT NULL COMMENT '管理用名称',
  `subject` VARCHAR(255) NOT NULL COMMENT '件名',
  `body_text` TEXT NOT NULL COMMENT 'プレーンテキスト本文',
  `body_html` TEXT NULL COMMENT 'HTML本文',
  `is_active` BOOLEAN DEFAULT TRUE COMMENT 'デフォルト有効フラグ',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.4 `send_logs` テーブル
```sql
CREATE TABLE IF NOT EXISTS `send_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `template_id` BIGINT UNSIGNED NOT NULL,
  `email_to` VARCHAR(255) NOT NULL,
  `sendgrid_message_id` VARCHAR(255) NULL COMMENT 'SendGrid Message-ID',
  `status` ENUM('queued', 'sending', 'delivered', 'opened', 'clicked', 'bounced', 'spam_report', 'error') DEFAULT 'queued',
  `error_message` TEXT NULL,
  `scheduled_at` DATETIME NOT NULL COMMENT '送信予定日時',
  `sent_at` DATETIME NULL COMMENT '実送信日時',
  `opened_at` DATETIME NULL COMMENT '初回開封日時',
  `clicked_at` DATETIME NULL COMMENT '初回クリック日時',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`template_id`) REFERENCES `email_templates`(`id`),
  INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
  INDEX `idx_sg_msg_id` (`sendgrid_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. 【聖域ロジック】特定電子メール法・拒否ワード自動判定エンジン

以下のキーワードがページ内テキストに含まれている場合、該当企業は `is_opt_out = 1`, `status = 'rejected'` とし、配信対象から絶対除外する。

```python
OPTOUT_KEYWORDS = [
    "営業お断り", "セールスお断り", "勧誘お断り", "営業メールはお断り",
    "営業のご案内はお控え", "セールス等はお断り", "営業等はご遠慮",
    "特定電子メール", "営業のご連絡はご遠慮", "セールスご遠慮",
    "売込みお断り", "売り込みお断り", "一切お断りしております",
    "営業目的のお問い合わせは", "営業メール等はご遠慮"
]
```

---

## 4. メール配信ルール（レピュテーション保護）
* **インターバル送信制御**: 送信キュー処理時は 1通あたり `sleep(rand(180, 480))` (3分〜8分のランダム待機) を挟み、スパム判定を防止する。
* **バウンス・スパム即時停止**: Webhook経由でバウンス/スパム報告を受信した場合、即時に `is_opt_out = 1` 及び `status = 'failed'` を更新。
