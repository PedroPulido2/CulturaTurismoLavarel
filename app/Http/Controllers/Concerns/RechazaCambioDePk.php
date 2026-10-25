<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait RechazaCambioDePk
{
    /**
     * La PK es inmutable: el front puede reenviar el ID en el body
     * (ej. reutilizando el objeto del GET), pero debe coincidir con la URL.
     * Devuelve una respuesta 422 si intenta cambiarlo, null si todo está bien.
     */
    protected function rechazarCambioDePk(Request $request, string $campo, mixed $id): ?JsonResponse
    {
        if ($request->has($campo) && (string) $request->input($campo) !== (string) $id) {
            return response()->json([
                'success' => false,
                'message' => "El {$campo} no se puede modificar. Debe coincidir con el de la URL",
            ], 422);
        }

        return null;
    }
}
