<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guia extends Model
{
    protected $table = 'culturetourismsog.guia';

    protected $primaryKey = 'id_guia';

    public $timestamps = false;

    protected $fillable = [
        'n_cedula',
        'nombre',
        'celular',
        'correo',
        'rnt',
        'años_experiencia',
        'idiomas',
        'especialidad',
        'principales_atractivos',
        'disponibilidad_habitual',
        'competencias_adicionales',
        'publico_tiene_experiencia',
        'asociacion'
    ];
}
