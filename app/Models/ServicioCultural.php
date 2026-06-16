<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicioCultural extends Model
{
    protected $table = 'servicios_culturales';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_area_artistica',
        'id_tipo_perfil_sc',
        'nombre_artistico',
        'telefono',
        'correo',
        'contacto',
        'url_foto',
        'biografia',
        'tipo_servicio',
        'publico_objetivo',
        'reconocimientos',
        'correo_publicar',
        'telefono_publicar',
        'sitio_web',
        'instagram',
        'facebook',
        'youtube',
        'tiktok',
        'otra_red',
    ];

    public function areaArtistica()
    {
        return $this->belongsTo(AreaArtistica::class, 'id_area_artistica');
    }

    public function tipoPerfilSc()
    {
        return $this->belongsTo(TipoPerfilSc::class, 'id_tipo_perfil_sc');
    }
}
