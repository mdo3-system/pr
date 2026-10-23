<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * 送信キュー生成＆重複送信防止・クールダウン制御クラス
 */

class QueueManager {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 送信インターバル(秒)を取得する (標準: 180秒〜480秒のランダム間隔)
     */
    public static function getSendInterval(int $min = 180, int $max = 480): int {
        return rand($min, $max);
    }

    /**
     * Approved ステータスの企業から送信キューを生成する
     * 【重要制御】
     * 1. 累計送信回数が3回未満 (send_count < 3)
     * 2. 直近90日間未送信 (last_sent_at IS NULL または 90日以上経過)
     * 3. 同一メールアドレスへの重複送信完全抑止 (直近90日以内に全社通じて送信履歴なし)
     * 4. 既に未送信キューに入っていない企業
     */
    public function generateQueue(int $templateId, int $dailyLimit = 50): int {
        $sql = "SELECT c.id, c.email, c.name, c.clean_name, c.city, c.opt_out_token
                FROM companies c 
                WHERE c.status = 'approved' 
                  AND c.is_opt_out = 0 
                  AND c.email IS NOT NULL 
                  AND c.email != '' 
                  -- ① 累計送信回数キャップ (最大3回まで)
                  AND c.send_count < 3
                  -- ② クールダウン制御 (未送信 または 最終送信から90日以上経過)
                  AND (c.last_sent_at IS NULL OR c.last_sent_at <= DATE_SUB(NOW(), INTERVAL 90 DAY))
                  -- ③ 既にキュー(queued)または送信処理中(sending)に入っていないこと
                  AND NOT EXISTS (
                      SELECT 1 FROM send_logs sl_current 
                      WHERE sl_current.company_id = c.id 
                        AND sl_current.status IN ('queued', 'sending')
                  )
                  -- ④ 同一メールアドレスへの重複送信抑止 (過去90日以内に全社通じて送信履歴なし)
                  AND NOT EXISTS (
                      SELECT 1 FROM send_logs sl_addr 
                      WHERE sl_addr.email_to = c.email 
                        AND sl_addr.status IN ('delivered', 'sent', 'queued', 'sending')
                        AND sl_addr.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                  )
                ORDER BY 
                  c.last_sent_at ASC, -- 未送信(NULL)が最優先
                  c.id ASC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $dailyLimit, PDO::PARAM_INT);
        $stmt->execute();

        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $queuedCount = 0;

        $insertSql = "INSERT INTO send_logs 
                      (company_id, template_id, email_to, status, scheduled_at) 
                      VALUES (:company_id, :template_id, :email_to, 'queued', :scheduled_at)";
        $insertStmt = $this->pdo->prepare($insertSql);

        $now = new DateTime();
        foreach ($companies as $comp) {
            // スケジュール時刻を順次分散加算 (3分〜8分のランダムインターバル)
            $interval = self::getSendInterval();
            $now->modify("+{$interval} seconds");

            $insertStmt->execute([
                ':company_id'   => $comp['id'],
                ':template_id'  => $templateId,
                ':email_to'     => $comp['email'],
                ':scheduled_at' => $now->format('Y-m-d H:i:s')
            ]);
            $queuedCount++;
        }

        return $queuedCount;
    }
}
