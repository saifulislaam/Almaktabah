<?php
/**
 * Auth — password-based login/logout, session + "remember me" handling,
 * email-verification gate, and brute-force rate limiting.
 *
 * Regular readers register with email + password on the public register.php
 * and must verify their email via a one-time code (see Otp.php) before they
 * can sign in. Admin/sub-admin accounts are created directly from the admin
 * dashboard (admin/admins.php) and are marked pre-verified since the main
 * admin is vouching for them directly — no OTP round-trip needed there.
 */
class Auth
{
    // ---------------------------------------------------------------
    // Registration
    // ---------------------------------------------------------------
    public static function register(string $username, string $email, string $password, bool $preVerified = false): array
    {
        $username = trim($username);
        $email    = trim(strtolower($email));

        if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
            return ['success' => false, 'error' => 'Username must be 3-50 characters (letters, numbers, . and _ only).'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please enter a valid email address.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }

        $exists = Database::fetchOne('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email]);
        if ($exists) {
            return ['success' => false, 'error' => 'That username or email is already registered.'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $id = Database::insert(
            'INSERT INTO users (username, email, password, email_verified, display_name) VALUES (?, ?, ?, ?, ?)',
            [$username, $email, $hash, $preVerified ? 1 : 0, $username]
        );

        return ['success' => true, 'user_id' => $id, 'email' => $email];
    }

    // ---------------------------------------------------------------
    // Login / logout (regular users, subadmins, and admin all share this —
    // the admin area additionally checks role in Admin::requireAdmin()).
    // ---------------------------------------------------------------
    public static function attemptLogin(string $identifier, string $password, bool $remember = false): array
    {
        $identifier = trim($identifier);
        $ip = self::clientIp();

        $lock = self::checkLock($identifier, $ip);
        if ($lock !== null) {
            return ['success' => false, 'error' => 'Too many attempts. Try again in ' . $lock . ' minute(s).'];
        }

        $user = Database::fetchOne(
            'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$identifier, strtolower($identifier)]
        );

        if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
            self::registerFailedAttempt($identifier);
            self::registerFailedAttempt($ip);
            return ['success' => false, 'error' => 'Incorrect email or password.'];
        }
        if ($user['role'] === 'user' && !$user['email_verified']) {
            return ['success' => false, 'error' => 'Please verify your email before signing in.', 'needs_verification' => true, 'email' => $user['email']];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'error' => 'This account is ' . $user['status'] . '. Contact the site administrator.'];
        }

        self::clearAttempts($identifier);
        self::clearAttempts($ip);
        self::startSession($user);

        Database::execute('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);

        if ($remember) {
            self::setRememberCookie((int) $user['id']);
        }

        return ['success' => true, 'user' => $user];
    }

    public static function startSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id']      = (int) $user['id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['profile_image']= $user['profile_image'];
        $_SESSION['theme']        = $user['theme_preference'];
        $_SESSION['permissions']  = $user['permissions'] ?? null;
        if (!empty($user['language_preference'])) {
            $_SESSION['language_preference'] = $user['language_preference'];
        }
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['user_id'])) {
            Database::execute('UPDATE users SET remember_token = NULL WHERE id = ?', [$_SESSION['user_id']]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path']);
        }
        setcookie('remember_me', '', time() - 42000, '/');
        session_destroy();
    }

    public static function check(): bool
    {
        if (!empty($_SESSION['user_id'])) {
            return true;
        }
        return self::loginFromRememberCookie();
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['admin', 'subadmin'], true);
    }

    public static function requireLogin(string $redirectTo = 'login.php'): void
    {
        if (!self::check()) {
            $return = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . url($redirectTo . '?return=' . $return));
            exit;
        }
    }

    public static function currentUser(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return Database::fetchOne('SELECT * FROM users WHERE id = ?', [self::id()]);
    }

    // ---------------------------------------------------------------
    // Remember-me cookie
    // ---------------------------------------------------------------
    private static function setRememberCookie(int $userId): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $hash = hash('sha256', $validator);

        Database::execute('UPDATE users SET remember_token = ? WHERE id = ?', [$selector . ':' . $hash, $userId]);

        setcookie(
            'remember_me',
            $selector . ':' . $validator,
            time() + (REMEMBER_ME_DAYS * 86400),
            '/', '', false, true
        );
    }

    private static function loginFromRememberCookie(): bool
    {
        if (empty($_COOKIE['remember_me']) || strpos($_COOKIE['remember_me'], ':') === false) {
            return false;
        }
        [$selector, $validator] = explode(':', $_COOKIE['remember_me'], 2);

        $user = Database::fetchOne("SELECT * FROM users WHERE remember_token LIKE ?", [$selector . ':%']);
        if (!$user) {
            return false;
        }
        [, $storedHash] = explode(':', $user['remember_token'], 2);
        if (!hash_equals($storedHash, hash('sha256', $validator))) {
            return false;
        }
        if ($user['status'] !== 'active') {
            return false;
        }
        self::startSession($user);
        self::setRememberCookie((int) $user['id']); // rotate
        return true;
    }

    // ---------------------------------------------------------------
    // Rate limiting
    // ---------------------------------------------------------------
    private static function checkLock(string $identifier, string $ip): ?int
    {
        foreach ([$identifier, $ip] as $key) {
            $row = Database::fetchOne('SELECT locked_until FROM login_attempts WHERE identifier = ?', [$key]);
            if ($row && $row['locked_until'] && strtotime($row['locked_until']) > time()) {
                return (int) ceil((strtotime($row['locked_until']) - time()) / 60);
            }
        }
        return null;
    }

    private static function registerFailedAttempt(string $identifier): void
    {
        $row = Database::fetchOne('SELECT * FROM login_attempts WHERE identifier = ?', [$identifier]);
        if (!$row) {
            Database::execute(
                'INSERT INTO login_attempts (identifier, attempts, last_attempt) VALUES (?, 1, NOW())',
                [$identifier]
            );
            return;
        }
        $attempts = (int) $row['attempts'] + 1;
        $lockedUntil = null;
        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $lockedUntil = date('Y-m-d H:i:s', time() + (LOGIN_LOCKOUT_MINUTES * 60));
            $attempts = 0;
        }
        Database::execute(
            'UPDATE login_attempts SET attempts = ?, last_attempt = NOW(), locked_until = ? WHERE identifier = ?',
            [$attempts, $lockedUntil, $identifier]
        );
    }

    private static function clearAttempts(string $identifier): void
    {
        Database::execute('DELETE FROM login_attempts WHERE identifier = ?', [$identifier]);
    }

    public static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /** Sets a new password (settings.php change-password, and reset-password.php). */
    public static function setPassword(int $userId, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        Database::execute('UPDATE users SET password = ?, remember_token = NULL WHERE id = ?', [password_hash($newPassword, PASSWORD_BCRYPT), $userId]);
        return ['success' => true];
    }

    public static function markEmailVerified(string $email): void
    {
        Database::execute('UPDATE users SET email_verified = 1 WHERE email = ?', [$email]);
    }
}
