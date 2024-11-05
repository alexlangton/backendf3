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
        $sql = 'SELECT * FROM ' . $this->tabla;
        $this->logQuery($sql);
        return $this->db->exec($sql);
    }

    protected function manejarError($e) {
        $mensaje = $e->getMessage();
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
                    'mensaje' => "Valor duplicado: {$campo} = {$valor}"
                ];
            }
        }
        // Otros errores SQL
        else {
            $errorInfo = [
                'tipo' => 'sql',
                'mensaje' => $mensaje
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

    public function insertar($datos) {
        try {
            // Validar que haya datos
            if (empty($datos)) {
                return [
                    'estado' => 'error',
                    'mensaje' => 'No hay datos para insertar'
                ];
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
                return [
                    'estado' => 'error',
                    'mensaje' => 'Error en la ejecución de la consulta'
                ];
            }
            
            // Obtener el ID del nuevo registro
            $nuevoId = $this->db->lastInsertId();
            
            return [
                'estado' => 'exito',
                'mensaje' => 'Registro creado correctamente',
                'id' => $nuevoId
            ];
                
        } catch (\Exception $e) {
            $error = $this->manejarError($e);
            $this->f3->write(
                $this->logFile,
                date('Y-m-d H:i:s') . " - Error en inserción: " . $e->getMessage() . "\n",
                true
            );
            return [
                'estado' => 'error',
                'mensaje' => $error['mensaje'],
                'detalles' => $error
            ];
        }
    }

    public function actualizar($id, $datos) {
        try {
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
            return null;
        } catch (\Exception $e) {
            // Registrar el error
            $this->f3->write(
                $this->logFile,
                date('Y-m-d H:i:s') . ' - Error en actualización: ' . $e->getMessage() . "\n",
                true
            );
            return null;
        }
    }

    public function eliminar($id) {
        return $this->db->exec('DELETE FROM ' . $this->tabla . ' WHERE id = ?', [$id]);
    }

    // Métodos adicionales para consultas más específicas
    public function buscarPor($campo, $valor) {
        return $this->db->exec('SELECT * FROM ' . $this->tabla . ' WHERE ' . $campo . ' = ?', [$valor]);
    }

    public function contarRegistros() {
        return $this->db->exec('SELECT COUNT(*) as total FROM ' . $this->tabla)[0]['total'];
    }

    // Búsqueda con múltiples condiciones
    public function buscarConFiltros($filtros = [], $orden = null, $limite = null) {
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
        return $this->db->exec($sql, $valores);
    }

    // Búsqueda por texto en múltiples campos
    public function buscarTexto($campos, $texto) {
        $condiciones = array_map(function($campo) {
            return "$campo LIKE ?";
        }, $campos);
        
        $sql = 'SELECT * FROM ' . $this->tabla . ' WHERE ' . implode(' OR ', $condiciones);
        $valores = array_fill(0, count($campos), "%$texto%");
        
        return $this->db->exec($sql, $valores);
    }

    // Obtener registros con paginación
    public function obtenerPaginado($pagina = 1, $porPagina = 10) {
        $offset = ($pagina - 1) * $porPagina;
        $sql = "SELECT * FROM {$this->tabla} LIMIT ? OFFSET ?";
        
        return [
            'datos' => $this->db->exec($sql, [$porPagina, $offset]),
            'total' => $this->contarRegistros(),
            'pagina_actual' => $pagina,
            'por_pagina' => $porPagina
        ];
    }

    // Actualización condicional
    public function actualizarDonde($condiciones, $datos) {
        $campos = array_map(function($campo) {
            return "$campo = ?";
        }, array_keys($datos));
        
        $where = array_map(function($campo) {
            return "$campo = ?";
        }, array_keys($condiciones));
        
        $sql = "UPDATE {$this->tabla} SET " . implode(', ', $campos) . 
               " WHERE " . implode(' AND ', $where);
               
        $valores = array_merge(array_values($datos), array_values($condiciones));
        
        return $this->db->exec($sql, $valores);
    }
} 