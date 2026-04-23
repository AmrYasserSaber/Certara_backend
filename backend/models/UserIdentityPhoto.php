<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class UserIdentityPhoto extends Model
{
    protected $table = 'user_identity_photos';

    protected $fillable = [
        'user_id',
        'type',
        'file_id',
        'file_path',
        'file_url',
        'original_name',
        'size_bytes',
        'mime_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'size_bytes' => 'int',
        'user_id' => 'int',
    ];
}
