<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $connection = 'external_mysql';

    protected $table = 'ost_staff';

    protected $primaryKey = 'staff_id';

    public $timestamps = false;

    protected $fillable = [
        'dept_id',
        'role_id',
        'username',
        'firstname',
        'lastname',
        'id_departamento',
        'id_provincia',
        'id_distrito',
        'zona_tecnico',
        'id_empresa',
        'id_cargo',
        'id_area',
        'activo',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'id_area', 'id_area');
    }
}
