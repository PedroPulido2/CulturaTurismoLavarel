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
use App\Http\Controllers\ServiciosCulturalesController;

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

// ==========================================
// RUTAS DE Hotel / hotel (HotelController)
// ==========================================
Route::get('/hotel', [HotelController::class, 'getAllHoteles']);
Route::get('/hotel/{id}', [HotelController::class, 'getHotelById']);
Route::post('/hotel/register', [HotelController::class, 'createHotel']);
Route::put('/hotel/{id}', [HotelController::class, 'updateHotel']);
Route::delete('/hotel/{id}', [HotelController::class, 'deleteHotel']);

// ==========================================
// RUTAS DE Restaurante / restaurant (RestauranteController)
// ==========================================
Route::get('/restaurant', [RestauranteController::class, 'getAllRestaurantes']);
Route::get('/restaurant/{id}', [RestauranteController::class, 'getRestauranteById']);
Route::post('/restaurant/register', [RestauranteController::class, 'createRestaurante']);
Route::put('/restaurant/{id}', [RestauranteController::class, 'updateRestaurante']);
Route::delete('/restaurant/{id}', [RestauranteController::class, 'deleteRestaurante']);
// ==========================================
// RUTAS DE Agencias / agency (AgenciaController)
// ==========================================
Route::get('/agency', [AgenciaController::class, 'getAllAgencias']);
Route::get('/agency/{id}', [AgenciaController::class, 'getAgenciaById']);
Route::post('/agency/register', [AgenciaController::class, 'createAgencia']);
Route::put('/agency/{id}', [AgenciaController::class, 'updateAgencia']);
Route::delete('/agency/{id}', [AgenciaController::class, 'deleteAgencia']);
// ==========================================
// RUTAS DE Guia / guide (GuiaController)
// ==========================================
Route::get('/guide', [GuiaController::class, 'getAllGuias']);
Route::get('/guide/{id}', [GuiaController::class, 'getGuiaById']);
Route::post('/guide/register', [GuiaController::class, 'createGuia']);
Route::put('/guide/{id}', [GuiaController::class, 'updateGuia']);
Route::delete('/guide/{id}', [GuiaController::class, 'deleteGuia']);
// ==========================================
// RUTAS DE ServiciosCulturales / culturalServices (ServiciosCulturalesController)
// ==========================================
Route::get('/culturalServices', [ServiciosCulturalesController::class, 'getAllServiciosCulturales']);
Route::get('/culturalServices/{id}', [ServiciosCulturalesController::class, 'getServicioCulturalById']);
Route::post('/culturalServices/register', [ServiciosCulturalesController::class, 'createServicioCultural']);
Route::put('/culturalServices/{id}', [ServiciosCulturalesController::class, 'updateServicioCultural']);
Route::delete('/culturalServices/{id}', [ServiciosCulturalesController::class, 'deleteServicioCultural']);
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