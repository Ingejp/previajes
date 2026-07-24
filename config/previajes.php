<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super administrador inicial
    |--------------------------------------------------------------------------
    |
    | Credenciales del usuario raíz que crea `UsuarioSeeder` al instalar. Se
    | leen del entorno para no versionar un secreto (§7). La contraseña debe
    | cambiarse en el primer ingreso.
    |
    */

    'super_admin' => [
        'nombre' => env('PREVIAJES_SUPER_ADMIN_NOMBRE', 'Super Administrador'),
        'email' => env('PREVIAJES_SUPER_ADMIN_EMAIL', 'super@previajes.test'),
        'password' => env('PREVIAJES_SUPER_ADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Evidencia fotográfica
    |--------------------------------------------------------------------------
    |
    | Límites de la subida ANTES de comprimir (RF-11). El tamaño y las
    | dimensiones del archivo ya comprimido se configuran en caliente desde la
    | tabla `configuraciones`, editable por el administrador (RF-16.1).
    |
    */

    'fotos' => [
        'disco' => env('PREVIAJES_DISCO_FOTOS', 'local'),
        'directorio' => 'previajes',
        'max_subida_kb' => (int) env('PREVIAJES_MAX_SUBIDA_KB', 12288),
        'mimes_permitidos' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
        'max_por_item' => (int) env('PREVIAJES_MAX_FOTOS_POR_ITEM', 5),
    ],

];
