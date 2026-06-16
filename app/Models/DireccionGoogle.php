<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DireccionGoogle extends Model
{
    protected $table = 'direcciongoogle';

    protected $primaryKey = 'id_direccion';

    public $timestamps = false;

    protected $fillable = [
        'direccion',
        'latitud',
        'longitud',
        'google_place_id',
    ];
}
