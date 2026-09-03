<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../middleware/RoleGuard.php';

class DashboardController {
    public static function summary(array $currentUser): void {
        $pdo = Database::getConnection();
        
        $stateId = isset($_GET['state_id']) ? (int)$_GET['state_id'] : null;
        $stateId = RoleGuard::enforceStateScope($currentUser, $stateId);

        $stateFilterSQL = '';
        $stateParams = [];
        $stateName = null;

        if ($stateId) {
            $stStmt = $pdo->prepare("SELECT state_name FROM states WHERE id = :id");
            $stStmt->execute(['id' => $stateId]);
            $stateRow = $stStmt->fetch();
            if ($stateRow) {
                $stateName = $stateRow['state_name'];
                $stateFilterSQL = " WHERE state = :state_name ";
                $stateParams = ['state_name' => $stateName];
            }
        }

        // 1. QR Referrals count
        $qrStmt = $pdo->prepare("SELECT COUNT(*) as total FROM data_qr_referrals" . $stateFilterSQL);
        $qrStmt->execute($stateParams);
        $qrTotal = (int)($qrStmt->fetch()['total'] ?? 0);

        // 2. Hub Site counts
        $hubWhere = $stateName ? " WHERE state = :state_name " : "";
        $hubStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_hub,
                SUM(CASE WHEN tb_diagnosis IS NOT NULL AND tb_diagnosis != '' AND tb_diagnosis != 'No' THEN 1 ELSE 0 END) as diagnosed,
                SUM(CASE WHEN treatment_date IS NOT NULL THEN 1 ELSE 0 END) as treated,
                SUM(CASE WHEN naat_date IS NOT NULL THEN 1 ELSE 0 END) as naat_tested
            FROM data_hub_site
            {$hubWhere}
        ");
        $hubStmt->execute($stateParams);
        $hubCounts = $hubStmt->fetch() ?: ['total_hub' => 0, 'diagnosed' => 0, 'treated' => 0, 'naat_tested' => 0];

        // 3. Down Training counts
        $dtStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_trainings,
                COALESCE(SUM(total_participants), 0) as total_sensitized
            FROM data_down_training
            " . $stateFilterSQL);
        $dtStmt->execute($stateParams);
        $dtCounts = $dtStmt->fetch() ?: ['total_trainings' => 0, 'total_sensitized' => 0];

        // 4. Sync summary
        $syncSheetWhere = $stateId ? " WHERE state_id = :state_id " : "";
        $syncSheetParams = $stateId ? ['state_id' => $stateId] : [];
        $syncStatsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sheets,
                SUM(CASE WHEN last_sync_status = 'success' THEN 1 ELSE 0 END) as successful_sheets,
                SUM(CASE WHEN last_sync_status = 'failed' THEN 1 ELSE 0 END) as failed_sheets,
                SUM(CASE WHEN DATE(last_sync_at) = CURDATE() THEN 1 ELSE 0 END) as synced_today
            FROM sheet_registry
            {$syncSheetWhere}
        ");
        $syncStatsStmt->execute($syncSheetParams);
        $syncStats = $syncStatsStmt->fetch() ?: ['total_sheets' => 0, 'successful_sheets' => 0, 'failed_sheets' => 0, 'synced_today' => 0];

        Response::success([
            'state_id' => $stateId,
            'state_name' => $stateName ?: 'All States (National)',
            'kpis' => [
                'qr_referrals' => $qrTotal,
                'hub_site_entries' => (int)($hubCounts['total_hub'] ?? 0),
                'tb_diagnosed' => (int)($hubCounts['diagnosed'] ?? 0),
                'treatment_initiated' => (int)($hubCounts['treated'] ?? 0),
                'naat_tested' => (int)($hubCounts['naat_tested'] ?? 0),
                'down_trainings' => (int)($dtCounts['total_trainings'] ?? 0),
                'sensitized_staff' => (int)($dtCounts['total_sensitized'] ?? 0),
            ],
            'sync' => [
                'total_sheets' => (int)($syncStats['total_sheets'] ?? 0),
                'synced_today' => (int)($syncStats['synced_today'] ?? 0),
                'successful' => (int)($syncStats['successful_sheets'] ?? 0),
                'failed' => (int)($syncStats['failed_sheets'] ?? 0),
            ]
        ], 'Dashboard summary retrieved');
    }

    public static function stateComparison(array $currentUser): void {
        $pdo = Database::getConnection();

        // National query aggregating all 7 states
        $sql = "
            SELECT 
                s.id as state_id,
                s.state_name,
                s.state_code,
                (SELECT COUNT(*) FROM data_qr_referrals qr WHERE qr.state = s.state_name) as qr_count,
                (SELECT COUNT(*) FROM data_hub_site hs WHERE hs.state = s.state_name) as hub_count,
                (SELECT SUM(CASE WHEN tb_diagnosis IS NOT NULL AND tb_diagnosis != '' THEN 1 ELSE 0 END) FROM data_hub_site hs WHERE hs.state = s.state_name) as diagnosed_count,
                (SELECT SUM(CASE WHEN treatment_date IS NOT NULL THEN 1 ELSE 0 END) FROM data_hub_site hs WHERE hs.state = s.state_name) as treated_count,
                (SELECT COUNT(*) FROM data_down_training dt WHERE dt.state = s.state_name) as training_count
            FROM states s
            WHERE s.is_active = 1
            ORDER BY s.state_name ASC
        ";

        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll();

        Response::success($data, 'State comparison retrieved');
    }

    public static function monthlyTrend(array $currentUser): void {
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
                $stateFilter = " AND state = :state_name ";
                $params['state_name'] = $stateName;
            }
        }

        $sql = "
            SELECT 
                DATE_FORMAT(timestamp, '%Y-%m') as month,
                COUNT(*) as referrals
            FROM data_qr_referrals
            WHERE timestamp IS NOT NULL {$stateFilter}
            GROUP BY DATE_FORMAT(timestamp, '%Y-%m')
            ORDER BY month ASC
            LIMIT 12
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trends = $stmt->fetchAll();

        Response::success($trends, 'Monthly trend data retrieved');
    }
}
