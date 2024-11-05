<?php

class AuthController extends JsonController {
    protected $consultasAuth;
    protected $tokenManager;

    public function __construct() {
        parent::__construct();
        $this->consultasAuth = new ConsultasAuth();
        $this->tokenManager = new TokenManager();
    }

    public function login($f3) {
        try {
            return $this->procesarLogin($f3);
        } catch (Exception $e) {
            return $this->manejarError($e, 'Error en el proceso de login');
        }
    }

    protected function procesarLogin($f3) {
        $resultadoJSON = $this->decodificarJSON($f3->get('BODY'));
        if ($resultadoJSON['error']) {
            return $this->respuestaError($resultadoJSON['response']['mensaje'], 400);
        }

        $datos = $resultadoJSON['datos'];
        $usuario = $this->consultasAuth->verificarCredenciales(
            $datos['usuario'], 
            $datos['password']
        );

        if (!$usuario) {
            return $this->respuestaError('Credenciales inválidas', 401);
        }
        
        $token = $this->tokenManager->generarToken($usuario['id']);
        if ($token['estado'] === 'error') {
            return $this->respuestaError('Error al generar el token');
        }

        return $this->respuestaExito([
            'token' => $token['datos']['token'],
            'usuario' => [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'rol' => $usuario['rol']
            ]
        ]);
    }

    public function logout($f3) {
        try {
            return $this->procesarLogout($f3);
        } catch (Exception $e) {
            return $this->manejarError($e, 'Error en el proceso de logout');
        }
    }

    protected function procesarLogout($f3) {
        $token = $this->tokenManager->obtenerToken();
        if (!$token) {
            return $this->respuestaError('Token no proporcionado', 401);
        }

        $resultado = $this->tokenManager->invalidarToken($token);
        if (!$resultado) {
            return $this->respuestaError('Error al cerrar sesión');
        }

        return $this->respuestaExito(null, 'Sesión cerrada correctamente');
    }
} 