<?php

class HasheoPassword {
    private $sal = '%xJ7ksn@CQ7#q%';  // Usa el mismo valor que está en config.php

    public function hashear($password) {
        return hash('sha256', $this->sal . $password);
    }
}

$hasheador = new HasheoPassword();
$password = 'admin123';
$hash = $hasheador->hashear($password);

echo "Password: $password\n";
echo "Hash: $hash\n";