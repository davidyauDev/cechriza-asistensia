<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserFcmToken extends Model
{
    protected $connection = 'mysql_external';

    protected $table = 'staff_fcm_tokens';

    protected $fillable = [
        'staff_id',
        'token',
        'is_active',
    ];

    protected $casts = [
        'staff_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
