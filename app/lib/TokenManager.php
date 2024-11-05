<?php

class TokenManager {
    protected $consultasAuth;
    
    public function __construct() {
        $this->consultasAuth = new ConsultasAuth();
    }

    public function obtenerToken() {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (empty($authHeader)) {
            return null;
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        return $matches[1];
    }

    public function verificarToken($token) {
        $usuario = $this->consultasAuth->verificarToken($token);
        
        if (!$usuario) {
            return [
                'valido' => false,
                'mensaje' => 'Token inválido o expirado'
            ];
        }

        return [
            'valido' => true,
            'usuario' => [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'rol' => $usuario['rol']
            ]
        ];
    }

    public function generarToken($idUsuario) {
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        return $this->consultasAuth->guardarToken($idUsuario, $token, $expiracion);
    }

    public function invalidarToken($token) {
        return $this->consultasAuth->invalidarToken($token);
    }
} 