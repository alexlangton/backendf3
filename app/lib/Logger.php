<?php

class Logger {
    protected $f3;
    protected $logPath;

    public function __construct($f3) {
        $this->f3 = $f3;
        $this->logPath = $f3['LOGPATH'];
        
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0777, true);
        }
    }

    public function debug($mensaje) {
        $this->log('DEBUG', $mensaje, 'debug.log');
    }

    public function error($mensaje) {
        $this->log('ERROR', $mensaje, 'error.log');
    }

    protected function log($nivel, $mensaje, $archivo) {
        $fecha = date('Y-m-d H:i:s');
        $logMessage = "[$fecha] $nivel: $mensaje" . PHP_EOL;
        $this->f3->write($this->logPath . '/' . $archivo, $logMessage, true);
    }
} 