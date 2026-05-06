<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoryMatchScore extends Model
{

      protected $connection = 'external_mysql';
        protected $table = 'memory_match_scores';
    protected $fillable = [
        'user_id',
        'user_name',
        'moves',
        'elapsed_seconds',
        'matched_pairs',
        'score',
        'played_at',
    ];

    protected $casts = [
        'played_at' => 'datetime',
    ];
}

