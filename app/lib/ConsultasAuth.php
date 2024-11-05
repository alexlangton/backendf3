<?php

class ConsultasAuth extends ConsultasSQL {
    private $hasheoPassword;

    public function __construct() {
        parent::__construct('usuarios');
        $this->hasheoPassword = new HasheoPassword();
    }

    public function verificarCredenciales($usuario, $password) {
        $sql = "SELECT * FROM usuarios WHERE usuario = ? AND password = ?";
        $passwordHasheado = $this->hasheoPassword->hashear($password);
        
        
        $this->logQuery($sql, [$usuario, $passwordHasheado]); // No logear passwords
        $resultado = $this->db->exec($sql, [$usuario, $passwordHasheado]);

        return $resultado ? $resultado[0] : null;
    }

    public function guardarToken($idUsuario, $token, $expiracion) {
        try {
            $datos = [
                'token' => $token,
                'expiracion' => $expiracion,
                'fecha_creacion_token' => date('Y-m-d H:i:s')
            ];
            
            $sql = "UPDATE usuarios SET token = ?, expiracion = ?, fecha_creacion_token = ? WHERE id = ?";
            $this->logQuery($sql, [$token, $expiracion, $datos['fecha_creacion_token'], $idUsuario]);
            $resultado = $this->db->exec($sql, [$token, $expiracion, $datos['fecha_creacion_token'], $idUsuario]);
            
            if ($resultado === false) {
                return [
                    'estado' => 'error',
                    'mensaje' => 'Error al guardar el token en la base de datos'
                ];
            }
            
            return [
                'estado' => 'exito',
                'datos' => [
                    'token' => $token,
                    'expiracion' => $expiracion
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'estado' => 'error',
                'mensaje' => 'Error en el proceso de guardado del token'
            ];
        }
    }

    protected function invalidarTokensUsuario($idUsuario) {
        $sql = "UPDATE usuarios SET token = NULL, expiracion = NULL, fecha_creacion_token = NULL WHERE id = ?";
        $this->logQuery($sql, [$idUsuario]);
        return $this->db->exec($sql, [$idUsuario]);
    }

    public function verificarToken($token) {
        $sql = "SELECT * FROM usuarios WHERE token = ? AND expiracion > NOW()";
        $this->logQuery($sql, [$token]);
        $resultado = $this->db->exec($sql, [$token]);
        return $resultado ? $resultado[0] : null;
    }

    public function invalidarToken($token) {
        $sql = "UPDATE usuarios SET token = NULL, expiracion = NULL, fecha_creacion_token = NULL WHERE token = ?";
        $this->logQuery($sql, [$token]);
        return $this->db->exec($sql, [$token]);
    }

    public function limpiarTokensExpirados() {
        $sql = "UPDATE usuarios SET token = NULL, expiracion = NULL, fecha_creacion_token = NULL WHERE expiracion < NOW()";
        $this->logQuery($sql);
        return $this->db->exec($sql);
    }

    public function crearUsuario($datos) {
        if (isset($datos['password'])) {
            $datos['password'] = $this->hasheoPassword->hashear($datos['password']);
        }
        return $this->insertar($datos);
    }

    // ... resto de los métodos ...
} 