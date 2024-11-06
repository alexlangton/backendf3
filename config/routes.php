<?php

// Rutas de autenticación
$f3->route('POST /api/public/login', 'AutenticacionController->login');
$f3->route('POST /api/public/logout', 'AutenticacionController->logout');
$f3->route('POST /api/public/recuperarPassword', 'AutenticacionController->recuperarPassword');

// Configuración de recursos
$recursos = [
    'parkings' => [
        'metodos_prohibidos' => ['DELETE']
    ],
    'carteles',
    'usuarios'
];

// Configuración de rutas
$rutas_crud = [
    'GET /@recurso' => 'obtenerConFiltros',
    'GET /@recurso/@id' => 'obtener',
    'POST /@recurso' => 'guardarnuevo',
    'PUT /@recurso/@id' => 'guardar',
    'DELETE /@recurso/@id' => 'borrar'
];

$rutas_adicionales = [
    'GET /@recurso/buscar' => 'buscarPorTexto',
    'GET /@recurso/pagina/@pagina/@por_pagina' => 'obtenerPaginado'
];

// Funciones auxiliares
function obtenerControlador($recurso, $config) {
    return is_array($config) && isset($config['controlador'])
        ? $config['controlador']
        : ucfirst($recurso);
}

function obtenerMetodosProhibidos($config) {
    return is_array($config) ? ($config['metodos_prohibidos'] ?? []) : [];
}

// Registrar rutas para cada recurso
foreach ($recursos as $recurso => $config) {
    // Normalizar configuración
    if (is_numeric($recurso)) {
        $recurso = $config;
        $config = [];
    }
    
    $controlador = obtenerControlador($recurso, $config);
    $metodos_prohibidos = obtenerMetodosProhibidos($config);
    
    // Registrar rutas CRUD
    foreach ($rutas_crud as $patron => $metodo) {
        $metodo_http = explode(' ', $patron)[0];
        
        if (!in_array($metodo_http, $metodos_prohibidos)) {
            $ruta = str_replace('@recurso', "api/$recurso", $patron);
            $f3->route($ruta, "{$controlador}Controller->$metodo");
        }
    }
    
    // Registrar rutas adicionales
    foreach ($rutas_adicionales as $patron => $metodo) {
        $ruta = str_replace('@recurso', "api/$recurso", $patron);
        $f3->route($ruta, "{$controlador}Controller->$metodo");
    }
} 