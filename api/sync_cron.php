<?php
/**
 * TB Data Tracker — Web & CLI Sync Runner
 * Designed for Free Shared Hosting (e.g., InfinityFree) without Cron Job access.
 *
 * Usage:
 * 1. Via Web Browser / Webhook:
 *    https://tbdata.great-site.net/api/sync_cron.php?key=tbdata_sync_secret_2026
 *    Optional params: &sheet_id=5
 *
 * 2. Via CLI (if available):
 *    php sync_cron.php --key=tbdata_sync_secret_2026
 */

@set_time_limit(300);
@ini_set('memory_limit', '256M');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/sync/SyncEngine.php';

$appConfig = require __DIR__ . '/config/app.php';
$validKey = $appConfig['sync_cron_key'] ?? 'tbdata_sync_secret_2026';

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    $options = getopt('', ['sheet-id::', 'key::']);
    $providedKey = $options['key'] ?? null;
    $sheetId = isset($options['sheet-id']) ? (int)$options['sheet-id'] : null;

    if ($providedKey && !hash_equals($validKey, (string)$providedKey)) {
        fwrite(STDERR, "Error: Invalid secret key.\n");
        exit(1);
    }
} else {
    header('Content-Type: application/json; charset=utf-8');
    $providedKey = $_GET['key'] ?? ($_SERVER['HTTP_X_SYNC_KEY'] ?? null);
    $sheetId = isset($_GET['sheet_id']) ? (int)$_GET['sheet_id'] : null;

    if (!$providedKey || !hash_equals($validKey, (string)$providedKey)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Invalid or missing secret key. Pass ?key=' . substr($validKey, 0, 4) . '...'
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

try {
    $engine = new SyncEngine();
    $triggerSource = $isCli ? 'cli_cron' : 'web_cron';
    $results = $engine->run($sheetId, $triggerSource, 'scheduled');

    if ($isCli) {
        echo "Sync complete!\n";
        echo "Batch: {$results['batch_id']}\n";
        echo "Sheets: {$results['successful']} succeeded, {$results['failed']} failed\n";
        echo "Rows Inserted: {$results['total_inserted']}, Skipped: {$results['total_skipped']}\n";
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Synchronization executed successfully',
            'data' => $results
        ], JSON_PRETTY_PRINT);
    }
} catch (Throwable $e) {
    if ($isCli) {
        fwrite(STDERR, "Sync Error: " . $e->getMessage() . "\n");
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Sync Error: ' . $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }
}
