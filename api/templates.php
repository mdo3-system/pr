<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * 営業DMテンプレート CRUD API コントローラー
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
        $stmt = $pdo->query("SELECT * FROM email_templates ORDER BY id DESC");
        $templates = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'data' => $templates]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = $input['action'] ?? 'save';

        if ($action === 'save') {
            $id = $input['id'] ?? null;
            $name = $input['template_name'] ?? '無題のテンプレート';
            $subject = $input['subject'] ?? '';
            $bodyText = $input['body_text'] ?? '';
            $bodyHtml = $input['body_html'] ?? null;

            if ($id) {
                $sql = "UPDATE email_templates SET template_name = :name, subject = :subj, body_text = :text, body_html = :html WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':name' => $name, ':subj' => $subject, ':text' => $bodyText, ':html' => $bodyHtml, ':id' => $id]);
            } else {
                $sql = "INSERT INTO email_templates (template_name, subject, body_text, body_html) VALUES (:name, :subj, :text, :html)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':name' => $name, ':subj' => $subject, ':text' => $bodyText, ':html' => $bodyHtml]);
                $id = $pdo->lastInsertId();
            }

            echo json_encode(['status' => 'success', 'id' => $id]);

        } elseif ($action === 'toggle_active') {
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE email_templates SET is_active = NOT is_active WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['status' => 'success']);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
