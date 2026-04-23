<?php

declare(strict_types=1);

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * MAIL_DRIVER=smtp uses PHPMailer; any other value logs to storage/logs/mail.log
 * so the team can develop without an SMTP host.
 */
final class EmailHelper
{
    public static function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        $driver = strtolower((string) env('MAIL_DRIVER', 'log'));

        if ($driver !== 'smtp') {
            return self::logToFile('email', [
                'to'      => $to,
                'subject' => $subject,
                'body'    => $body,
            ]);
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = (string) env('MAIL_HOST', 'localhost');
            $mail->Port       = (int) env('MAIL_PORT', 587);
            $mail->SMTPAuth   = (bool) env('MAIL_USERNAME');
            $mail->Username   = (string) env('MAIL_USERNAME', '');
            $mail->Password   = (string) env('MAIL_PASSWORD', '');
            $mail->SMTPSecure = (string) env('MAIL_ENCRYPTION', 'tls');
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(
                (string) env('MAIL_FROM_ADDRESS', 'no-reply@irb.local'),
                (string) env('MAIL_FROM_NAME', 'IRB System')
            );
            $mail->addAddress($to);
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $sent = $mail->send();
            self::logToFile('email_sent', [
                'to'      => $to,
                'subject' => $subject,
                'driver'  => 'smtp',
                'host'    => (string) env('MAIL_HOST', 'localhost'),
                'port'    => (int) env('MAIL_PORT', 587),
                'secure'  => (string) env('MAIL_ENCRYPTION', 'tls'),
            ]);
            return $sent;
        } catch (\Throwable $e) {
            self::logToFile('email_error', [
                'to'      => $to,
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    private static function logToFile(string $kind, array $payload): bool
    {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = '[' . date('c') . "] {$kind} " . json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return (bool) @file_put_contents($dir . '/mail.log', $line, FILE_APPEND);
    }
}
