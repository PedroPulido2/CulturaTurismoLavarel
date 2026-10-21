<?php

namespace App\Http\Controllers;

use App\Models\Agencia;
use App\Models\Hotel;
use App\Models\RedesSocial;
use App\Models\Restaurante;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PrestadoresPublicoController extends Controller
{
    /**
     * Helper para convertir URLs de Google Drive a formato de imagen directa
     */
    private function parseImageUrl($rawUrl, $fallbackUrl)
    {
        if (empty($rawUrl)) {
            return $fallbackUrl;
        }

        // Si es un link de visualización de Drive, convertirlo a link directo (Raw Image)
        if (str_contains($rawUrl, 'drive.google.com/file/d/')) {
            preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $rawUrl, $matches);
            if (! empty($matches[1])) {
                return 'https://drive.google.com/uc?export=view&id='.$matches[1];
            }
        }

        // Si es ruta interna de Laravel
        if (! filter_var($rawUrl, FILTER_VALIDATE_URL)) {
            return url($rawUrl);
        }

        return $rawUrl;
    }

    /**
     * Extrae la url de una red social por su nombre desde la relación pivote.
     */
    private function redSocialUrl($entidad, string $nombreRed)
    {
        if (! $entidad || ! $entidad->relationLoaded('redesSociales')) {
            return null;
        }

        foreach ($entidad->redesSociales as $red) {
            if (strtolower(trim($red->nombre ?? '')) === $nombreRed) {
                return $red->pivot->url ?? null;
            }
        }

        return null;
    }

    public function getPrestadoresPublicos(Request $request)
    {
        try {
            $categoriaFiltro = $request->query('categoria');
            // Tope anti-scraping por categoría (el agregador mezcla 3 tablas).
            $perCategoria = min(max((int) $request->query('per_page', 15), 1), 50);
            $resultado = [];

            // 1. HOTELES (solo visibles, portada + campos mínimos)
            if (! $categoriaFiltro || $categoriaFiltro === 'all' || $categoriaFiltro === 'hoteles') {
                $hoteles = Hotel::select(['id_hotel', 'nombre', 'celular', 'calificacion_salud', 'id_direccion'])
                    ->where('is_visible', true)
                    ->with([
                        'direccion:id_direccion,direccion',
                        'fotos:id_foto,url_foto,is_portada,id_hotel',
                        'redesSociales:id_redes_sociales,nombre',
                    ])
                    ->limit($perCategoria)->get();
                foreach ($hoteles as $hotel) {
                    $urlFoto = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&auto=format&fit=crop&q=60'; // Fallback
                    if ($hotel->fotos && $hotel->fotos->count() > 0) {
                        $urlFoto = $this->parseImageUrl($hotel->fotos->first()->url_foto, $urlFoto);
                    }

                    $resultado[] = [
                        'id' => 'hotel_'.($hotel->id_hotel ?? rand(100, 999)),
                        'name' => $hotel->nombre ?? 'Establecimiento sin nombre',
                        'category' => 'Hotel',
                        'direccion' => $hotel->direccion?->direccion ?? 'Sogamoso, Boyacá',
                        'celular' => $hotel->celular ?? 'No disponible',
                        'imageUrl' => $urlFoto,
                        'facebook' => $this->redSocialUrl($hotel, 'facebook'),
                        'instagram' => $this->redSocialUrl($hotel, 'instagram'),
                    ];
                }
            }

            // 2. RESTAURANTES (solo visibles)
            if (! $categoriaFiltro || $categoriaFiltro === 'all' || $categoriaFiltro === 'restaurantes') {
                $restaurantes = Restaurante::select(['id_restaurante', 'nombre', 'celular', 'id_direccion', 'id_tipo_cocina'])
                    ->where('is_visible', true)
                    ->with([
                        'direccion:id_direccion,direccion',
                        'fotos:id_foto,url_foto,is_portada,id_restaurante',
                        'redesSociales:id_redes_sociales,nombre',
                    ])
                    ->limit($perCategoria)->get();
                foreach ($restaurantes as $restaurante) {
                    $urlFoto = 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=600&auto=format&fit=crop&q=60';
                    if ($restaurante->fotos && $restaurante->fotos->count() > 0) {
                        $urlFoto = $this->parseImageUrl($restaurante->fotos->first()->url_foto, $urlFoto);
                    }

                    $resultado[] = [
                        'id' => 'restaurante_'.($restaurante->id_restaurante ?? rand(100, 999)),
                        'name' => $restaurante->nombre ?? 'Establecimiento sin nombre',
                        'category' => 'Restaurante',
                        'direccion' => $restaurante->direccion?->direccion ?? 'Sogamoso, Boyacá',
                        'celular' => $restaurante->celular ?? 'No disponible',
                        'imageUrl' => $urlFoto,
                        'facebook' => $this->redSocialUrl($restaurante, 'facebook'),
                        'instagram' => $this->redSocialUrl($restaurante, 'instagram'),
                    ];
                }
            }

            // 3. AGENCIAS (solo visibles; sin dirección en el esquema)
            if (! $categoriaFiltro || $categoriaFiltro === 'all' || $categoriaFiltro === 'agencias') {
                $agencias = Agencia::select(['id_agencia', 'nombre', 'celular', 'correo'])
                    ->where('is_visible', true)
                    ->with([
                        'fotos:id_foto,url_foto,is_portada,id_agencia',
                        'redesSociales:id_redes_sociales,nombre',
                    ])
                    ->limit($perCategoria)->get();
                foreach ($agencias as $agencia) {
                    $urlFoto = 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=600&auto=format&fit=crop&q=60';
                    if ($agencia->fotos && $agencia->fotos->count() > 0) {
                        $urlFoto = $this->parseImageUrl($agencia->fotos->first()->url_foto, $urlFoto);
                    }

                    $resultado[] = [
                        'id' => 'agencia_'.($agencia->id_agencia ?? rand(100, 999)),
                        'name' => $agencia->nombre ?? 'Agencia sin nombre',
                        'category' => 'Agencia',
                        'celular' => $agencia->celular ?? $agencia->correo ?? 'No disponible',
                        'imageUrl' => $urlFoto,
                        'facebook' => $this->redSocialUrl($agencia, 'facebook'),
                        'instagram' => $this->redSocialUrl($agencia, 'instagram'),
                        'correo' => $agencia->correo ?? null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'count' => count($resultado),
                'data' => $resultado,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error crítico en API de Prestadores: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno procesando prestadores: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Catálogo de redes sociales disponibles (para los pivotes *_redes_sociales).
     */
    public function getRedesSociales()
    {
        try {
            return response()->json(['success' => true, 'data' => RedesSocial::all()], 200);
        } catch (Exception $e) {
            Log::error('Error obteniendo redes sociales: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno obteniendo redes sociales: '.$e->getMessage(),
            ], 500);
        }
    }
}
