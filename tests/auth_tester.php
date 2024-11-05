<?php

class AuthTester {
    private $urlBase = 'http://localhost/pk/api';
    private $token = '9df25e03667c6f5fc33a217b4c6a790f1cc06eefb8a94acb8f19356992ac43f7';
    private $archivoPeticiones = 'auth_test.log';
    
    public function __construct() {
        $this->registrarPeticion("INICIO", "Iniciando pruebas de autenticación");
        $this->ejecutarPruebas();
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
        $cabeceras = ['Content-Type: application/json'];
        if ($this->token) {
            $cabeceras[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $cabeceras,
            CURLOPT_POSTFIELDS => $datos ? json_encode($datos) : null
        ]);

        $respuesta = curl_exec($ch);
        $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($respuesta === false) {
            $error = curl_error($ch);
            $this->registrarPeticion("ERROR", "Error CURL: $error");
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        $datosRespuesta = json_decode($respuesta, true);
        $this->registrarPeticion("RESPUESTA", "$ruta - HTTP $codigoHttp", $datosRespuesta);
        
        return [
            'codigo' => $codigoHttp,
            'datos' => $datosRespuesta
        ];
    }

    private function ejecutarPruebas() {
        echo "\n=== Pruebas de Autenticación ===\n";
        
        $this->probarLoginExitoso();
        $this->probarLoginFallido();
        $this->probarLoginIncompleto();
        $this->probarAccesoSinToken();
        $this->probarAccesoConToken();
        $this->probarLogout();
        
        echo "\n=== Pruebas completadas ===\n";
    }

    private function probarLoginExitoso() {
        echo "\nPrueba: Login exitoso\n";
        $credenciales = [
            'usuario' => 'admin',
            'password' => 'admin123'
        ];
        
        $respuesta = $this->hacerPeticion('/public/login', 'POST', $credenciales);
        if ($respuesta['codigo'] === 200 && 
            isset($respuesta['datos']['estado']) && 
            $respuesta['datos']['estado'] === 'exito') {
            $this->token = $respuesta['datos']['datos']['token'];
            echo "✓ Login exitoso\n";
        } else {
            echo "✗ Login fallido\n";
        }
    }

    private function probarLoginFallido() {
        echo "\nPrueba: Login con credenciales inválidas\n";
        $credenciales = [
            'usuario' => 'admin',
            'password' => 'incorrecta'
        ];
        
        $respuesta = $this->hacerPeticion('/public/login', 'POST', $credenciales);
        if ($respuesta['codigo'] === 401) {
            echo "✓ Rechazo correcto de credenciales inválidas\n";
        } else {
            echo "✗ Error en validación de credenciales\n";
        }
    }

    private function probarLoginIncompleto() {
        echo "\nPrueba: Login con datos incompletos\n";
        $credenciales = [
            'usuario' => 'admin'
        ];
        
        $respuesta = $this->hacerPeticion('/public/login', 'POST', $credenciales);
        if ($respuesta['codigo'] >= 400) {  // Acepta cualquier código de error
            echo "✓ Validación correcta de datos incompletos (código: {$respuesta['codigo']})\n";
        } else {
            echo "✗ Error en validación de datos incompletos\n";
        }
    }

    private function probarAccesoSinToken() {
        echo "\nPrueba: Acceso a ruta protegida sin token\n";
        $tokenTemp = $this->token;
        $this->token = '';
        
        $respuesta = $this->hacerPeticion('/parkings', 'GET');
        if ($respuesta['codigo'] === 401) {
            echo "✓ Acceso denegado correctamente\n";
        } else {
            echo "✗ Error en validación de token\n";
        }
        
        $this->token = $tokenTemp;
    }

    private function probarAccesoConToken() {
        echo "\nPrueba: Acceso a ruta protegida con token\n";
        if (!$this->token) {
            echo "✗ No hay token disponible para la prueba\n";
            return;
        }
        
        $respuesta = $this->hacerPeticion('/parkings', 'GET');
        if ($respuesta['codigo'] === 200) {
            echo "✓ Acceso permitido correctamente\n";
        } else {
            echo "✗ Error en acceso con token\n";
        }
    }

    private function probarLogout() {
        echo "\nPrueba: Logout\n";
        if (!$this->token) {
            echo "✗ No hay token disponible para la prueba\n";
            return;
        }
        
        $respuesta = $this->hacerPeticion('/public/logout', 'POST');
        if ($respuesta['codigo'] === 200) {
            echo "✓ Logout exitoso\n";
            $this->token = '';
        } else {
            echo "✗ Error en logout\n";
        }
    }
}

// Ejecutar las pruebas
new AuthTester();