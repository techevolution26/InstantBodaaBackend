<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model {
    protected $fillable = [
        'user_id', 'provider_id',
        'pickup_lat', 'pickup_lng',
        'dropoff_lat', 'dropoff_lng',
        'status',
        'requested_at', 'assigned_at', 'delivered_at',
        'fee_estimate', 'fee_actual',
        'package_desc',
    ];

    protected $casts = [
        'pickup_lat'     => 'decimal:7',
        'pickup_lng'     => 'decimal:7',
        'dropoff_lat'    => 'decimal:7',
        'dropoff_lng'    => 'decimal:7',
        'requested_at'   => 'datetime',
        'assigned_at'    => 'datetime',
        'delivered_at'   => 'datetime',
        'fee_estimate'   => 'decimal:2',
        'fee_actual'     => 'decimal:2',
    ];

    public function user() {
        return $this->belongsTo( User::class, 'user_id' );
    }

    public function provider() {
        return $this->belongsTo( User::class, 'provider_id' );
    }
}
