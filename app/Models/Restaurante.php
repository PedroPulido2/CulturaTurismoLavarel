<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restaurante extends Model
{
    protected $table = 'restaurante';

    protected $primaryKey = 'id_restaurante';

    public $timestamps = false;

    protected $fillable = [
        'nombre', 'celular', 'correo', 'tipo_cocina', 'horarios', 
        'propietario', 'capacidad', 'instagram', 'facebook', 
        'whatsapp', 'web', 'platos_principales', 'id_direccion'
    ];

    // Relación con la dirección (Un restaurante pertenece a una dirección)
    public function direccion()
    {
        return $this->belongsTo(DireccionGoogle::class, 'id_direccion');
    }

    // Relación con las fotos (Un restaurante tiene muchas fotos)
    public function fotos()
    {
        return $this->hasMany(FotosRestaurante::class, 'id_restaurante');
    }

}
