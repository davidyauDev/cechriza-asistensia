<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComprobanteGasto extends Model
{
    protected $connection = 'external_mysql';

    protected $table = 'comprobantes_gasto';

    protected $fillable = [
        'solicitud_gasto_id',
        'tipo',
        'numero',
        'monto',
        'archivo_url',
    ];

    public $timestamps = false;

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function solicitudGasto()
    {
        return $this->belongsTo(SolicitudGasto::class, 'solicitud_gasto_id', 'id');
    }
}
