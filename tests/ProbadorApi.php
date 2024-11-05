<?php

class ProbadorApi {
    private $urlBase = 'http://localhost/pk/api';
    private $token = '9df25e03667c6f5fc33a217b4c6a790f1cc06eefb8a94acb8f19356992ac43f7';
    private $archivoPeticiones = 'peticiones.log';
    
    public function __construct() {
        $this->registrarPeticion("INICIO", "Iniciando pruebas con token pre-configurado");
        echo "\n=== Sesión pre-configurada ===\n";
        echo "Token: " . substr($this->token, 0, 20) . "...\n";
        echo "==========================\n\n";
    }

    private function registrarPeticion($metodo, $descripcion, $datos = null) {
        $fecha = date('Y-m-d H:i:s');
        $mensaje = "[$fecha] $metodo - $descripcion";
        if ($datos) {
            $mensaje .= " - Datos: " . json_encode($datos, JSON_UNESCAPED_UNICODE);
        }
        $mensaje .= PHP_EOL;
        file_put_contents($this->archivoPeticiones, $mensaje, FILE_APPEND);
        echo $mensaje;
    }

    private function hacerPeticion($ruta, $metodo, $datos = null) {
        $url = $this->urlBase . $ruta;
        $this->registrarPeticion($metodo, $url, $datos);
        
        $ch = curl_init($url);
        $cabeceras = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token  // Siempre incluir el token
        ];

        $opciones = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $cabeceras,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ];

        if ($datos && in_array($metodo, ['POST', 'PUT'])) {
            $opciones[CURLOPT_POSTFIELDS] = json_encode($datos);
        }

        curl_setopt_array($ch, $opciones);
        $respuesta = curl_exec($ch);
        $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($respuesta === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            $this->registrarPeticion("ERROR", "Error CURL ($errno): $error");
            curl_close($ch);
            die("Error de conexión: $error\n");
        }

        curl_close($ch);

        $datosRespuesta = json_decode($respuesta, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->registrarPeticion("ERROR", "Respuesta no válida: " . $respuesta);
            die("Error: Respuesta no válida del servidor\n");
        }

        // Depuración
        echo "\nDepuración de respuesta:\n";
        echo "Estado: " . ($datosRespuesta['estado'] ?? 'no definido') . "\n";
        echo "Respuesta completa: " . json_encode($datosRespuesta, JSON_PRETTY_PRINT) . "\n";

        if ($codigoHttp >= 400) {
            $this->registrarPeticion("ERROR", "Error HTTP $codigoHttp", $datosRespuesta);
            if ($codigoHttp == 401 && $ruta !== '/login') {
                die("Error de autenticación. Por favor, inicie sesión nuevamente.\n");
            }
            return $datosRespuesta;
        }

        $this->registrarPeticion("RESPUESTA", "$ruta - HTTP $codigoHttp", $datosRespuesta);
        return $datosRespuesta;
    }
} 