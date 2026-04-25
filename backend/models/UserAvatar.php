<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class UserAvatar extends Model
{
    protected $table = 'user_avatars';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'file_id',
        'file_path',
        'file_url',
        'original_name',
        'size_bytes',
        'mime_type',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'size_bytes' => 'int',
        'user_id' => 'int',
    ];

    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }
}
