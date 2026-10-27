<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Delivery Receipt: one delivery trip of finished PO laundry to a customer.
 */
class PoDelivery extends Model
{
    protected $fillable = [
        'branch_id',
        'customer_id',
        'dr_number',
        'delivered_on',
        'amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'delivered_on' => 'date',
        'amount' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PoDeliveryItem::class);
    }
}
