<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LoginController;

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
Route::get('/profiles/{id_perfil}',[UserController::class,'getProfileById']);
Route::post('/profiles/registro', [UserController::class, 'createProfile']);
Route::put('/profiles/{id}', [UserController::class, 'updateProfile']);
Route::delete('/profiles/{id}', [UserController::class, 'deleteProfile']);

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