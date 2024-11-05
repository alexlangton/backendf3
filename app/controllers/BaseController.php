<?php

class BaseController extends JsonController {
    protected $tabla;
    protected $consultas;

    public function __construct($tabla = null) {
        parent::__construct();
        if ($tabla) {
            $this->tabla = $tabla;
            $this->consultas = new ConsultasSQL($tabla);
        }
    }

    protected function validarToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->respuestaError('Token no proporcionado', 401);
        }

        $token = $matches[1];
        // Aquí puedes agregar la validación del token con tu lógica específica
        // Por ahora solo verificamos que exista
        if (empty($token)) {
            return $this->respuestaError('Token inválido', 401);
        }

        return true;
    }

    public function obtener($f3, $params) {
        if ($this->validarToken() !== true) {
            return $this->validarToken();
        }

        try {
            $id = $this->validarId($params);
            if ($id === false) return;

            $this->logger->debug("Intento de obtener registro con ID: {$id}");
            $registro = $this->consultas->obtenerPorId($id);
            
            if (!$registro) {
                return $this->errorRegistroNoEncontrado($id, $this->tabla);
            }

            return $this->respuestaExito($registro);

        } catch (\Exception $e) {
            return $this->manejarError($e, 'Error al obtener el registro');
        }
    }

    public function obtenerTodos($f3) {
        if ($this->validarToken() !== true) {
            return $this->validarToken();
        }

        try {
            $this->logger->debug("Intento de obtener todos los registros");
            $registros = $this->consultas->obtenerTodos();
            return $this->respuestaExito($registros);
        } catch (\Exception $e) {
            return $this->manejarError($e, 'Error al obtener los registros');
        }
    }

    private function procesarGuardado($datos, $id = null) {
        // Validar datos
        $validador = new Validador($this->f3);
        $resultado = $validador->validar($this->tabla, $datos, $id !== null);

        if (!$resultado['valido']) {
            $this->logger->error("Datos inválidos en " . ($id ? "actualización" : "creación") . ": " . json_encode($resultado['errores']));
            return [
                'estado' => 'error',
                'mensaje' => 'Datos inválidos',
                'errores' => $resultado['errores'],
                'codigo' => 400
            ];
        }

        // Insertar o actualizar según corresponda
        if ($id) {
            $registro = $this->consultas->actualizar($id, $resultado['datos_limpios']);
            $operacion = "actualizar";
        } else {
            $registro = $this->consultas->insertar($resultado['datos_limpios']);
            $operacion = "crear";
        }
        
        if (!$registro) {
            $this->logger->error("Error al $operacion registro. Datos: " . json_encode($resultado['datos_limpios']));
            return [
                'estado' => 'error',
                'mensaje' => "Error al $operacion el registro",
                'codigo' => 500
            ];
        }

        $this->logger->debug("Registro " . ($id ? "actualizado" : "creado") . " correctamente. ID: " . ($id ?: $registro['id']));
        return [
            'estado' => 'exito',
            'mensaje' => "Registro " . ($id ? "actualizado" : "creado") . " correctamente",
            'datos' => $registro,
            'codigo' => $id ? 200 : 201
        ];
    }

    public function guardarnuevo($f3) {
        try {
            $rawBody = $f3->get('BODY');
            $this->logger->debug("Intento de crear nuevo registro. Body recibido: " . $rawBody);
            
            $resultadoJSON = $this->decodificarJSON($rawBody);
            if ($resultadoJSON['error']) {
                return $this->jsonResponse($resultadoJSON['response'], 400);
            }

            $resultado = $this->procesarGuardado($resultadoJSON['datos']);
            return $this->jsonResponse($resultado, $resultado['codigo']);

        } catch (\Exception $e) {
            return $this->manejarError($e, 'Error al crear el registro');
        }
    }

    public function guardar($f3, $params) {
        try {
            $id = $this->validarId($params, ' en actualización');
            if ($id === false) return;

            $resultadoJSON = $this->decodificarJSON($f3->get('BODY'), ' en actualización');
            if ($resultadoJSON['error']) {
                return $this->jsonResponse($resultadoJSON['response'], 400);
            }

            $registro = $this->consultas->obtenerPorId($id);
            if (!$registro) {
                return $this->errorRegistroNoEncontrado($id, $this->tabla);
            }

            $resultado = $this->procesarGuardado($resultadoJSON['datos'], $id);
            return $this->jsonResponse($resultado, $resultado['codigo']);

        } catch (\Exception $e) {
            return $this->manejarError($e, 'Error al actualizar el registro');
        }
    }

    public function borrar($f3, $params) {
        try {
            $id = $this->validarId($params);
            if ($id === false) return;

            $this->logger->debug("Intento de eliminar registro con ID: {$id}");
            $success = $this->consultas->eliminar($id);
            
            if (!$success) {
                return $this->respuestaError('Error al eliminar el registro', 500);
            }

            $this->logger->debug("Registro eliminado correctamente. ID: {$id}");
            return $this->respuestaExito(null, 'Registro eliminado correctamente');

        } catch (\Exception $e) {
            return $this->manejarError($e, 'Error al eliminar el registro');
        }
    }

    public function obtenerConFiltros($f3) {
        try {
            $filtros = array_filter($f3->get('GET'), function($valor) {
                return !empty($valor);
            });
            
            $this->logger->debug("Intento de obtener registros con filtros: " . json_encode($filtros));
            $registros = $this->consultas->buscarConFiltros(
                $filtros,
                $f3->get('GET.orden'),
                $f3->get('GET.limite')
            );
            
            return $this->respuestaExito($registros);
        } catch (\Exception $e) {
            return $this->manejarError($e, 'Error al obtener los registros');
        }
    }

    public function buscarPorTexto($f3) {
        try {
            $texto = $f3->get('GET.texto');
            $this->logger->debug("Intento de búsqueda por texto: {$texto}");
            
            if (!$texto) {
                return $this->respuestaError('Texto de búsqueda no proporcionado');
            }
            
            $campos = $f3->get('GET.campos') ? 
                      explode(',', $f3->get('GET.campos')) : 
                      ['nombre'];
            
            $registros = $this->consultas->buscarTexto($campos, $texto);
            return $this->respuestaExito($registros);

        } catch (\Exception $e) {
            return $this->manejarError($e, 'Error en la búsqueda');
        }
    }

    public function obtenerPaginado($f3, $params) {
        try {
            $pagina = isset($params['pagina']) ? (int)$params['pagina'] : 1;
            $porPagina = isset($params['por_pagina']) ? (int)$params['por_pagina'] : 10;
            
            $this->logger->debug("Intento de obtener registros paginados. Página: $pagina, Por página: $porPagina");
            $resultado = $this->consultas->obtenerPaginado($pagina, $porPagina);
            
            return $this->respuestaExito($resultado['datos'], null, 200, [
                'paginacion' => [
                    'total' => $resultado['total'],
                    'pagina_actual' => $resultado['pagina_actual'],
                    'por_pagina' => $resultado['por_pagina'],
                    'total_paginas' => ceil($resultado['total'] / $resultado['por_pagina'])
                ]
            ]);

        } catch (\Exception $e) {
            return $this->manejarError($e, 'Error al obtener los registros');
        }
    }

    private function validarId($params, $contexto = '') {
        if (!isset($params['id'])) {
            $this->logger->error("ID no proporcionado" . $contexto);
            return $this->errorIdNoProporcionado();
        }
        return $params['id'];
    }

    protected function ejecutarOperacion(callable $operacion, $mensajeError) {
        try {
            return $operacion();
        } catch (\Exception $e) {
            return $this->manejarError($e, $mensajeError);
        }
    }
} 