<?php

namespace Tests\Feature;

use App\Http\Resources\UsuarioDetailResource;
use App\Http\Resources\UsuarioListResource;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Tests\TestCase;

class SecurityAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('JWT_SECRET=test-secret-para-pruebas-1234567890');
        $_ENV['JWT_SECRET'] = 'test-secret-para-pruebas-1234567890';
    }

    public function test_escritura_sin_token_es_401(): void
    {
        // Cada módulo: un POST representativo debe exigir Bearer.
        $this->postJson('/api/tourism/register', [])->assertStatus(401);
        $this->postJson('/api/hotel/register', [])->assertStatus(401);
        $this->postJson('/api/restaurant/register', [])->assertStatus(401);
        $this->postJson('/api/agency/register', [])->assertStatus(401);
        $this->postJson('/api/guide/register', [])->assertStatus(401);
        $this->postJson('/api/event/register', [])->assertStatus(401);
        $this->postJson('/api/cultural-services/register', [])->assertStatus(401);
    }

    public function test_gestion_usuarios_sin_token_es_401(): void
    {
        $this->getJson('/api/profiles')->assertStatus(401);
        $this->postJson('/api/profiles/registro', [])->assertStatus(401);
        $this->postJson('/api/login/unlock/1')->assertStatus(401);
        $this->getJson('/api/roles')->assertStatus(401);
        $this->getJson('/api/modulos')->assertStatus(401);
    }

    public function test_token_con_secreto_incorrecto_es_401(): void
    {
        $jwt = new JwtService('secreto-correcto-para-pruebas-1234567890ABCD');
        $token = $jwt->issueToken(1);

        // El middleware usa otro secreto -> firma inválida.
        putenv('JWT_SECRET=otro-secreto-diferente-para-pruebas-0987654321WXYZ');
        $_ENV['JWT_SECRET'] = 'otro-secreto-diferente-para-pruebas-0987654321WXYZ';

        $this->postJson('/api/event/register', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(401);
    }

    public function test_jwt_minimo_roundtrip_sin_pii(): void
    {
        $jwt = new JwtService('secreto-prueba-roundtrip-para-tests-1234567890AB');
        $token = $jwt->issueToken(42);
        $payload = $jwt->decode($token);

        $this->assertSame(42, $payload->sub);
        $this->assertObjectNotHasProperty('user', $payload);
        $this->assertSame(42, $jwt->parseUserId($token));
    }

    public function test_lista_oculta_datos_sensibles_y_detalle_los_incluye(): void
    {
        $usuario = new Usuario([
            'tipo_identificacion' => 'CC',
            'num_documento' => 123456,
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'correo' => 'ana@example.com',
            'fecha_nacimiento' => '1990-01-01',
            'genero' => 'F',
            'telefono' => 3001234567,
            'url_foto' => null,
        ]);
        $usuario->id_usuario = 7;
        $rol = new Rol(['nombre' => 'Administrador']);
        $rol->id_rol = 2;
        $modulo = new Modulo(['nombre' => 'Eventos']);
        $modulo->id_modulo = 4;
        $usuario->setRelation('rol', $rol);
        $usuario->setRelation('modulos', collect([$modulo]));

        $request = Request::create('/', 'GET');

        $lista = (new UsuarioListResource($usuario))->toArray($request);
        $this->assertArrayHasKey('correo', $lista);
        $this->assertArrayNotHasKey('num_documento', $lista);
        $this->assertArrayNotHasKey('telefono', $lista);
        $this->assertArrayNotHasKey('fecha_nacimiento', $lista);
        $this->assertArrayNotHasKey('genero', $lista);
        $this->assertArrayNotHasKey('reset_token_hash', $lista);

        $detalle = (new UsuarioDetailResource($usuario))->toArray($request);
        $this->assertSame(123456, $detalle['num_documento']);
        $this->assertArrayNotHasKey('reset_token_hash', $detalle);
        $this->assertArrayNotHasKey('login', $detalle);
    }
}
