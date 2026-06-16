<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Login extends Model
{
    protected $table = 'login';

    protected $primaryKey = 'id_login';

    public $timestamps = false;

    //campos que permitimos llenar desde la API
    protected $fillable = [
        'id_perfil',
        'password',
        'estado',
        'intentos_fallidos',
        'ultimo_acceso'
    ];

    public function perfil()
    {
        return $this->belongsTo(Perfil::class, 'id_perfil');
    }
}
