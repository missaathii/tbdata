<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../middleware/RoleGuard.php';

class DataController {

    public static function getStates(): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT id, state_name, state_code FROM states WHERE is_active = 1 ORDER BY state_name ASC");
        Response::success($stmt->fetchAll());
    }

    public static function getDistricts(): void {
        $pdo = Database::getConnection();
        $stateId = isset($_GET['state_id']) ? (int)$_GET['state_id'] : null;

        if ($stateId) {
            $stmt = $pdo->prepare("SELECT id, state_id, district_name FROM districts WHERE state_id = :sid AND is_active = 1 ORDER BY district_name ASC");
            $stmt->execute(['sid' => $stateId]);
        } else {
            $stmt = $pdo->query("SELECT id, state_id, district_name FROM districts WHERE is_active = 1 ORDER BY district_name ASC");
        }

        Response::success($stmt->fetchAll());
    }

    public static function getTrackerTypes(): void {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM tracker_types WHERE is_active = 1 ORDER BY CAST(tracker_number AS UNSIGNED) ASC, id ASC");
        Response::success($stmt->fetchAll());
    }

    public static function qrReferrals(array $currentUser): void {
        self::paginateTable('data_qr_referrals', [
            'id', 'state', 'district', 'block_name', 'referring_facility',
            'staff_name', 'designation', 'child_name', 'child_age', 'symptoms',
            'referred_to_facility', 'parent_contact', 'facility_visit_date',
            'referral_id', 'remarks', 'timestamp', 'synced_at'
        ], ['state', 'district', 'staff_name', 'child_name', 'referral_id', 'symptoms'], $currentUser);
    }

    public static function hubSite(array $currentUser): void {
        self::paginateTable('data_hub_site', [
            'id', 'state', 'district', 'facility_name', 'facility_type',
            'child_name', 'age', 'gender', 'parent_name', 'contact_number',
            'qr_referral_id', 'hub_site_referral_id', 'cxr_result', 'sample_type',
            'microscopy_result', 'naat_interpretation', 'tb_diagnosis',
            'diagnosis_date', 'treatment_date', 'remarks', 'synced_at'
        ], ['state', 'district', 'facility_name', 'child_name', 'parent_name', 'qr_referral_id', 'hub_site_referral_id', 'tb_diagnosis'], $currentUser);
    }

    public static function snmTracker(array $currentUser): void {
        self::paginateTable('data_snm_tracker', [
            'id', 'state', 'district', 'report_month', 'hub_site_count', 'oss_done_sites',
            'staff_trained_sites', 'nrc_count', 'nrc_staff_trained', 'nrc_doing_ga',
            'sample_consumables_sites', 'ga_initiated_sites', 'is_initiated_sites',
            'site_visits', 'synced_at'
        ], ['state', 'district'], $currentUser);
    }

    public static function downTraining(array $currentUser): void {
        self::paginateTable('data_down_training', [
            'id', 'state', 'district', 'training_date', 'platform', 'training_level',
            'block_name', 'tb_unit', 'phi', 'venue', 'participant_types',
            'total_participants', 'no_phc_mo', 'no_cho', 'no_anm', 'no_asha',
            'has_attendance_sheet', 'upload_link', 'synced_at'
        ], ['state', 'district', 'block_name', 'tb_unit', 'phi', 'venue', 'participant_types'], $currentUser);
    }

    public static function generic(int $trackerTypeId, array $currentUser): void {
        $pdo = Database::getConnection();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $stateId = isset($_GET['state_id']) ? (int)$_GET['state_id'] : null;
        $stateId = RoleGuard::enforceStateScope($currentUser, $stateId);

        $where = ["tracker_type_id = :tracker_type_id"];
        $params = ['tracker_type_id' => $trackerTypeId];

        if ($stateId) {
            $stStmt = $pdo->prepare("SELECT state_name FROM states WHERE id = :id");
            $stStmt->execute(['id' => $stateId]);
            $stateName = $stStmt->fetchColumn();
            if ($stateName) {
                $where[] = "state = :state_name";
                $params['state_name'] = $stateName;
            }
        }

        $whereSQL = " WHERE " . implode(" AND ", $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM data_generic {$whereSQL}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT id, sheet_registry_id, state, district, raw_data, synced_at FROM data_generic {$whereSQL} ORDER BY id DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Parse JSON raw_data for each record
        foreach ($rows as &$r) {
            $r['raw_data'] = json_decode($r['raw_data'], true);
        }

        Response::success([
            'items' => $rows,
            'pagination' => [
                'total' => $totalRows,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalRows / $perPage),
            ]
        ]);
    }

    private static function paginateTable(string $table, array $allowedColumns, array $searchColumns, array $currentUser): void {
        $pdo = Database::getConnection();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $sortBy = in_array($_GET['sort_by'] ?? '', $allowedColumns) ? $_GET['sort_by'] : 'id';
        $sortOrder = strtoupper($_GET['sort_order'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

        $search = trim($_GET['search'] ?? '');
        $stateId = isset($_GET['state_id']) ? (int)$_GET['state_id'] : null;
        $stateId = RoleGuard::enforceStateScope($currentUser, $stateId);

        $where = [];
        $params = [];

        if ($stateId) {
            $stStmt = $pdo->prepare("SELECT state_name FROM states WHERE id = :id");
            $stStmt->execute(['id' => $stateId]);
            $stateName = $stStmt->fetchColumn();
            if ($stateName) {
                $where[] = "state = :state_name";
                $params['state_name'] = $stateName;
            }
        }

        if (!empty($_GET['district'])) {
            $where[] = "district = :district";
            $params['district'] = trim($_GET['district']);
        }

        if (!empty($search) && !empty($searchColumns)) {
            $searchClauses = [];
            foreach ($searchColumns as $idx => $col) {
                $pName = "search_{$idx}";
                $searchClauses[] = "{$col} LIKE :{$pName}";
                $params[$pName] = "%{$search}%";
            }
            $where[] = "(" . implode(" OR ", $searchClauses) . ")";
        }

        $whereSQL = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        // Total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} {$whereSQL}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        // Query rows
        $colsSQL = implode(", ", array_map(fn($c) => "`{$c}`", $allowedColumns));
        $sql = "SELECT {$colsSQL} FROM {$table} {$whereSQL} ORDER BY `{$sortBy}` {$sortOrder} LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        Response::success([
            'items' => $items,
            'pagination' => [
                'total' => $totalRows,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalRows / $perPage),
            ]
        ]);
    }
}
