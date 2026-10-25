<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Evento extends Model
{
    protected $table = 'culturayturismo.evento';

    protected $primaryKey = 'id_evento';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'is_visible' => 'boolean',
        'cancelado' => 'boolean',
        'impacto_economico' => 'decimal:6',
    ];

    protected $fillable = [
        'id_evento',
        'nombre',
        'descripcion',
        'tipo',
        'organizador',
        'contacto',
        'fecha_inicio',
        'fecha_fin',
        'asistentes_estimados',
        'asistentes_reales',
        'impacto_economico',
        'observaciones',
        'is_visible',
        'cancelado',
        'motivo_cancelacion',
        'id_direccion',
    ];

    /**
     * Estados posibles: programado | en_curso | finalizado | cancelado | sin_fecha.
     * Calculado del servidor (zona America/Bogota); `cancelado` precede a las fechas.
     */
    public const ESTADOS_EVENTO = ['programado', 'en_curso', 'finalizado', 'cancelado', 'sin_fecha'];

    public function getEstadoAttribute(): string
    {
        if ($this->cancelado) {
            return 'cancelado';
        }

        $hoy = Carbon::today('America/Bogota')->toDateString();
        $inicio = $this->fecha_inicio ? substr((string) $this->fecha_inicio, 0, 10) : null;
        $fin = $this->fecha_fin ? substr((string) $this->fecha_fin, 0, 10) : null;

        if ($inicio === null && $fin === null) {
            return 'sin_fecha';
        }

        if ($inicio !== null && $hoy < $inicio) {
            return 'programado';
        }

        if ($fin !== null && $hoy > $fin) {
            return 'finalizado';
        }

        return 'en_curso';
    }

    // Relación con la dirección
    public function direccion()
    {
        return $this->belongsTo(DireccionGoogle::class, 'id_direccion');
    }

    // Relación con la galería de fotos (tabla unificada fotos)
    public function fotos()
    {
        return $this->hasMany(Foto::class, 'id_evento');
    }
}
