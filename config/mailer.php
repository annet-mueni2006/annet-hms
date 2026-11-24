<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends an email through Gmail SMTP using an App Password stored in .env.
 * Returns true on success, false on failure (error written to storage/mail.log).
 */
function send_mail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = env('MAIL_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('MAIL_USER');
        $mail->Password   = env('MAIL_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)env('MAIL_PORT', '587');
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom((string)env('MAIL_FROM', env('MAIL_USER')), (string)env('MAIL_FROM_NAME', 'Annet Healthcare'));
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $htmlBody)));
        $mail->send();
        return true;
    } catch (Exception $e) {
        @file_put_contents(dirname(__DIR__) . '/storage/mail.log', '[' . date('c') . '] ' . $mail->ErrorInfo . PHP_EOL, FILE_APPEND);
        return false;
    }
}

function send_otp_email(string $email, string $name, string $otp): bool
{
    $ttl  = (int)env('OTP_TTL_MINUTES', '10');
    $app  = e((string)env('APP_NAME', 'Annet Healthcare'));
    $html = "<div style=\"font-family:Arial,sans-serif;max-width:480px;margin:auto\">"
          . "<h2 style=\"color:#0f766e\">{$app}</h2>"
          . "<p>Hello " . e($name) . ",</p>"
          . "<p>Use this code to finish creating your account:</p>"
          . "<p style=\"font-size:32px;letter-spacing:8px;font-weight:bold;color:#0f766e\">{$otp}</p>"
          . "<p>The code expires in {$ttl} minutes. If you did not request it, ignore this email.</p></div>";
    return send_mail($email, $name, "Your {$app} verification code", $html);
}