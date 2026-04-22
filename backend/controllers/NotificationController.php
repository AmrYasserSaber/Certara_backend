<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\Notification;

final class NotificationController extends Controller
{
    public function index(Request $request): never
    {
        $user  = $request->user();
        $page  = max(1, (int) $request->query('page', 1));
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        $query = Notification::where('user_id', $user->id)->orderByDesc('created_at');

        $total = (clone $query)->count();
        $items = $query->forPage($page, $limit)->get()->toArray();

        $this->ok([
            'items'        => $items,
            'unread_count' => Notification::unreadCount((int) $user->id),
        ], [
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
        ]);
    }

    public function markRead(Request $request): never
    {
        $user = $request->user();
        $id   = (int) $request->param('id');

        if ($id <= 0) {
            $this->fail('Invalid notification id.', 422, 'validation_error');
        }

        $changed = Notification::markRead($id, (int) $user->id);
        if (!$changed) {
            $this->fail('Notification not found.', 404, 'not_found');
        }

        $this->ok(['id' => $id, 'is_read' => true]);
    }

    public function markAllRead(Request $request): never
    {
        $user     = $request->user();
        $affected = Notification::markAllRead((int) $user->id);

        $this->ok(['affected' => $affected]);
    }
}
