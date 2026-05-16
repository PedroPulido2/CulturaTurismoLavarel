<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiciosCulturales extends Model
{
    
    protected $table = 'culturetourismsog.serviciosculturales';

    protected $primaryKey = 'id_servicios_culturales';

    public $timestamps = false;

    protected $fillable = [
        'nombre_completo', 'nombre_artistico', 'celular', 'correo', 
        'sector', 'area_artistica', 'servicio', 'descripcion_experiencia', 
        'facebook', 'instagram', 'youtube', 'x', 'id_direccion'
    ];

    // Relación con la dirección (Un servicio cultural pertenece a una dirección)
    public function direccion()
    {
        return $this->belongsTo(DireccionGoogle::class, 'id_direccion');
    }
}
