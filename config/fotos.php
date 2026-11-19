<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver de fotos: local | drive
    |--------------------------------------------------------------------------
    | En desarrollo se usa el disco público local. En Render (staging con
    | carga externa) se usa Google Drive, cuyo disco es persistente.
    | En el deploy final se vuelve a local tras migrar con
    | php artisan fotos:migrar-drive-a-local.
    */
    'driver' => env('FOTOS_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Carpeta local <-> carpeta de Drive por entidad
    |--------------------------------------------------------------------------
    */
    'carpetas' => [
        'hoteles' => env('ID_CARPETA_FOTOS_HOTELES'),
        'atractivos' => env('ID_CARPETA_FOTOS_ATRACTIVOS'),
        'restaurantes' => env('ID_CARPETA_FOTOS_RESTAURANTES'),
        'agencias' => env('ID_CARPETA_FOTOS_AGENCIAS'),
        'eventos' => env('ID_CARPETA_FOTOS_EVENTOS'),
        'servicios' => env('ID_CARPETA_SERVICIOS_CULTURALES'),
        'perfiles' => env('ID_CARPETA_FOTOS_PERFILES'),
    ],

];
