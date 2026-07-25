<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * Serper API (Google Search) 公式WEBサイト特定クラス
 */

class SerperSearch {
    private $apiKey;
    private static $ignoredDomains = [
        'suumo.jp', 'homes.co.jp', 'itp.ne.jp', 'ekiten.jp', 'houjin.jp',
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com',
        'bing.com', 'yahoo.co.jp', 'wikipedia.org', 'mapion.co.jp', 'navitime.co.jp'
    ];

    public function __construct(?string $apiKey = null) {
        $this->apiKey = $apiKey;
    }

    /**
     * ポータルサイト・SNS等の除外対象ドメインか判定する
     */
    public static function isIgnoredDomain(string $url): bool {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return true;
        $host = strtolower($host);

        foreach (self::$ignoredDomains as $ignored) {
            if (strpos($host, $ignored) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Serper API のレスポンス項目から最初に見つかる公式WEBサイトURLを抽出する
     */
    public static function extractOfficialUrl(array $organicResults): ?string {
        foreach ($organicResults as $item) {
            $link = $item['link'] ?? '';
            if ($link && !self::isIgnoredDomain($link)) {
                return $link;
            }
        }
        return null;
    }

    /**
     * クエリ文字列を生成する
     */
    public static function buildQuery(string $cleanName, string $city): string {
        return "{$cleanName} {$city} (設計事務所 OR 工務店 OR 建築)";
    }
}
