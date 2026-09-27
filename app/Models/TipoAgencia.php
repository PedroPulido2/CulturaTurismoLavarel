<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoAgencia extends Model
{
    protected $table = 'culturayturismo.tipo_agencia';

    protected $primaryKey = 'id_tipo_agencia';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function agencias()
    {
        return $this->hasMany(Agencia::class, 'id_tipo_agencia');
    }
}
