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
        $dupOnly = $_GET['is_duplicate'] ?? '';
        $search = trim($_GET['search'] ?? '');
        $limit = min(max((int)($_GET['limit'] ?? 200), 1), 500);

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
        } elseif ($hasEmail === '0') {
            $where[] = "(c.email IS NULL OR c.email = '')";
        }
        if ($optOut !== '') {
            $where[] = "c.is_opt_out = :optOut";
            $params[':optOut'] = (int)$optOut;
        }
        if ($dupOnly === '1') {
            $where[] = "c.is_duplicate_email = 1";
        }
        if ($search !== '') {
            $where[] = "(c.name LIKE :search OR c.email LIKE :search OR c.official_url LIKE :search OR c.corporate_number LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT c.*, cl.detected_optout_keywords, cl.raw_text_snippet 
                FROM companies c 
                LEFT JOIN (
                    SELECT l1.company_id, l1.detected_optout_keywords, l1.raw_text_snippet 
                    FROM crawl_logs l1
                    INNER JOIN (
                        SELECT company_id, MAX(id) AS max_id FROM crawl_logs GROUP BY company_id
                    ) l2 ON l1.id = l2.max_id
                ) cl ON c.id = cl.company_id
                {$whereSql} 
                ORDER BY c.id ASC LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $countsStmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM companies GROUP BY status");
        $counts = [];
        foreach ($countsStmt->fetchAll() as $row) {
            $counts[$row['status']] = (int)$row['cnt'];
        }
        $crawledWithEmail = (int)$pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'crawled' AND email IS NOT NULL AND email != ''")->fetchColumn();
        $counts['crawled_with_email'] = $crawledWithEmail;

        echo json_encode(['status' => 'success', 'data' => $items, 'counts' => $counts]);

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

        } elseif ($action === 'reset_recrawl') {
            $companyId = (int)($input['company_id'] ?? 0);
            $resetUrl = !empty($input['reset_url']);
            if ($companyId <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid company_id']);
                exit;
            }
            if ($resetUrl) {
                $stmt = $pdo->prepare("UPDATE companies SET status = 'pending', official_url = NULL, contact_url = NULL, email = NULL, is_duplicate_email = 0, updated_at = NOW() WHERE id = :id");
            } else {
                $stmt = $pdo->prepare("UPDATE companies SET status = 'pending', email = NULL, is_duplicate_email = 0, updated_at = NOW() WHERE id = :id");
            }
            $stmt->execute([':id' => $companyId]);
            echo json_encode(['status' => 'success', 'message' => 'Reset to pending successfully']);

        } elseif ($action === 'bulk_update_status') {
            $ids = array_values(array_filter(array_map('intval', $input['ids'] ?? []), fn($v) => $v > 0));
            $mode = $input['mode'] ?? '';
            if (empty($ids)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No IDs specified']);
                exit;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if (in_array($mode, ['approved', 'rejected', 'crawled'], true)) {
                $stmt = $pdo->prepare("UPDATE companies SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders})");
                $stmt->execute(array_merge([$mode], $ids));
            } elseif ($mode === 'recrawl_email') {
                $stmt = $pdo->prepare("UPDATE companies SET status = 'pending', email = NULL, is_duplicate_email = 0, updated_at = NOW() WHERE id IN ({$placeholders})");
                $stmt->execute($ids);
            } elseif ($mode === 'recrawl_url') {
                $stmt = $pdo->prepare("UPDATE companies SET status = 'pending', official_url = NULL, contact_url = NULL, email = NULL, is_duplicate_email = 0, updated_at = NOW() WHERE id IN ({$placeholders})");
                $stmt->execute($ids);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid mode']);
                exit;
            }
            echo json_encode(['status' => 'success', 'affected' => $stmt->rowCount()]);

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
