<?php
/**
 * TB Data Tracker — REST API Router
 */

require_once __DIR__ . '/middleware/CORS.php';
require_once __DIR__ . '/middleware/Auth.php';
require_once __DIR__ . '/helpers/Response.php';

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/DataController.php';
require_once __DIR__ . '/controllers/SyncController.php';
require_once __DIR__ . '/controllers/RegistryController.php';
require_once __DIR__ . '/controllers/ExportController.php';
require_once __DIR__ . '/controllers/UserController.php';

// Handle CORS
CORS::handle();

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Normalize path: strip base path if running under subdirectory or /api
$path = preg_replace('#^(/api|/backend|/TB_Data_Trackers/backend)#', '', $requestUri);
$path = rtrim($path, '/');
if (empty($path)) {
    $path = '/';
}

// ----------------------------------------------------------------------------
// PUBLIC ROUTES
// ----------------------------------------------------------------------------

if ($path === '/auth/login' && $method === 'POST') {
    AuthController::login();
    exit;
}

if ($path === '/health' || $path === '/') {
    Response::success(['status' => 'healthy', 'time' => date('Y-m-d H:i:s')], 'TB Data API is running');
    exit;
}

// Web Cron Sync endpoint (No JWT required, secured by ?key=...)
if ($path === '/sync/cron' && ($method === 'GET' || $method === 'POST')) {
    SyncController::cronSync();
    exit;
}

// ----------------------------------------------------------------------------
// PROTECTED ROUTES (JWT REQUIRED)
// ----------------------------------------------------------------------------

$user = AuthMiddleware::authenticate();

// Auth / Profile
if ($path === '/auth/me' && $method === 'GET') {
    AuthController::me($user);
}

// References
elseif ($path === '/references/states' && $method === 'GET') {
    DataController::getStates();
}
elseif ($path === '/references/districts' && $method === 'GET') {
    DataController::getDistricts();
}
elseif ($path === '/references/tracker-types' && $method === 'GET') {
    DataController::getTrackerTypes();
}

// Dashboard
elseif ($path === '/dashboard/summary' && $method === 'GET') {
    DashboardController::summary($user);
}
elseif ($path === '/dashboard/state-comparison' && $method === 'GET') {
    DashboardController::stateComparison($user);
}
elseif ($path === '/dashboard/monthly-trend' && $method === 'GET') {
    DashboardController::monthlyTrend($user);
}

// Data Trackers
elseif ($path === '/data/qr-referrals' && $method === 'GET') {
    DataController::qrReferrals($user);
}
elseif ($path === '/data/hub-site' && $method === 'GET') {
    DataController::hubSite($user);
}
elseif ($path === '/data/snm-tracker' && $method === 'GET') {
    DataController::snmTracker($user);
}
elseif ($path === '/data/down-training' && $method === 'GET') {
    DataController::downTraining($user);
}
elseif (preg_match('#^/data/generic/(\d+)$#', $path, $m) && $method === 'GET') {
    DataController::generic((int)$m[1], $user);
}

// Export
elseif (preg_match('#^/export/([a-zA-Z0-9_-]+)$#', $path, $m) && $method === 'GET') {
    ExportController::exportData($m[1], $user);
}

// Sync
elseif ($path === '/sync/trigger' && $method === 'POST') {
    SyncController::trigger($user);
}
elseif ($path === '/sync/status' && $method === 'GET') {
    SyncController::status();
}
elseif ($path === '/sync/logs' && $method === 'GET') {
    SyncController::logs($user);
}

// Sheet Registry
elseif ($path === '/registry/sheets' && $method === 'GET') {
    RegistryController::listSheets($user);
}
elseif ($path === '/registry/sheets' && $method === 'POST') {
    RegistryController::createSheet($user);
}
elseif (preg_match('#^/registry/sheets/(\d+)/toggle$#', $path, $m) && $method === 'POST') {
    RegistryController::toggleSheet((int)$m[1], $user);
}
elseif (preg_match('#^/registry/sheets/(\d+)$#', $path, $m) && $method === 'DELETE') {
    RegistryController::deleteSheet((int)$m[1], $user);
}
elseif ($path === '/registry/tracker-types' && $method === 'POST') {
    RegistryController::createTrackerType($user);
}

// Users
elseif ($path === '/users' && $method === 'GET') {
    UserController::listUsers($user);
}
elseif ($path === '/users' && $method === 'POST') {
    UserController::createUser($user);
}
elseif (preg_match('#^/users/(\d+)/reset-password$#', $path, $m) && $method === 'POST') {
    UserController::resetPassword((int)$m[1], $user);
}

// Fallback: 404
else {
    Response::notFound("Endpoint not found: {$method} {$path}");
}
