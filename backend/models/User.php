<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'id_photo_front_path',
        'id_photo_back_path',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'id'         => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class, 'user_id');
    }
}
