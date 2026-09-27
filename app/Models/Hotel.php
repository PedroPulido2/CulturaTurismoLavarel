<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    protected $table = 'culturayturismo.hotel';

    protected $primaryKey = 'id_hotel';

    // PK alfanumérica asignada por el cliente (VARCHAR(15)), no autoincremental
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'petfriendly' => 'boolean',
        'acceso_discapacidad' => 'boolean',
        'parqueadero' => 'boolean',
        'restaurante' => 'boolean',
        'visita_inspeccion_turismo' => 'boolean',
        'is_visible' => 'boolean',
        'calificacion_salud' => 'decimal:2',
    ];

    // campos que se permiten llenar desde la API
    protected $fillable = [
        'id_hotel',
        'nombre',
        'rnt',
        'celular',
        'correo',
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
        'observacion',
        'is_visible',
        'id_direccion',
    ];

    // Relación con la dirección (Un hotel pertenece a una dirección)
    public function direccion()
    {
        return $this->belongsTo(DireccionGoogle::class, 'id_direccion');
    }

    // Relación con las fotos (tabla unificada fotos)
    public function fotos()
    {
        return $this->hasMany(Foto::class, 'id_hotel');
    }

    // Redes sociales del hotel (pivote hotel_redes_sociales con columna url)
    public function redesSociales()
    {
        return $this->belongsToMany(
            RedesSocial::class,
            'culturayturismo.hotel_redes_sociales',
            'id_hotel',
            'id_redes_sociales'
        )->withPivot('url');
    }
}
