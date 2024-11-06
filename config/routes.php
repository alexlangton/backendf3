<?php

// Rutas de autenticación
$f3->route('POST /api/public/login', 'AutenticacionController->login');
$f3->route('POST /api/public/logout', 'AutenticacionController->logout');
$f3->route('POST /api/public/recuperarPassword', 'AutenticacionController->recuperarPassword');

// Recursos
$recursos = [
    'parkings' => 'Parkings',
    'carteles' => 'Carteles',
    'usuarios' => 'Usuarios'
];

// Rutas base para cada recurso
$rutas = [
    'GET /@recurso' => 'obtenerConFiltros',
    'GET /@recurso/@id' => 'obtener',
    'POST /@recurso' => 'guardarnuevo',
    'PUT /@recurso/@id' => 'guardar',
    'DELETE /@recurso/@id' => 'borrar'
];

// Rutas adicionales para cada recurso
$rutas_adicionales = [
    'GET /@recurso/buscar' => 'buscarPorTexto',
    'GET /@recurso/pagina/@pagina/@por_pagina' => 'obtenerPaginado'
];

// Registrar rutas para todos los recursos
foreach ($recursos as $recurso => $controlador) {
    foreach ($rutas as $patron => $metodo) {
        $ruta = str_replace('@recurso', "api/$recurso", $patron);
        $f3->route($ruta, "{$controlador}Controller->$metodo");
    }
    
    foreach ($rutas_adicionales as $patron => $metodo) {
        $ruta = str_replace('@recurso', "api/$recurso", $patron);
        $f3->route($ruta, "{$controlador}Controller->$metodo");
    }
} 