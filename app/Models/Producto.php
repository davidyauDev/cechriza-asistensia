<?php

namespace App\Models;

use App\Models\SolicitudGasto\SolicitudGastoDetalle;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $connection = 'external_mysql';

    protected $table = 'productos';

    protected $primaryKey = 'id_producto';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'precio_referencia',
    ];

    protected $casts = [
        'precio_referencia' => 'decimal:2',
    ];

    public function detallesGasto()
    {
        return $this->hasMany(SolicitudGastoDetalle::class, 'id_producto', 'id_producto');
    }
}
