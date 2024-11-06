<?php

class JsonController extends Controller {
    protected $autenticacionController;

    public function __construct() {
        parent::__construct();
        $this->autenticacionController = new AutenticacionController();
    }

    // Middleware de autenticación
    public function beforeRoute($f3) {
        $resultado = $this->autenticacionController->verificarAutenticacion($f3);
        if ($resultado !== true) {
            return $this->respuestaError(
                $resultado['mensaje'],
                $resultado['codigo'],
                $resultado['detalles'] ?? null
            );
        }
    }

    // Métodos de respuesta JSON
    protected function jsonResponse($data, $code = 200) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode($this->formatearRespuesta($data));
        exit;
    }

    private function formatearRespuesta($data) {
        if (is_array($data) && isset($data['estado'])) {
            return $data;
        }
        return [
            'estado' => 'exito',
            'datos' => $data
        ];
    }

    protected function respuestaExito($datos = null, $mensaje = null, $codigo = 200, $metadata = null) {
        $respuesta = ['estado' => 'exito'];
        
        if ($mensaje) $respuesta['mensaje'] = $mensaje;
        if ($datos !== null) $respuesta['datos'] = $datos;
        if ($metadata) $respuesta['metadata'] = $metadata;
        
        return $this->jsonResponse($respuesta, $codigo);
    }

    protected function respuestaError($mensaje, $codigo = 400, $detalles = null) {
        $respuesta = [
            'estado' => 'error',
            'mensaje' => $mensaje
        ];
        
        if ($detalles !== null) {
            $respuesta['detalles'] = $detalles;
        }
        
        return $this->jsonResponse($respuesta, $codigo);
    }

    // Manejo de errores y validaciones
    protected function manejarError(\Exception $e, $mensaje = null) {
        $this->logger->error($e->getMessage());
        return $this->respuestaError(
            $mensaje ?? 'Error interno del servidor',
            500,
            $this->f3->get('DEBUG') ? ['error' => $e->getMessage()] : null
        );
    }

    protected function ejecutarOperacion(callable $operacion, $mensajeError) {
        try {
            return $operacion();
        } catch (\Exception $e) {
            return $this->manejarError($e, $mensajeError);
        }
    }

    // Utilidades de validación
    protected function validarId($id, $tabla) {
        if (empty($id)) {
            return $this->respuestaError('ID no proporcionado', 400);
        }

        if (!is_numeric($id) || $id <= 0) {
            return $this->respuestaError('ID inválido', 400);
        }

        return true;
    }

    protected function errorRegistroNoEncontrado($id, $tabla) {
        return $this->respuestaError(
            "Registro no encontrado",
            404,
            [
                'tabla' => $tabla,
                'id' => $id
            ]
        );
    }

    // Procesamiento de JSON
    protected function decodificarJSON($rawBody, $contexto = '') {
        if (empty($rawBody)) {
            return [
                'error' => true,
                'response' => [
                    'estado' => 'error',
                    'mensaje' => 'No se proporcionaron datos'
                ]
            ];
        }

        $datos = json_decode($rawBody, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = json_last_error_msg();
            $this->logger->error("Error decodificando JSON{$contexto}: $error");
            return [
                'error' => true,
                'response' => [
                    'estado' => 'error',
                    'mensaje' => 'Formato JSON inválido',
                    'detalles' => $this->f3->get('DEBUG') ? ['error' => $error] : null
                ]
            ];
        }
        
        return ['error' => false, 'datos' => $datos];
    }
} 