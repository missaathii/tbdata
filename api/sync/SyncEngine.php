<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SheetsClient.php';
require_once __DIR__ . '/parsers/QRReferralParser.php';
require_once __DIR__ . '/parsers/HubSiteParser.php';
require_once __DIR__ . '/parsers/SNMTrackerParser.php';
require_once __DIR__ . '/parsers/DownTrainingParser.php';
require_once __DIR__ . '/parsers/DistrictPlanningParser.php';
require_once __DIR__ . '/parsers/GenericParser.php';

class SyncEngine {
    private PDO $pdo;
    private SheetsClient $sheetsClient;

    public function __construct() {
        $this->pdo = Database::getConnection();
        $this->sheetsClient = new SheetsClient();
    }

    /**
     * Sync all enabled sheets or a specific sheet by ID
     */
    public function run(?int $specificSheetRegistryId = null, string $triggeredBy = 'system', string $syncType = 'nightly'): array {
        $batchId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $where = "WHERE sr.sync_enabled = 1";
        $params = [];

        if ($specificSheetRegistryId) {
            $where = "WHERE sr.id = :id";
            $params = ['id' => $specificSheetRegistryId];
        }

        $sql = "
            SELECT 
                sr.*,
                tt.tracker_number, tt.tracker_name, tt.parser_class, tt.target_table,
                s.state_name, s.state_code
            FROM sheet_registry sr
            JOIN tracker_types tt ON sr.tracker_type_id = tt.id
            JOIN states s ON sr.state_id = s.id
            {$where}
            ORDER BY tt.sync_priority DESC, sr.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $sheets = $stmt->fetchAll();

        $results = [
            'batch_id' => $batchId,
            'total_sheets' => count($sheets),
            'successful' => 0,
            'failed' => 0,
            'total_inserted' => 0,
            'total_skipped' => 0,
            'details' => [],
        ];

        foreach ($sheets as $sheet) {
            $sheetRes = $this->syncSingleSheet($sheet, $batchId, $triggeredBy, $syncType);
            $results['details'][] = $sheetRes;

            if ($sheetRes['status'] === 'success') {
                $results['successful']++;
            } else {
                $results['failed']++;
            }

            $results['total_inserted'] += $sheetRes['rows_inserted'];
            $results['total_skipped'] += $sheetRes['rows_skipped'];

            // 200ms rate limit delay
            usleep(200000);
        }

        return $results;
    }

    public function syncSingleSheet(array $sheet, string $batchId, string $triggeredBy, string $syncType): array {
        $startTime = microtime(true);
        $sheetId = $sheet['id'];

        // Insert started log
        $logStmt = $this->pdo->prepare("
            INSERT INTO sync_logs (sheet_registry_id, batch_id, sync_type, status, triggered_by, started_at)
            VALUES (:sheet_id, :batch_id, :sync_type, 'started', :triggered_by, NOW())
        ");
        $logStmt->execute([
            'sheet_id' => $sheetId,
            'batch_id' => $batchId,
            'sync_type' => $syncType,
            'triggered_by' => $triggeredBy,
        ]);
        $logId = $this->pdo->lastInsertId();

        try {
            // 1. Fetch values from Google Sheet
            $rawRows = $this->sheetsClient->getSheetValues($sheet['google_sheet_id'], $sheet['sheet_tab_name'] ?? 'Sheet1');
            $totalFetched = count($rawRows);

            if ($totalFetched < 2) {
                throw new Exception("Sheet is empty or has no header row.");
            }

            // 2. Instantiate Parser
            $parserClass = $sheet['parser_class'] ?: 'GenericParser';
            if (!class_exists($parserClass)) {
                $parserClass = 'GenericParser';
            }

            /** @var ParserInterface $parser */
            $parser = new $parserClass();
            $parsedRecords = $parser->parse($rawRows, $sheet);

            // 3. Upsert records into target table
            $targetTable = $sheet['target_table'] ?: 'data_generic';
            $upsertStats = $this->upsertRecords($targetTable, $parsedRecords);

            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            // 4. Update Sheet Registry status
            $updSheet = $this->pdo->prepare("
                UPDATE sheet_registry 
                SET last_sync_at = NOW(),
                    last_sync_row_count = :row_count,
                    last_sync_status = 'success',
                    last_error = NULL
                WHERE id = :id
            ");
            $updSheet->execute([
                'row_count' => count($parsedRecords),
                'id' => $sheetId
            ]);

            // 5. Update Log
            $updLog = $this->pdo->prepare("
                UPDATE sync_logs 
                SET status = 'success',
                    rows_fetched = :fetched,
                    rows_inserted = :inserted,
                    rows_skipped = :skipped,
                    duration_ms = :duration,
                    completed_at = NOW()
                WHERE id = :id
            ");
            $updLog->execute([
                'fetched' => $totalFetched,
                'inserted' => $upsertStats['inserted'],
                'skipped' => $upsertStats['skipped'],
                'duration' => $durationMs,
                'id' => $logId
            ]);

            return [
                'sheet_id' => $sheetId,
                'tracker_name' => $sheet['tracker_name'],
                'state_name' => $sheet['state_name'],
                'status' => 'success',
                'rows_fetched' => $totalFetched,
                'rows_inserted' => $upsertStats['inserted'],
                'rows_skipped' => $upsertStats['skipped'],
                'duration_ms' => $durationMs,
                'error' => null
            ];

        } catch (Exception $e) {
            $durationMs = (int)((microtime(true) - $startTime) * 1000);
            $errMsg = $e->getMessage();

            // Update Sheet Registry failed status
            $updSheet = $this->pdo->prepare("
                UPDATE sheet_registry 
                SET last_sync_at = NOW(),
                    last_sync_status = 'failed',
                    last_error = :err
                WHERE id = :id
            ");
            $updSheet->execute(['err' => $errMsg, 'id' => $sheetId]);

            // Update Log
            $updLog = $this->pdo->prepare("
                UPDATE sync_logs 
                SET status = 'failed',
                    error_message = :err,
                    duration_ms = :duration,
                    completed_at = NOW()
                WHERE id = :id
            ");
            $updLog->execute([
                'err' => $errMsg,
                'duration' => $durationMs,
                'id' => $logId
            ]);

            return [
                'sheet_id' => $sheetId,
                'tracker_name' => $sheet['tracker_name'],
                'state_name' => $sheet['state_name'],
                'status' => 'failed',
                'rows_fetched' => 0,
                'rows_inserted' => 0,
                'rows_skipped' => 0,
                'duration_ms' => $durationMs,
                'error' => $errMsg
            ];
        }
    }

    private function upsertRecords(string $table, array $records): array {
        if (empty($records)) {
            return ['inserted' => 0, 'skipped' => 0];
        }

        $inserted = 0;
        $skipped = 0;

        $columns = array_keys($records[0]);
        $colNames = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $placeholders = implode(', ', array_map(fn($c) => ":{$c}", $columns));

        $sql = "INSERT IGNORE INTO `{$table}` ({$colNames}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);

        foreach ($records as $record) {
            $stmt->execute($record);
            if ($stmt->rowCount() > 0) {
                $inserted++;
            } else {
                $skipped++;
            }
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }
}
