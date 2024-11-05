<?php
return [
    'nombre' => ['requerido', 'string', 'max:100'],
    'direccion' => ['requerido', 'string', 'max:200'],
    'capacidad_total' => ['requerido', 'entero', 'min:1'],
    'espacios_disponibles' => ['requerido', 'entero', 'min:0'],
    'tarifa_hora' => ['requerido', 'decimal', 'min:0'],
    'horario_apertura' => ['requerido', 'hora'],
    'horario_cierre' => ['requerido', 'hora'],
    'estado' => ['requerido', 'enum:activo,inactivo,mantenimiento']
]; 