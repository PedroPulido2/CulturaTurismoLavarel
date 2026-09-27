<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restaurante extends Model
{
    protected $table = 'culturayturismo.restaurante';

    protected $primaryKey = 'id_restaurante';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    protected $fillable = [
        'id_restaurante',
        'nombre',
        'celular',
        'correo',
        'horarios',
        'propietario',
        'capacidad',
        'platos_principales',
        'is_visible',
        'id_tipo_cocina',
        'id_direccion',
    ];

    // Relación con el tipo de cocina (catálogo tipo_cocina)
    public function tipoCocina()
    {
        return $this->belongsTo(TipoCocina::class, 'id_tipo_cocina');
    }

    // Relación con la dirección (Un restaurante pertenece a una dirección)
    public function direccion()
    {
        return $this->belongsTo(DireccionGoogle::class, 'id_direccion');
    }

    // Relación con las fotos (tabla unificada fotos)
    public function fotos()
    {
        return $this->hasMany(Foto::class, 'id_restaurante');
    }

    // Redes sociales del restaurante (pivote restaurante_redes_sociales con columna url)
    public function redesSociales()
    {
        return $this->belongsToMany(
            RedesSocial::class,
            'culturayturismo.restaurante_redes_sociales',
            'id_restaurante',
            'id_redes_sociales'
        )->withPivot('url');
    }
}
