<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EspecialidadTuristica extends Model
{
    protected $table = 'culturayturismo.especialidad_turistica';

    protected $primaryKey = 'id_especialidad_turistica';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_agencia',
    ];

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'id_agencia');
    }
}
