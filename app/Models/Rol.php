<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'culturayturismo.rol';

    protected $primaryKey = 'id_rol';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'culturayturismo.usuario_rol', 'rol_id_rol', 'usuario_id_usuario');
    }
}
