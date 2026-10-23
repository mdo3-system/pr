<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * 国税庁 法人番号システム API 連携・地域巡回インポートクラス
 */

class NtaApiImporter {
    private $pdo;
    private $apiId;

    public function __construct(PDO $pdo, ?string $apiId = null) {
        $this->pdo = $pdo;
        $this->apiId = $apiId;
    }

    /**
     * 法人名から「株式会社」「有限会社」等の組織形態を除去して検索用クリーン企業名を生成する
     */
    public static function cleanCompanyName(string $name): string {
        $patterns = [
            '/^一般社団法人\s*/u', '/\s*一般社団法人$/u',
            '/^公益社団法人\s*/u', '/\s*公益社団法人$/u',
            '/^特定非営利活動法人\s*/u', '/\s*特定非営利活動法人$/u',
            '/^株式会社\s*/u', '/\s*株式会社$/u',
            '/^有限会社\s*/u', '/\s*有限会社$/u',
            '/^合同会社\s*/u', '/\s*合同会社$/u',
            '/^合資会社\s*/u', '/\s*合資会社$/u',
            '/^合名会社\s*/u', '/\s*合名会社$/u',
            '/^（株）\s*/u', '/\s*（株）$/u',
            '/^\(株\)\s*/u', '/\s*\(株\)$/u',
            '/^（有）\s*/u', '/\s*（有）$/u',
            '/^\(有\)\s*/u', '/\s*\(有\)$/u',
        ];
        $clean = trim($name);
        foreach ($patterns as $pattern) {
            $clean = preg_replace($pattern, '', $clean);
        }
        return trim($clean);
    }

    /**
     * 国税庁 法人番号公表サイト Web-API から指定地域・キーワードの法人情報を取得する (XML形式)
     *
     * @param string $prefectureCode 都道府県コード (11=埼玉, 13=東京等)
     * @param string $keyword 検索キーワード (設計, 工務店等)
     * @param int $page ページ番号 (divideNumber)
     * @return array ['count' => int, 'corporations' => array]
     */
    public function fetchCorporationsFromApi(string $prefectureCode, string $keyword, int $page = 1): array {
        if (empty($this->apiId)) {
            throw new RuntimeException("国税庁 API ID (NTA_API_ID) が未設定です。");
        }

        $encodedKeyword = urlencode($keyword);
        // type=12: Unicode XML
        $url = "https://api.houjin-bangou.nta.go.jp/4/name?id={$this->apiId}&name={$encodedKeyword}&address={$prefectureCode}&type=12&mode=1&page={$page}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'J-ALG Lead Generator/1.0 (pr.eie.tokyo)'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            throw new RuntimeException("国税庁APIリクエスト失敗 HTTP: {$httpCode}, Error: {$curlErr}");
        }

        $xml = @simplexml_load_string($response);
        if ($xml === false) {
            throw new RuntimeException("国税庁APIレスポンスのXMLパースに失敗しました。");
        }

        $totalCount = (int)($xml->count ?? 0);
        $corps = [];

        if (isset($xml->corporation)) {
            foreach ($xml->corporation as $c) {
                $corpNum = (string)$c->corporateNumber;
                $name = (string)$c->name;
                $prefName = (string)$c->prefectureName;
                $cityName = (string)$c->cityName;
                $street = (string)$c->streetNumber;
                $postal = (string)$c->postCode;

                $corps[] = [
                    'corporate_number' => $corpNum,
                    'name'             => $name,
                    'prefecture'       => $prefName,
                    'city'             => $cityName,
                    'address'          => $street,
                    'postal_code'      => $postal,
                    'category'         => (strpos($keyword, '設計') !== false) ? 'architect_office' : 'builder',
                    'source_type'      => 'nta_api'
                ];
            }
        }

        return [
            'count'        => $totalCount,
            'corporations' => $corps
        ];
    }

    /**
     * 企業データ配列を DB `companies` テーブルにインポートする
     * （オプトアウトトークンの自動生成および既存企業の重複防止を含む）
     */
    public function importCompanies(array $companies): int {
        $sql = "INSERT INTO `companies` 
            (`corporate_number`, `name`, `clean_name`, `prefecture`, `city`, `address`, `postal_code`, `category`, `source_type`, `status`, `opt_out_token`)
            VALUES
            (:corporate_number, :name, :clean_name, :prefecture, :city, :address, :postal_code, :category, :source_type, 'pending', :token)
            ON DUPLICATE KEY UPDATE 
            `name` = VALUES(`name`),
            `clean_name` = VALUES(`clean_name`),
            `address` = VALUES(`address`),
            `updated_at` = CURRENT_TIMESTAMP";

        $stmt = $this->pdo->prepare($sql);
        $importedCount = 0;

        foreach ($companies as $comp) {
            $cleanName = self::cleanCompanyName($comp['name']);
            $token = bin2hex(random_bytes(16));

            $stmt->execute([
                ':corporate_number' => $comp['corporate_number'] ?? null,
                ':name'             => $comp['name'],
                ':clean_name'       => $cleanName,
                ':prefecture'       => $comp['prefecture'],
                ':city'             => $comp['city'],
                ':address'          => $comp['address'] ?? '',
                ':postal_code'      => $comp['postal_code'] ?? null,
                ':category'         => $comp['category'] ?? 'architect_office',
                ':source_type'      => $comp['source_type'] ?? 'nta_api',
                ':token'            => $token
            ]);
            $importedCount++;
        }

        return $importedCount;
    }

    /**
     * 進捗テーブル (nta_extract_progress) を参照し、次回巡回すべき地域を1件実行する
     * （同一地域の重複抽出防止＆180日クールダウン制御）
     */
    public function executeScheduledExtraction(): array {
        // 待機中またはクールダウン期間が終了した対象地域を取得
        $selectSql = "SELECT * FROM nta_extract_progress 
                      WHERE status IN ('waiting', 'in_progress')
                         OR (status = 'completed' AND next_available_at <= NOW())
                      ORDER BY 
                        CASE status 
                          WHEN 'in_progress' THEN 1 
                          WHEN 'waiting' THEN 2 
                          ELSE 3 
                        END, 
                        last_extracted_at ASC 
                      LIMIT 1";

        $stmt = $this->pdo->query($selectSql);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$target) {
            return [
                'success' => true,
                'message' => '現在巡回が必要な対象地域はありません（すべて完了またはクールダウン待機中）。',
                'imported' => 0
            ];
        }

        $regionId = (int)$target['id'];
        $prefCode = $target['prefecture_code'];
        $prefName = $target['prefecture_name'];
        $keyword  = $target['search_keyword'];
        $page     = (int)$target['current_page'];

        // ステータスを in_progress へ更新
        $this->pdo->prepare("UPDATE nta_extract_progress SET status = 'in_progress', last_extracted_at = NOW() WHERE id = :id")->execute([':id' => $regionId]);

        // 国税庁APIから取得
        $apiResult = $this->fetchCorporationsFromApi($prefCode, $keyword, $page);
        $totalRecords = $apiResult['count'];
        $corps = $apiResult['corporations'];

        $imported = 0;
        if (!empty($corps)) {
            $imported = $this->importCompanies($corps);
        }

        // ページネーションおよび完了判定
        // 1ページ最大50件想定。取得件数が0件または総件数に達した場合は完了
        $isCompleted = empty($corps) || ($page * 50 >= $totalRecords) || (count($corps) < 50);

        if ($isCompleted) {
            $upSql = "UPDATE nta_extract_progress 
                      SET status = 'completed', 
                          total_records = :total,
                          imported_count = imported_count + :imp,
                          current_page = 1,
                          next_available_at = DATE_ADD(NOW(), INTERVAL 180 DAY),
                          updated_at = NOW()
                      WHERE id = :id";
            $this->pdo->prepare($upSql)->execute([
                ':total' => $totalRecords,
                ':imp'   => $imported,
                ':id'    => $regionId
            ]);
            $statusText = "全件抽出完了（180日間の再抽出ロックを適用）";
        } else {
            $upSql = "UPDATE nta_extract_progress 
                      SET current_page = current_page + 1,
                          total_records = :total,
                          imported_count = imported_count + :imp,
                          updated_at = NOW()
                      WHERE id = :id";
            $this->pdo->prepare($upSql)->execute([
                ':total' => $totalRecords,
                ':imp'   => $imported,
                ':id'    => $regionId
            ]);
            $statusText = "次ページ ({$page} -> " . ($page + 1) . ") へ進捗を保存";
        }

        return [
            'success'       => true,
            'region'        => "{$prefName} × 「{$keyword}」",
            'page'          => $page,
            'total_records' => $totalRecords,
            'imported'      => $imported,
            'status'        => $statusText
        ];
    }
}
