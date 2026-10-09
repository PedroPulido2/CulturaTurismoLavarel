<?php

use App\Http\Controllers\AgenciaController;
use App\Http\Controllers\AtractivoTuristicoController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\GuiaController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PrestadoresPublicoController;
use App\Http\Controllers\RestauranteController;
use App\Http\Controllers\ServicioCulturalController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// ==========================================
// RUTAS DE AUTENTICACIÓN (LoginController)
// ==========================================
Route::post('/login', [LoginController::class, 'login']);
// Desbloqueo de cuentas: solo Super Administrador (evita bypass del lockout).
Route::post('/login/unlock/{id_usuario}', [LoginController::class, 'unlockUser'])->middleware('superadmin');

// ==========================================
// ACTIVACIÓN Y RECUPERACIÓN POR CORREO (PasswordController)
// ==========================================
Route::post('/password/activar', [PasswordController::class, 'activar']);
Route::post('/password/restablecer', [PasswordController::class, 'restablecer']);
Route::post('/password/olvide', [PasswordController::class, 'olvide']);
Route::post('/password/reenviar-activacion', [PasswordController::class, 'reenviarActivacion']);

// ==========================================
// RUTAS DE USUARIOS / PROFILES (UserController)
//
// Lista/crear/borrar: solo Super Administrador.
// Detalle/actualizar: Super Administrador o dueño.
// Roles y módulos (catálogo): cualquier usuario autenticado.
// ==========================================
Route::get('/profiles', [UserController::class, 'getAllProfiles'])->middleware('superadmin');
Route::get('/profiles/e/{email}', [UserController::class, 'getProfileByEmail'])->middleware('self_or_superadmin:email');
Route::get('/profiles/{id_usuario}', [UserController::class, 'getProfileById'])->middleware('self_or_superadmin:id_usuario');
Route::post('/profiles/registro', [UserController::class, 'createProfile'])->middleware('superadmin');
Route::put('/profiles/{id_usuario}', [UserController::class, 'updateProfile'])->middleware('self_or_superadmin:id_usuario');
Route::delete('/profiles/{id_usuario}', [UserController::class, 'deleteProfile'])->middleware('superadmin');

// ==========================================
// ROLES, MÓDULOS Y PERMISOS (UserController)
// ==========================================
Route::get('/roles', [UserController::class, 'getRoles'])->middleware('auth.jwt');
Route::get('/modulos', [UserController::class, 'getModulos'])->middleware('auth.jwt');
Route::get('/profiles/{id_usuario}/modulos', [UserController::class, 'getUserModulos'])->middleware('self_or_superadmin:id_usuario');
Route::post('/profiles/{id_usuario}/modulos', [UserController::class, 'assignModulos'])->middleware('superadmin');
Route::delete('/profiles/{id_usuario}/modulos/{id_modulo}', [UserController::class, 'revokeModulo'])->middleware('superadmin');

// ==========================================
// LECTURA PÚBLICA (vitrina del frontend, sin token)
// Escritura (POST/PUT/PATCH/DELETE): middleware 'modulo:X'
// con el nombre exacto de culturayturismo.modulo.
// ==========================================
// --- Atractivos Turísticos (módulo 1) ---
Route::get('/tourism', [AtractivoTuristicoController::class, 'getAllAtractivos']);
Route::get('/tourism/{id}', [AtractivoTuristicoController::class, 'getAtractivoById']);
Route::post('/tourism/register', [AtractivoTuristicoController::class, 'createAtractivo'])->middleware('modulo:Atractivos Turísticos');
Route::put('/tourism/{id}', [AtractivoTuristicoController::class, 'updateAtractivo'])->middleware('modulo:Atractivos Turísticos');
Route::delete('/tourism/{id}', [AtractivoTuristicoController::class, 'deleteAtractivo'])->middleware('modulo:Atractivos Turísticos');
Route::patch('/tourism/{id}/visibility', [AtractivoTuristicoController::class, 'updateVisibility'])->middleware('modulo:Atractivos Turísticos');

// --- Prestadores Servicios (módulo 2): Hotel / Restaurante / Agencia / Guía ---
Route::get('/hotel', [HotelController::class, 'getAllHoteles']);
Route::get('/hotel/{id}', [HotelController::class, 'getHotelById']);
Route::post('/hotel/register', [HotelController::class, 'createHotel'])->middleware('modulo:Prestadores Servicios');
Route::put('/hotel/{id}', [HotelController::class, 'updateHotel'])->middleware('modulo:Prestadores Servicios');
Route::delete('/hotel/{id}', [HotelController::class, 'deleteHotel'])->middleware('modulo:Prestadores Servicios');
Route::patch('/hotel/{id}/visibility', [HotelController::class, 'updateVisibility'])->middleware('modulo:Prestadores Servicios');

Route::get('/restaurant', [RestauranteController::class, 'getAllRestaurantes']);
Route::get('/restaurant/{id}', [RestauranteController::class, 'getRestauranteById']);
Route::post('/restaurant/register', [RestauranteController::class, 'createRestaurante'])->middleware('modulo:Prestadores Servicios');
Route::put('/restaurant/{id}', [RestauranteController::class, 'updateRestaurante'])->middleware('modulo:Prestadores Servicios');
Route::delete('/restaurant/{id}', [RestauranteController::class, 'deleteRestaurante'])->middleware('modulo:Prestadores Servicios');
Route::patch('/restaurant/{id}/visibility', [RestauranteController::class, 'updateVisibility'])->middleware('modulo:Prestadores Servicios');

Route::get('/agency', [AgenciaController::class, 'getAllAgencias']);
Route::get('/agency/{id}', [AgenciaController::class, 'getAgenciaById']);
Route::post('/agency/register', [AgenciaController::class, 'createAgencia'])->middleware('modulo:Prestadores Servicios');
Route::put('/agency/{id}', [AgenciaController::class, 'updateAgencia'])->middleware('modulo:Prestadores Servicios');
Route::delete('/agency/{id}', [AgenciaController::class, 'deleteAgencia'])->middleware('modulo:Prestadores Servicios');
Route::patch('/agency/{id}/visibility', [AgenciaController::class, 'updateVisibility'])->middleware('modulo:Prestadores Servicios');

Route::get('/guide', [GuiaController::class, 'getAllGuias']);
Route::get('/guide/{id}', [GuiaController::class, 'getGuiaById']);
Route::post('/guide/register', [GuiaController::class, 'createGuia'])->middleware('modulo:Prestadores Servicios');
Route::put('/guide/{id}', [GuiaController::class, 'updateGuia'])->middleware('modulo:Prestadores Servicios');
Route::delete('/guide/{id}', [GuiaController::class, 'deleteGuia'])->middleware('modulo:Prestadores Servicios');

// --- Eventos (módulo 4) ---
Route::get('/event', [EventoController::class, 'getAllEventos']);
Route::get('/event/{id}', [EventoController::class, 'getEventoById']);
Route::post('/event/register', [EventoController::class, 'createEvento'])->middleware('modulo:Eventos');
Route::put('/event/{id}', [EventoController::class, 'updateEvento'])->middleware('modulo:Eventos');
Route::delete('/event/{id}', [EventoController::class, 'deleteEvento'])->middleware('modulo:Eventos');

// ==========================================
// CATÁLOGOS: lectura pública, escritura con permiso del módulo dueño
// ==========================================
Route::get('/tipos-cocina', [RestauranteController::class, 'getTiposCocina']);
Route::post('/tipos-cocina/register', [RestauranteController::class, 'createTipoCocina'])->middleware('modulo:Prestadores Servicios');
Route::delete('/tipos-cocina/{id}', [RestauranteController::class, 'deleteTipoCocina'])->middleware('modulo:Prestadores Servicios');

Route::get('/tipos-agencia', [AgenciaController::class, 'getTiposAgencia']);
Route::post('/tipos-agencia/register', [AgenciaController::class, 'createTipoAgencia'])->middleware('modulo:Prestadores Servicios');
Route::delete('/tipos-agencia/{id}', [AgenciaController::class, 'deleteTipoAgencia'])->middleware('modulo:Prestadores Servicios');

Route::get('/especialidades', [GuiaController::class, 'getEspecialidades']);
Route::post('/especialidades/register', [GuiaController::class, 'createEspecialidad'])->middleware('modulo:Prestadores Servicios');
Route::delete('/especialidades/{id}', [GuiaController::class, 'deleteEspecialidad'])->middleware('modulo:Prestadores Servicios');

Route::get('/disponibilidades', [GuiaController::class, 'getDisponibilidades']);
Route::post('/disponibilidades/register', [GuiaController::class, 'createDisponibilidad'])->middleware('modulo:Prestadores Servicios');
Route::delete('/disponibilidades/{id}', [GuiaController::class, 'deleteDisponibilidad'])->middleware('modulo:Prestadores Servicios');

Route::get('/tipos-publico', [GuiaController::class, 'getTiposPublico']);
Route::post('/tipos-publico/register', [GuiaController::class, 'createTipoPublico'])->middleware('modulo:Prestadores Servicios');
Route::delete('/tipos-publico/{id}', [GuiaController::class, 'deleteTipoPublico'])->middleware('modulo:Prestadores Servicios');

// ==========================================
// RUTA DE PrestadoresPublicos / prestadores-turisticos (PrestadoresPublicoController)
// ==========================================
Route::get('/prestadores-turisticos', [PrestadoresPublicoController::class, 'getPrestadoresPublicos']);
Route::get('/redes-sociales', [PrestadoresPublicoController::class, 'getRedesSociales']);

// ==========================================
// RUTAS DE Servicios Culturales / cultural-services (módulo 3)
// ==========================================
Route::get('/cultural-services', [ServicioCulturalController::class, 'getAllServicios']);
Route::get('/cultural-services/{id}', [ServicioCulturalController::class, 'getServicioById']);
Route::post('/cultural-services/register', [ServicioCulturalController::class, 'createServicio'])->middleware('modulo:Servicios Culturales');
Route::put('/cultural-services/{id}', [ServicioCulturalController::class, 'updateServicio'])->middleware('modulo:Servicios Culturales');
Route::delete('/cultural-services/{id}', [ServicioCulturalController::class, 'deleteServicio'])->middleware('modulo:Servicios Culturales');

Route::get('/areas-artisticas', [ServicioCulturalController::class, 'getAreasArtisticas']);
Route::post('/areas-artisticas/register', [ServicioCulturalController::class, 'createAreasArtisticas'])->middleware('modulo:Servicios Culturales');
Route::delete('/areas-artisticas/{id}', [ServicioCulturalController::class, 'deleteAreasArtisticas'])->middleware('modulo:Servicios Culturales');

Route::get('/tipos-servicio', [ServicioCulturalController::class, 'getTiposServicio']);
Route::post('/tipos-servicio/register', [ServicioCulturalController::class, 'createTipoServicio'])->middleware('modulo:Servicios Culturales');
Route::delete('/tipos-servicio/{id}', [ServicioCulturalController::class, 'deleteTipoServicio'])->middleware('modulo:Servicios Culturales');

Route::get('/publico-dirigido', [ServicioCulturalController::class, 'getPublicoDirigido']);
Route::post('/publico-dirigido/register', [ServicioCulturalController::class, 'createPublicoDirigido'])->middleware('modulo:Servicios Culturales');
Route::delete('/publico-dirigido/{id}', [ServicioCulturalController::class, 'deletePublicoDirigido'])->middleware('modulo:Servicios Culturales');

// ==========================================
// RUTAS DE DIAGNÓSTICO (Opcionales, para pruebas)
// ==========================================
Route::get('/debug-db', function () {
    try {
        // Consultamos directamente al diccionario de PostgreSQL
        $tablas = DB::select("
            SELECT table_schema, table_name 
            FROM information_schema.tables 
            WHERE table_schema NOT IN ('information_schema', 'pg_catalog')
        ");

        return response()->json([
            'mensaje' => 'Esto es lo que REALMENTE existe en Render:',
            'tablas_en_render' => $tablas,
        ]);
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});
