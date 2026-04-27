<?php

namespace App\Models\SolicitudGasto;

use Illuminate\Database\Eloquent\Model;

class Reembolso extends Model
{
    protected $connection = 'external_mysql';

    protected $table = 'reembolsos';

    protected $fillable = [
        'solicitud_gasto_id',
        'monto',
        'metodo_pago',
        'fecha',
    ];

    public $timestamps = false;

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function solicitudGasto()
    {
        return $this->belongsTo(SolicitudGasto::class, 'solicitud_gasto_id', 'id');
    }
}
