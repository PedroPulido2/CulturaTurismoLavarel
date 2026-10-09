<?php

namespace App\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detalle de un perfil: lo ve el Super Administrador
 * o el propio dueño. Nunca expone tokens ni login.
 *
 * @mixin Usuario
 */
class UsuarioDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_usuario' => $this->id_usuario,
            'tipo_identificacion' => $this->tipo_identificacion,
            'num_documento' => $this->num_documento,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'correo' => $this->correo,
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'genero' => $this->genero,
            'telefono' => $this->telefono,
            'url_foto' => $this->url_foto,
            'rol' => $this->whenLoaded('rol', fn () => $this->rol ? [
                'id_rol' => $this->rol->id_rol,
                'nombre' => $this->rol->nombre,
            ] : null),
            'modulos' => $this->whenLoaded('modulos', fn () => $this->modulos->map(fn ($m) => [
                'id_modulo' => $m->id_modulo,
                'nombre' => $m->nombre,
            ])->values()),
        ];
    }
}
