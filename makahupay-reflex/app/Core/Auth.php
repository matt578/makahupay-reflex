<?php
declare(strict_types=1);

final class Auth {
    public static function attempt(string $email, string $password): bool {
        $email=mb_strtolower(trim($email));$ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
        $limit=db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE email=? AND ip_address=? AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');$limit->execute([$email,$ip]);
        if((int)$limit->fetchColumn()>=10)return false;
        $stmt = db()->prepare('SELECT u.*,r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=? LIMIT 1');
        $stmt->execute([$email]); $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            db()->prepare('INSERT INTO login_attempts (email,ip_address) VALUES (?,?)')->execute([$email,$ip]);
            Activity::log('login_failed','Failed sign-in attempt');
            return false;
        }
        db()->prepare('DELETE FROM login_attempts WHERE email=? AND ip_address=?')->execute([$email,$ip]);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT),$user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>(int)$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role_name'],'therapist_id'=>$user['therapist_id'] ? (int)$user['therapist_id'] : null];
        db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
        Activity::log('login', 'User signed in');
        return true;
    }
    public static function logout(): void { Activity::log('logout', 'User signed out'); $_SESSION = []; if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']); } session_destroy(); }
}
