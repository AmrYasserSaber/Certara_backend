<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class RefreshToken extends Model
{
    protected $table = 'refresh_tokens';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'jti',
        'expires_at',
        'revoked_at',
        'created_at',
    ];

    protected $casts = [
        'id'         => 'integer',
        'user_id'    => 'integer',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

