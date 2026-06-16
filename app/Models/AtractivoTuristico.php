<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtractivoTuristico extends Model
{
    protected $table = 'atractivoturistico';

    protected $primaryKey = 'id_atractivo_turistico';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'tipo',
        'descripcion',
        'telefono',
        'instagram',
        'facebook',
        'whatsapp',
        'web',
        'horario',
        'precio',
        'id_direccion',
    ];

    //Relacion con la direccion (Un atractivo pertenece a una direccion)
    public function direccion()
    {
        return $this->belongsTo(DireccionGoogle::class, 'id_direccion');
    }

    //Relacion con las fotos (Un atractivo tiene muchas fotos)
    public function fotos()
    {
        return $this->hasMany(FotosAtractivo::class, 'id_atractivo_turistico');
    }
}
