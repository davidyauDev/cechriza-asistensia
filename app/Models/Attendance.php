<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'timestamp',
        'latitude',
        'longitude',
        'notes',
        'device_model',
        'battery_percentage',
        'signal_strength',
        'network_type',
        'is_internet_available',
        'type',
    ];

    protected $casts = [
        'is_internet_available' => 'boolean',
        'timestamp' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function image()
    {
        return $this->hasOne(Image::class);
    }
}
