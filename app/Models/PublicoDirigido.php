<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicoDirigido extends Model
{
    protected $table = 'culturayturismo.publico_dirigido';

    protected $primaryKey = 'id_publico_dirigido';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    public function serviciosCulturales()
    {
        return $this->hasMany(ServicioCultural::class, 'id_publico_dirigido');
    }
}
