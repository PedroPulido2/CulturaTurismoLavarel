<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modulo extends Model
{
    protected $table = 'culturayturismo.modulo';

    protected $primaryKey = 'id_modulo';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];
}
