<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditLogMiddleware;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    /**
     * Emula el esquema culturayturismo en sqlite :memory:.
     * Se omite si pdo_sqlite no está instalado (ej. este entorno
     * solo tiene pdo_pgsql); en CI con sqlite sí corre.
     */
    private function prepararBaseDatos(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requiere pdo_sqlite, no instalado en este entorno.');
        }

        try {
            DB::statement('ATTACH DATABASE ":memory:" AS culturayturismo');
        } catch (\Exception $e) {
            // Ya adjuntada en este proceso.
        }

        DB::statement('DROP TABLE IF EXISTS culturayturismo."auditoria_logs"');
        DB::statement('CREATE TABLE culturayturismo."auditoria_logs" (id INTEGER PRIMARY KEY AUTOINCREMENT, fecha DATETIME NOT NULL, modulo VARCHAR(80) NOT NULL, estado VARCHAR(10) NOT NULL, datos_cifrados TEXT NOT NULL)');
    }

    private function peticion(string $uri = '/api/hotel/register'): Request
    {
        return Request::create($uri, 'POST', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.10',
            'HTTP_HOST' => 'api.test',
        ]);
    }

    public function test_registra_payload_cifrado_y_desencripta(): void
    {
        $this->prepararBaseDatos();
        $servicio = app(AuditLogService::class);

        $log = $servicio->registrar(
            'Prestadores Servicios', 'CREAR_HOTEL', 'OK',
            'Hotel HOT001 creado', '123456789', $this->peticion(),
        );

        $this->assertNotNull($log);

        $crudo = DB::table('culturayturismo.auditoria_logs')->where('id', $log->id)->value('datos_cifrados');
        // Nada sensible en claro en la BD.
        $this->assertStringNotContainsString('123456789', $crudo);
        $this->assertStringNotContainsString('HOT001', $crudo);
        $this->assertStringNotContainsString('192.168.1.10', $crudo);

        $datos = json_decode(Crypt::decryptString($crudo), true);
        $this->assertSame('123456789', $datos['num_documento']);
        $this->assertSame('CREAR_HOTEL', $datos['activity']);
        $this->assertSame('192.168.1.10', $datos['ip']);
        $this->assertSame('api.test', $datos['server_name']);
        $this->assertSame('Hotel HOT001 creado', $datos['detalle']);

        // Columnas índice en claro.
        $this->assertSame('Prestadores Servicios', $log->modulo);
        $this->assertSame('OK', $log->estado);
    }

    public function test_listado_devuelve_registros_desencriptados(): void
    {
        $this->prepararBaseDatos();
        $this->withoutMiddleware();
        $servicio = app(AuditLogService::class);

        $servicio->registrar('Eventos', 'CREAR_EVENTO', 'OK', 'Evento EV1', '111', $this->peticion('/api/event/register'));
        $servicio->registrar('Auth', 'LOGIN_FALLIDO', 'ERROR', 'Clave errónea', '222', $this->peticion('/api/login'));

        $respuesta = $this->getJson('/api/logs');

        $respuesta->assertOk()->assertJsonPath('success', true);
        $datos = $respuesta->json('data');
        $this->assertCount(2, $datos);
        // Orden descendente por fecha.
        $this->assertSame('LOGIN_FALLIDO', $datos[0]['activity']);
        $this->assertSame('222', $datos[0]['num_documento']);
        $this->assertSame('192.168.1.10', $datos[0]['ip']);
        $this->assertSame('api.test', $datos[0]['server_name']);
        $this->assertSame('Auth', $datos[0]['modulo']);
        $this->assertSame('ERROR', $datos[0]['estado']);
        $this->assertArrayHasKey('fecha', $datos[0]);
        $this->assertArrayHasKey('detalle', $datos[0]);
        $this->assertSame(2, $respuesta->json('meta.total'));
    }

    public function test_listado_filtra_por_estado_modulo_y_documento(): void
    {
        $this->prepararBaseDatos();
        $this->withoutMiddleware();
        $servicio = app(AuditLogService::class);

        $servicio->registrar('Eventos', 'CREAR_EVENTO', 'OK', 'Evento EV1', '111', $this->peticion());
        $servicio->registrar('Eventos', 'ELIMINAR_EVENTO', 'ERROR', 'Evento EV2', '222', $this->peticion());

        $this->getJson('/api/logs?estado=ERROR')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.activity', 'ELIMINAR_EVENTO');

        $this->getJson('/api/logs?num_documento=11')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.num_documento', '111');

        $this->getJson('/api/logs?modulo=Auth')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_descargar_csv(): void
    {
        $this->prepararBaseDatos();
        $this->withoutMiddleware();
        app(AuditLogService::class)->registrar(
            'Perfiles', 'OTORGAR_PERMISO', 'OK', 'Permiso módulo 2 al usuario 5', '999', $this->peticion()
        );

        $respuesta = $this->get('/api/logs/descargar');

        $respuesta->assertOk();
        $this->assertStringContainsString('text/csv', $respuesta->headers->get('Content-Type'));

        $contenido = $respuesta->streamedContent();
        $this->assertStringContainsString('Num documento', $contenido);
        $this->assertStringContainsString('999', $contenido);
        $this->assertStringContainsString('OTORGAR_PERMISO', $contenido);
    }

    public function test_endpoints_exigen_superadmin(): void
    {
        // Sin token el middleware superadmin responde 401 antes de tocar la BD.
        $this->getJson('/api/logs')->assertStatus(401);
        $this->getJson('/api/logs/descargar')->assertStatus(401);
    }

    public function test_middleware_omite_respuestas_de_error(): void
    {
        $this->prepararBaseDatos();

        $peticion = $this->peticion();
        $peticion->setRouteResolver(fn () => null);

        app(AuditLogMiddleware::class)->terminate(
            $peticion,
            new JsonResponse(['success' => false, 'message' => 'Errores de validación'], 422)
        );

        $this->assertSame(0, DB::table('culturayturismo.auditoria_logs')->count());
    }

    public function test_middleware_registra_frase_descriptiva_en_exito(): void
    {
        $this->prepararBaseDatos();

        $peticion = $this->peticion();
        $ruta = new Route(['POST'], 'api/hotel/{id}', function () {});
        $ruta->setParameter('id', 'HOT-001');
        $peticion->setRouteResolver(fn () => $ruta);

        app(AuditLogMiddleware::class)->terminate(
            $peticion,
            new JsonResponse(['success' => true, 'data' => ['nombre' => 'Hotel Sogamoso Real']], 201)
        );

        $fila = DB::table('culturayturismo.auditoria_logs')->first();
        $this->assertNotNull($fila);
        $this->assertSame('OK', $fila->estado);
        $this->assertSame('Prestadores Servicios', $fila->modulo);

        $datos = json_decode(Crypt::decryptString($fila->datos_cifrados), true);
        $this->assertSame('CREAR_HOTEL', $datos['activity']);
        $this->assertStringContainsString('registró el hotel con id HOT-001', $datos['detalle']);
        $this->assertStringContainsString('(Hotel Sogamoso Real)', $datos['detalle']);
        $this->assertStringContainsString('en el módulo Prestadores Servicios', $datos['detalle']);
    }
}
