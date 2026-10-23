<?php
/**
 * 国税庁 Web-API 疎通テストスクリプト (test_nta_api.php)
 * 1. 法人番号システム Web-API (api.houjin-bangou.nta.go.jp)
 * 2. 適格請求書発行事業者公表システム Web-API (web-api.invoice-kohyo.nta.go.jp)
 */

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
                    $env[trim($k)] = trim($v);
                }
            }
        }
    }
    return isset($env[$key]) ? $env[$key] : $default;
}

$appId = getEnvParam('NTA_API_ID', 'Kk8QGG6E7tVRM');

echo "========================================================\n";
echo " 国税庁 Web-API 疎通テスト\n";
echo " アプリケーションID: {$appId}\n";
echo "========================================================\n\n";

/**
 * 簡易HTTP GETヘルパー
 */
function callApi($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'J-ALG Lead Generator/1.0 (pr.eie.tokyo)');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'error' => $error,
        'body' => $response
    ];
}

// -------------------------------------------------------------
// テスト1: 法人番号システム Web-API（法人番号指定照会）
// 例としてトヨタ自動車株式会社（法人番号: 1180301018771）または国税庁（1000012050001）
// -------------------------------------------------------------
echo "--- [テスト1] 法人番号公表サイト Web-API 疎通確認 ---\n";
// 国税庁（法人番号: 1000012050001）
$targetCorpNum = '1000012050001';
// type=12: JSON (Unicode)
$houjinUrl = "https://api.houjin-bangou.nta.go.jp/4/num?id={$appId}&number={$targetCorpNum}&type=12";
echo "リクエストURL: {$houjinUrl}\n";

$res1 = callApi($houjinUrl);
echo "HTTP ステータス: {$res1['code']}\n";
if ($res1['code'] === 200) {
    echo "【通信成功】レスポンスプレビュー:\n";
    $json1 = json_decode($res1['body'], true);
    if ($json1) {
        echo "カウント: " . ($json1['count'] ?? 'N/A') . "\n";
        if (!empty($json1['corporation'])) {
            $corp = $json1['corporation'][0];
            echo "法人番号: " . ($corp['corporateNumber'] ?? '') . "\n";
            echo "法人名: " . ($corp['name'] ?? '') . "\n";
            echo "所在地: " . ($corp['prefectureName'] ?? '') . ($corp['cityName'] ?? '') . ($corp['streetNumber'] ?? '') . "\n";
        }
    } else {
        echo substr($res1['body'], 0, 500) . "\n";
    }
} else {
    echo "【通信失敗 / エラー】\n";
    echo "Error: {$res1['error']}\n";
    echo "Body: {$res1['body']}\n";
}

echo "\n";

// -------------------------------------------------------------
// テスト2: 法人番号システム Web-API（名称・地域指定照会）
// 例: 埼玉県川越市、「設計」が含まれる法人
// -------------------------------------------------------------
echo "--- [テスト2] 法人番号公表サイト 名称・地域検索 (埼玉県川越市) ---\n";
// address=11 (埼玉県), name=設計 (URLエンコード)
$nameQuery = urlencode('設計');
$houjinNameUrl = "https://api.houjin-bangou.nta.go.jp/4/name?id={$appId}&name={$nameQuery}&address=11&type=12&mode=1";
echo "リクエストURL: {$houjinNameUrl}\n";

$res2 = callApi($houjinNameUrl);
echo "HTTP ステータス: {$res2['code']}\n";
if ($res2['code'] === 200) {
    $json2 = json_decode($res2['body'], true);
    if ($json2) {
        $count = $json2['count'] ?? 0;
        echo "【検索成功】該当法人数: {$count} 件\n";
        if (!empty($json2['corporation'])) {
            echo "取得サンプル（上位3件）:\n";
            $samples = array_slice($json2['corporation'], 0, 3);
            foreach ($samples as $idx => $c) {
                echo " " . ($idx + 1) . ". [" . ($c['corporateNumber'] ?? '') . "] " . ($c['name'] ?? '') . " (" . ($c['prefectureName'] ?? '') . ($c['cityName'] ?? '') . ")\n";
            }
        }
    } else {
        echo substr($res2['body'], 0, 500) . "\n";
    }
} else {
    echo "【検索失敗 / エラー】: {$res2['body']}\n";
}

echo "\n";

// -------------------------------------------------------------
// テスト3: 適格請求書発行事業者公表システム Web-API
// -------------------------------------------------------------
echo "--- [テスト3] 適格請求書発行事業者公表サイト Web-API 疎通確認 ---\n";
// 例: トヨタ自動車株式会社 (T1180301018771) または 国税庁
// type=21: JSON
$invoiceUrl = "https://web-api.invoice-kohyo.nta.go.jp/1/num?id={$appId}&number=T1180301018771&type=21";
echo "リクエストURL: {$invoiceUrl}\n";

$res3 = callApi($invoiceUrl);
echo "HTTP ステータス: {$res3['code']}\n";
if ($res3['code'] === 200) {
    $json3 = json_decode($res3['body'], true);
    if ($json3) {
        echo "【照会成功】適格請求書発行事業者公表システム レスポンス:\n";
        echo "カウント: " . ($json3['count'] ?? 'N/A') . "\n";
        if (!empty($json3['announcement'])) {
            $ann = $json3['announcement'][0];
            echo "登録番号: " . ($ann['registratedNumber'] ?? '') . "\n";
            echo "法人名: " . ($ann['name'] ?? '') . "\n";
            echo "登録年月日: " . ($ann['registrationDate'] ?? '') . "\n";
            echo "所在地: " . ($ann['address'] ?? '') . "\n";
        }
    } else {
        echo substr($res3['body'], 0, 500) . "\n";
    }
} else {
    echo "【照会失敗 / エラー】: {$res3['body']}\n";
}

echo "\n========================================================\n";
echo " 疎通テスト完了\n";
echo "========================================================\n";
