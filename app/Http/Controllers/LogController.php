<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaLog;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogController extends Controller
{
    /** Tope de filas que lee la descarga para no agotar memoria. */
    private const MAX_DESCARGA = 5000;

    public function __construct(private AuditLogService $audit) {}

    /**
     * Tabla de auditoría (paginada, con payload desencriptado).
     * Solo Super Administrador.
     */
    public function index(Request $request)
    {
        $validador = $this->validarFiltros($request);

        if ($validador->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validador->errors(),
            ], 400);
        }

        $items = $this->registrosFiltrados($request);

        $pagina = max(1, (int) $request->input('page', 1));
        $porPagina = (int) $request->input('per_page', 15);
        $total = $items->count();

        return response()->json([
            'success' => true,
            'data' => $items->forPage($pagina, $porPagina)->values(),
            'meta' => [
                'current_page' => $pagina,
                'per_page' => $porPagina,
                'total' => $total,
                'last_page' => (int) ceil($total / $porPagina),
            ],
        ]);
    }

    /**
     * Descarga CSV con los mismos filtros del listado.
     * Solo Super Administrador.
     */
    public function descargar(Request $request): StreamedResponse|JsonResponse
    {
        $validador = $this->validarFiltros($request);

        if ($validador->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validador->errors(),
            ], 400);
        }

        $items = $this->registrosFiltrados($request);
        $nombre = 'logs-auditoria-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($items) {
            $salida = fopen('php://output', 'w');
            // BOM para que Excel abra los tildes correctamente.
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Fecha', 'Num documento', 'Actividad', 'IP', 'Servidor', 'Modulo', 'Estado', 'Detalle']);

            foreach ($items as $fila) {
                fputcsv($salida, [
                    $fila['fecha'],
                    $fila['num_documento'],
                    $fila['activity'],
                    $fila['ip'],
                    $fila['server_name'],
                    $fila['modulo'],
                    $fila['estado'],
                    $fila['detalle'],
                ]);
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validarFiltros(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->all(), [
            'modulo' => 'sometimes|string|max:80',
            'estado' => 'sometimes|in:OK,ERROR',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date|after_or_equal:desde',
            'num_documento' => 'sometimes|string|max:30',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
    }

    /**
     * Aplica en SQL los filtros indexados (modulo/estado/fecha),
     * desencripta y aplica en PHP el filtro de num_documento
     * (vive dentro del payload cifrado, no se puede filtrar en SQL).
     *
     * @return Collection<int, array>
     */
    private function registrosFiltrados(Request $request): Collection
    {
        $query = AuditoriaLog::query()->orderByDesc('fecha')->limit(self::MAX_DESCARGA);

        if ($request->filled('modulo')) {
            $query->where('modulo', $request->input('modulo'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('desde')) {
            $query->where('fecha', '>=', $request->input('desde'));
        }

        if ($request->filled('hasta')) {
            $query->where('fecha', '<=', $request->input('hasta').' 23:59:59');
        }

        $items = $query->get()->map(function (AuditoriaLog $log) {
            $datos = $this->audit->desencriptar($log);

            return [
                'id' => $log->id,
                'fecha' => $log->fecha?->toDateTimeString(),
                'num_documento' => $datos['num_documento'] ?? null,
                'activity' => $datos['activity'] ?? null,
                'ip' => $datos['ip'] ?? null,
                'server_name' => $datos['server_name'] ?? null,
                'modulo' => $log->modulo,
                'estado' => $log->estado,
                'detalle' => $datos['detalle'] ?? ($datos === null ? 'No se pudo desencriptar el registro' : null),
            ];
        });

        if ($request->filled('num_documento')) {
            $buscado = $request->input('num_documento');
            $items = $items->filter(
                fn (array $fila) => $fila['num_documento'] !== null
                    && str_contains((string) $fila['num_documento'], $buscado)
            )->values();
        }

        return $items;
    }
}
