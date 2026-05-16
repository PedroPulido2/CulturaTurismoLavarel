<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agencia extends Model
{
    protected $table = 'culturetourismsog.agencia';

    protected $primaryKey = 'id_agencia';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'celular',
        'correo',
        'nit',
        'rnt',
        'tipo',
        'instagram',
        'facebook',
        'whatsapp',
        'web',
        'representante_legal',
        'n_empleados_asociados',
        'especialidad_turistica',
        'destinos_principales',
        'observaciones'
    ];

    // Relación con las fotos (Una agencia tiene muchas fotos)
    public function fotos()
    {
        return $this->hasMany(FotosAgencia::class, 'id_agencia');
    }
}
