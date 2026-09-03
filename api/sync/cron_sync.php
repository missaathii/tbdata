<?php
/**
 * CLI Nightly Sync Script
 * Usage: php cron_sync.php [--sheet-id=123] [--dry-run]
 */

if (php_sapi_name() !== 'cli') {
    die("Access denied. CLI only.\n");
}

require_once __DIR__ . '/SyncEngine.php';

$options = getopt('', ['sheet-id::', 'dry-run']);
$specificSheetId = isset($options['sheet-id']) ? (int)$options['sheet-id'] : null;

echo "=========================================================\n";
echo "TB DATA TRACKER — CRON SYNC\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";
echo "=========================================================\n";

$engine = new SyncEngine();
$results = $engine->run($specificSheetId, 'cron_nightly', 'nightly');

echo "Batch ID: " . $results['batch_id'] . "\n";
echo "Total Sheets Processed: " . $results['total_sheets'] . "\n";
echo "Successful: " . $results['successful'] . "\n";
echo "Failed: " . $results['failed'] . "\n";
echo "Total Rows Inserted: " . $results['total_inserted'] . "\n";
echo "Total Rows Skipped (Deduplicated): " . $results['total_skipped'] . "\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
echo "=========================================================\n";
