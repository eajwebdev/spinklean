<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoDeliveryItem extends Model
{
    protected $fillable = [
        'po_delivery_id',
        'po_transaction_id',
        'job_order_id',
        'description',
        'service_category',
        'unit_price',
        'quantity',
        'total',
        'amount',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'total' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function delivery()
    {
        return $this->belongsTo(PoDelivery::class, 'po_delivery_id');
    }

    public function poTransaction()
    {
        return $this->belongsTo(PoTransaction::class);
    }

    public function jobOrder()
    {
        return $this->belongsTo(JobOrder::class);
    }
}
