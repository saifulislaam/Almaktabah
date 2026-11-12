<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once ROOT_PATH . '/libraries/PHPMailer/Exception.php';
require_once ROOT_PATH . '/libraries/PHPMailer/SMTP.php';
require_once ROOT_PATH . '/libraries/PHPMailer/PHPMailer.php';

/**
 * Mailer — thin wrapper around PHPMailer for transactional email
 * (OTP verification codes, password reset codes). Vendored directly from
 * PHPMailer's GitHub source (libraries/PHPMailer/) rather than pulled in
 * via Composer, so the project still deploys with a plain file copy.
 *
 * Credentials live in config/config.php as SMTP_* constants — never
 * hardcoded here, so they're easy to rotate without touching this class.
 */
class Mailer
{
    public static function sendOtp(string $toEmail, string $code, string $purpose = 'verify_email'): array
    {
        $heading = $purpose === 'reset_password' ? 'Reset your password' : 'Verify your email';
        $intro   = $purpose === 'reset_password'
            ? 'Use this code to reset your ' . SITE_NAME . ' password:'
            : 'Use this code to verify your email address and finish creating your ' . SITE_NAME . ' account:';

        $subject = $purpose === 'reset_password'
            ? 'Your ' . SITE_NAME . ' password reset code'
            : 'Your ' . SITE_NAME . ' verification code';

        return self::send($toEmail, $subject, self::otpTemplate($heading, $intro, $code));
    }

    private static function send(string $to, string $subject, string $html): array
    {
        if (SMTP_PASSWORD === 'REPLACE_WITH_YOUR_16_CHARACTER_GMAIL_APP_PASSWORD') {
            error_log('Mailer: SMTP_PASSWORD is still the placeholder in config/config.php — email not sent.');
            return ['success' => false, 'error' => 'Email sending isn\'t configured yet. Please contact the site administrator.'];
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 15;

            $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));

            $mail->send();
            return ['success' => true];
        } catch (PHPMailerException | \Exception $e) {
            error_log('Mailer send failed: ' . $mail->ErrorInfo);
            return ['success' => false, 'error' => 'Could not send the email right now. Please try again in a moment.'];
        }
    }

    private static function otpTemplate(string $heading, string $intro, string $code): string
    {
        $siteName = e(SITE_NAME);
        $codeSpaced = e($code);
        $expiryMinutes = OTP_EXPIRY_MINUTES;
        return <<<HTML
        <div style="font-family:Arial,Helvetica,sans-serif;background:#F6F1E4;padding:32px 16px;">
          <div style="max-width:420px;margin:0 auto;background:#FDFBF5;border-radius:14px;padding:36px 32px;text-align:center;border:1px solid #DED2B4;">
            <div style="font-size:20px;font-weight:700;color:#1B4A5A;margin-bottom:4px;">{$siteName}</div>
            <p style="color:#8E6529;font-size:12px;letter-spacing:.05em;text-transform:uppercase;font-weight:700;margin-top:0;">Islamic Digital Library</p>
            <h2 style="color:#23313A;margin:20px 0 8px;font-size:18px;">{$heading}</h2>
            <p style="color:#5B6B72;font-size:14px;line-height:1.6;">{$intro}</p>
            <div style="font-size:34px;font-weight:700;letter-spacing:10px;color:#1B4A5A;background:#EFE7D3;border-radius:10px;padding:16px 10px;margin:22px 0;">{$codeSpaced}</div>
            <p style="color:#5B6B72;font-size:13px;">This code expires in {$expiryMinutes} minutes. If you didn't request this, you can safely ignore this email.</p>
          </div>
        </div>
        HTML;
    }
}
