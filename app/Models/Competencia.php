<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competencia extends Model
{
    protected $table = 'culturayturismo.competencias';

    protected $primaryKey = 'id_competencias';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function guias()
    {
        return $this->hasMany(Guia::class, 'id_competencias');
    }
}
