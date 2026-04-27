<?php

namespace App\Models\SolicitudGasto;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;

class SolicitudGastoDetalle extends Model
{
    protected $connection = 'external_mysql';

    protected $table = 'solicitud_gasto_detalles';

    protected $fillable = [
        'solicitud_gasto_id',
        'id_producto',
        'cantidad',
        'precio_estimado',
        'precio_real',
        'descripcion_adicional',
        'ruta_imagen',
    ];

    public $timestamps = false;

    protected $casts = [
        'precio_estimado' => 'decimal:2',
        'precio_real' => 'decimal:2',
    ];

    public function solicitudGasto()
    {
        return $this->belongsTo(SolicitudGasto::class, 'solicitud_gasto_id', 'id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }
}
