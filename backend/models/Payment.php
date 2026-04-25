<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'research_id',
        'amount',
        'currency',
        'type',
        'status',
        'gateway',
        'gateway_ref',
        'checkout_url',
        'paid_at',
    ];

    protected $casts = [
        'id'          => 'integer',
        'research_id' => 'integer',
        'amount'      => 'float',
        'paid_at'     => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function research()
    {
        return $this->belongsTo(Research::class, 'research_id');
    }
}
