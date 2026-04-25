<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Research;

final class Document extends Model
{
    protected $table = 'documents';

    public $timestamps = false; // Using uploaded_at instead of created_at/updated_at

    protected $fillable = [
        'research_id',
        'type',
        'file_path',
        'original_name',
        'size_bytes',
    ];

    protected $casts = [
        'id'          => 'integer',
        'research_id' => 'integer',
        'size_bytes'  => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function research()
    {
        return $this->belongsTo(Research::class, 'research_id');
    }
}
