<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FotosEvento extends Model
{

    protected $table = 'fotosevento';

    protected $primaryKey = 'id_foto';

    public $timestamps = false;

    protected $fillable = [
        'url_foto',
        'id_evento'
    ];
}
