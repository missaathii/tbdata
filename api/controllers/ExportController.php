<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/RoleGuard.php';

class ExportController {

    public static function exportData(string $type, array $currentUser): void {
        $pdo = Database::getConnection();

        $stateId = isset($_GET['state_id']) ? (int)$_GET['state_id'] : null;
        $stateId = RoleGuard::enforceStateScope($currentUser, $stateId);

        $stateFilter = '';
        $params = [];
        if ($stateId) {
            $stStmt = $pdo->prepare("SELECT state_name FROM states WHERE id = :id");
            $stStmt->execute(['id' => $stateId]);
            $stateName = $stStmt->fetchColumn();
            if ($stateName) {
                $stateFilter = " WHERE state = :state_name";
                $params['state_name'] = $stateName;
            }
        }

        $table = match ($type) {
            'qr-referrals'    => 'data_qr_referrals',
            'hub-site'        => 'data_hub_site',
            'snm-tracker'     => 'data_snm_tracker',
            'down-training'   => 'data_down_training',
            'district-planning'=> 'data_district_planning',
            default           => 'data_qr_referrals',
        };

        $sql = "SELECT * FROM `{$table}` {$stateFilter} ORDER BY id DESC LIMIT 50000";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filename = "{$type}_export_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility with regional languages
        fputs($out, "\xEF\xBB\xBF");

        if (!empty($rows)) {
            // Write headers
            fputcsv($out, array_keys($rows[0]));
            // Write rows
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        } else {
            fputcsv($out, ['No data available for the selected filters']);
        }

        fclose($out);
        exit;
    }
}
