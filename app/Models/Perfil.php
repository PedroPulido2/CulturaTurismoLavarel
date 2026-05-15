<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perfil extends Model
{
    protected $table = 'culturetourismsog.perfil';

    protected $primaryKey = 'id_perfil';

    public $timestamps = false;

    //campos que permitimos llenar desde la API
    protected $fillable = [
        'id_perfil',
        'nombre',
        'apellido',
        'correo',
        'fecha_nacimiento',
        'genero',
        'telefono',
        'tipo_identificacion',
        'url_foto',
    ];
}
