<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoría con envelope cifrado.
 *
 * `fecha`, `modulo` y `estado` van en claro para poder filtrar,
 * ordenar y paginar en SQL. El resto (num_documento, activity,
 * ip, server_name, detalle) viaja cifrado en `datos_cifrados`
 * con Laravel Crypt (AES-256 con APP_KEY).
 *
 * En tests (sqlite :memory:) el esquema se emula con
 * ATTACH DATABASE ':memory:' AS culturayturismo.
 */
class AuditoriaLog extends Model
{
    protected $table = 'culturayturismo.auditoria_logs';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'modulo',
        'estado',
        'datos_cifrados',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];
}
