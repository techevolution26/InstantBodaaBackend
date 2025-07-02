<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderLocation extends Model {
    public $timestamps = false;

    protected $fillable = [
        'provider_id', 'latitude', 'longitude', 'online', 'accuracy', 'recorded_at',
    ];

    protected $casts = [
        'latitude'    => 'decimal:7',
        'longitude'   => 'decimal:7',
        'accuracy'    => 'float',
        'recorded_at' => 'datetime',
    ];

    public function provider() {
        return $this->belongsTo( ServiceProvider::class, 'provider_id' );
    }
}
