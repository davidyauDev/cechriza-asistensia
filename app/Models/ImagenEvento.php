<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagenEvento extends Model
{
    use HasFactory;

    protected $table = 'imagenes_evento';

    protected $fillable = [
        'evento_id',
        'url_imagen',
        'descripcion',
        'orden',
        'autor',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    /**
     * Relación con el evento
     */
    public function evento()
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }
}