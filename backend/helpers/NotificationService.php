<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;

/**
 * One-call notification entrypoint:
 *   NotificationService::notify($userId, NotificationType::X, $title, $message, $researchId);
 * Creates an in-app row, then fans out to email + SMS (best-effort).
 */
final class NotificationService
{
    public static function notify(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?int $researchId = null
    ): Notification {
        $notification = Notification::create([
            'user_id'     => $userId,
            'type'        => $type,
            'title'       => $title,
            'message'     => $message,
            'is_read'     => false,
            'research_id' => $researchId,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $user = User::find($userId);
        if ($user === null) {
            return $notification;
        }

        if (!empty($user->email)) {
            EmailHelper::send(
                (string) $user->email,
                $title,
                self::buildEmailBody($title, $message)
            );
        }

        if (!empty($user->phone)) {
            SMSHelper::send((string) $user->phone, $title . ' - ' . $message);
        }

        return $notification;
    }

    private static function buildEmailBody(string $title, string $message): string
    {
        $title   = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        return <<<HTML
<!doctype html>
<html lang="ar" dir="rtl">
  <body style="font-family: Tahoma, Arial, sans-serif; background:#f6f6f6; padding:20px;">
    <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:24px;">
      <h2 style="color:#0b3d91;margin:0 0 16px;">{$title}</h2>
      <p style="line-height:1.7;color:#333;">{$message}</p>
      <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
      <p style="color:#888;font-size:12px;">IRB Digital System — automated notification.</p>
    </div>
  </body>
</html>
HTML;
    }
}
