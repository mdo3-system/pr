<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * Serper API (Google Search) 公式WEBサイト特定クラス
 */

class SerperSearch {
    private $apiKey;
    private static $ignoredDomains = [
        // 既存ポータル・SNS
        'suumo.jp', 'homes.co.jp', 'itp.ne.jp', 'ekiten.jp', 'houjin.jp',
        'facebook.com', 'instagram.com', 'twitter.com', 'x.com',
        'bing.com', 'yahoo.co.jp', 'wikipedia.org', 'mapion.co.jp', 'navitime.co.jp',
        // まとめサイト・企業DB・求人系
        'houzz.jp', 'biz.ne.jp', 'salesnow.jp', 'baseconnect.in',
        'clip.jp', 'clip-corporation.com', 'nextcity.jp', 'ii-ie2.net',
        'sumaidea.com', 'cnavi.g-search.or.jp', 'lawplayer.com', 'ienavi.com',
        'jglobal.jst.go.jp', 'kenzai-navi.com', 'companydata.tsujigawa.com',
        'toukibo.ai-con.lawyer', 'mailmate.jp', 'doda.jp', 'tenpodesign.com',
        'xn--pckua2a7gp15o89zb.com',
        // 建築関連の団体・会員一覧・名簿系
        'hamaken.jp', 'kanagawa-kentikusikai.com', 'kkj-yokohama1.jp',
        'nikkenren.com', 'aij.or.jp', 'j-kana.or.jp'
    ];

    public function __construct(?string $apiKey = null) {
        $this->apiKey = $apiKey;
    }

    /**
     * ポータルサイト・SNS・団体一覧・PDF直リンク等の除外対象URLか判定する
     */
    public static function isIgnoredDomain(string $url): bool {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        if (!$host) return true;
        $host = strtolower($host);

        foreach (self::$ignoredDomains as $ignored) {
            if (strpos($host, $ignored) !== false) {
                return true;
            }
        }

        // PDFやOfficeファイル等の直リンクを除外
        $path = strtolower($parsed['path'] ?? '');
        if (preg_match('/\.(pdf|doc|docx|xls|xlsx|ppt|pptx|zip|csv)$/i', $path)) {
            return true;
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

    /**
     * Serper API (Google Search) を呼び出し、企業の公式WEBサイトURLを特定する
     *
     * @param string $cleanName クリーン企業名
     * @param string $city 市区町村名
     * @return string|null 特定された公式URL（見つからない場合は null）
     * @throws RuntimeException API通信エラー時
     */
    public function searchOfficialUrl(string $cleanName, string $city): ?string {
        if (empty($this->apiKey)) {
            throw new RuntimeException("Serper API Key (SERPER_API_KEY) が未設定です。");
        }

        $query = self::buildQuery($cleanName, $city);
        $payload = json_encode([
            'q'   => $query,
            'gl'  => 'jp',
            'hl'  => 'ja',
            'num' => 10
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://google.serper.dev/search');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'X-API-KEY: ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS     => $payload
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            throw new RuntimeException("Serper API Error HTTP: {$httpCode}, Error: {$curlErr}");
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['organic'])) {
            return null;
        }

        return self::extractOfficialUrl($data['organic']);
    }
}
