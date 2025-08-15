<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Usuario {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    public function login($username, $password) {
        try {
            $sql = "SELECT u.*, r.nombre_rol, e.nombre_estado 
                    FROM usuarios u 
                    JOIN roles r ON u.id_rol = r.id_rol 
                    JOIN estados e ON u.id_estado = e.id_estado 
                    WHERE u.username = :username AND e.nombre_estado = 'Activo'";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                unset($user['password']); // No devolver la contraseña
                return $user;
            }
            
            return false;
        } catch (\Exception $e) {
            throw new \Exception("Error en login: " . $e->getMessage());
        }
    }

    public function cambiarPassword($username, $newPassword) {
        try {
            // Verificar que el usuario existe
            $sqlCheck = "SELECT id_usuario FROM usuarios WHERE username = :username";
            $stmtCheck = $this->conn->prepare($sqlCheck);
            $stmtCheck->bindParam(':username', $username);
            $stmtCheck->execute();
            
            if ($stmtCheck->rowCount() == 0) {
                return false;
            }

            // Cambiar contraseña
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET password = :password WHERE username = :username";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':username', $username);
            
            return $stmt->execute();
        } catch (\Exception $e) {
            throw new \Exception("Error al cambiar contraseña: " . $e->getMessage());
        }
    }
}
?>