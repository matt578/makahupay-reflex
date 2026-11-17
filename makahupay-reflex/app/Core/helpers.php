<?php
declare(strict_types=1);

function db(): PDO { return Database::connection(); }
function h(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(mixed $amount): string { return '₱' . number_format((float)$amount, 2); }
function app_url(string $path = ''): string {
    $base = rtrim(getenv('APP_URL') ?: '', '/');
    if ($base === '') $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}
function redirect(string $url): never { header('Location: ' . $url); exit; }
function flash(string $type, string $message): void { $_SESSION['_flash'][] = ['type'=>$type,'message'=>$message]; }
function take_flashes(): array { $items = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $items; }
function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">'; }
function verify_csrf(): void {
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) { http_response_code(419); exit('Your session token expired. Please go back, refresh, and try again.'); }
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function current_user_id(): ?int { return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null; }
function is_admin(): bool { return (current_user()['role'] ?? '') === 'Administrator'; }
function is_staff(): bool { return in_array(current_user()['role'] ?? '', ['Administrator','Staff'], true); }
function is_therapist(): bool { return (current_user()['role'] ?? '') === 'Therapist'; }
function require_login(): void { if (!current_user()) redirect(app_url('index.php?page=login')); }
function require_roles(array $roles): void { require_login(); if (!in_array(current_user()['role'] ?? '', $roles, true)) { http_response_code(403); exit('You are not authorized to access this page.'); } }
function input(string $key, mixed $default = null): mixed { return $_POST[$key] ?? $_GET[$key] ?? $default; }
function post_string(string $key, int $max = 255, bool $required = false): string {
    $value = trim((string)($_POST[$key] ?? ''));
    if ($required && $value === '') throw new InvalidArgumentException("{$key} is required.");
    if (mb_strlen($value) > $max) throw new InvalidArgumentException("{$key} is too long.");
    return $value;
}
function post_int(string $key, int $min = 0): int {
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value < $min) throw new InvalidArgumentException("Invalid {$key}.");
    return (int)$value;
}
function post_money(string $key, bool $allowZero = true): float {
    $raw = $_POST[$key] ?? null;
    if (!is_numeric($raw)) throw new InvalidArgumentException("Invalid {$key}.");
    $value = round((float)$raw, 2);
    if (!is_finite($value) || $value < 0 || (!$allowZero && $value <= 0)) throw new InvalidArgumentException("Invalid {$key}.");
    return $value;
}
function valid_date(string $date): bool { $d = DateTime::createFromFormat('Y-m-d', $date); return $d && $d->format('Y-m-d') === $date; }
function create_code(string $prefix): string { return $prefix . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); }
function setting(string $key, string $default = ''): string { static $cache = null; if ($cache === null) { $cache = []; foreach (db()->query('SELECT setting_key,setting_value FROM system_settings') as $r) $cache[$r['setting_key']] = $r['setting_value']; } return (string)($cache[$key] ?? $default); }
function json_response(array $data, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function selected(string|int|null $value, string|int|null $expected): string { return (string)$value === (string)$expected ? 'selected' : ''; }
function checked(bool $condition): string { return $condition ? 'checked' : ''; }
function date_label(?string $date, string $format = 'M d, Y'): string { return $date ? date($format, strtotime($date)) : '—'; }
function log_activity(string $action, string $description): void { Activity::log($action, $description); }
