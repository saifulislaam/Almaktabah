<?php
/**
 * Otp — generates, emails, and verifies 6-digit one-time codes for
 * email verification (registration) and password reset.
 *
 * Codes are never stored in plain text (only a sha256 hash), expire after
 * OTP_EXPIRY_MINUTES, are capped at OTP_MAX_ATTEMPTS wrong guesses, and are
 * rate-limited for resend via OTP_RESEND_COOLDOWN_SECONDS.
 */
class Otp
{
    /** Generates a fresh code, stores its hash, and emails it. */
    public static function generateAndSend(string $email, string $purpose = 'verify_email'): array
    {
        $cooldown = self::cooldownRemaining($email, $purpose);
        if ($cooldown > 0) {
            return ['success' => false, 'error' => "Please wait {$cooldown}s before requesting another code.", 'cooldown' => $cooldown];
        }

        $code = str_pad((string) random_int(0, (10 ** OTP_LENGTH) - 1), OTP_LENGTH, '0', STR_PAD_LEFT);
        $hash = hash('sha256', $code);
        $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);

        Database::execute('DELETE FROM otp_verifications WHERE email = ? AND purpose = ?', [$email, $purpose]);
        Database::insert(
            'INSERT INTO otp_verifications (email, purpose, otp_hash, expires_at) VALUES (?,?,?,?)',
            [$email, $purpose, $hash, $expires]
        );

        $sent = Mailer::sendOtp($email, $code, $purpose);
        if (!$sent['success']) {
            return ['success' => false, 'error' => $sent['error']];
        }
        return ['success' => true];
    }

    /** Verifies a submitted code. On success, deletes the record so it can't be reused. */
    public static function verify(string $email, string $code, string $purpose = 'verify_email'): array
    {
        $row = Database::fetchOne(
            'SELECT * FROM otp_verifications WHERE email = ? AND purpose = ? ORDER BY created_at DESC LIMIT 1',
            [$email, $purpose]
        );
        if (!$row) {
            return ['success' => false, 'error' => 'No verification code found for this email. Please request a new one.'];
        }
        if (strtotime($row['expires_at']) < time()) {
            Database::execute('DELETE FROM otp_verifications WHERE id = ?', [$row['id']]);
            return ['success' => false, 'error' => 'This code has expired. Please request a new one.'];
        }
        if ((int) $row['attempts'] >= OTP_MAX_ATTEMPTS) {
            return ['success' => false, 'error' => 'Too many incorrect attempts. Please request a new code.'];
        }

        $code = preg_replace('/\D/', '', trim($code));
        if ($code === '' || !hash_equals($row['otp_hash'], hash('sha256', $code))) {
            Database::execute('UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            $remaining = OTP_MAX_ATTEMPTS - ((int) $row['attempts'] + 1);
            return ['success' => false, 'error' => $remaining > 0 ? "Incorrect code. {$remaining} attempt(s) remaining." : 'Too many incorrect attempts. Please request a new code.'];
        }

        Database::execute('DELETE FROM otp_verifications WHERE email = ? AND purpose = ?', [$email, $purpose]);
        return ['success' => true];
    }

    public static function cooldownRemaining(string $email, string $purpose = 'verify_email'): int
    {
        $last = Database::fetchOne(
            'SELECT created_at FROM otp_verifications WHERE email = ? AND purpose = ? ORDER BY created_at DESC LIMIT 1',
            [$email, $purpose]
        );
        if (!$last) return 0;
        $elapsed = time() - strtotime($last['created_at']);
        return max(0, OTP_RESEND_COOLDOWN_SECONDS - $elapsed);
    }

    public static function hasPending(string $email, string $purpose = 'verify_email'): bool
    {
        $row = Database::fetchOne(
            'SELECT id FROM otp_verifications WHERE email = ? AND purpose = ? AND expires_at > NOW() LIMIT 1',
            [$email, $purpose]
        );
        return $row !== null;
    }
}
