<?php
declare(strict_types=1);

final class Permissions {
    private const MAP = [
        'dashboard' => ['Administrator','Staff','Therapist'],
        'appointments' => ['Administrator','Staff','Therapist'],
        'walkins' => ['Administrator','Staff'], 'customers' => ['Administrator','Staff','Therapist'],
        'services' => ['Administrator','Staff'], 'therapists' => ['Administrator','Staff'],
        'pos' => ['Administrator','Staff'], 'transactions' => ['Administrator','Staff'],
        'inventory' => ['Administrator','Staff'], 'expenses' => ['Administrator'],
        'reports' => ['Administrator','Staff'], 'users' => ['Administrator'],
        'logs' => ['Administrator'], 'settings' => ['Administrator'],
        'receipt' => ['Administrator','Staff'], 'profile' => ['Administrator','Staff','Therapist'], 'backup' => ['Administrator'], 'session' => ['Administrator','Staff','Therapist'],
    ];
    public static function can(string $page): bool { return in_array(current_user()['role'] ?? '', self::MAP[$page] ?? [], true); }
    public static function require(string $page): void { if (!self::can($page)) { http_response_code(403); exit('You are not authorized to access this module.'); } }
}
