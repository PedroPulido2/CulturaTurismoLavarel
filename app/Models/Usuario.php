<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table = 'culturayturismo.usuario';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $hidden = ['reset_token_hash'];

    protected $fillable = [
        'tipo_identificacion',
        'num_documento',
        'nombre',
        'apellido',
        'correo',
        'fecha_nacimiento',
        'genero',
        'telefono',
        'url_foto',
        'id_rol',
    ];

    public function login()
    {
        return $this->hasOne(Login::class, 'id_usuario');
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'id_rol');
    }

    public function modulos()
    {
        return $this->belongsToMany(Modulo::class, 'culturayturismo.usuario_modulo', 'usuario_id_usuario', 'modulo_id_modulo')
            ->withPivot('asignado_por', 'fecha_asignacion');
    }

    public function esSuperAdmin(): bool
    {
        return $this->rol?->nombre === 'Super Administrador';
    }
}
