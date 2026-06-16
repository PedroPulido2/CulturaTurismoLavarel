<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoPerfilSc extends Model
{
    protected $table = 'tipos_perfiles_sc';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function serviciosCulturales()
    {
        return $this->hasMany(ServicioCultural::class, 'id_tipo_perfil_sc');
    }
}
