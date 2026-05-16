<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FotosAgencia extends Model
{
    protected $table = 'culturetourismsog.fotosagencia';

    protected $primaryKey = 'id_foto';

    public $timestamps = false;

    protected $fillable = [
        'url_foto',
        'id_agencia',
    ];
}
