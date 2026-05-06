<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemoryMatchScore extends Model
{
    protected $connection = 'external_mysql';
    protected $table = 'memory_match_leaderboard';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'user_name',
        'best_score',
        'best_moves',
        'best_elapsed_seconds',
        'matched_pairs',
        'last_played_at',
    ];

    protected $casts = [
        'last_played_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
