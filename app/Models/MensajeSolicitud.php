<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MensajeSolicitud extends Model
{
    protected $connection = 'mysql_external';

    protected $table = 'mensajes_solicitud';

    protected $primaryKey = 'id_mensaje';

    public $timestamps = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'id_solicitud',
        'staff_id',
        'mensaje',
        'tipo',
        'archivo_url',
        'archivo_nombre',
        'archivo_mime',
        'archivo_size',
        'leido',
    ];

    protected $casts = [
        'id_solicitud' => 'integer',
        'staff_id' => 'integer',
        'archivo_size' => 'integer',
        'leido' => 'boolean',
        'created_at' => 'datetime',
    ];
}
