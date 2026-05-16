<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FotosHotel extends Model
{
    protected $table = 'culturetourismsog.fotoshotel';

    protected $primaryKey = 'id_foto';

    public $timestamps = false;

    protected $fillable = [
        'url_foto',
        'id_hotel',
    ];
}
