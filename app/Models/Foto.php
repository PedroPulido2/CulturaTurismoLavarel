<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Foto extends Model
{
    protected $table = 'culturayturismo.fotos';

    protected $primaryKey = 'id_foto';

    public $timestamps = false;

    protected $casts = [
        'is_portada' => 'boolean',
    ];

    // Tabla unificada: exactamente una de las FK de entidad debe ir informada
    protected $fillable = [
        'url_foto',
        'is_portada',
        'id_hotel',
        'id_atractivo_turistico',
        'id_restaurante',
        'id_agencia',
        'id_servicio_cultural',
        'id_evento',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'id_hotel');
    }

    public function atractivo()
    {
        return $this->belongsTo(AtractivoTuristico::class, 'id_atractivo_turistico');
    }

    public function restaurante()
    {
        return $this->belongsTo(Restaurante::class, 'id_restaurante');
    }

    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'id_agencia');
    }

    public function servicioCultural()
    {
        return $this->belongsTo(ServicioCultural::class, 'id_servicio_cultural');
    }

    public function evento()
    {
        return $this->belongsTo(Evento::class, 'id_evento');
    }
}
