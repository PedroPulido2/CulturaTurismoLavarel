<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    protected $table = 'culturetourismsog.hotel';

    protected $primaryKey = 'id_hotel';

    public $timestamps = false;

    //campos que se permiten llenar desde la API
    protected $fillable = [
        'nombre',
        'celular',
        'correo',
        'rnt',
        'nombre_contacto',
        'n_habitaciones_totales',
        'n_habitaciones_simples',
        'n_habitaciones_dobles',
        'n_habitaciones_suites',
        'petfriendly',
        'acceso_discapacidad',
        'parqueadero',
        'restaurante',
        'calificacion_salud',
        'visita_inspeccion_turismo',
        'instagram',
        'facebook',
        'whatsapp',
        'web',
        'observaciones',
        'id_direccion'
    ];

    // Relación con la dirección (Un hotel pertenece a una dirección)
    public function direccion()
    {
        return $this->belongsTo(DireccionGoogle::class, 'id_direccion');
    }

    // Relación con las fotos (Un hotel tiene muchas fotos)
    public function fotos()
    {
        return $this->hasMany(FotosHotel::class, 'id_hotel');
    }
}
