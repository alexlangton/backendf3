<?php
require 'vendor/autoload.php';

$f3 = Base::instance();

// Cargar configuración básica
$f3->config('config/config.ini');

// Cargar rutas
require 'config/routes.php';

// Manejo de errores para API REST
$f3->set('ONERROR', function($f3) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'code' => $f3->get('ERROR.code'),
        'message' => $f3->get('ERROR.text'),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
});

$f3->run();
