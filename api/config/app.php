<?php
/**
 * Application Settings
 */

return [
    'app_name' => 'TB Data Tracker MIS',
    'app_version' => '1.0.0',
    'jwt_secret' => getenv('JWT_SECRET') ?: 'saathii_tb_tracker_jwt_secret_key_2026_x99',
    'jwt_expiry' => 86400 * 7, // 7 days in seconds
    'google_credentials_path' => __DIR__ . '/google_credentials.json',
    'rate_limit_ms' => 200, // delay between Google Sheet API requests
    'default_page_size' => 25,
    'max_page_size' => 500,
    'sync_cron_key' => getenv('SYNC_CRON_KEY') ?: 'tbdata_sync_secret_2026',
];
