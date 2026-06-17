<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perfil extends Model
{
    protected $table = 'perfil';

    protected $primaryKey = 'id_perfil';

    public $timestamps = false;

    //campos que se permiten llenar desde la API
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
        'role',
        'perm_atractivos',
        'perm_prestadores_servicios',
        'perm_servicios_culturales',
        'perm_agenda_eventos',
        'perm_dashboard',
    ];
}
