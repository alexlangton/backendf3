<?php

class Controller {
    protected $db;
    protected $f3;
    protected $logger;
    protected $logPath;

    public function __construct() {
        $this->f3 = Base::instance();
        $this->logPath = $this->f3['LOGS'];
        $this->logger = new Logger($this->f3);
        
        // Asegurarse que existe el directorio de logs
        if (!file_exists($this->logPath)) {
            mkdir($this->logPath, 0777, true);
        }
        
        try {
            // Inicializar conexión a la base de datos
            $this->db = new DB\SQL(
                'mysql:host=' . $this->f3->get('DB_HOST') . 
                ';dbname=' . $this->f3->get('DB_NAME') . 
                ';charset=utf8mb4',
                $this->f3->get('DB_USER'),
                $this->f3->get('DB_PASS'),
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            // Guardar la instancia de DB en F3 para uso global
            $this->f3->set('DB', $this->db);
            
        } catch (\PDOException $e) {
            // Log del error
            $this->log($e->getMessage(), 'error');
            
            // Siempre responder en JSON
            $this->jsonResponse([
                'error' => true,
                'message' => 'Error de conexión a la base de datos'
            ], 500);
        }
    }

    // Método helper para respuestas JSON
    protected function jsonResponse($data, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    // Método helper para validar que un registro existe
    protected function checkRecord($table, $id) {
        $result = $this->db->exec('SELECT id FROM ' . $table . ' WHERE id = ?', [$id]);
        return !empty($result);
    }

    // Método helper para sanitizar input
    protected function sanitize($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitize($value);
            }
        } else {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }

    // Método helper para validar campos requeridos
    protected function validateRequired($data, $fields) {
        $errors = [];
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "El campo '$field' es requerido";
            }
        }
        return $errors;
    }

    // Método helper para paginación
    protected function paginate($table, $page = 1, $limit = 10, $conditions = '') {
        $offset = ($page - 1) * $limit;
        
        // Contar total de registros
        $total = $this->db->exec('SELECT COUNT(*) as count FROM ' . $table . ' ' . $conditions);
        $total = $total[0]['count'];
        
        // Obtener registros de la página actual
        $query = 'SELECT * FROM ' . $table . ' ' . $conditions . ' LIMIT ? OFFSET ?';
        $results = $this->db->exec($query, [$limit, $offset]);
        
        return [
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    // Método helper para manejo de transacciones
    protected function transaction($callback) {
        try {
            $this->db->begin();
            $result = $callback();
            $this->db->commit();
            return $result;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Método mejorado para logging
    protected function log($message, $type = 'debug') {
        $date = date('Y-m-d H:i:s');
        $logMessage = "[$date] $type: $message" . PHP_EOL;
        
        switch ($type) {
            case 'error':
                error_log($logMessage, 3, $this->logPath . 'app.log');
                break;
            case 'debug':
                if ($this->f3->get('DEBUG')) {
                    error_log($logMessage, 3, $this->logPath . 'debug.log');
                }
                break;
        }
    }

    // Método para debug
    protected function debug($data, $label = '') {
        if ($this->f3->get('DEBUG')) {
            $output = $label ? "=== $label ===" . PHP_EOL : '';
            $output .= print_r($data, true) . PHP_EOL;
            $output .= str_repeat('=', 50) . PHP_EOL;
            error_log($output, 3, $this->logPath . 'debug.log');
        }
    }
} 