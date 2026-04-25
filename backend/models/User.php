<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'phone',
        'national_id',
        'department',
        'faculty',
        'specialization',
        'role',
        'status',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'id'         => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class, 'user_id');
    }

    public function avatars(): HasMany
    {
        return $this->hasMany(UserAvatar::class, 'user_id');
    }

    public function activeAvatar(): HasOne
    {
        return $this->hasOne(UserAvatar::class, 'user_id')
            ->where('is_active', 1)
            ->orderByDesc('id');
    }
}
