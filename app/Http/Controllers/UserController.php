<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Perfil;
use App\Models\Login;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use App\Services\GoogleDriveService;

class UserController extends Controller
{
    protected $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    public function getAllProfiles()
    {
        $perfiles = Perfil::all();
        return response()->json(['success' => true, 'data' => $perfiles]);
    }

    public function getProfileByEmail($email)
    {
        $perfil = Perfil::where('correo', $email)->first();
        if (!$perfil) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }
        return response()->json(['success' => true, 'data' => $perfil]);
    }

    public function getPRofileById($id_perfil)
    {
        $perfil = Perfil::where('id_perfil', $id_perfil)->first();

        if (!$perfil) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }
        return response()->json(['success' => true, 'data' => $perfil]);
    }

    public function createProfile(Request $request)
    {
        $reglas = [
            'id_perfil' => ['required', Rule::unique(Perfil::class, 'id_perfil')],
            'correo' => ['required', 'email', Rule::unique(Perfil::class, 'correo')]
        ];

        $mensajes = [
            'id_perfil.unique' => 'Este ID / Número de identificación ya se encuentra registrado.',
            'correo.unique' => 'Este correo electrónico ya está en uso por otra cuenta.'
        ];

        $validador = Validator::make($request->all(), $reglas, $mensajes);

        if ($validador->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validador->errors()
            ], 400);
        }

        $rutaFoto = null;

        //Si se proporciona foto al momento de crear el usuario
        if ($request->hasFile('url_foto')) {
            $archivo = $request->file('url_foto');
            $nombreArchivo = $request->id_perfil . '.' . $archivo->getClientOriginalExtension();
            $idCarpetaDestino = env('ID_CARPETA_FOTOS_PERFILES');

            $rutaFoto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);
        }

        $perfil = Perfil::create([
            'id_perfil' => $request->id_perfil,
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'correo' => $request->correo,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'genero' => $request->genero,
            'telefono' => $request->telefono,
            'tipo_identificacion' => $request->tipo_identificacion,
            'url_foto' => $rutaFoto
        ]);

        Login::create([
            'id_perfil' => $perfil->id_perfil,
            'password' => Hash::make($request->password),
            'estado' => 'ACTIVO',
            'intentos_fallidos' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil creado exitosamente',
            'data' => $perfil
        ], 201);
    }

    public function updateProfile(Request $request, $id_perfil)
    {
        $perfilExist = Perfil::find($id_perfil);

        if (!$perfilExist) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado',
            ], 404);
        }

        $reglas = [
            'id_perfil' => ['required', Rule::unique(Perfil::class, 'id_perfil')->ignore($id_perfil, 'id_perfil')],
            'correo' => ['required', 'email', Rule::unique(Perfil::class, 'correo')->ignore($id_perfil, 'id_perfil')]
        ];

        $mensajes = [
            'id_perfil.unique' => 'Este ID / Número de identificación ya está ocupado por otra persona.',
            'correo.unique' => 'Este correo electrónico ya está en uso por otra persona.'
        ];

        $validador = Validator::make($request->all(), $reglas, $mensajes);

        if ($validador->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validador->errors()
            ], 400);
        }

        // LLenan los datos básicos del request (excepto la foto y password que requieren trato especial)
        $perfilExist->fill($request->except(['url_foto', 'password']));

        // CASO A: Se adjuntó una imagen nueva (Se elimina la anterior y se sube la nueva)        
        if ($request->hasFile('url_foto')) {
            // 1. Eliminar la anterior
            if ($perfilExist->url_foto) {
                $this->driveService->deleteFromDrive($perfilExist->url_foto);
            }

            // 2. Subir la nueva
            $archivo = $request->file('url_foto');
            $nombreArchivo = $perfilExist->id_perfil . '.' . $archivo->getClientOriginalExtension();
            $idCarpetaDestino = env('ID_CARPETA_FOTOS_PERFILES');

            // 3. Asignar la nueva URL al modelo
            $perfilExist->url_foto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);

            // CASO B: No hay imagen nueva, pero el id_perfil cambió en el request
        } else if ($perfilExist->isDirty('id_perfil')) {
            // Si el usuario ya tiene una foto asignada, se renombra en Drive
            if ($perfilExist->url_foto) {
                $this->driveService->changeFileName($perfilExist->id_perfil, $perfilExist->url_foto);
            }
        }

        $perfilExist->save();

        if ($request->filled('password')) {
            Login::where('id_perfil', $perfilExist->id_perfil)->update([
                'password' => Hash::make($request->password)
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado exitosamente',
            'data' => $perfilExist
        ]);
    }

    public function deleteProfile($id)
    {
        $perfil = Perfil::find($id);

        if (!$perfil) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado'
            ], 404);
        }

        // Borrar la imagen de Drive
        if ($perfil->url_foto) {
            $this->driveService->deleteFromDrive($perfil->url_foto);
        }

        $perfil->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perfil eliminado correctamente'
        ]);
    }
}