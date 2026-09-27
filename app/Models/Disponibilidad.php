<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disponibilidad extends Model
{
    protected $table = 'culturayturismo.disponibilidad';

    protected $primaryKey = 'id_disponibilidad';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function guias()
    {
        return $this->hasMany(Guia::class, 'id_disponibilidad');
    }
}
