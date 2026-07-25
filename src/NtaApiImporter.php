<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * 国税庁 法人番号システム API 連携・インポートクラス
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
     * 企業データ配列を DB `companies` テーブルにインポートする
     */
    public function importCompanies(array $companies): int {
        $sql = "INSERT INTO `companies` 
            (`corporate_number`, `name`, `clean_name`, `prefecture`, `city`, `address`, `postal_code`, `category`, `source_type`, `status`)
            VALUES
            (:corporate_number, :name, :clean_name, :prefecture, :city, :address, :postal_code, :category, :source_type, 'pending')
            ON DUPLICATE KEY UPDATE 
            `name` = VALUES(`name`),
            `clean_name` = VALUES(`clean_name`),
            `address` = VALUES(`address`),
            `updated_at` = CURRENT_TIMESTAMP";

        $stmt = $this->pdo->prepare($sql);
        $importedCount = 0;

        foreach ($companies as $comp) {
            $cleanName = self::cleanCompanyName($comp['name']);
            $stmt->execute([
                ':corporate_number' => $comp['corporate_number'] ?? null,
                ':name'             => $comp['name'],
                ':clean_name'       => $cleanName,
                ':prefecture'       => $comp['prefecture'],
                ':city'             => $comp['city'],
                ':address'          => $comp['address'] ?? '',
                ':postal_code'      => $comp['postal_code'] ?? null,
                ':category'         => $comp['category'] ?? 'architect_office',
                ':source_type'      => $comp['source_type'] ?? 'nta_api'
            ]);
            $importedCount++;
        }

        return $importedCount;
    }
}
