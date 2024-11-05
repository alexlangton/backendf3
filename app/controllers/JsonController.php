<?php

class JsonController extends Controller {
    protected $consultasAuth;
    protected $tokenManager;

    public function __construct() {
        parent::__construct();
        $this->consultasAuth = new ConsultasAuth();
        $this->tokenManager = new TokenManager();
    }

    protected function esRutaPublica($ruta, $metodo) {
        // Si la ruta comienza con /api/public/
        return strpos($ruta, '/api/public/') === 0;
    }

    public function beforeRoute($f3) {
        $rutaActual = $f3->get('PATTERN');
        $metodo = $f3->get('VERB');

        if ($this->esRutaPublica($rutaActual, $metodo)) {
            return;
        }

        $token = $this->tokenManager->obtenerToken();
        if (!$token) {
            $this->respuestaError('Token no proporcionado', 401);
            exit;
        }

        $resultadoToken = $this->tokenManager->verificarToken($token);
        if (!$resultadoToken['valido']) {
            $this->respuestaError($resultadoToken['mensaje'], 401);
            exit;
        }

        $f3->set('USUARIO', $resultadoToken['usuario']);
    }

    protected function jsonResponse($data, $code = 200) {
        // Asegurar formato consistente para todas las respuestas
        $response = [];
        
        if (is_array($data) && isset($data['estado'])) {
            // Ya tiene el formato correcto
            $response = $data;
        } else if (is_array($data) && isset($data[0]) && $data[0] === 'exito') {
            // Convertir formato antiguo a nuevo
            $response = [
                'estado' => 'exito',
                'datos' => $data[1]
            ];
        } else {
            // Cualquier otra respuesta
            $response = [
                'estado' => 'exito',
                'datos' => $data
            ];
        }

        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode($response);
        exit;
    }

    protected function respuestaExito($datos = null, $mensaje = null, $codigo = 200, $adicional = []) {
        $respuesta = ['estado' => 'exito'];
        if ($mensaje) $respuesta['mensaje'] = $mensaje;
        if ($datos !== null) $respuesta['datos'] = $datos;
        if ($adicional) $respuesta = array_merge($respuesta, $adicional);
        return $this->jsonResponse($respuesta, $codigo);
    }

    protected function respuestaError($mensaje, $codigo = 400, $detalles = null) {
        $respuesta = [
            'estado' => 'error',
            'mensaje' => $mensaje
        ];
        if ($detalles !== null) $respuesta['detalles'] = $detalles;
        return $this->jsonResponse($respuesta, $codigo);
    }

    protected function manejarError(\Exception $e, $mensaje) {
        $this->logger->error($e->getMessage());
        return $this->jsonResponse([
            'estado' => 'error',
            'mensaje' => $mensaje,
            'error_detalle' => $e->getMessage()
        ], 500);
    }

    protected function errorIdNoProporcionado() {
        return $this->respuestaError('ID no proporcionado', 400);
    }

    protected function errorRegistroNoEncontrado($id, $tabla) {
        return $this->respuestaError(
            "Registro no encontrado con ID: {$id}",
            404,
            [
                'debug' => [
                    'tabla' => $tabla,
                    'id_buscado' => $id
                ]
            ]
        );
    }

    protected function decodificarJSON($rawBody, $contexto = '') {
        $datos = json_decode($rawBody, true);
        
        if (!$datos) {
            $error = json_last_error_msg();
            $this->logger->error("Error decodificando JSON{$contexto}: $error");
            return [
                'error' => true,
                'response' => [
                    'estado' => 'error',
                    'mensaje' => 'Datos no proporcionados o formato JSON inválido',
                    'debug' => $error
                ]
            ];
        }
        
        return ['error' => false, 'datos' => $datos];
    }
} 