<?php

namespace App\Http\Controllers;

use App\Http\Resources\UsuarioDetailResource;
use App\Http\Resources\UsuarioListResource;
use App\Mail\ActivarCuenta;
use App\Models\Login;
use App\Models\Modulo;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\GoogleDriveService;
use App\Services\PasswordTokenService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    protected $driveService;

    protected $tokens;

    public function __construct(GoogleDriveService $driveService, PasswordTokenService $tokens)
    {
        $this->driveService = $driveService;
        $this->tokens = $tokens;
    }

    /**
     * Lista de gestión (solo Super Administrador, vía middleware).
     * Devuelve vista recortada sin documento/teléfono/nacimiento.
     */
    public function getAllProfiles()
    {
        $usuarios = Usuario::with(['rol', 'modulos'])->get();

        return response()->json(['success' => true, 'data' => UsuarioListResource::collection($usuarios)]);
    }

    /**
     * Detalle (Super Administrador o dueño, vía middleware).
     */
    public function getProfileByEmail($email)
    {
        $usuario = Usuario::with(['rol', 'modulos'])->where('correo', $email)->first();
        if (! $usuario) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => new UsuarioDetailResource($usuario)]);
    }

    /**
     * Detalle (Super Administrador o dueño, vía middleware).
     */
    public function getProfileById($id_usuario)
    {
        $usuario = Usuario::with(['rol', 'modulos'])->where('id_usuario', $id_usuario)->first();

        if (! $usuario) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => new UsuarioDetailResource($usuario)]);
    }

    public function createProfile(Request $request)
    {
        $reglas = [
            'tipo_identificacion' => 'required|string|max:70',
            'num_documento' => ['required', 'integer', Rule::unique(Usuario::class, 'num_documento')],
            'nombre' => 'required|string|max:60',
            'apellido' => 'required|string|max:60',
            'correo' => ['required', 'email', 'max:150', Rule::unique(Usuario::class, 'correo')],
            'fecha_nacimiento' => 'required|date',
            'genero' => 'required|string|max:50',
            'telefono' => 'required|integer',
            'url_foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'id_rol' => ['sometimes', 'integer', Rule::exists(Rol::class, 'id_rol')],
        ];

        // La contraseña la define el propio usuario desde el correo de activación,
        // nunca viaja en el registro.

        $mensajes = [
            'num_documento.unique' => 'Este número de documento ya se encuentra registrado.',
            'correo.unique' => 'Este correo electrónico ya está en uso por otra cuenta.',
        ];

        $validador = Validator::make($request->all(), $reglas, $mensajes);

        if ($validador->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validador->errors(),
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Rol: el enviado, o Administrador por defecto (la columna id_rol es NOT NULL).
            // El usuario nuevo nace SIN módulos asignados; los otorga un Super Administrador.
            $idRol = $request->input('id_rol') ?? Rol::where('nombre', 'Administrador')->value('id_rol');

            if (! $idRol) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró un rol válido para el usuario',
                ], 400);
            }

            $usuario = Usuario::create([
                'tipo_identificacion' => $request->tipo_identificacion,
                'num_documento' => $request->num_documento,
                'nombre' => $request->nombre,
                'apellido' => $request->apellido,
                'correo' => $request->correo,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'genero' => $request->genero,
                'telefono' => $request->telefono,
                'id_rol' => $idRol,
            ]);

            // Si se proporciona foto al momento de crear el usuario
            if ($request->hasFile('url_foto')) {
                $archivo = $request->file('url_foto');
                $nombreArchivo = $usuario->id_usuario.'.'.$archivo->getClientOriginalExtension();
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_PERFILES');

                $usuario->url_foto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);
                $usuario->save();
            }

            Login::create([
                'id_usuario' => $usuario->id_usuario,
                // Hash aleatorio inutilizable: la clave real la define el usuario
                // desde el correo de activación. La cuenta nace pendiente.
                'password' => Hash::make(Str::random(40)),
                'estado' => 'EN_VERIFICACION',
                'intentos_fallidos' => 0,
            ]);

            DB::commit();

            // Correo de activación (fuera de la transacción para no retenerla
            // durante el envío SMTP). Si falla, se puede reenviar.
            $correoEnviado = true;
            try {
                $token = $this->tokens->generar();
                $usuario->reset_token_hash = $token['hash'];
                $usuario->reset_token_expires_at = $token['expira_en'];
                $usuario->save();

                $url = $this->urlActivacion($token['token']);
                Mail::to($usuario->correo)->send(new ActivarCuenta($usuario->nombre, $url));
            } catch (Exception $e) {
                Log::error('Error enviando correo de activación a '.$usuario->correo.': '.$e->getMessage());
                $correoEnviado = false;
            }

            $usuario->load(['rol', 'modulos']);

            return response()->json([
                'success' => true,
                'message' => $correoEnviado
                    ? 'Perfil creado exitosamente. Se envió el correo de activación'
                    : 'Perfil creado, pero no se pudo enviar el correo de activación. Usa reenviar-activacion',
                'data' => new UsuarioDetailResource($usuario),
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el perfil: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza un perfil (Super Administrador o dueño, vía middleware).
     * Solo el Super Administrador puede cambiar id_rol.
     */
    public function updateProfile(Request $request, $id_usuario)
    {
        $usuarioExist = Usuario::find($id_usuario);

        if (! $usuarioExist) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado',
            ], 404);
        }

        // Defensa en profundidad por si la ruta se expone sin middleware:
        // solo superadmin o el dueño pueden llegar aquí.
        $authId = $request->attributes->get('auth_user_id');
        $esSuper = (bool) $request->attributes->get('auth_is_superadmin', false);
        if ($authId !== null && ! $esSuper && (string) $authId !== (string) $id_usuario) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado. Solo puedes editar tu propio perfil'], 403);
        }

        if (! $esSuper && $request->has('id_rol')) {
            return response()->json(['success' => false, 'message' => 'Solo un Super Administrador puede cambiar el rol'], 403);
        }

        $reglas = [
            'tipo_identificacion' => 'sometimes|string|max:70',
            'num_documento' => ['sometimes', 'integer', Rule::unique(Usuario::class, 'num_documento')->ignore($id_usuario, 'id_usuario')],
            'nombre' => 'sometimes|string|max:60',
            'apellido' => 'sometimes|string|max:60',
            'correo' => ['sometimes', 'email', 'max:150', Rule::unique(Usuario::class, 'correo')->ignore($id_usuario, 'id_usuario')],
            'fecha_nacimiento' => 'sometimes|date',
            'genero' => 'sometimes|string|max:50',
            'telefono' => 'sometimes|integer',
            'url_foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'id_rol' => ['sometimes', 'integer', Rule::exists(Rol::class, 'id_rol')],
        ];

        $mensajes = [
            'num_documento.unique' => 'Este número de documento ya está ocupado por otra persona.',
            'correo.unique' => 'Este correo electrónico ya está en uso por otra persona.',
        ];

        $validador = Validator::make($request->all(), $reglas, $mensajes);

        if ($validador->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validador->errors(),
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Llenan los datos básicos del request (excepto la foto y password que requieren trato especial).
            // id_rol viaja directo por fill al ser FK única del usuario.
            $usuarioExist->fill($request->except(['url_foto', 'id_usuario']));

            // Se adjuntó una imagen nueva (se elimina la anterior y se sube la nueva)
            if ($request->hasFile('url_foto')) {
                // 1. Eliminar la anterior
                if ($usuarioExist->url_foto) {
                    $this->driveService->deleteFromDrive($usuarioExist->url_foto);
                }

                // 2. Subir la nueva
                $archivo = $request->file('url_foto');
                $nombreArchivo = $usuarioExist->id_usuario.'.'.$archivo->getClientOriginalExtension();
                $idCarpetaDestino = env('ID_CARPETA_FOTOS_PERFILES');

                // 3. Asignar la nueva URL al modelo
                $usuarioExist->url_foto = $this->driveService->uploadToDrive($archivo, $nombreArchivo, $idCarpetaDestino);
            }

            $usuarioExist->save();

            // La contraseña solo se define por correo (activación/recuperación),
            // nunca desde la actualización del perfil.
            DB::commit();

            $usuarioExist->load(['rol', 'modulos']);

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado exitosamente',
                'data' => new UsuarioDetailResource($usuarioExist),
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el perfil: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteProfile($id)
    {
        $usuario = Usuario::find($id);

        if (! $usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil no encontrado',
            ], 404);
        }

        // Borrar la imagen de Drive
        if ($usuario->url_foto) {
            $this->driveService->deleteFromDrive($usuario->url_foto);
        }

        // El borrado en cascada elimina login y módulos asignados
        $usuario->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perfil eliminado correctamente',
        ]);
    }

    /**
     * Catálogo de roles (Super Administrador, Administrador, Visitante).
     */
    public function getRoles()
    {
        return response()->json(['success' => true, 'data' => Rol::all()]);
    }

    /**
     * Catálogo de módulos gestionables (Atractivos, Hoteles, ...).
     */
    public function getModulos()
    {
        return response()->json(['success' => true, 'data' => Modulo::all()]);
    }

    /**
     * Módulos que un administrador tiene permiso de gestionar.
     */
    public function getUserModulos($id_usuario)
    {
        $usuario = Usuario::with('modulos')->find($id_usuario);

        if (! $usuario) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }

        return response()->json(['success' => true, 'data' => $usuario->modulos]);
    }

    /**
     * Otorga módulos a un administrador (solo Super Administrador, vía middleware).
     * Espera { "modulo_ids": [1, 6] }. No duplica los ya asignados.
     */
    public function assignModulos(Request $request, $id_usuario)
    {
        $usuario = Usuario::find($id_usuario);

        if (! $usuario) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }

        $validador = Validator::make($request->all(), [
            'modulo_ids' => 'required|array|min:1',
            'modulo_ids.*' => ['integer', Rule::exists(Modulo::class, 'id_modulo')],
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'errors' => $validador->errors()], 400);
        }

        $otorgadoPor = $request->attributes->get('auth_user_id');

        $adjuntos = [];
        foreach ($request->modulo_ids as $idModulo) {
            $adjuntos[$idModulo] = ['asignado_por' => $otorgadoPor, 'fecha_asignacion' => now()];
        }

        $usuario->modulos()->syncWithoutDetaching($adjuntos);
        $usuario->load('modulos');

        return response()->json([
            'success' => true,
            'message' => 'Permisos otorgados correctamente',
            'data' => $usuario->modulos,
        ]);
    }

    /**
     * Quita el permiso de un módulo a un administrador (solo Super Administrador).
     */
    public function revokeModulo($id_usuario, $id_modulo)
    {
        $usuario = Usuario::find($id_usuario);

        if (! $usuario) {
            return response()->json(['success' => false, 'message' => 'Perfil no encontrado'], 404);
        }

        if (! $usuario->modulos()->where('culturayturismo.modulo.id_modulo', $id_modulo)->exists()) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene asignado ese módulo'], 404);
        }

        $usuario->modulos()->detach($id_modulo);

        return response()->json(['success' => true, 'message' => 'Permiso retirado correctamente']);
    }

    // Armador del enlace de activación que viaja en el correo.
    private function urlActivacion(string $token): string
    {
        $base = rtrim(env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/');

        return $base.'/'.ltrim(env('FRONTEND_ACTIVATION_PATH', '/activar-cuenta'), '/').'?token='.$token;
    }
}
