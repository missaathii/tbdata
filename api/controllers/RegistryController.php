<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../middleware/RoleGuard.php';

class RegistryController {

    public static function listSheets(array $currentUser): void {
        $pdo = Database::getConnection();

        $stateId = isset($_GET['state_id']) ? (int)$_GET['state_id'] : null;
        $stateId = RoleGuard::enforceStateScope($currentUser, $stateId);
        $trackerTypeId = isset($_GET['tracker_type_id']) ? (int)$_GET['tracker_type_id'] : null;

        $where = [];
        $params = [];

        if ($stateId) {
            $where[] = "sr.state_id = :state_id";
            $params['state_id'] = $stateId;
        }

        if ($trackerTypeId) {
            $where[] = "sr.tracker_type_id = :tracker_type_id";
            $params['tracker_type_id'] = $trackerTypeId;
        }

        $whereSQL = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT 
                sr.*,
                tt.tracker_number, tt.tracker_name, tt.parser_class, tt.target_table,
                s.state_name, s.state_code
            FROM sheet_registry sr
            JOIN tracker_types tt ON sr.tracker_type_id = tt.id
            JOIN states s ON sr.state_id = s.id
            {$whereSQL}
            ORDER BY CAST(tt.tracker_number AS UNSIGNED) ASC, s.state_name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sheets = $stmt->fetchAll();

        Response::success($sheets);
    }

    public static function createSheet(array $currentUser): void {
        RoleGuard::requireAdmin($currentUser);

        $input = json_decode(file_get_contents('php://input'), true);

        $trackerTypeId = (int)($input['tracker_type_id'] ?? 0);
        $stateId = (int)($input['state_id'] ?? 0);
        $sheetUrl = trim($input['google_sheet_url'] ?? '');
        $tabName = trim($input['sheet_tab_name'] ?? 'Sheet1') ?: 'Sheet1';

        if (!$trackerTypeId || !$stateId || empty($sheetUrl)) {
            Response::error('Tracker type, State, and Google Sheet URL are required.');
        }

        // Extract Google Sheet ID from URL
        if (preg_match('/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $sheetUrl, $matches)) {
            $sheetId = $matches[1];
        } else {
            $sheetId = $sheetUrl; // Raw ID provided
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO sheet_registry (tracker_type_id, state_id, google_sheet_id, google_sheet_url, sheet_tab_name, added_by)
            VALUES (:tracker_type_id, :state_id, :sheet_id, :sheet_url, :tab_name, :added_by)
            ON DUPLICATE KEY UPDATE 
                google_sheet_url = VALUES(google_sheet_url),
                sync_enabled = 1
        ");

        $stmt->execute([
            'tracker_type_id' => $trackerTypeId,
            'state_id'        => $stateId,
            'sheet_id'        => $sheetId,
            'sheet_url'       => $sheetUrl,
            'tab_name'        => $tabName,
            'added_by'        => $currentUser['username'],
        ]);

        Response::success(['id' => $pdo->lastInsertId()], 'Sheet registered successfully', 201);
    }

    public static function toggleSheet(int $id, array $currentUser): void {
        RoleGuard::requireAdmin($currentUser);

        $input = json_decode(file_get_contents('php://input'), true);
        $enabled = isset($input['sync_enabled']) ? (int)$input['sync_enabled'] : 1;

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE sheet_registry SET sync_enabled = :enabled WHERE id = :id");
        $stmt->execute(['enabled' => $enabled, 'id' => $id]);

        Response::success(null, 'Sheet status updated');
    }

    public static function deleteSheet(int $id, array $currentUser): void {
        RoleGuard::requireAdmin($currentUser);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM sheet_registry WHERE id = :id");
        $stmt->execute(['id' => $id]);

        Response::success(null, 'Sheet removed from registry');
    }

    public static function createTrackerType(array $currentUser): void {
        RoleGuard::requireAdmin($currentUser);

        $input = json_decode(file_get_contents('php://input'), true);
        $num = trim($input['tracker_number'] ?? '');
        $name = trim($input['tracker_name'] ?? '');
        $desc = trim($input['description'] ?? '');

        if (empty($num) || empty($name)) {
            Response::error('Tracker number and name are required.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO tracker_types (tracker_number, tracker_name, description, parser_class, target_table)
            VALUES (:num, :name, :desc, 'GenericParser', 'data_generic')
        ");
        $stmt->execute(['num' => $num, 'name' => $name, 'desc' => $desc]);

        Response::success(['id' => $pdo->lastInsertId()], 'Tracker type registered successfully', 201);
    }
}
