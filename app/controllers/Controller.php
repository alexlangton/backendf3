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
        
        if (!file_exists($this->logPath)) {
            mkdir($this->logPath, 0777, true);
        }
        
        try {
            // Reutilizar la conexión existente si ya existe
            if ($this->f3->exists('DB')) {
                $this->db = $this->f3->get('DB');
            } else {
                // Solo crear una nueva conexión si no existe
                $this->db = new DB\SQL(
                    'mysql:host=' . $this->f3->get('db.DB_HOST') . 
                    ';dbname=' . $this->f3->get('db.DB_NAME') . 
                    ';charset=utf8mb4',
                    $this->f3->get('db.DB_USER'),
                    $this->f3->get('db.DB_PASS'),
                    [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                $this->f3->set('DB', $this->db);
            }
        } catch (\PDOException $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        }
    }
} 