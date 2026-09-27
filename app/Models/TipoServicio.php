<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoServicio extends Model
{
    protected $table = 'culturayturismo.tipo_servicio';

    protected $primaryKey = 'id_tipo_servicio';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function serviciosCulturales()
    {
        return $this->hasMany(ServicioCultural::class, 'id_tipo_servicio');
    }
}
