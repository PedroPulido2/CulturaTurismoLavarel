<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FotosRestaurante extends Model
{
    
    protected $table = 'fotosrestaurante';

    protected $primaryKey = 'id_foto';

    public $timestamps = false;

    protected $fillable = [
        'url_foto',
        'id_restaurante'
    ];

}
