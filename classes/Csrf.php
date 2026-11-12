<?php
/**
 * Csrf — generates and validates per-session CSRF tokens.
 * Every state-changing form in the app includes csrf_field().
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    public static function validate(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Call at the top of any POST handler; halts the request on failure. */
    public static function verifyOrDie(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!self::validate($token)) {
            http_response_code(419);
            die('Your session expired or the form token was invalid. Please go back and try again.');
        }
    }
}
