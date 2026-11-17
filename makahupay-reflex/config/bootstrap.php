<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim(trim($value), "\"'");
        if ($key !== '' && getenv($key) === false) putenv($key . '=' . $value);
    }
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Manila');
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('makahupay_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

$debug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

require_once $root . '/app/Core/helpers.php';
require_once $root . '/app/Core/Database.php';
require_once $root . '/app/Core/Auth.php';
require_once $root . '/app/Core/Permissions.php';
require_once $root . '/app/Core/Activity.php';
require_once $root . '/app/Controllers/Actions.php';

try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    error_log('[Makahupay DB] ' . $e->getMessage());
    http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Setup required</title></head><body style="font-family:Arial;padding:3rem"><h1>Makahupay setup required</h1><p>Configure your database in the <code>.env</code> file and import <code>database/schema.sql</code>.</p>';
    if ($debug) echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</body></html>';
    exit;
}
