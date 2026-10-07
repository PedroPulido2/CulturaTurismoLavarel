<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guia extends Model
{
    protected $table = 'culturayturismo.guia';

    protected $primaryKey = 'id_guia';

    // PK alfanumérica asignada por el cliente (VARCHAR(15)), no autoincremental
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    protected $fillable = [
        'id_guia',
        'nombre',
        'n_cedula',
        'rnt',
        'celular',
        'correo',
        'num_tarjeta_profesional',
        'rango_anios_experiencia',
        'idiomas',
        'principales_atractivos',
        'asociacion',
        'is_visible',
        'id_especialidad',
        'id_disponibilidad',
        'descripcion',
        'id_tipo_publico',
    ];

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class, 'id_especialidad');
    }

    public function disponibilidad()
    {
        return $this->belongsTo(Disponibilidad::class, 'id_disponibilidad');
    }

    public function tipoPublico()
    {
        return $this->belongsTo(TipoPublico::class, 'id_tipo_publico');
    }
}
