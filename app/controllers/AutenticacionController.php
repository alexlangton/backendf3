<?php

class AutenticacionController extends JsonController {
    protected $consultasAuth;
    protected $tokenManager;

    public function __construct() {
        parent::__construct();
        $this->consultasAuth = new ConsultasAuth();
        $this->tokenManager = new TokenManager();
    }

    protected function esRutaPublica($ruta) {
        return strpos($ruta, '/api/public/') === 0 ||
               in_array($ruta, ['/api/auth/login', '/api/auth/registro']);
    }

    public function verificarAutenticacion($f3) {
        try {
            $rutaActual = $f3->get('PATTERN');

            if ($this->esRutaPublica($rutaActual)) {
                return true;
            }

            $token = $this->tokenManager->obtenerToken();
            if (!$token) {
                return [
                    'estado' => 'error',
                    'mensaje' => 'Token no proporcionado',
                    'codigo' => 401
                ];
            }

            $resultadoToken = $this->tokenManager->verificarToken($token);
            if (!$resultadoToken['valido']) {
                return [
                    'estado' => 'error',
                    'mensaje' => $resultadoToken['mensaje'],
                    'codigo' => 401,
                    'detalles' => $resultadoToken['detalles'] ?? null
                ];
            }

            $f3->set('USUARIO', $resultadoToken['usuario']);
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error("Error en autenticación: " . $e->getMessage());
            return [
                'estado' => 'error',
                'mensaje' => 'Error de autenticación',
                'codigo' => 500
            ];
        }
    }

    public function login($f3) {
        return $this->ejecutarOperacion(function() use ($f3) {
            $resultadoJSON = $this->decodificarJSON($f3->get('BODY'));
            if ($resultadoJSON['error']) {
                return $resultadoJSON['response'];
            }

            $datos = $resultadoJSON['datos'];
            if (!isset($datos['usuario']) || !isset($datos['password'])) {
                return $this->respuestaError(
                    'Usuario y contraseña son requeridos',
                    400
                );
            }

            $usuario = $this->consultasAuth->verificarCredenciales(
                $datos['usuario'], 
                $datos['password']
            );

            if (!$usuario) {
                return $this->respuestaError('Credenciales inválidas', 401);
            }
            
            $token = $this->tokenManager->generarToken($usuario['id']);
            if ($token['estado'] === 'error') {
                return $this->respuestaError(
                    'Error al generar el token',
                    500,
                    $token['mensaje'] ?? null
                );
            }

            return $this->respuestaExito([
                'token' => $token['datos']['token'],
                'usuario' => [
                    'id' => $usuario['id'],
                    'nombre' => $usuario['nombre'],
                    'rol' => $usuario['rol']
                ]
            ], 'Inicio de sesión exitoso');
        }, 'Error en el proceso de login');
    }

    public function logout($f3) {
        return $this->ejecutarOperacion(function() {
            $token = $this->tokenManager->obtenerToken();
            if (!$token) {
                return $this->respuestaError('Token no proporcionado', 401);
            }

            if (!$this->tokenManager->invalidarToken($token)) {
                return $this->respuestaError('Error al cerrar sesión', 500);
            }

            return $this->respuestaExito(
                null, 
                'Sesión cerrada correctamente'
            );
        }, 'Error en el proceso de logout');
    }
} 