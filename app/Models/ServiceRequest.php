<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model {
    protected $fillable = [
        'user_id', 'provider_id', 'pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng',
        'fare_estimate', 'fare_actual', 'status', 'started_at', 'completed_at'
    ];

    protected $casts = [
        'pickup_lat'     => 'decimal:7',
        'pickup_lng'     => 'decimal:7',
        'dropoff_lat'    => 'decimal:7',
        'dropoff_lng'    => 'decimal:7',
        'requested_at'   => 'datetime',
        'assigned_at'    => 'datetime',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'fare_estimate'  => 'decimal:2',
        'fare_actual'    => 'decimal:2',
    ];

    public function user() {
        return $this->belongsTo( User::class, 'user_id' );
    }

    public function provider() {
        // provider_id references users.id via service_providers
        return $this->belongsTo( User::class, 'provider_id' );
    }

    public function ratings() {
        return $this->hasMany( Rating::class, 'request_id' );
    }
}
