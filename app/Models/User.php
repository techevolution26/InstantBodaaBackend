<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'is_provider', 'dob', 'blood_group', 'address', 'avatar_url',
    ];

    // protected $appends = [ 'avatar_url' ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'is_provider' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    /** If this user is a service provider: */

    public function providerProfile() {
        return $this->hasOne( ServiceProvider::class, 'user_id' );
    }

    /** All rides they’ve requested: */

    public function ridesRequested() {
        return $this->hasMany( ServiceRequest::class, 'user_id' );
    }

    /** All rides they’ve been assigned: */

    public function ridesAssigned() {
        return $this->hasMany( ServiceRequest::class, 'provider_id' );
    }

    /** All deliveries they’ve requested: */

    public function deliveriesRequested() {
        return $this->hasMany( Delivery::class, 'user_id' );
    }

    /** All deliveries they’ve been assigned: */

    public function deliveriesAssigned() {
        return $this->hasMany( Delivery::class, 'provider_id' );
    }

    /** Ratings they’ve given: */

    public function ratingsGiven() {
        return $this->hasMany( Rating::class, 'rater_id' );
    }

    /** Ratings they’ve received: */

    public function ratingsReceived() {
        return $this->hasMany( Rating::class, 'ratee_id' );
    }

    /** Device tokens for push notifications: */

    public function deviceTokens() {
        return $this->hasMany( DeviceToken::class );
    }
}
