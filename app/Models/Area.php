<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $connection = 'external_mysql';

    protected $table = 'area';

    protected $primaryKey = 'id_area';

    public $timestamps = false;

    protected $fillable = [
        'descripcion_area',
    ];

    public function solicitudesGasto()
    {
        return $this->hasMany(SolicitudGasto::class, 'id_area', 'id_area');
    }
}
