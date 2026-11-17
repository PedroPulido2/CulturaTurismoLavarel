#!/usr/bin/env python3
"""Genera la colección Postman CRUD de la API CulturaTurismo (nuevo esquema).

Uso: python3 postman/generar_coleccion.py
Salida: postman/CulturaTurismo.postman_collection.json
"""
import json
import uuid
from pathlib import Path

BASE = '{{base_url}}'
SALIDA = Path(__file__).resolve().parent / 'CulturaTurismo.postman_collection.json'


def url(path):
    parts = [p for p in path.strip('/').split('/') if p]
    return {'raw': f"{BASE}/{path.strip('/')}", 'host': [BASE], 'path': parts}


def raw_req(name, method, path, body=None, desc='', events=None):
    req = {
        'method': method,
        'header': [{'key': 'Accept', 'value': 'application/json'}],
        'url': url(path),
    }
    if body is not None:
        req['header'].append({'key': 'Content-Type', 'value': 'application/json'})
        req['body'] = {
            'mode': 'raw',
            'raw': json.dumps(body, ensure_ascii=False, indent=2),
            'options': {'raw': {'language': 'json'}},
        }
    item = {'name': name, 'request': req, 'response': []}
    if desc:
        item['request']['description'] = desc
    if events:
        item['event'] = events
    return item


def form_req(name, method, path, fields, desc=''):
    """fields: lista de tuplas (key, value, type, disabled). type: text|file"""
    formdata = []
    for key, value, ftype, disabled in fields:
        entry = {'key': key, 'type': ftype, 'disabled': disabled}
        if ftype == 'file':
            entry['src'] = []
        else:
            entry['value'] = value
        formdata.append(entry)
    req = {
        'method': method,
        'header': [{'key': 'Accept', 'value': 'application/json'}],
        'url': url(path),
        'body': {'mode': 'formdata', 'formdata': formdata},
    }
    if desc:
        req['description'] = desc
    return {'name': name, 'request': req, 'response': []}


def T(key, value, disabled=False):
    return (key, value, 'text', disabled)


def F(key, disabled=False):
    return (key, '', 'file', disabled)


def folder(name, items, desc=''):
    f = {'name': name, 'item': items}
    if desc:
        f['description'] = desc
    return f


def catalog_folder(singular, base_path, nombre_ejemplo):
    return folder(f'Catálogo: {singular}', [
        raw_req('Listar', 'GET', base_path),
        raw_req('Crear', 'POST', base_path, {'nombre': nombre_ejemplo}),
        raw_req('Eliminar', 'DELETE', f'{base_path}/1'),
    ])


VITRINA = ('Vitrina pública: solo visibles, campos mínimos, paginada (?page&per_page, máx 50). '
           'El detalle por ID trae el modelo completo.')


def pub_list(name, path):
    return raw_req(name, 'GET', path, None, VITRINA)


def visibility_req(path):
    return raw_req(
        'Cambiar visibilidad', 'PATCH', path, {'is_visible': True},
        'Acepta true/false. Columna real: is_visible.',
    )


def admin_list(name, path, modulo):
    return raw_req(
        name, 'GET', path, None,
        f'Gestión admin: visibles + ocultos, con contacto e is_visible (columna Estado). '
        f'Requiere Bearer con permiso del módulo "{modulo}". Filtros: ?is_visible=true|false, ?page&per_page (máx 50).',
    )


ADMIN_DESC = ('Listas de gestión para las tablas del panel admin (Imagen/Nombre/Categoría/Ubicación/Contacto/Estado). '
              'Todas exigen Bearer con el permiso del módulo indicado; sin token devuelven 401.')


items = [
    folder('1. Auth', [
        raw_req(
            'Login', 'POST', 'login',
            {'correo': 'admin@sogamoso.gov.co', 'password': 'password123'},
            'Guarda el JWT en la variable de colección {{jwt}}.',
            events=[{'listen': 'test', 'script': {
                'type': 'text/javascript',
                'exec': [
                    'if (pm.response.code === 200) {',
                    '  const json = pm.response.json();',
                    "  if (json.token) pm.collectionVariables.set('jwt', json.token);",
                    '}',
                ],
            }}],
        ),
        raw_req('Desbloquear usuario', 'POST', 'login/unlock/1', None,
                'Pasa el id_usuario. Solo si la cuenta está BLOQUEADA.'),
    ]),
    folder('2. Perfiles (usuario + login)', [
        raw_req('Listar perfiles', 'GET', 'profiles'),
        raw_req('Obtener por email', 'GET', 'profiles/e/admin@sogamoso.gov.co'),
        raw_req('Obtener por ID', 'GET', 'profiles/1'),
        form_req('Crear perfil', 'POST', 'profiles/registro', [
            T('tipo_identificacion', 'CC'),
            T('num_documento', '123456789'),
            T('nombre', 'María'),
            T('apellido', 'Pérez'),
            T('correo', 'admin@sogamoso.gov.co'),
            T('fecha_nacimiento', '1990-05-14'),
            T('genero', 'Femenino'),
            T('telefono', '3101234567'),
            T('id_rol', '2'),
            F('url_foto', disabled=True),
        ], 'Cada usuario tiene UN solo rol (usuario.id_rol). Sin id_rol se asigna Administrador (2). '
            'Para el PRIMER superadmin usa id_rol=1. Foto opcional (habilítala y adjunta archivo).'),
        form_req('Actualizar perfil', 'POST', 'profiles/1', [
            T('_method', 'PUT'),
            T('nombre', 'María Fernanda'),
            T('telefono', '3107654321'),
            T('id_rol', '1', disabled=True),
            T('password', 'nuevoPassword123', disabled=True),
            F('url_foto', disabled=True),
        ], 'Multipart + _method=PUT (Laravel no procesa archivos en PUT directo). id_rol cambia el rol único.'),
        raw_req('Eliminar perfil', 'DELETE', 'profiles/1', None,
                'Borra foto de Drive; login y módulos se eliminan en cascada.'),
    ]),
    folder('3. Roles y permisos (superadmin)', [
        raw_req('Listar roles', 'GET', 'roles'),
        raw_req('Listar módulos', 'GET', 'modulos'),
        raw_req('Ver módulos de un usuario', 'GET', 'profiles/2/modulos'),
        raw_req('Otorgar módulos', 'POST', 'profiles/2/modulos', {'modulo_ids': [1, 4]},
                'Requiere Bearer de un SUPERADMIN ({{jwt}} del login). '
                'Módulos: 1 Atractivos Turísticos, 2 Prestadores Servicios, 3 Servicios Culturales, '
                '4 Eventos, 5 Usuarios y Roles. asignado_por se toma del token, no del body. Agrega sin duplicar.'),
        raw_req('Quitar módulo', 'DELETE', 'profiles/2/modulos/4', None,
                'Requiere Bearer de un SUPERADMIN. 404 si el usuario no tiene ese módulo.'),
    ], 'Crear usuarios da rol Administrador sin módulos. Solo el Super Administrador otorga/retira módulos.'),
    folder('4. Catálogos', [
        catalog_folder('Tipos de cocina', 'tipos-cocina', 'Típica boyacense'),
        catalog_folder('Tipos de agencia', 'tipos-agencia', 'Operadora'),
        catalog_folder('Especialidades de guía', 'especialidades', 'Avistamiento de aves'),
        catalog_folder('Disponibilidades de guía', 'disponibilidades', 'Fines de semana'),
        catalog_folder('Tipos de público (guía)', 'tipos-publico', 'Familiar'),
        catalog_folder('Áreas artísticas', 'areas-artisticas', 'Música'),
        catalog_folder('Tipos de servicio cultural', 'tipos-servicio', 'Taller'),
        catalog_folder('Público dirigido', 'publico-dirigido', 'Juvenil'),
        raw_req('Listar redes sociales', 'GET', 'redes-sociales', None,
                'IDs necesarios para el campo redes_sociales de hotel/restaurante/agencia/atractivo.'),
    ], 'Crear primero los catálogos: las entidades exigen sus IDs por FK.'),
    folder('5. Atractivos turísticos', [
        pub_list('Listar', 'tourism'),
        raw_req('Obtener por ID', 'GET', 'tourism/ATR-001'),
        form_req('Crear', 'POST', 'tourism/register', [
            T('id_atractivo_turistico', 'ATR-001'),
            T('nombre', 'Plaza de la Villa'),
            T('tipo', 'Patrimonial'),
            T('descripcion', 'Plaza principal de Sogamoso'),
            T('telefono', '3101234567'),
            T('horario', 'Lun-Dom 8am-6pm'),
            T('precio', 'Entrada libre'),
            T('is_visible', 'true'),
            T('direccion', 'Plaza de la Villa, Sogamoso, Boyacá'),
            T('latitud', '5.7189'),
            T('longitud', '-72.9289'),
            T('redes_sociales[0][id_redes_sociales]', '1', disabled=True),
            T('redes_sociales[0][url]', 'https://facebook.com/plaza', disabled=True),
            F('fotos', disabled=True),
        ], 'id_atractivo_turistico: código manual VARCHAR(15), único y obligatorio. Habilita redes/fotos según necesites.'),
        form_req('Actualizar', 'POST', 'tourism/ATR-001', [
            T('_method', 'PUT'),
            T('nombre', 'Plaza de la Villa - Sogamoso'),
            T('precio', 'Gratuito'),
            T('is_visible', 'true'),
            T('fotos_a_eliminar[0]', '1', disabled=True),
            F('nuevas_fotos', disabled=True),
        ]),
        visibility_req('tourism/ATR-001/visibility'),
        raw_req('Eliminar', 'DELETE', 'tourism/ATR-001'),
    ]),
    folder('6. Hoteles', [
        pub_list('Listar', 'hotel'),
        raw_req('Obtener por ID', 'GET', 'hotel/HOT-001'),
        form_req('Crear', 'POST', 'hotel/register', [
            T('id_hotel', 'HOT-001'),
            T('nombre', 'Hotel Sogamoso Real'),
            T('rnt', '12345'),
            T('celular', '3101234567'),
            T('correo', 'hotel@ejemplo.com'),
            T('nombre_contacto', 'Juan Pérez'),
            T('n_habitaciones_totales', '20'),
            T('n_habitaciones_simples', '10'),
            T('n_habitaciones_dobles', '8'),
            T('n_habitaciones_suites', '2'),
            T('petfriendly', 'true'),
            T('acceso_discapacidad', 'false'),
            T('parqueadero', 'true'),
            T('restaurante', 'true'),
            T('calificacion_salud', '4.5'),
            T('visita_inspeccion_turismo', 'false'),
            T('observacion', 'Hotel céntrico (observacion en singular)'),
            T('is_visible', 'true'),
            T('direccion', 'Calle 11 #11-28, Sogamoso'),
            T('latitud', '5.7189'),
            T('longitud', '-72.9289'),
            T('redes_sociales[0][id_redes_sociales]', '1', disabled=True),
            T('redes_sociales[0][url]', 'https://instagram.com/hotel', disabled=True),
            F('fotos', disabled=True),
        ], 'correo único. calificacion_salud es numérico (no booleano).'),
        form_req('Actualizar', 'POST', 'hotel/HOT-001', [
            T('_method', 'PUT'),
            T('n_habitaciones_totales', '25'),
            T('petfriendly', 'false'),
            T('observacion', 'Ampliación 2026'),
            T('fotos_a_eliminar[0]', '1', disabled=True),
            F('nuevas_fotos', disabled=True),
        ]),
        visibility_req('hotel/HOT-001/visibility'),
        raw_req('Eliminar', 'DELETE', 'hotel/HOT-001'),
    ]),
    folder('7. Restaurantes', [
        pub_list('Listar', 'restaurant'),
        raw_req('Obtener por ID', 'GET', 'restaurant/RES-001'),
        form_req('Crear', 'POST', 'restaurant/register', [
            T('id_restaurante', 'RES-001'),
            T('nombre', 'Restaurante El Fogón'),
            T('celular', '3101234567'),
            T('correo', 'fogon@ejemplo.com'),
            T('horarios', 'Lun-Sáb 11am-9pm'),
            T('propietario', 'Ana Gómez'),
            T('capacidad', '80 personas'),
            T('platos_principales', 'Cocido boyacense, cuchuco'),
            T('is_visible', 'true'),
            T('id_tipo_cocina', '1'),
            T('direccion', 'Carrera 11 #12-30, Sogamoso'),
            T('latitud', '5.7189'),
            T('longitud', '-72.9289'),
            T('redes_sociales[0][id_redes_sociales]', '1', disabled=True),
            T('redes_sociales[0][url]', 'https://facebook.com/fogon', disabled=True),
            F('fotos', disabled=True),
        ], 'id_tipo_cocina debe existir (ver catálogo tipos-cocina).'),
        form_req('Actualizar', 'POST', 'restaurant/RES-001', [
            T('_method', 'PUT'),
            T('capacidad', '100 personas'),
            T('id_tipo_cocina', '1'),
            T('fotos_a_eliminar[0]', '1', disabled=True),
            F('nuevas_fotos', disabled=True),
        ]),
        visibility_req('restaurant/RES-001/visibility'),
        raw_req('Eliminar', 'DELETE', 'restaurant/RES-001'),
    ]),
    folder('8. Agencias', [
        pub_list('Listar', 'agency'),
        raw_req('Obtener por ID', 'GET', 'agency/AGE-001'),
        form_req('Crear', 'POST', 'agency/register', [
            T('id_agencia', 'AGE-001'),
            T('nit', '900123456-1'),
            T('rnt', '54321'),
            T('nombre', 'Agencia Sugamuxi Tours'),
            T('celular', '3101234567'),
            T('correo', 'tours@ejemplo.com'),
            T('representante_legal', 'Carlos Ruiz'),
            T('n_empleados_asociados', '5'),
            T('destinos_principales', 'Laguna de Tota, Monguí'),
            T('observaciones', 'Operadora local'),
            T('is_visible', 'true'),
            T('id_tipo_agencia', '1'),
            T('especialidades[0]', 'Ecoturismo'),
            T('especialidades[1]', 'Turismo cultural', disabled=True),
            T('redes_sociales[0][id_redes_sociales]', '1', disabled=True),
            T('redes_sociales[0][url]', 'https://instagram.com/sugamuxi', disabled=True),
            F('fotos', disabled=True),
        ], 'id_tipo_agencia obligatorio (catálogo tipos-agencia). Sin dirección: la agencia no lleva id_direccion.'),
        form_req('Actualizar', 'POST', 'agency/AGE-001', [
            T('_method', 'PUT'),
            T('n_empleados_asociados', '8'),
            T('especialidades[0]', 'Ecoturismo'),
            T('especialidades[1]', 'Aventura'),
            T('fotos_a_eliminar[0]', '1', disabled=True),
            F('nuevas_fotos', disabled=True),
        ], 'Enviar especialidades reemplaza todas las anteriores.'),
        visibility_req('agency/AGE-001/visibility'),
        raw_req('Eliminar', 'DELETE', 'agency/AGE-001'),
    ]),
    folder('9. Guías', [
        pub_list('Listar', 'guide'),
        raw_req('Obtener por ID', 'GET', 'guide/GUI-001'),
        raw_req('Crear', 'POST', 'guide/register', {
            'id_guia': 'GUI-001',
            'nombre': 'Pedro Guianza',
            'n_cedula': 1054123456,
            'rnt': '98765',
            'celular': 3101234567,
            'correo': 'guia@ejemplo.com',
            'num_tarjeta_profesional': 44556,
            'rango_anios_experiencia': '5-10 años',
            'idiomas': 'Español, Inglés',
            'principales_atractivos': 'Plaza de la Villa, Museo',
            'asociacion': 'Asoguías',
            'is_visible': True,
            'id_especialidad': 1,
            'id_disponibilidad': 1,
            'descripcion': 'Guía local con 8 años de experiencia',
            'id_tipo_publico': 1,
        }, 'Las FK id_especialidad/id_disponibilidad/id_tipo_publico son obligatorias. n_cedula/correo/tarjeta únicos.'),
        raw_req('Actualizar', 'PUT', 'guide/GUI-001', {
            'idiomas': 'Español, Inglés, Francés',
            'rango_anios_experiencia': '10-15 años',
            'is_visible': True,
        }),
        visibility_req('guide/GUI-001/visibility'),
        raw_req('Eliminar', 'DELETE', 'guide/GUI-001'),
    ]),
    folder('10. Eventos', [
        raw_req('Listar', 'GET', 'event', None,
                'Vitrina pública: solo visibles, campos mínimos, paginada (?page&per_page, máx 50). '
                'id_evento es VARCHAR(15) manual, ya no existe codigo.'),
        raw_req('Obtener por ID', 'GET', 'event/EVT001'),
        form_req('Crear', 'POST', 'event/register', [
            T('id_evento', 'EVT001'),
            T('nombre', 'Festival del Sol'),
            T('descripcion', 'Fiesta tradicional sogamoseña'),
            T('tipo', 'Cultural'),
            T('organizador', 'Alcaldía'),
            T('contacto', '3101234567'),
            T('fecha_inicio', '2026-12-15'),
            T('fecha_fin', '2026-12-18'),
            T('asistentes_estimados', '5000'),
            T('asistentes_reales', '4800'),
            T('impacto_economico', '150.75'),
            T('observaciones', 'Edición 2026'),
            T('is_visible', 'true'),
            T('direccion', 'Plaza de la Villa, Sogamoso'),
            T('latitud', '5.7189'),
            T('longitud', '-72.9289'),
            F('fotos', disabled=True),
        ], 'id_evento: código manual VARCHAR(15), único y obligatorio (reemplaza al antiguo codigo). '
            'Sin flyer: solo galería en tabla fotos.'),
        form_req('Actualizar', 'POST', 'event/EVT001', [
            T('_method', 'PUT'),
            T('asistentes_reales', '5200'),
            T('is_visible', 'true'),
            T('fotos_a_eliminar[0]', '1', disabled=True),
            F('nuevas_fotos', disabled=True),
        ]),
        visibility_req('event/EVT001/visibility'),
        raw_req('Eliminar', 'DELETE', 'event/EVT001'),
    ]),
    folder('11. Servicios culturales', [
        raw_req('Listar', 'GET', 'cultural-services', None,
                'Vitrina pública: solo visibles, campos mínimos, paginada (?page&per_page, máx 50). '
                'No expone nit/telefono/correo/reconocimientos.'),
        raw_req('Obtener por ID', 'GET', 'cultural-services/1'),
        form_req('Crear', 'POST', 'cultural-services/register', [
            T('id_servicio_cultural', '1'),
            T('id_tipo_servicio', '1'),
            T('id_publico_dirigido', '1'),
            T('id_area_artistica', '1'),
            T('nombre', 'Carranga Los Andes'),
            T('telefono', '3101234567'),
            T('correo', 'grupo@ejemplo.com'),
            T('contacto', 'Jorge Eliecer'),
            T('biografia', 'Agrupación con 10 años de trayectoria'),
            T('reconocimientos', 'Premio municipal 2024'),
            T('is_visible', 'true'),
            F('fotos', disabled=True),
        ], 'id_servicio_cultural manual (entero, NO autoincremental). id_publico_dirigido opcional.'),
        form_req('Actualizar', 'POST', 'cultural-services/1', [
            T('_method', 'PUT'),
            T('biografia', 'Agrupación con 12 años de trayectoria'),
            T('is_visible', 'true'),
            T('fotos_a_eliminar[0]', '1', disabled=True),
            F('nuevas_fotos', disabled=True),
        ]),
        visibility_req('cultural-services/1/visibility'),
        raw_req('Eliminar', 'DELETE', 'cultural-services/1'),
    ]),
    folder('12. Prestadores públicos', [
        raw_req('Listar todos', 'GET', 'prestadores-turisticos'),
        raw_req('Filtrar hoteles', 'GET', 'prestadores-turisticos?categoria=hoteles', None,
                'categoria: hoteles | restaurantes | agencias | all. Tope por categoría (?per_page, máx 50), solo visibles.'),
    ], 'Agregador de vitrina: hoteles + restaurantes + agencias con foto portada y redes principales.'),
    folder('13. Activación y recuperación', [
        raw_req('Activar cuenta', 'POST', 'password/activar',
                {'token': 'PEGAR_TOKEN_DEL_CORREO', 'password': 'claveSegura1', 'password_confirmation': 'claveSegura1'},
                'El token llega al correo (válido 24h, un solo uso). Mínimo 8 caracteres + confirmación.'),
        raw_req('Solicitar recuperación', 'POST', 'password/olvide',
                {'correo': 'admin@sogamoso.gov.co'},
                'Respuesta siempre genérica (no revela si el correo existe).'),
        raw_req('Restablecer contraseña', 'POST', 'password/restablecer',
                {'token': 'PEGAR_TOKEN_DEL_CORREO', 'password': 'nuevaClave99', 'password_confirmation': 'nuevaClave99'},
                'Si la cuenta estaba BLOQUEADA, se desbloquea.'),
        raw_req('Reenviar activación', 'POST', 'password/reenviar-activacion',
                {'correo': 'admin@sogamoso.gov.co'},
                'Solo para cuentas aún EN_VERIFICACION.'),
    ], 'Crear perfil ya no recibe password: el correo de activación sale solo al registrar.'),
    folder('14. Listas admin (tablas de gestión)', [
        admin_list('Atractivos (admin)', 'admin/tourism', 'Atractivos Turísticos'),
        admin_list('Hoteles (admin)', 'admin/hotel', 'Prestadores Servicios'),
        admin_list('Restaurantes (admin)', 'admin/restaurant', 'Prestadores Servicios'),
        admin_list('Agencias (admin)', 'admin/agency', 'Prestadores Servicios'),
        admin_list('Guías (admin)', 'admin/guide', 'Prestadores Servicios'),
        admin_list('Eventos (admin)', 'admin/event', 'Eventos'),
        admin_list('Servicios culturales (admin)', 'admin/cultural-services', 'Servicios Culturales'),
    ], ADMIN_DESC),
]

collection = {
    'info': {
        'name': 'CulturaTurismo API',
        '_postman_id': str(uuid.uuid4()),
        'description': (
            'Colección CRUD de la API CulturaTurismo (esquema culturayturismo).\n'
            'Orden sugerido: 1 Auth → 2 Perfiles → 3 Permisos → 4 Catálogos → 5-11 entidades → 14 Admin.\n'
            'Vitrinas públicas (5-12): solo visibles, campos mínimos, paginadas. '
            'Listas admin (14): visibles + ocultos con is_visible, exigen Bearer + permiso del módulo.\n'
            'Los campos de archivo (fotos/url_foto/nuevas_fotos) vienen deshabilitados: '
            'habilítalos y adjunta una imagen para probar subidas a Drive.\n'
            'Otorgar/quitar módulos exige Bearer de un Super Administrador.'
        ),
        'schema': 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    },
    'variable': [
        {'key': 'base_url', 'value': 'http://localhost:8000/api'},
        {'key': 'jwt', 'value': ''},
    ],
    'auth': {
        'type': 'bearer',
        'bearer': [{'key': 'token', 'value': '{{jwt}}', 'type': 'string'}],
    },
    'item': items,
}

with open(SALIDA, 'w', encoding='utf-8') as f:
    json.dump(collection, f, ensure_ascii=False, indent=2)

print(f'generado OK: {SALIDA}')
