<?php

return [
    'nombre' => [
        'requerido',
        'string',
        'max:100'
    ],
    'ubicacion' => [
        'requerido',
        'string',
        'max:200'
    ],
    'tipo' => [
        'requerido',
        'enum:led,lcd,plasma'
    ],
    'estado' => [
        'opcional',
        'enum:activo,inactivo,mantenimiento'
    ],
    'resolucion' => [
        'opcional',
        'string',
        'max:20'
    ],
    'dimensiones' => [
        'opcional',
        'string',
        'max:50'
    ],
    'fecha_instalacion' => [
        'opcional',
        'fecha'
    ]
]; 