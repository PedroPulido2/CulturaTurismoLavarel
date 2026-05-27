<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hotel;
use App\Models\Restaurante;
use App\Models\Agencia;
use Illuminate\Support\Facades\Log;
use Exception;

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
            if (!empty($matches[1])) {
                return 'https://drive.google.com/uc?export=view&id=' . $matches[1];
            }
        }

        // Si es ruta interna de Laravel
        if (!filter_var($rawUrl, FILTER_VALIDATE_URL)) {
            return url($rawUrl);
        }

        return $rawUrl;
    }

    public function getPrestadoresPublicos(Request $request)
    {
        try {
            $categoriaFiltro = $request->query('categoria');
            $resultado = [];

            // 1. HOTELES
            if (!$categoriaFiltro || $categoriaFiltro === 'all' || $categoriaFiltro === 'hoteles') {
                $hoteles = Hotel::with(['direccion', 'fotos'])->get();
                foreach ($hoteles as $hotel) {
                    $urlFoto = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&auto=format&fit=crop&q=60'; // Fallback
                    if ($hotel->fotos && $hotel->fotos->count() > 0) {
                        $urlFoto = $this->parseImageUrl($hotel->fotos->first()->url_foto, $urlFoto);
                    }

                    $resultado[] = [
                        'id' => 'hotel_' . ($hotel->id ?? $hotel->id_hotel ?? rand(100,999)),
                        'name' => $hotel->nombre ?? 'Establecimiento sin nombre',
                        'category' => 'Hotel',
                        'description' => $hotel->descripcion ?? 'Establecimiento hotelero autorizado en el municipio de Sogamoso.',
                        'address' => ($hotel->direccion && isset($hotel->direccion->direccion)) ? $hotel->direccion->direccion : 'Sogamoso, Boyacá',
                        'phone' => $hotel->celular ?? $hotel->correo ?? 'No disponible',
                        'imageUrl' => $urlFoto
                    ];
                }
            }

            // 2. RESTAURANTES
            if (!$categoriaFiltro || $categoriaFiltro === 'all' || $categoriaFiltro === 'restaurantes') {
                $restaurantes = Restaurante::with(['direccion', 'fotos'])->get();
                foreach ($restaurantes as $restaurante) {
                    $urlFoto = 'https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=600&auto=format&fit=crop&q=60';
                    if ($restaurante->fotos && $restaurante->fotos->count() > 0) {
                        $urlFoto = $this->parseImageUrl($restaurante->fotos->first()->url_foto, $urlFoto);
                    }

                    $resultado[] = [
                        'id' => 'restaurante_' . ($restaurante->id ?? $restaurante->id_restaurante ?? rand(100,999)),
                        'name' => $restaurante->nombre ?? 'Establecimiento sin nombre',
                        'category' => 'Restaurante',
                        'description' => $restaurante->descripcion ?? 'Disfruta de la gastronomía tradicional y moderna en Sogamoso.',
                        'address' => ($restaurante->direccion && isset($restaurante->direccion->direccion)) ? $restaurante->direccion->direccion : 'Sogamoso, Boyacá',
                        'phone' => $restaurante->celular ?? $restaurante->correo ?? 'No disponible',
                        'imageUrl' => $urlFoto
                    ];
                }
            }

            // 3. AGENCIAS
            if (!$categoriaFiltro || $categoriaFiltro === 'all' || $categoriaFiltro === 'agencias') {
                $agencias = Agencia::with(['fotos'])->get();
                foreach ($agencias as $agencia) {
                    $urlFoto = 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=600&auto=format&fit=crop&q=60';
                    if ($agencia->fotos && $agencia->fotos->count() > 0) {
                        $urlFoto = $this->parseImageUrl($agencia->fotos->first()->url_foto, $urlFoto);
                    }

                    $resultado[] = [
                        'id' => 'agencia_' . ($agencia->id ?? $agencia->id_agencia ?? rand(100,999)),
                        'name' => $agencia->nombre ?? 'Agencia sin nombre',
                        'category' => 'Agencia',
                        'description' => 'Agencia de viajes y turismo operando de manera legal y certificada.',
                        'address' => 'Sogamoso, Boyacá',
                        'phone' => $agencia->celular ?? $agencia->correo ?? 'No disponible',
                        'imageUrl' => $urlFoto
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'count' => count($resultado),
                'data' => $resultado
            ], 200);

        } catch (Exception $e) {
            Log::error('Error crítico en API de Prestadores: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno procesando prestadores: ' . $e->getMessage()
            ], 500);
        }
    }
}