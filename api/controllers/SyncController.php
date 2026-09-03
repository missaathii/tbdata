<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../middleware/RoleGuard.php';
require_once __DIR__ . '/../sync/SyncEngine.php';

class SyncController {

    public static function trigger(array $currentUser): void {
        RoleGuard::requireAdmin($currentUser);

        $input = json_decode(file_get_contents('php://input'), true);
        $sheetId = isset($input['sheet_registry_id']) ? (int)$input['sheet_registry_id'] : null;

        $syncEngine = new SyncEngine();
        $results = $syncEngine->run($sheetId, $currentUser['username'], 'manual');

        Response::success($results, 'Sync process completed');
    }

    public static function cronSync(): void {
        $config = require __DIR__ . '/../config/app.php';
        $validKey = $config['sync_cron_key'] ?? 'tbdata_sync_secret_2026';

        $key = $_GET['key'] ?? ($_SERVER['HTTP_X_SYNC_KEY'] ?? null);
        if (!$key || !hash_equals($validKey, (string)$key)) {
            Response::unauthorized('Invalid or missing sync secret key. Provide ?key=...');
            return;
        }

        @set_time_limit(300);
        @ini_set('memory_limit', '256M');

        $sheetId = isset($_GET['sheet_id']) ? (int)$_GET['sheet_id'] : null;

        $syncEngine = new SyncEngine();
        $results = $syncEngine->run($sheetId, 'web_cron', 'scheduled');

        Response::success($results, 'Web sync process completed');
    }

    public static function status(): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_sheets,
                SUM(CASE WHEN last_sync_status = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN last_sync_status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN last_sync_status = 'never' THEN 1 ELSE 0 END) as pending,
                MAX(last_sync_at) as last_sync_time
            FROM sheet_registry
        ");
        $status = $stmt->fetch();

        Response::success($status);
    }

    public static function logs(array $currentUser): void {
        $pdo = Database::getConnection();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->query("SELECT COUNT(*) FROM sync_logs");
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT 
                l.*,
                sr.sheet_tab_name, sr.google_sheet_url,
                tt.tracker_number, tt.tracker_name,
                s.state_name, s.state_code
            FROM sync_logs l
            LEFT JOIN sheet_registry sr ON l.sheet_registry_id = sr.id
            LEFT JOIN tracker_types tt ON sr.tracker_type_id = tt.id
            LEFT JOIN states s ON sr.state_id = s.id
            ORDER BY l.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        Response::success([
            'items' => $logs,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ]
        ]);
    }
}
