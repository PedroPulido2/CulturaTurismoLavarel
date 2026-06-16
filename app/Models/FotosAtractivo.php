<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FotosAtractivo extends Model
{
    protected $table = 'fotosatractivo';

    protected $primaryKey = 'id_foto';

    public $timestamps = false;

    protected $fillable = [
        'url_foto',
        'id_atractivo_turistico',
    ];
}
