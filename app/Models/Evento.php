<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    use HasFactory;

    protected $table = 'eventos';

    protected $fillable = [
        'titulo',
        'descripcion',
        'fecha',
        // 'fecha_fin',
        'active'
    ];

    protected $casts = [
        // 'fecha' => 'date',
        // 'fecha_fin' => 'date',
    ];

    /**
     * Relación con imágenes del evento
     */
    public function imagenes()
    {
        return $this->hasMany(ImagenEvento::class, 'evento_id')->orderBy('orden');
    }

    /**
     * Scope para eventos activos en una fecha específica
     * Compara solo la parte de fecha ignorando la hora
     */
    public function scopeActivoEnFecha($query, $fecha)
    {
        return $query->whereDate('fecha', '=', $fecha)
            // ->whereDate('fecha_fin', '>=', $fecha)
            ->where('active', true);
    }

    /**
     * Scope para eventos programados
     */
    public function scopeProgramados($query)
    {
        return $query->where('active', 1);
    }

    /**
     * Scope para eventos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('active', 1);
    }

    /**
     * Scope para eventos finalizados
     */
    public function scopeFinalizados($query)
    {
        return $query->where('active', 0);
    }
}