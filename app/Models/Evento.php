<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    protected $table = 'culturayturismo.evento';

    protected $primaryKey = 'id_evento';

    public $timestamps = false;

    protected $casts = [
        'is_visible' => 'boolean',
        'impacto_economico' => 'decimal:6',
    ];

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'tipo',
        'organizador',
        'contacto',
        'fecha_inicio',
        'fecha_fin',
        'asistentes_estimados',
        'asistentes_reales',
        'impacto_economico',
        'observaciones',
        'is_visible',
        'id_direccion',
    ];

    // Relación con la dirección
    public function direccion()
    {
        return $this->belongsTo(DireccionGoogle::class, 'id_direccion');
    }

    // Relación con la galería de fotos (tabla unificada fotos)
    public function fotos()
    {
        return $this->hasMany(Foto::class, 'id_evento');
    }
}
