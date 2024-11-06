<?php

namespace Tests;

class TestBaseController {
    private $token = '1a8ef3d99d9fceb80a59c6d500a7e752d36eb93f139217492c40a3b69c5a7baa';
    private $baseUrl = 'http://localhost/pk/api';
    private $recursos = ['parkings', 'carteles', 'usuarios'];

    private function request($method, $endpoint, $data = null) {
        $ch = curl_init("{$this->baseUrl}/$endpoint");
        
        $headers = [
            'Content-Type: application/json',
            "Authorization: Bearer {$this->token}"
        ];

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($data) {
            $jsonData = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            echo "\nEnviando datos: $jsonData\n";
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            echo "\nError CURL: " . curl_error($ch) . "\n";
        }
        
        curl_close($ch);
        
        echo "\nRespuesta: $response\n";
        
        return [
            'code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }

    public function testearTodo() {
        foreach ($this->recursos as $recurso) {
            echo "\n=== Testeando recurso: $recurso ===\n";
            echo str_repeat("=", 40) . "\n";

            // Test obtenerTodos
            $result = $this->request('GET', $recurso);
            echo "\nGET /$recurso: {$result['code']}\n";
            $this->validarRespuesta($result);

            // Test obtenerPaginado
            $result = $this->request('GET', "$recurso/pagina/1/10");
            echo "\nGET /$recurso/pagina/1/10: {$result['code']}\n";
            $this->validarRespuesta($result);

            // Test buscarPorTexto
            $result = $this->request('GET', "$recurso/buscar?texto=test");
            echo "\nGET /$recurso/buscar?texto=test: {$result['code']}\n";
            $this->validarRespuesta($result);

            // Test guardarnuevo
            $datos = $this->getDatosPrueba($recurso);
            $result = $this->request('POST', $recurso, $datos);
            echo "\nPOST /$recurso: {$result['code']}\n";
            $this->validarRespuesta($result);
            
            if ($result['code'] == 201 && isset($result['response']['datos']['id'])) {
                $id = $result['response']['datos']['id'];
                
                // Test obtener
                $result = $this->request('GET', "$recurso/$id");
                echo "\nGET /$recurso/$id: {$result['code']}\n";
                $this->validarRespuesta($result);

                // Test guardar (actualizar)
                $datos['nombre'] = "Actualizado " . date('Y-m-d H:i:s');
                $result = $this->request('PUT', "$recurso/$id", $datos);
                echo "\nPUT /$recurso/$id: {$result['code']}\n";
                $this->validarRespuesta($result);

                // Test borrar
                $result = $this->request('DELETE', "$recurso/$id");
                echo "\nDELETE /$recurso/$id: {$result['code']}\n";
                $this->validarRespuesta($result);
            } else {
                echo "\nError al crear recurso. No se ejecutarán las pruebas restantes.\n";
            }
        }
    }

    private function validarRespuesta($result) {
        if (!isset($result['response'])) {
            echo "ERROR: No hay respuesta del servidor\n";
            return;
        }
        
        if (isset($result['response']['estado']) && $result['response']['estado'] === 'error') {
            echo "ERROR: " . ($result['response']['mensaje'] ?? 'Error desconocido') . "\n";
            if (isset($result['response']['detalles'])) {
                echo "Detalles: " . print_r($result['response']['detalles'], true) . "\n";
            }
        }
    }

    private function getDatosPrueba($recurso) {
        switch ($recurso) {
            case 'usuarios':
                return [
                    'usuario' => 'test_' . time(),
                    'nombre' => 'Usuario Test',
                    'email' => 'test_' . time() . '@test.com',
                    'password' => 'test123456',
                    'rol' => 'usuario'
                ];
            
            case 'parkings':
                return [
                    'nombre' => 'Parking Central',
                    'direccion' => 'Calle Principal 123',
                    'estado' => 'activo',
                    'capacidad_total' => 100,
                    'espacios_disponibles' => 100,
                    'horario_apertura' => '08:00:00',
                    'horario_cierre' => '20:00:00',
                    'tarifa_hora' => 2.50
                ];
            
            case 'carteles':
                return [
                    'nombre' => 'Cartel Test ' . time(),
                    'descripcion' => 'Descripción Test',
                    'estado' => 'activo'
                ];
            
            default:
                throw new \Exception("Recurso no soportado: $recurso");
        }
    }
}

// Ejecutar los tests
$tester = new TestBaseController();
$tester->testearTodo();