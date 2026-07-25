<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * 送信キュー生成＆ランダムインターバル分散制御クラス
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
     */
    public function generateQueue(int $templateId, int $dailyLimit = 50): int {
        // オプトアウトされておらず、未送信かつ Approved の企業を取得
        $sql = "SELECT c.id, c.email 
                FROM companies c 
                LEFT JOIN send_logs sl ON c.id = sl.company_id AND sl.template_id = :template_id
                WHERE c.status = 'approved' 
                  AND c.is_opt_out = 0 
                  AND c.email IS NOT NULL 
                  AND c.email != '' 
                  AND sl.id IS NULL 
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':template_id', $templateId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $dailyLimit, PDO::PARAM_INT);
        $stmt->execute();

        $companies = $stmt->fetchAll();
        $queuedCount = 0;

        $insertSql = "INSERT INTO send_logs 
                      (company_id, template_id, email_to, status, scheduled_at) 
                      VALUES (:company_id, :template_id, :email_to, 'queued', :scheduled_at)";
        $insertStmt = $this->pdo->prepare($insertSql);

        $now = new DateTime();
        foreach ($companies as $comp) {
            // スケジュール時刻を順次分散加算
            $interval = self::getSendInterval();
            $now->modify("+{$interval} seconds");

            $insertStmt->execute([
                ':company_id'   => $comp['id'],
                ':template_id' => $templateId,
                ':email_to'     => $comp['email'],
                ':scheduled_at' => $now->format('Y-m-d H:i:s')
            ]);
            $queuedCount++;
        }

        return $queuedCount;
    }
}
