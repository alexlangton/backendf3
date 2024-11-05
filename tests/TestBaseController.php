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
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }

    public function testearTodo() {
        foreach ($this->recursos as $recurso) {
            echo "\nTesteando recurso: $recurso\n";
            echo "------------------------\n";

            // Test obtenerConFiltros
            $result = $this->request('GET', $recurso);
            echo "GET /$recurso: {$result['code']}\n";

            // Test obtenerPaginado
            $result = $this->request('GET', "$recurso/pagina/1/10");
            echo "GET /$recurso/pagina/1/10: {$result['code']}\n";

            // Test buscarPorTexto
            $result = $this->request('GET', "$recurso/buscar?texto=test");
            echo "GET /$recurso/buscar?texto=test: {$result['code']}\n";

            // Test guardarnuevo
            $datos = $this->getDatosPrueba($recurso);
            $result = $this->request('POST', $recurso, $datos);
            echo "POST /$recurso: {$result['code']}\n";
            
            if ($result['code'] == 201) {
                $id = $result['response']['datos']['id'];
                
                // Test obtener
                $result = $this->request('GET', "$recurso/$id");
                echo "GET /$recurso/$id: {$result['code']}\n";

                // Test guardar (actualizar)
                $datos['nombre'] = 'Actualizado';
                $result = $this->request('PUT', "$recurso/$id", $datos);
                echo "PUT /$recurso/$id: {$result['code']}\n";

                // Test borrar
                $result = $this->request('DELETE', "$recurso/$id");
                echo "DELETE /$recurso/$id: {$result['code']}\n";
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
        }
    }
}

// Ejecutar los tests
$tester = new TestBaseController();
$tester->testearTodo(); 