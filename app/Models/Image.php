<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = ['attendance_id', 'path'];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}