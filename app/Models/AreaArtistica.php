<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AreaArtistica extends Model
{
    protected $table = 'areas_artisticas';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function serviciosCulturales()
    {
        return $this->hasMany(ServicioCultural::class, 'id_area_artistica');
    }
}
