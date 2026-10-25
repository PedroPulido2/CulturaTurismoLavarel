<?php

namespace Tests\Feature;

use App\Models\Login;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\JwtService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CambiarPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('JWT_SECRET=test-secret-key-para-pruebas-1234567890');
        $_ENV['JWT_SECRET'] = 'test-secret-key-para-pruebas-1234567890';
    }

    /**
     * Prepara el esquema culturayturismo en sqlite :memory:.
     * Se omite si el driver sqlite no está instalado (ej. este entorno
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

        DB::statement('DROP TABLE IF EXISTS culturayturismo."login"');
        DB::statement('DROP TABLE IF EXISTS culturayturismo."usuario"');
        DB::statement('DROP TABLE IF EXISTS culturayturismo."rol"');

        DB::statement('CREATE TABLE culturayturismo."rol" (id_rol INTEGER PRIMARY KEY AUTOINCREMENT, nombre VARCHAR(100) NOT NULL)');
        DB::statement('CREATE TABLE culturayturismo."usuario" (id_usuario INTEGER PRIMARY KEY AUTOINCREMENT, tipo_identificacion VARCHAR(70) NOT NULL DEFAULT \'CC\', num_documento INTEGER NOT NULL, nombre VARCHAR(60) NOT NULL, apellido VARCHAR(60) NOT NULL, correo VARCHAR(150) NOT NULL UNIQUE, fecha_nacimiento DATE NULL, genero VARCHAR(50) NULL, telefono INTEGER NULL, url_foto VARCHAR(255) NULL, id_rol INTEGER NULL, reset_token_hash VARCHAR(255) NULL, reset_token_expires_at DATETIME NULL)');
        DB::statement('CREATE TABLE culturayturismo."login" (id_login INTEGER PRIMARY KEY AUTOINCREMENT, id_usuario INTEGER NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, estado VARCHAR(20) NOT NULL DEFAULT \'ACTIVO\', intentos_fallidos INTEGER NOT NULL DEFAULT 0, ultimo_acceso DATETIME NULL)');
    }

    private function crearUsuarioActivo(string $claveActual = 'Anterior123'): array
    {
        $rol = Rol::create(['nombre' => 'Administrador']);

        $usuario = Usuario::create([
            'tipo_identificacion' => 'CC',
            'num_documento' => random_int(1000000, 9999999),
            'nombre' => 'Prueba',
            'apellido' => 'Cambio',
            'correo' => 'cambio'.uniqid().'@test.com',
            'fecha_nacimiento' => '1990-01-01',
            'genero' => 'Otro',
            'telefono' => 3001234567,
            'id_rol' => $rol->id_rol,
        ]);

        Login::create([
            'id_usuario' => $usuario->id_usuario,
            'password' => Hash::make($claveActual),
            'estado' => 'ACTIVO',
            'intentos_fallidos' => 0,
        ]);

        $token = app(JwtService::class)->issueToken($usuario->id_usuario);

        return [$usuario, $token];
    }

    public function test_requiere_token(): void
    {
        $response = $this->putJson('/api/password/cambiar', [
            'current_password' => 'Anterior123',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ]);

        $response->assertStatus(401);
    }

    public function test_cambia_contrasena_con_actual_correcta(): void
    {
        $this->prepararBaseDatos();
        [$usuario, $token] = $this->crearUsuarioActivo();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)->putJson('/api/password/cambiar', [
            'current_password' => 'Anterior123',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $login = Login::where('id_usuario', $usuario->id_usuario)->first();
        $this->assertTrue(Hash::check('NuevaClave123', $login->password));
        $this->assertFalse(Hash::check('Anterior123', $login->password));

        // La nueva clave permite iniciar sesión.
        $loginResponse = $this->postJson('/api/login', [
            'correo' => $usuario->correo,
            'password' => 'NuevaClave123',
        ]);
        $loginResponse->assertOk()->assertJson(['success' => true]);
    }

    public function test_rechaza_actual_incorrecta(): void
    {
        $this->prepararBaseDatos();
        [$usuario, $token] = $this->crearUsuarioActivo();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)->putJson('/api/password/cambiar', [
            'current_password' => 'Equivocada999',
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);

        $login = Login::where('id_usuario', $usuario->id_usuario)->first();
        $this->assertTrue(Hash::check('Anterior123', $login->password));
    }

    public function test_valida_nueva_diferente_y_confirmada(): void
    {
        $this->prepararBaseDatos();
        [, $token] = $this->crearUsuarioActivo();

        $misma = $this->withHeader('Authorization', 'Bearer '.$token)->putJson('/api/password/cambiar', [
            'current_password' => 'Anterior123',
            'password' => 'Anterior123',
            'password_confirmation' => 'Anterior123',
        ]);
        $misma->assertStatus(400);

        $sinConfirmacion = $this->withHeader('Authorization', 'Bearer '.$token)->putJson('/api/password/cambiar', [
            'current_password' => 'Anterior123',
            'password' => 'NuevaClave123',
        ]);
        $sinConfirmacion->assertStatus(400);
    }
}
