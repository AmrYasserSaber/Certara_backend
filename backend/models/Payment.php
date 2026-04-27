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
        'paymob_order_id',
        'paymob_transaction_id',
        'paymob_integration_id',
        'paymob_method',
        'amount_cents_reported',
        'paymob_callback_payload',
        'paid_at',
    ];

    protected $casts = [
        'id'          => 'integer',
        'research_id' => 'integer',
        'amount'      => 'float',
        'paymob_order_id' => 'integer',
        'paymob_transaction_id' => 'integer',
        'paymob_integration_id' => 'integer',
        'amount_cents_reported' => 'integer',
        'paymob_callback_payload' => 'array',
        'paid_at'     => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function research()
    {
        return $this->belongsTo(Research::class, 'research_id');
    }
}
