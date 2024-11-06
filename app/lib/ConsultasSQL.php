<?php

class ConsultasSQL {
    protected $db;
    protected $tabla;
    protected $f3;
    protected $logFile;

    public function __construct($tabla) {
        $this->f3 = \Base::instance();
        $this->db = $this->f3->get('DB');
        $this->tabla = $tabla;
        
        // Crear directorio de logs si no existe
        $logDir = $this->f3['LOGPATH'];
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $this->logFile = $logDir . '/sql.log';
        
        // Crear archivo de log si no existe
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0777);
        }
    }

    protected function logQuery($sql, $valores = []) {
        // Reemplazar los placeholders ? con los valores reales
        $index = 0;
        $sqlCompleta = preg_replace_callback('/\?/', function($match) use ($valores, &$index) {
            $valor = $valores[$index] ?? 'NULL';
            $index++;
            return is_string($valor) ? "'$valor'" : $valor;
        }, $sql);

        // Registrar la consulta
        if ($this->f3->get('DEBUG') >= 3) {
            $this->f3->write(
                $this->logFile,
                date('Y-m-d H:i:s') . ' - ' . "SQL Query [$this->tabla]: " . $sqlCompleta . "\n",
                true
            );
        }
    }

    public function obtenerPorId($id) {
        try {
            // Validar que $id sea un número y no esté vacío
            if (empty($id) || !is_numeric($id) || $id <= 0) {
                $this->f3->write(
                    $this->logFile,
                    date('Y-m-d H:i:s') . ' - Error: ID inválido: ' . var_export($id, true) . "\n",
                    true
                );
                return [
                    'estado' => 'error',
                    'mensaje' => 'ID inválido',
                    'detalles' => [
                        'id' => $id,
                        'tipo' => gettype($id),
                        'razon' => empty($id) ? 'vacío' : (!is_numeric($id) ? 'no numérico' : 'menor o igual a cero')
                    ]
                ];
            }

            // Convertir a entero para asegurar formato correcto
            $id = (int)$id;
            
            $sql = 'SELECT * FROM ' . $this->tabla . ' WHERE id = ?';
            $this->logQuery($sql, [$id]);
            $resultado = $this->db->exec($sql, [$id]);

            if (!$resultado) {
                return [
                    'estado' => 'error',
                    'mensaje' => 'Registro no encontrado',
                    'detalles' => ['id' => $id]
                ];
            }

            return [
                'estado' => 'exito',
                'datos' => $resultado[0]
            ];

        } catch (\Exception $e) {
            $mensaje = $e->getMessage();
            $this->f3->write(
                $this->logFile,
                date('Y-m-d H:i:s') . ' - Error al obtener registro: ' . $mensaje . "\n",
                true
            );
            return [
                'estado' => 'error',
                'mensaje' => 'Error al obtener registro',
                'detalles' => [
                    'error' => $mensaje,
                    'id' => $id
                ]
            ];
        }
    }

    public function obtenerTodos() {
        try {
            $sql = 'SELECT * FROM ' . $this->tabla;
            $this->logQuery($sql);
            $resultado = $this->db->exec($sql);
            
            return $this->respuestaExito($resultado);
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            return $this->respuestaError(
                $error['mensaje'],
                $error,
                $error['codigo'] ?? 500
            );
        }
    }

    protected function manejarError($e) {
        $mensaje = $e->getMessage();
        $codigo = $e->getCode();
        $errorInfo = [];

        // Capturar errores de duplicados
        if (strpos($mensaje, 'Duplicate entry') !== false) {
            preg_match("/Duplicate entry '(.+)' for key '(.+)'/", $mensaje, $matches);
            if (count($matches) >= 3) {
                $valor = $matches[1];
                $campo = $matches[2];
                
                $errorInfo = [
                    'tipo' => 'duplicado',
                    'campo' => $campo,
                    'valor' => $valor,
                    'mensaje' => "Valor duplicado: {$campo} = {$valor}",
                    'codigo' => 409 // Conflict
                ];
            }
        }
        // Error de restricción de llave foránea
        elseif (strpos($mensaje, 'foreign key constraint fails') !== false) {
            $errorInfo = [
                'tipo' => 'restriccion_foranea',
                'mensaje' => 'Violación de restricción de llave foránea',
                'codigo' => 409
            ];
        }
        // Error de tabla no encontrada
        elseif (strpos($mensaje, "Table") !== false && strpos($mensaje, "doesn't exist") !== false) {
            $errorInfo = [
                'tipo' => 'tabla_no_existe',
                'mensaje' => 'La tabla no existe',
                'codigo' => 500
            ];
        }
        // Error de columna no encontrada
        elseif (strpos($mensaje, "Unknown column") !== false) {
            $errorInfo = [
                'tipo' => 'columna_no_existe',
                'mensaje' => 'Columna no existe en la tabla',
                'codigo' => 400
            ];
        }
        // Otros errores SQL
        else {
            $errorInfo = [
                'tipo' => 'sql',
                'mensaje' => $mensaje,
                'codigo' => $codigo ?: 500
            ];
        }

        // Registrar el error completo en el log
        $this->f3->write(
            $this->logFile,
            date('Y-m-d H:i:s') . " - Error SQL en tabla {$this->tabla}: " . $mensaje . "\n",
            true
        );

        return $errorInfo;
    }

    protected function respuestaExito($datos = null, $mensaje = null) {
        return [
            'estado' => 'exito',
            'mensaje' => $mensaje,
            'datos' => $datos
        ];
    }

    protected function respuestaError($mensaje, $detalles = null, $codigo = 500) {
        return [
            'estado' => 'error',
            'mensaje' => $mensaje,
            'detalles' => $detalles,
            'codigo' => $codigo
        ];
    }

    public function insertar($datos) {
        try {
            // Validar que haya datos
            if (empty($datos)) {
                return $this->respuestaError('No hay datos para insertar', null, 400);
            }

            // Hashear password si existe y estamos en la tabla usuarios
            if ($this->tabla === 'usuarios' && isset($datos['password'])) {
                $hasheo = new HasheoPassword();
                $datos['password'] = $hasheo->hashear($datos['password']);
            }

            // Preparar la consulta
            $columnas = implode(', ', array_keys($datos));
            $valores = implode(', ', array_fill(0, count($datos), '?'));
            $sql = "INSERT INTO {$this->tabla} ($columnas) VALUES ($valores)";
            
            // Registrar la consulta en el log
            $this->logQuery($sql, array_values($datos));
            
            // Ejecutar la inserción
            $result = $this->db->exec($sql, array_values($datos));
            
            if ($result === false) {
                return $this->respuestaError('Error en la ejecución de la consulta');
            }
            
            // Obtener el ID del nuevo registro
            $nuevoId = $this->db->lastInsertId();
            
            return $this->respuestaExito(
                ['id' => $nuevoId],
                'Registro creado correctamente'
            );
                
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            return $this->respuestaError(
                $error['mensaje'],
                $error,
                $error['codigo'] ?? 500
            );
        }
    }

    public function actualizar($id, $datos) {
        try {
            if (empty($id)) {
                return $this->respuestaError('ID no proporcionado', null, 400);
            }

            // Hashear password si existe y estamos en la tabla usuarios
            if ($this->tabla === 'usuarios' && isset($datos['password'])) {
                $hasheo = new HasheoPassword();
                $datos['password'] = $hasheo->hashear($datos['password']);
            }

            $campos = array_map(function($campo) {
                return "$campo = ?";
            }, array_keys($datos));
            
            $sql = "UPDATE {$this->tabla} SET " . implode(', ', $campos) . " WHERE id = ?";
            $valores = array_merge(array_values($datos), [$id]);
            
            $this->logQuery($sql, $valores);
            $result = $this->db->exec($sql, $valores);
            
            if ($result !== false) {
                // Si la actualización fue exitosa, devolver el registro actualizado
                return $this->obtenerPorId($id);
            }
            
            return $this->respuestaError('Error al actualizar el registro');
            
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            return $this->respuestaError(
                $error['mensaje'],
                $error,
                $error['codigo'] ?? 500
            );
        }
    }

    public function eliminar($id) {
        try {
            if (empty($id)) {
                return $this->respuestaError('ID no proporcionado', null, 400);
            }

            $sql = 'DELETE FROM ' . $this->tabla . ' WHERE id = ?';
            $this->logQuery($sql, [$id]);
            $result = $this->db->exec($sql, [$id]);
            
            if ($result === false) {
                return $this->respuestaError('Error al eliminar el registro');
            }
            
            return $this->respuestaExito(
                ['id' => $id],
                'Registro eliminado correctamente'
            );
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            return $this->respuestaError(
                $error['mensaje'],
                $error,
                $error['codigo'] ?? 500
            );
        }
    }

    // Métodos adicionales para consultas más específicas
    public function buscarPor($campo, $valor) {
        try {
            $sql = 'SELECT * FROM ' . $this->tabla . ' WHERE ' . $campo . ' = ?';
            $this->logQuery($sql, [$valor]);
            $resultado = $this->db->exec($sql, [$valor]);
            
            return $this->respuestaExito($resultado);
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            return $this->respuestaError(
                $error['mensaje'],
                $error,
                $error['codigo'] ?? 500
            );
        }
    }

    public function contarRegistros() {
        try {
            $sql = 'SELECT COUNT(*) as total FROM ' . $this->tabla;
            $this->logQuery($sql);
            $resultado = $this->db->exec($sql);
            
            return $this->respuestaExito([
                'total' => $resultado[0]['total']
            ]);
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            return $this->respuestaError(
                $error['mensaje'],
                $error,
                $error['codigo'] ?? 500
            );
        }
    }

    // Búsqueda con múltiples condiciones
    public function buscarConFiltros($filtros = [], $orden = null, $limite = null) {
        try {
            $sql = 'SELECT * FROM ' . $this->tabla;
            $valores = [];
            
            if (!empty($filtros)) {
                $condiciones = [];
                foreach ($filtros as $campo => $valor) {
                    $condiciones[] = "$campo = ?";
                    $valores[] = $valor;
                }
                $sql .= ' WHERE ' . implode(' AND ', $condiciones);
            }
            
            if ($orden) {
                $sql .= ' ORDER BY ' . $orden;
            }
            
            if ($limite) {
                $sql .= ' LIMIT ' . $limite;
            }
            
            $this->logQuery($sql, $valores);
            $resultado = $this->db->exec($sql, $valores);
            
            return $this->respuestaExito($resultado);
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            return $this->respuestaError(
                $error['mensaje'],
                $error,
                $error['codigo'] ?? 500
            );
        }
    }

    // Búsqueda por texto en múltiples campos
    public function buscarTexto($campos, $texto) {
        try {
            $condiciones = array_map(function($campo) {
                return "$campo LIKE ?";
            }, $campos);
            
            $sql = 'SELECT * FROM ' . $this->tabla . ' WHERE ' . implode(' OR ', $condiciones);
            $valores = array_fill(0, count($campos), "%$texto%");
            
            $this->logQuery($sql, $valores);
            $resultado = $this->db->exec($sql, $valores);
            
            return $this->respuestaExito($resultado);
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            return $this->respuestaError(
                $error['mensaje'],
                $error,
                $error['codigo'] ?? 500
            );
        }
    }

    // Obtener registros con paginación
    public function obtenerPaginado($pagina = 1, $porPagina = 10) {
        try {
            $offset = ($pagina - 1) * $porPagina;
            $sql = "SELECT * FROM {$this->tabla} LIMIT ? OFFSET ?";
            $this->logQuery($sql, [$porPagina, $offset]);
            
            $total = $this->contarRegistros();
            if ($total['estado'] === 'error') {
                return $total;
            }
            
            $resultado = $this->db->exec($sql, [$porPagina, $offset]);
            
            return $this->respuestaExito([
                'datos' => $resultado,
                'total' => $total['datos']['total'],
                'pagina_actual' => $pagina,
                'por_pagina' => $porPagina,
                'total_paginas' => ceil($total['datos']['total'] / $porPagina)
            ]);
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            return $this->respuestaError(
                $error['mensaje'],
                $error,
                $error['codigo'] ?? 500
            );
        }
    }

    // Actualización condicional
    public function actualizarDonde($condiciones, $datos) {
        try {
            if (empty($condiciones) || empty($datos)) {
                return $this->respuestaError('Condiciones o datos no proporcionados', null, 400);
            }

            $campos = array_map(function($campo) {
                return "$campo = ?";
            }, array_keys($datos));
            
            $where = array_map(function($campo) {
                return "$campo = ?";
            }, array_keys($condiciones));
            
            $sql = "UPDATE {$this->tabla} SET " . implode(', ', $campos) . 
                   " WHERE " . implode(' AND ', $where);
                   
            $valores = array_merge(array_values($datos), array_values($condiciones));
            
            $this->logQuery($sql, $valores);
            $resultado = $this->db->exec($sql, $valores);
            
            if ($resultado === false) {
                return $this->respuestaError('Error al actualizar los registros');
            }
            
            return $this->respuestaExito([
                'registros_actualizados' => $resultado
            ], 'Registros actualizados correctamente');
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            return $this->respuestaError(
                $error['mensaje'],
                $error,
                $error['codigo'] ?? 500
            );
        }
    }
} 