<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\AtractivoTuristicoController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\RestauranteController;
use App\Http\Controllers\AgenciaController;
use App\Http\Controllers\GuiaController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\PrestadoresPublicoController;
use App\Http\Controllers\ServicioCulturalController;

// ==========================================
// RUTAS DE AUTENTICACIÓN (LoginController)
// ==========================================
Route::post('/login', [LoginController::class, 'login']);
Route::post('/login/unlock/{id_perfil}', [LoginController::class, 'unlockUser']);

// ==========================================
// RUTAS DE USUARIOS / PROFILES (UserController)
// ==========================================
Route::get('/profiles', [UserController::class, 'getAllProfiles']);
Route::get('/profiles/e/{email}', [UserController::class, 'getProfileByEmail']);
Route::get('/profiles/{id_perfil}', [UserController::class, 'getProfileById']);
Route::post('/profiles/registro', [UserController::class, 'createProfile']);
Route::put('/profiles/{id_perfil}', [UserController::class, 'updateProfile']);
Route::delete('/profiles/{id_perfil}', [UserController::class, 'deleteProfile']);

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
// RUTA DE PrestadoresPublicos / prestadores-turisticos (PrestadoresPublicoController)
// ==========================================
Route::get('/prestadores-turisticos', [PrestadoresPublicoController::class, 'getPrestadoresPublicos']);

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

Route::get('/tipos-perfiles-sc', [ServicioCulturalController::class, 'getTiposPerfilesSc']);
Route::post('/tipos-perfiles-sc/register', [ServicioCulturalController::class, 'createTiposPerfilesSc']);
Route::delete('/tipos-perfiles-sc/{id}', [ServicioCulturalController::class, 'deleteTiposPerfilesSc']);

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
            'tablas_en_render' => $tablas
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});