<?php

namespace Tests\Feature;

use App\Http\Controllers\Concerns\RechazaCambioDePk;
use App\Models\Login;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PkInmutableEnUpdateTest extends TestCase
{
    public function test_trait_tolera_ausente_o_igual_y_rechaza_distinto(): void
    {
        $sujeto = new class
        {
            use RechazaCambioDePk;

            public function verificar(Request $request, string $campo, mixed $id)
            {
                return $this->rechazarCambioDePk($request, $campo, $id);
            }
        };

        // Sin ID en el body: se ignora y se sigue con el de la URL.
        $this->assertNull($sujeto->verificar(Request::create('/x', 'PUT', []), 'id_hotel', 'H1'));

        // ID igual al de la URL: se tolera (el front puede reenviar el objeto del GET).
        $igual = Request::create('/x', 'PUT', ['id_hotel' => 'H1']);
        $this->assertNull($sujeto->verificar($igual, 'id_hotel', 'H1'));

        // ID distinto: 422, la PK no se puede modificar.
        $distinto = Request::create('/x', 'PUT', ['id_hotel' => 'H2']);
        $respuesta = $sujeto->verificar($distinto, 'id_hotel', 'H1');
        $this->assertNotNull($respuesta);
        $this->assertSame(422, $respuesta->getStatusCode());
        $this->assertFalse($respuesta->getData(true)['success']);
    }

    public function test_updates_rechazan_id_distinto_con_422(): void
    {
        $token = $this->prepararBaseDatos();

        $casos = [
            ['/api/tourism/ATR-1', 'atractivo_turistico', 'id_atractivo_turistico', 'ATR-1'],
            ['/api/hotel/HOT-1', 'hotel', 'id_hotel', 'HOT-1'],
            ['/api/restaurant/RES-1', 'restaurante', 'id_restaurante', 'RES-1'],
            ['/api/agency/AGE-1', 'agencia', 'id_agencia', 'AGE-1'],
            ['/api/guide/GUI-1', 'guia', 'id_guia', 'GUI-1'],
            ['/api/event/EVE-1', 'evento', 'id_evento', 'EVE-1'],
            ['/api/cultural-services/7', 'servicio_cultural', 'id_servicio_cultural', 7],
        ];

        foreach ($casos as [$url, $tabla, $campo, $idUrl]) {
            $pk = $tabla === 'servicio_cultural' ? ['id_servicio_cultural' => 7] : [$campo => $idUrl];
            DB::table("culturayturismo.\"{$tabla}\"")->insert($pk);

            $idDistinto = $tabla === 'servicio_cultural' ? 999 : 'OTRO-ID';

            $response = $this->withHeader('Authorization', 'Bearer '.$token)
                ->putJson($url, [$campo => $idDistinto]);

            $response->assertStatus(422, "PUT {$url} con {$campo} distinto debió devolver 422");
            $response->assertJson(['success' => false]);
        }
    }

    public function test_update_acepta_id_igual_al_de_la_url(): void
    {
        $token = $this->prepararBaseDatos();

        DB::table('culturayturismo."atractivo_turistico"')->insert([
            'id_atractivo_turistico' => 'ATR-1',
            'nombre' => 'Viejo',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/tourism/ATR-1', [
                'id_atractivo_turistico' => 'ATR-1',
                'nombre' => 'Nuevo',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame('Nuevo', $response->json('data.nombre'));
    }

    /**
     * Esquema mínimo en sqlite :memory: (BD adjunta culturayturismo).
     * Devuelve un JWT de Super Administrador (bypasea el middleware de módulos).
     */
    private function prepararBaseDatos(): string
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Requiere pdo_sqlite, no instalado en este entorno.');
        }

        putenv('JWT_SECRET=test-secret-key-para-pruebas-1234567890');
        $_ENV['JWT_SECRET'] = 'test-secret-key-para-pruebas-1234567890';

        try {
            DB::statement('ATTACH DATABASE ":memory:" AS culturayturismo');
        } catch (\Exception $e) {
            // Ya adjuntada en este proceso.
        }

        foreach (['login', 'usuario', 'rol', 'direccion_google', 'fotos', 'atractivo_turistico', 'hotel', 'restaurante', 'agencia', 'guia', 'evento', 'servicio_cultural'] as $tabla) {
            DB::statement("DROP TABLE IF EXISTS culturayturismo.\"{$tabla}\"");
        }

        DB::statement('CREATE TABLE culturayturismo."rol" (id_rol INTEGER PRIMARY KEY AUTOINCREMENT, nombre VARCHAR(100) NOT NULL)');
        DB::statement('CREATE TABLE culturayturismo."usuario" (id_usuario INTEGER PRIMARY KEY AUTOINCREMENT, tipo_identificacion VARCHAR(70) NOT NULL DEFAULT \'CC\', num_documento INTEGER NOT NULL, nombre VARCHAR(60) NOT NULL, apellido VARCHAR(60) NOT NULL, correo VARCHAR(150) NOT NULL UNIQUE, fecha_nacimiento DATE NULL, genero VARCHAR(50) NULL, telefono INTEGER NULL, url_foto VARCHAR(255) NULL, id_rol INTEGER NULL, reset_token_hash VARCHAR(255) NULL, reset_token_expires_at DATETIME NULL)');
        DB::statement('CREATE TABLE culturayturismo."login" (id_login INTEGER PRIMARY KEY AUTOINCREMENT, id_usuario INTEGER NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, estado VARCHAR(20) NOT NULL DEFAULT \'ACTIVO\', intentos_fallidos INTEGER NOT NULL DEFAULT 0, ultimo_acceso DATETIME NULL)');
        DB::statement('CREATE TABLE culturayturismo."direccion_google" (id_direccion INTEGER PRIMARY KEY AUTOINCREMENT, direccion TEXT NULL, latitud REAL NULL, longitud REAL NULL)');
        DB::statement('CREATE TABLE culturayturismo."fotos" (id_foto INTEGER PRIMARY KEY AUTOINCREMENT, url_foto TEXT NULL, is_portada INTEGER NULL, id_hotel TEXT NULL, id_atractivo_turistico TEXT NULL, id_restaurante TEXT NULL, id_agencia TEXT NULL, id_servicio_cultural INTEGER NULL, id_evento TEXT NULL)');
        DB::statement('CREATE TABLE culturayturismo."atractivo_turistico" (id_atractivo_turistico TEXT PRIMARY KEY, nombre TEXT NULL)');
        DB::statement('CREATE TABLE culturayturismo."hotel" (id_hotel TEXT PRIMARY KEY, nombre TEXT NULL)');
        DB::statement('CREATE TABLE culturayturismo."restaurante" (id_restaurante TEXT PRIMARY KEY, nombre TEXT NULL)');
        DB::statement('CREATE TABLE culturayturismo."agencia" (id_agencia TEXT PRIMARY KEY, nombre TEXT NULL)');
        DB::statement('CREATE TABLE culturayturismo."guia" (id_guia TEXT PRIMARY KEY, nombre TEXT NULL)');
        DB::statement('CREATE TABLE culturayturismo."evento" (id_evento TEXT PRIMARY KEY, nombre TEXT NULL)');
        DB::statement('CREATE TABLE culturayturismo."servicio_cultural" (id_servicio_cultural INTEGER PRIMARY KEY, nombre TEXT NULL)');

        $rol = Rol::create(['nombre' => 'Super Administrador']);
        $usuario = Usuario::create([
            'tipo_identificacion' => 'CC',
            'num_documento' => 123456789,
            'nombre' => 'Super',
            'apellido' => 'Admin',
            'correo' => 'super@test.com',
            'fecha_nacimiento' => '1990-01-01',
            'genero' => 'Otro',
            'telefono' => 3001234567,
            'id_rol' => $rol->id_rol,
        ]);
        Login::create([
            'id_usuario' => $usuario->id_usuario,
            'password' => Hash::make('Clave1234'),
            'estado' => 'ACTIVO',
            'intentos_fallidos' => 0,
        ]);

        return app(JwtService::class)->issueToken($usuario->id_usuario);
    }
}
