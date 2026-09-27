<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RedesSocial extends Model
{
    protected $table = 'culturayturismo.redes_sociales';

    protected $primaryKey = 'id_redes_sociales';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];
}
