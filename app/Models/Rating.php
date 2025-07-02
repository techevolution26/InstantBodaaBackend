<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model {
    protected $fillable = [
        'request_id', 'rater_id', 'ratee_id', 'stars', 'comment',
    ];

    protected $casts = [
        'stars' => 'integer',
    ];

    public function request() {
        return $this->belongsTo( ServiceRequest::class, 'request_id' );
    }

    public function rater() {
        return $this->belongsTo( User::class, 'rater_id' );
    }

    public function ratee() {
        return $this->belongsTo( User::class, 'ratee_id' );
    }
}
