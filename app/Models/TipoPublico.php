<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoPublico extends Model
{
    protected $table = 'culturayturismo.tipo_publico';

    protected $primaryKey = 'id_tipo_publico';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function guias()
    {
        return $this->hasMany(Guia::class, 'id_tipo_publico');
    }
}
