<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Paciente {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    public function crear($datos) {
        try {
            $this->conn->beginTransaction();

            // Crear usuario - USANDO CAMPOS CORRECTOS
            $sqlUsuario = "INSERT INTO usuarios (nombres, apellidos, cedula, correo, password, id_rol, id_estado, sexo, nacionalidad, username) 
                          VALUES (:nombres, :apellidos, :cedula, :correo, :password, 71, 1, :sexo, :nacionalidad, :username)";
            
            $username = strtolower(str_replace(' ', '.', $datos['nombres'])) . '.' . strtolower(str_replace(' ', '.', $datos['apellidos'])) . '.paciente';
            
            $stmtUsuario = $this->conn->prepare($sqlUsuario);
            $stmtUsuario->execute([
                ':nombres' => $datos['nombres'],
                ':apellidos' => $datos['apellidos'],
                ':cedula' => (int)$datos['cedula'], // 👈 CONVERTIR A ENTERO
                ':correo' => $datos['correo'],
                ':password' => password_hash($datos['contrasena'] ?? '123456', PASSWORD_DEFAULT), // 👈 USAR :password
                ':sexo' => $datos['sexo'] ?? 'M',
                ':nacionalidad' => $datos['nacionalidad'] ?? 'Ecuatoriana',
                ':username' => $username
            ]);

            $idUsuario = $this->conn->lastInsertId();

            // Crear paciente
            $sqlPaciente = "INSERT INTO pacientes (id_usuario, fecha_nacimiento, tipo_sangre, telefono, contacto_emergencia, telefono_emergencia) 
                           VALUES (:id_usuario, :fecha_nacimiento, :tipo_sangre, :telefono, :contacto_emergencia, :telefono_emergencia)";
            
            $stmtPaciente = $this->conn->prepare($sqlPaciente);
            $stmtPaciente->execute([
                ':id_usuario' => $idUsuario,
                ':fecha_nacimiento' => $datos['fecha_nacimiento'],
                ':tipo_sangre' => $datos['tipo_sangre'] ?? null,
                ':telefono' => $datos['telefono'] ?? null,
                ':contacto_emergencia' => $datos['contacto_emergencia'] ?? null,
                ':telefono_emergencia' => $datos['telefono_emergencia'] ?? null
            ]);

            $idPaciente = $this->conn->lastInsertId();

            $this->conn->commit();

            return [
                'id_paciente' => $idPaciente,
                'id_usuario' => $idUsuario,
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'cedula' => $datos['cedula'],
                'username' => $username
            ];

        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw new \Exception("Error al crear paciente: " . $e->getMessage());
        }
    }

    public function buscarPorCedula($cedula) {
        try {
            $sql = "SELECT p.*, u.nombres, u.apellidos, u.cedula, u.correo
                    FROM pacientes p
                    JOIN usuarios u ON p.id_usuario = u.id_usuario
                    WHERE u.cedula = :cedula AND u.id_estado = 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':cedula' => (int)$cedula]); // 👈 CONVERTIR A ENTERO
            
            return $stmt->fetch();
        } catch (\Exception $e) {
            throw new \Exception("Error al buscar paciente: " . $e->getMessage());
        }
    }
}
?>