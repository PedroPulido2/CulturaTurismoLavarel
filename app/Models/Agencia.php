<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agencia extends Model
{
    protected $table = 'culturayturismo.agencia';

    protected $primaryKey = 'id_agencia';

    // PK alfanumérica asignada por el cliente (VARCHAR(15)), no autoincremental
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    protected $fillable = [
        'id_agencia',
        'nit',
        'rnt',
        'nombre',
        'celular',
        'correo',
        'representante_legal',
        'n_empleados_asociados',
        'destinos_principales',
        'observaciones',
        'is_visible',
        'id_tipo_agencia',
    ];

    // Relación con el tipo de agencia (catálogo tipo_agencia)
    public function tipoAgencia()
    {
        return $this->belongsTo(TipoAgencia::class, 'id_tipo_agencia');
    }

    // Especialidades turísticas propias de la agencia (tabla especialidad_turistica)
    public function especialidades()
    {
        return $this->hasMany(EspecialidadTuristica::class, 'id_agencia');
    }

    // Relación con las fotos (tabla unificada fotos)
    public function fotos()
    {
        return $this->hasMany(Foto::class, 'id_agencia');
    }

    // Redes sociales de la agencia (pivote agencia_redes_sociales con columna url)
    public function redesSociales()
    {
        return $this->belongsToMany(
            RedesSocial::class,
            'culturayturismo.agencia_redes_sociales',
            'id_agencia',
            'id_redes_sociales'
        )->withPivot('url');
    }
}
