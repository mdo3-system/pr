<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * リード企業精査・承認 API コントローラー
 */

header('Content-Type: application/json');

function getPdoConnection() {
    $envFile = __DIR__ . '/../.env';
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

    $dbHost = $env['DB_HOST'] ?? 'localhost';
    $dbPort = $env['DB_PORT'] ?? '3306';
    $dbName = $env['DB_DATABASE'] ?? 'mdo3_pr';
    $dbUser = $env['DB_USERNAME'] ?? 'mdo3_toolapp0001';
    $dbPass = $env['DB_PASSWORD'] ?? 'koki2656@';

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    return new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getPdoConnection();

    if ($method === 'GET') {
        $pref = $_GET['prefecture'] ?? '';
        $city = $_GET['city'] ?? '';
        $status = $_GET['status'] ?? '';
        $hasEmail = $_GET['has_email'] ?? '';
        $optOut = $_GET['is_opt_out'] ?? '';

        $where = [];
        $params = [];

        if ($pref !== '') {
            $where[] = "c.prefecture = :pref";
            $params[':pref'] = $pref;
        }
        if ($city !== '') {
            $where[] = "c.city LIKE :city";
            $params[':city'] = '%' . $city . '%';
        }
        if ($status !== '') {
            $where[] = "c.status = :status";
            $params[':status'] = $status;
        }
        if ($hasEmail === '1') {
            $where[] = "c.email IS NOT NULL AND c.email != ''";
        }
        if ($optOut !== '') {
            $where[] = "c.is_opt_out = :optOut";
            $params[':optOut'] = (int)$optOut;
        }

        $whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT c.*, cl.detected_optout_keywords, cl.raw_text_snippet 
                FROM companies c 
                LEFT JOIN (
                    SELECT company_id, detected_optout_keywords, raw_text_snippet 
                    FROM crawl_logs 
                    ORDER BY id DESC 
                    LIMIT 1
                ) cl ON c.id = cl.company_id
                {$whereSql} 
                ORDER BY c.id DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => $items]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = $input['action'] ?? '';

        if ($action === 'update_status') {
            $companyId = $input['company_id'] ?? null;
            $newStatus = $input['status'] ?? '';

            if (!$companyId || !in_array($newStatus, ['pending', 'crawled', 'approved', 'rejected'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE companies SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $companyId]);

            echo json_encode(['status' => 'success', 'message' => 'Status updated successfully']);

        } elseif ($action === 'bulk_approve') {
            $pref = $input['prefecture'] ?? '';

            $where = ["is_opt_out = 0", "email IS NOT NULL", "email != ''", "status != 'approved'"];
            $params = [];

            if ($pref !== '') {
                $where[] = "prefecture = :pref";
                $params[':pref'] = $pref;
            }

            $whereSql = implode(" AND ", $where);
            $sql = "UPDATE companies SET status = 'approved' WHERE {$whereSql}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $affected = $stmt->rowCount();

            echo json_encode(['status' => 'success', 'approved_count' => $affected]);

        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
