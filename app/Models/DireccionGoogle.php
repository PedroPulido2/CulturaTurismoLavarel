<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DireccionGoogle extends Model
{
    protected $table = 'culturayturismo.direccion_google';

    protected $primaryKey = 'id_direccion';

    public $timestamps = false;

    protected $fillable = [
        'direccion',
        'latitud',
        'longitud',
    ];
}
