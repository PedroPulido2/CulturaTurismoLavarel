<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{

    protected $table = 'culturetourismsog.evento';

    protected $primaryKey = 'id_evento';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo',
        'organizador',
        'contacto',
        'fecha_inicio',
        'fecha_fin',
        'asistentes_estimados',
        'impacto_economico',
        'estado',
        'url_foto',
        'observaciones',
        'id_direccion'
    ];

    // Relación con la dirección
    public function direccion()
    {
        return $this->belongsTo(DireccionGoogle::class, 'id_direccion');
    }

    // Relación con la galería de fotos
    public function fotos()
    {
        return $this->hasMany(FotosEvento::class, 'id_evento');
    }
}
