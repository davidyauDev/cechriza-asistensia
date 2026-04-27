<?php

namespace App\Models\SolicitudGasto;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;

class SeguimientoSolicitudGasto extends Model
{
    protected $connection = 'external_mysql';

    protected $table = 'seguimientos_solicitud_gasto';

    protected $fillable = [
        'solicitud_gasto_id',
        'estado_anterior',
        'estado_nuevo',
        'comentario',
        'staff_id',
        'fecha',
    ];

    public $timestamps = false;

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function solicitudGasto()
    {
        return $this->belongsTo(SolicitudGasto::class, 'solicitud_gasto_id', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
