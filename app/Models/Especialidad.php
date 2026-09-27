<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    protected $table = 'culturayturismo.especialidad';

    protected $primaryKey = 'id_especialidad';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function guias()
    {
        return $this->hasMany(Guia::class, 'id_especialidad');
    }
}
