<?php

namespace App\Models\SolicitudGasto;

use App\Models\Area;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;

class SolicitudGasto extends Model
{
    protected $connection = 'external_mysql';

    protected $table = 'solicitudes_gasto';

    protected $fillable = [
        'staff_id',
        'id_area',
        'motivo',
        'monto_estimado',
        'monto_real',
        'estado_id',
        'estado',
        'fecha_solicitud',
        'fecha_aprobacion',
        'fecha_reembolso',
    ];

    public $timestamps = false;

    protected $casts = [
        'monto_estimado' => 'decimal:2',
        'monto_real' => 'decimal:2',
        'fecha_solicitud' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_reembolso' => 'datetime',
    ];

    public function solicitante()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'id_area', 'id_area');
    }

    public function detalles()
    {
        return $this->hasMany(SolicitudGastoDetalle::class, 'solicitud_gasto_id', 'id');
    }

    public function comprobantes()
    {
        return $this->hasMany(ComprobanteGasto::class, 'solicitud_gasto_id', 'id');
    }

    public function reembolsos()
    {
        return $this->hasMany(Reembolso::class, 'solicitud_gasto_id', 'id');
    }

    public function seguimientos()
    {
        return $this->hasMany(SeguimientoSolicitudGasto::class, 'solicitud_gasto_id', 'id');
    }
}
