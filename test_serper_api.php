<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * Serper API 鍵認証・実検索疎通テストスクリプト (v1.0.5)
 */

require_once __DIR__ . '/src/SerperSearch.php';

function getEnvParam($key, $default = null) {
    static $env = null;
    if ($env === null) {
        $envFile = __DIR__ . '/.env';
        $env = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $val = trim($v);
                    $val = trim($val, '"\''); // Remove quotes
                    $env[trim($k)] = $val;
                }
            }
        }
    }
    return isset($env[$key]) ? $env[$key] : $default;
}

echo "=== Automated Test: Serper API Authentication & Real Query Verification (v1.0.5) ===\n\n";

$apiKey = getEnvParam('SERPER_API_KEY');
if (empty($apiKey)) {
    echo "[FAIL] SERPER_API_KEY is empty in .env!\n";
    exit(1);
}

echo "SERPER_API_KEY detected: " . substr($apiKey, 0, 8) . "...\n";

$query = SerperSearch::buildQuery("川越建築設計事務所", "川越市");
echo "Sending test query to Serper API: {$query}\n";

$ch = curl_init('https://google.serper.dev/search');
$payload = json_encode([
    'q' => $query,
    'gl' => 'jp',
    'hl' => 'ja',
    'num' => 3
]);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-API-KEY: ' . $apiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => $payload
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['organic']) && is_array($data['organic']) && count($data['organic']) > 0) {
        $extractedUrl = SerperSearch::extractOfficialUrl($data['organic']);
        echo "[PASS] Serper API response code: 200 OK.\n";
        echo "[PASS] Organic search results returned: " . count($data['organic']) . " items.\n";
        echo "       Top Official URL Extracted: " . ($extractedUrl ?? 'None (Portals filtered)') . "\n\n";
        echo "=== SERPER API AUTHENTICATION & LIVE SEARCH TEST PASSED ===\n";
        exit(0);
    } else {
        echo "[FAIL] Serper API returned 200 OK but no organic results found.\n";
        exit(1);
    }
} else {
    echo "[FAIL] Serper API request failed with HTTP code: {$httpCode}\n";
    echo "       Response: {$response}\n";
    exit(1);
}
