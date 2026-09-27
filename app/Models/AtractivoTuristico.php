<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtractivoTuristico extends Model
{
    protected $table = 'culturayturismo.atractivo_turistico';

    protected $primaryKey = 'id_atractivo_turistico';

    // PK alfanumérica asignada por el cliente (VARCHAR(15)), no autoincremental
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    protected $fillable = [
        'id_atractivo_turistico',
        'nombre',
        'tipo',
        'descripcion',
        'telefono',
        'horario',
        'precio',
        'is_visible',
        'id_direccion',
    ];

    // Relacion con la direccion (Un atractivo pertenece a una direccion)
    public function direccion()
    {
        return $this->belongsTo(DireccionGoogle::class, 'id_direccion');
    }

    // Relacion con las fotos (Un atractivo tiene muchas fotos en la tabla unificada)
    public function fotos()
    {
        return $this->hasMany(Foto::class, 'id_atractivo_turistico');
    }

    // Redes sociales del atractivo (pivote atractivo_redes_sociales con columna url)
    public function redesSociales()
    {
        return $this->belongsToMany(
            RedesSocial::class,
            'culturayturismo.atractivo_redes_sociales',
            'id_atractivo_turistico',
            'id_redes_sociales'
        )->withPivot('url');
    }
}
