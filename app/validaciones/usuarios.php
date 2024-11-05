<?php

return [
    'usuario' => [
        'requerido',
        'string',
        'min:4',
        'max:50',
        'unico:usuarios',
        'alfanumerico'
    ],
    'password' => [
        'requerido',
        'string',
        'min:6',
        'max:255'
    ],
    'nombre' => [
        'requerido',
        'string',
        'max:100'
    ],
    'email' => [
        'requerido',
        'email',
        'max:100',
        'unico:usuarios'
    ],
    'rol' => [
        'requerido',
        'enum:admin,usuario'
    ],
    'estado' => [
        'opcional',
        'enum:activo,inactivo',
        'default:activo'
    ],
    'ultimo_login' => [
        'opcional',
        'fecha'
    ],
    'token' => [
        'opcional',
        'string',
        'max:255'
    ],
    'expiracion' => [
        'opcional',
        'fecha'
    ],
    'fecha_creacion_token' => [
        'opcional',
        'fecha'
    ]
]; 