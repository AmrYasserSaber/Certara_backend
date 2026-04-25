<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'is_read',
        'research_id',
        'created_at',
    ];

    protected $casts = [
        'is_read'     => 'boolean',
        'user_id'     => 'integer',
        'research_id' => 'integer',
        'created_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function markRead(int $id, int $userId): bool
    {
        return self::where('id', $id)
            ->where('user_id', $userId)
            ->update(['is_read' => true]) > 0;
    }

    public static function markAllRead(int $userId): int
    {
        return self::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public static function unreadCount(int $userId): int
    {
        return self::where('user_id', $userId)->where('is_read', false)->count();
    }

    public static function getForUser(int $userId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = min(50, max(1, $limit));

        $query = self::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
        $total = (clone $query)->count();
        $items = $query->forPage($page, $limit)->get()->toArray();
        $pages = max(1, (int) ceil($total / $limit));

        return [
            'items' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
        ];
    }
}
