<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Delivery;

class ServiceProvider extends Model {
    public $incrementing = false;
    // PK is user_id
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'user_id', 'bike_model', 'plate_number',
        'avg_rating', 'status',
        'license_url', 'insurance_url', 'additional_image_urls',
    ];

    protected $casts = [
        'avg_rating'            => 'decimal:2',
        'additional_image_urls' => 'array',
    ];

    public function user() {
        return $this->belongsTo( User::class, 'user_id' );
    }

    public function locations() {
        return $this->hasMany( ProviderLocation::class, 'provider_id' );
    }

    public function rides() {
        return $this->hasMany( ServiceRequest::class, 'provider_id' );
    }

    public function deliveries() {
        return $this->hasMany( Delivery::class, 'provider_id' );
    }
}
