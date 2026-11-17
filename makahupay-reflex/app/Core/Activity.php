<?php
declare(strict_types=1);

final class Activity {
    public static function log(string $action, string $description): void {
        try { db()->prepare('INSERT INTO activity_logs (user_id,action,description,ip_address) VALUES (?,?,?,?)')->execute([current_user_id(),$action,mb_substr($description,0,255),substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45)]); }
        catch (Throwable $e) { error_log('[Activity] ' . $e->getMessage()); }
    }
}
