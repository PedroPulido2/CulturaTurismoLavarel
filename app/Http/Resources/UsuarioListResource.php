<?php

namespace App\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lista para el Super Administrador: datos de gestión
 * sin exponer documento, teléfono ni fecha de nacimiento.
 *
 * @mixin Usuario
 */
class UsuarioListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_usuario' => $this->id_usuario,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'correo' => $this->correo,
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
