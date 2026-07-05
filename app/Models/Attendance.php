<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'branch_id',
        'user_id',
        'work_date',
        'time_in',
        'time_in_latitude',
        'time_in_longitude',
        'time_in_photo_path',
        'time_out',
        'time_out_latitude',
        'time_out_longitude',
        'time_out_photo_path',
        'status',
    ];

    protected $casts = [
        'work_date' => 'date',
        'time_in' => 'datetime',
        'time_out' => 'datetime',
        'time_in_latitude' => 'decimal:7',
        'time_in_longitude' => 'decimal:7',
        'time_out_latitude' => 'decimal:7',
        'time_out_longitude' => 'decimal:7',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
