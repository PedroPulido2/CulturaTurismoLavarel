<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicioCultural extends Model
{
    protected $table = 'culturayturismo.servicio_cultural';

    protected $primaryKey = 'id_servicio_cultural';

    // PK entera asignada por el cliente (sin identity en la BD), no autoincremental
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_servicio_cultural',
        'nombre',
        'telefono',
        'correo',
        'contacto',
        'biografia',
        'reconocimientos',
        'id_tipo_servicio',
        'id_publico_dirigido',
        'id_area_artistica',
    ];

    public function tipoServicio()
    {
        return $this->belongsTo(TipoServicio::class, 'id_tipo_servicio');
    }

    public function publicoDirigido()
    {
        return $this->belongsTo(PublicoDirigido::class, 'id_publico_dirigido');
    }

    public function areaArtistica()
    {
        return $this->belongsTo(AreaArtistica::class, 'id_area_artistica');
    }

    // Galería de fotos del servicio (tabla unificada fotos)
    public function fotos()
    {
        return $this->hasMany(Foto::class, 'id_servicio_cultural');
    }
}
