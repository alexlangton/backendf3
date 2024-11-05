<?php

class HasheoPassword {
    private $sal;

    public function __construct() {
        $f3 = \Base::instance();
        $this->sal = $f3->get('SAL');
    }

    public function hashear($password) {
        return hash('sha256', $this->sal . $password);
    }

    public function verificar($password, $hash) {
        $hashCalculado = $this->hashear($password);
        return $hashCalculado === $hash;
    }
} 