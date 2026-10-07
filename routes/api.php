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
Route::post('/login/unlock/{id_usuario}', [LoginController::class, 'unlockUser']);

// ==========================================
// ACTIVACIÓN Y RECUPERACIÓN POR CORREO (PasswordController)
// ==========================================
Route::post('/password/activar', [PasswordController::class, 'activar']);
Route::post('/password/restablecer', [PasswordController::class, 'restablecer']);
Route::post('/password/olvide', [PasswordController::class, 'olvide']);
Route::post('/password/reenviar-activacion', [PasswordController::class, 'reenviarActivacion']);

// ==========================================
// RUTAS DE USUARIOS / PROFILES (UserController)
// ==========================================
Route::get('/profiles', [UserController::class, 'getAllProfiles']);
Route::get('/profiles/e/{email}', [UserController::class, 'getProfileByEmail']);
Route::get('/profiles/{id_usuario}', [UserController::class, 'getProfileById']);
Route::post('/profiles/registro', [UserController::class, 'createProfile']);
Route::put('/profiles/{id_usuario}', [UserController::class, 'updateProfile']);
Route::delete('/profiles/{id_usuario}', [UserController::class, 'deleteProfile']);

// ==========================================
// ROLES, MÓDULOS Y PERMISOS (UserController)
// ==========================================
Route::get('/roles', [UserController::class, 'getRoles']);
Route::get('/modulos', [UserController::class, 'getModulos']);
Route::get('/profiles/{id_usuario}/modulos', [UserController::class, 'getUserModulos']);
Route::post('/profiles/{id_usuario}/modulos', [UserController::class, 'assignModulos'])->middleware('superadmin');
Route::delete('/profiles/{id_usuario}/modulos/{id_modulo}', [UserController::class, 'revokeModulo'])->middleware('superadmin');

// ==========================================
// RUTAS DE ATRACTIVOS TURISTICOS / tourism (AtractivoTuristicoController)
// ==========================================
Route::get('/tourism', [AtractivoTuristicoController::class, 'getAllAtractivos']);
Route::get('/tourism/{id}', [AtractivoTuristicoController::class, 'getAtractivoById']);
Route::post('/tourism/register', [AtractivoTuristicoController::class, 'createAtractivo']);
Route::put('/tourism/{id}', [AtractivoTuristicoController::class, 'updateAtractivo']);
Route::delete('/tourism/{id}', [AtractivoTuristicoController::class, 'deleteAtractivo']);
Route::patch('/tourism/{id}/visibility', [AtractivoTuristicoController::class, 'updateVisibility']);

// ==========================================
// RUTAS DE Hotel / hotel (HotelController)
// ==========================================
Route::get('/hotel', [HotelController::class, 'getAllHoteles']);
Route::get('/hotel/{id}', [HotelController::class, 'getHotelById']);
Route::post('/hotel/register', [HotelController::class, 'createHotel']);
Route::put('/hotel/{id}', [HotelController::class, 'updateHotel']);
Route::delete('/hotel/{id}', [HotelController::class, 'deleteHotel']);
Route::patch('/hotel/{id}/visibility', [HotelController::class, 'updateVisibility']);

// ==========================================
// RUTAS DE Restaurante / restaurant (RestauranteController)
// ==========================================
Route::get('/restaurant', [RestauranteController::class, 'getAllRestaurantes']);
Route::get('/restaurant/{id}', [RestauranteController::class, 'getRestauranteById']);
Route::post('/restaurant/register', [RestauranteController::class, 'createRestaurante']);
Route::put('/restaurant/{id}', [RestauranteController::class, 'updateRestaurante']);
Route::delete('/restaurant/{id}', [RestauranteController::class, 'deleteRestaurante']);
Route::patch('/restaurant/{id}/visibility', [RestauranteController::class, 'updateVisibility']);

// ==========================================
// RUTAS DE Agencias / agency (AgenciaController)
// ==========================================
Route::get('/agency', [AgenciaController::class, 'getAllAgencias']);
Route::get('/agency/{id}', [AgenciaController::class, 'getAgenciaById']);
Route::post('/agency/register', [AgenciaController::class, 'createAgencia']);
Route::put('/agency/{id}', [AgenciaController::class, 'updateAgencia']);
Route::delete('/agency/{id}', [AgenciaController::class, 'deleteAgencia']);
Route::patch('/agency/{id}/visibility', [AgenciaController::class, 'updateVisibility']);

// ==========================================
// RUTAS DE Guia / guide (GuiaController)
// ==========================================
Route::get('/guide', [GuiaController::class, 'getAllGuias']);
Route::get('/guide/{id}', [GuiaController::class, 'getGuiaById']);
Route::post('/guide/register', [GuiaController::class, 'createGuia']);
Route::put('/guide/{id}', [GuiaController::class, 'updateGuia']);
Route::delete('/guide/{id}', [GuiaController::class, 'deleteGuia']);

// ==========================================
// RUTAS DE Evento / event (EventoController)
// ==========================================
Route::get('/event', [EventoController::class, 'getAllEventos']);
Route::get('/event/{id}', [EventoController::class, 'getEventoById']);
Route::post('/event/register', [EventoController::class, 'createEvento']);
Route::put('/event/{id}', [EventoController::class, 'updateEvento']);
Route::delete('/event/{id}', [EventoController::class, 'deleteEvento']);

// ==========================================
// CATÁLOGOS DE Restaurante / Guia / Agencia
// ==========================================
Route::get('/tipos-cocina', [RestauranteController::class, 'getTiposCocina']);
Route::post('/tipos-cocina/register', [RestauranteController::class, 'createTipoCocina']);
Route::delete('/tipos-cocina/{id}', [RestauranteController::class, 'deleteTipoCocina']);

Route::get('/tipos-agencia', [AgenciaController::class, 'getTiposAgencia']);
Route::post('/tipos-agencia/register', [AgenciaController::class, 'createTipoAgencia']);
Route::delete('/tipos-agencia/{id}', [AgenciaController::class, 'deleteTipoAgencia']);

Route::get('/especialidades', [GuiaController::class, 'getEspecialidades']);
Route::post('/especialidades/register', [GuiaController::class, 'createEspecialidad']);
Route::delete('/especialidades/{id}', [GuiaController::class, 'deleteEspecialidad']);

Route::get('/disponibilidades', [GuiaController::class, 'getDisponibilidades']);
Route::post('/disponibilidades/register', [GuiaController::class, 'createDisponibilidad']);
Route::delete('/disponibilidades/{id}', [GuiaController::class, 'deleteDisponibilidad']);

Route::get('/competencias', [GuiaController::class, 'getCompetencias']);
Route::post('/competencias/register', [GuiaController::class, 'createCompetencia']);
Route::delete('/competencias/{id}', [GuiaController::class, 'deleteCompetencia']);

Route::get('/tipos-publico', [GuiaController::class, 'getTiposPublico']);
Route::post('/tipos-publico/register', [GuiaController::class, 'createTipoPublico']);
Route::delete('/tipos-publico/{id}', [GuiaController::class, 'deleteTipoPublico']);

// ==========================================
// RUTA DE PrestadoresPublicos / prestadores-turisticos (PrestadoresPublicoController)
// ==========================================
Route::get('/prestadores-turisticos', [PrestadoresPublicoController::class, 'getPrestadoresPublicos']);
Route::get('/redes-sociales', [PrestadoresPublicoController::class, 'getRedesSociales']);

// ==========================================
// RUTAS DE Servicios Culturales / cultural-services (ServicioCulturalController)
// ==========================================
Route::get('/cultural-services', [ServicioCulturalController::class, 'getAllServicios']);
Route::get('/cultural-services/{id}', [ServicioCulturalController::class, 'getServicioById']);
Route::post('/cultural-services/register', [ServicioCulturalController::class, 'createServicio']);
Route::put('/cultural-services/{id}', [ServicioCulturalController::class, 'updateServicio']);
Route::delete('/cultural-services/{id}', [ServicioCulturalController::class, 'deleteServicio']);

Route::get('/areas-artisticas', [ServicioCulturalController::class, 'getAreasArtisticas']);
Route::post('/areas-artisticas/register', [ServicioCulturalController::class, 'createAreasArtisticas']);
Route::delete('/areas-artisticas/{id}', [ServicioCulturalController::class, 'deleteAreasArtisticas']);

Route::get('/tipos-servicio', [ServicioCulturalController::class, 'getTiposServicio']);
Route::post('/tipos-servicio/register', [ServicioCulturalController::class, 'createTipoServicio']);
Route::delete('/tipos-servicio/{id}', [ServicioCulturalController::class, 'deleteTipoServicio']);

Route::get('/publico-dirigido', [ServicioCulturalController::class, 'getPublicoDirigido']);
Route::post('/publico-dirigido/register', [ServicioCulturalController::class, 'createPublicoDirigido']);
Route::delete('/publico-dirigido/{id}', [ServicioCulturalController::class, 'deletePublicoDirigido']);

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
