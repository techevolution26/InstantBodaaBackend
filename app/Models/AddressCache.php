<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddressCache extends Model {
    protected $table = 'address_cache';

    protected $casts = [
        'lat'        => 'string',
        'lon'        => 'string',
        'components' => 'array',
    ];

    protected $fillable = [
        'lat', 'lon', 'formatted', 'components',
    ];

}
