<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Research extends Model
{
    protected $table = 'research';

    protected $fillable = [
        'student_id',
        'title',
        'principal_investigator',
        'co_investigators',
        'department',
        'faculty',
        'status',
        'serial_number',
    ];

    protected $casts = [
        'id'               => 'integer',
        'student_id'       => 'integer',
        'co_investigators' => 'array',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'research_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'research_id');
    }
}
