<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Logger;
use App\Models\Notification;

final class NotificationController extends Controller
{
    public function index(Request $request): never
    {
        $user  = $request->user();
        $page  = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 20);

        Logger::info('Notifications list requested', [
            'user_id' => (int) $user->id,
            'page' => $page,
            'limit' => $limit,
        ]);

        $result = Notification::getForUser((int) $user->id, $page, $limit);
        $items = $result['items'];
        $meta = $result['meta'];

        $this->ok([
            'items'        => $items,
            'unread_count' => Notification::unreadCount((int) $user->id),
        ], $meta);
    }

    public function markRead(Request $request): never
    {
        $user = $request->user();
        $id   = (int) $request->param('id');

        if ($id <= 0) {
            $this->fail('معرف التنبيه غير صالح.', 422, 'validation_error');
        }

        $changed = Notification::markRead($id, (int) $user->id);
        if (!$changed) {
            Logger::warning('Notification markRead failed: not found', [
                'user_id' => (int) $user->id,
                'notification_id' => $id,
            ]);
            $this->fail('التنبيه غير موجود.', 404, 'not_found');
        }

        Logger::info('Notification marked as read', [
            'user_id' => (int) $user->id,
            'notification_id' => $id,
        ]);

        $this->ok(['id' => $id, 'is_read' => true]);
    }

    public function markAllRead(Request $request): never
    {
        $user     = $request->user();
        $affected = Notification::markAllRead((int) $user->id);

        Logger::info('Notifications markAllRead', [
            'user_id' => (int) $user->id,
            'affected' => $affected,
        ]);

        $this->ok(['affected' => $affected]);
    }
}
