<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCocina extends Model
{
    protected $table = 'culturayturismo.tipo_cocina';

    protected $primaryKey = 'id_tipo_cocina';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function restaurantes()
    {
        return $this->hasMany(Restaurante::class, 'id_tipo_cocina');
    }
}
