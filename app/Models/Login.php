<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Login extends Model
{
    protected $table = 'culturayturismo.login';

    protected $primaryKey = 'id_login';

    public $timestamps = false;

    protected $hidden = ['password'];

    // campos que permitimos llenar desde la API
    protected $fillable = [
        'id_usuario',
        'password',
        'estado',
        'intentos_fallidos',
        'ultimo_acceso',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
