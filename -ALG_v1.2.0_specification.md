# J-ALG v1.2.0 拡張仕様書：SaaS課金・ユーザー管理・販売管理ポータル統合プラットフォーム

## 1. 概要・システム全体像
本仕様書は、WEB構造計算プログラム「上善如水」アプリ側（`https://2025.eie.jp`）と、販売管理ポータル側（`https://pr.eie.tokyo` / J-ALG基盤）をセキュアに統合し、Stripe決済（クレジットカード・バーチャル口座銀行振込自動消込）、ユーザーアクセス制御（ペイウォール）、売上分析、および社内アカウント管理を一元化するための実装・SQLマイグレーション定義書である。

システム間の同期の堅牢性を担保するため、【方式B：Webhookリアルタイム通知】と【方式A：最新状態照会API】を組み合わせたハイブリッド構成を必須要件とする。

---

## 2. 環境変数 (.env) 構成
双方向のサーバーにおいて、通信セキュリティと決済連携を維持するために以下の環境変数を追加・設定すること。

```text
# --- Stripe Payment Integration (2025.eie.jp側で主に利用) ---
STRIPE_PUBLISHABLE_KEY="pk_live_xxxxxxxxxxxxxxxxxxxx"
STRIPE_SECRET_KEY="sk_live_xxxxxxxxxxxxxxxxxxxx"
STRIPE_WEBHOOK_SECRET="whsec_xxxxxxxxxxxxxxxxxxxx"

# --- Stripe Price IDs ---
STRIPE_PRICE_SPOT="price_xxxxxx_spot"        # スポット利用
STRIPE_PRICE_MONTHLY="price_xxxxxx_monthly"  # 月額スタンダードプラン
STRIPE_PRICE_YEARLY="price_xxxxxx_yearly"    # 年額プラン

# --- サーバー間通信セキュアトークン (両サーバー共通) ---
PORTAL_SYNC_SECRET="your_high_entropy_random_secret_string_here"
APP_SERVER_URL="[https://2025.eie.jp](https://2025.eie.jp)"
PORTAL_SERVER_URL="[https://pr.eie.tokyo](https://pr.eie.tokyo)"


3. データベース物理設計（SQLマイグレーション DDL）
販売管理ポータル側（pr.eie.tokyo）およびアプリ側データベースにおいて、以下のテーブルを作成・更新するマイグレーションを実行すること。

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL COMMENT 'J-ALGのリード経由の場合、companies(id)と関連付け',
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    user_name VARCHAR(50) NOT NULL,
    role ENUM('general', 'internal_staff', 'admin') NOT NULL DEFAULT 'general' COMMENT 'internal_staffは無償アカウント',
    stripe_customer_id VARCHAR(100) NULL UNIQUE COMMENT 'Stripe側の顧客ID (cus_xxx)',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

3.2 subscriptions（契約・有効期限・ステータス管理）
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    stripe_subscription_id VARCHAR(100) NULL UNIQUE COMMENT 'sub_xxx (銀行振込単発・無償アカウント等はNULL)',
    plan_type ENUM('spot', 'monthly', 'yearly', 'free_permanent', 'free_trial') NOT NULL DEFAULT 'free_trial',
    payment_method ENUM('credit_card', 'bank_transfer', 'none') NOT NULL DEFAULT 'none',
    status ENUM('active', 'past_due', 'canceled', 'pending_transfer') NOT NULL DEFAULT 'pending_transfer',
    current_period_start DATETIME NOT NULL,
    current_period_end DATETIME NOT NULL COMMENT 'free_permanentの場合は 2099-12-31 23:59:59 等を設定',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_period_end (current_period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

3.3 payment_logs（売上・決済履歴ログ）
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    stripe_invoice_id VARCHAR(100) NULL COMMENT 'in_xxx',
    amount DECIMAL(10, 2) NOT NULL COMMENT '税込決済金額',
    tax_amount DECIMAL(10, 2) NOT NULL COMMENT '内消費税額',
    currency VARCHAR(10) DEFAULT 'jpy',
    payment_method ENUM('credit_card', 'bank_transfer', 'manual', 'internal') NOT NULL,
    status ENUM('succeeded', 'failed', 'refunded', 'pending') NOT NULL,
    paid_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_paid_at (paid_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

3.4 api_sync_logs（サーバー間同期エラー・監査ログ）
CREATE TABLE IF NOT EXISTS api_sync_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sync_type ENUM('webhook_push', 'api_pull', 'manual_sync') NOT NULL,
    target_user_id INT UNSIGNED NULL,
    request_payload JSON NULL,
    response_code INT NOT NULL,
    error_message TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_response_code (response_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

4. サーバー間ハイブリッド連携仕様
4.1 方式B：リアルタイムWebhook転送（2025.eie.jp → pr.eie.tokyo）
Stripeから決済成功（invoice.payment_succeeded）、振込完了、または解約通知をアプリ側が受信した際、DBを更新すると同時に、販売管理ポータル側へ即時同期POSTリクエストを送信する。

送信先エンドポイント：https://pr.eie.tokyo/api/internal/sync_subscription.php

HTTPヘッダー：

Content-Type: application/json

X-Portal-Sync-Token: {PORTAL_SYNC_SECRET}

ペイロード例 (JSON)：
{
  "event_type": "payment_succeeded",
  "user_email": "client@example.com",
  "stripe_customer_id": "cus_xxxxxxxxxx",
  "plan_type": "monthly",
  "payment_method": "credit_card",
  "status": "active",
  "current_period_end": "2026-08-26 23:59:59",
  "amount": 5500,
  "paid_at": "2026-07-26 12:00:00"
}

リトライロジック：ポータル側からのレスポンスが HTTP 200 OK 以外（通信エラーや500エラー等）であった場合、最大3回まで指数バックオフ（1分後、5分後、15分後）で再試行し、失敗時は api_sync_logs に記録すること。

4.2 方式A：状態照会API（pr.eie.tokyo → 2025.eie.jp）
ネットワーク瞬断によるWebhook取りこぼしを防ぐため、販売管理ポータル側から任意のタイミング（または日次Cronバッチ）で契約状態を引き出すAPIを実装する。

提供エンドポイント：https://2025.eie.jp/api/internal/get_user_status.php

HTTPヘッダー：

X-Portal-Sync-Token: {PORTAL_SYNC_SECRET}

リクエストパラメータ (GET)：

email={target_email} または updated_since={YYYY-MM-DD HH:II:SS}（差分一括照会用）

レスポンス仕様 (JSON - 200 OK)：
{
  "status": "success",
  "data": [
    {
      "user_id": 102,
      "email": "client@example.com",
      "plan_type": "monthly",
      "status": "active",
      "current_period_end": "2026-08-26 23:59:59",
      "last_payment_date": "2026-07-26 12:00:00"
    }
  ]
}

自動修復バッチ：ポータル側は毎日深夜3時に updated_since を用いて前日分の変更を照会し、ポータル側のDBと不整合がある場合はポータル側のステータスを自動で上書き更新する。

5. アクセス制御（ペイウォール）ミドルウェア仕様
WEB構造計算ソフトの画面（/calc/* や計算実行API）へのアクセス時、以下の判定ロジックを必ず実行すること。

セッションまたはJWTから user_id を取得。

users.role == 'internal_staff' または users.role == 'admin' の場合、無条件でアクセスを許可（決済チェックを完全にスキップ）。

一般ユーザー（role == 'general'）の場合、subscriptions を照会し、以下の両方を満たす場合のみアクセスを許可（200 OK）：

status == 'active'

current_period_end >= NOW()

上記を満たさない場合（未契約・決済エラー・期限切れ・振込待ち等）、HTTP 403 Forbidden を返し、プラン選択・決済画面（またはStripe Customer Portal）へのリダイレクト指示を画面に表示する。

6. 社内スタッフ用無償アカウント一括登録スクリプト
既存の社内スタッフ3名が課金バイパスで永久利用できるよう、CLIまたは1回限りのWEB叩きスクリプト（scripts/register_internal_staff.php）を作成し実行する。

6.1 要件定義
対象メールアドレスリストを環境変数またはスクリプト内設定から読み込む。

users テーブルにアカウントを作成（既存の場合は属性をアップデート）：

role = 'internal_staff'

subscriptions テーブルに永久有効レコードを作成：

plan_type = 'free_permanent'

payment_method = 'none'

status = 'active'

current_period_start = NOW()

current_period_end = '2099-12-31 23:59:59'

6.2 実装テンプレート (PHP)
<?php
// scripts/register_internal_staff.php
require_once __DIR__ . '/../config/database.php';

$staff_list = [
    ['email' => 'staff1@example.com', 'name' => '社内スタッフ1', 'company' => '自社'],
    ['email' => 'staff2@example.com', 'name' => '社内スタッフ2', 'company' => '自社'],
    ['email' => 'staff3@example.com', 'name' => '社内スタッフ3', 'company' => '自社'],
];

$pdo->beginTransaction();
try {
    foreach ($staff_list as$staff) {
        // 1. Userの登録または更新
        $stmt =$pdo->prepare("
            INSERT INTO users (email, password_hash, company_name, user_name, role) 
            VALUES (:email, :pass, :company, :name, 'internal_staff')
            ON DUPLICATE KEY UPDATE role = 'internal_staff', user_name = :name_up
        ");
        $default_pass = password_hash('ChangeMeAfterLogin2026!', PASSWORD_DEFAULT);$stmt->execute([
            ':email' => $staff['email'],
            ':pass' => $default_pass,
            ':company' => $staff['company'],
            ':name' => $staff['name'],
            ':name_up' => $staff['name']
        ]);
        
        $user_id = $pdo->lastInsertId() ?:$pdo->query("SELECT id FROM users WHERE email = '{$staff['email']}'")->fetchColumn();

        // 2. 永久有効サブスクリプションの付与
        $stmt_sub =$pdo->prepare("
            INSERT INTO subscriptions (user_id, plan_type, payment_method, status, current_period_start, current_period_end)
            VALUES (:uid, 'free_permanent', 'none', 'active', NOW(), '2099-12-31 23:59:59')
            ON DUPLICATE KEY UPDATE plan_type = 'free_permanent', status = 'active', current_period_end = '2099-12-31 23:59:59'
        ");
        $stmt_sub->execute([':uid' =>$user_id]);
        
        echo "[SUCCESS] Registered Internal Staff: {$staff['email']} (User ID: {$user_id})\n";
    }
    $pdo->commit();
} catch (Exception $e) {$pdo->rollBack();
    echo "[ERROR] Failed to register staff: " . $e->getMessage() . "\n";
    exit(1);
}

7. IDEエージェント向け 実装順序・自動テスト指示
Antigravity IDE は、本仕様を以下のステップで実装し、各段階で検証テストを実行すること。

Step 1: DBマイグレーション実行

仕様3章のSQLを対象環境（ローカル/テストDB）に対して実行し、テーブル生成を確認する。

Step 2: 社内スタッフ一括登録スクリプトのテスト

仕様6章のスクリプトを実行し、users と subscriptions に正確にデータが挿入されるかSQL照会で確認する。

Step 3: ペイウォールミドルウェアの単体テスト

モックリクエストを用いて、internal_staff（通過）、active な一般ユーザー（通過）、past_due な一般ユーザー（403ブロック）の3パターンが正しく機能するか検証する。

Step 4: ハイブリッドAPI（方式A/B）のエンドポイント作成と疎通確認

X-Portal-Sync-Token を付与した cURL リクエストによるダミーデータのPOST/GETテストを実行し、正しく200 OKと期待されるJSONが返ることを確認してデプロイする。